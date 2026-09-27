/**
 * Terminal Manager
 * Handles discovery, registry, polling, and terminal lifecycle.
 * Identity is based on Terminal ID from Status-Enquiry responses.
 */

import net from "net";
import fs from "fs";
import path from "path";
import { ZvtSession } from "./zvt-session.js";

const REGISTRY_FILE = "payment-terminals.json";

export class TerminalManager {
  constructor(config, brokerDir) {
    this.config = {
      password: config.password || "000000",
      configByte: config.configByte || 0x1E,
      // Registration receipt routing:
      //  - registrationTlvPermitPrint: declare ECR permits 06 D1/06 D3 (only
      //    relevant when the terminal should SEND receipts to the ECR).
      //  For "terminal prints its own receipts" leave this false and use a
      //  config byte with bit 7 cleared (e.g. 0x1C).
      registrationTlvPermitPrint: config.registrationTlvPermitPrint === true,
      registrationIncludeCurrency: config.registrationIncludeCurrency !== false,
      // Optional per-transaction receipt override (TLV 1F04). null = omit.
      receiptParam1F04: (config.receiptParam1F04 === undefined || config.receiptParam1F04 === null) ? null : config.receiptParam1F04,
      pollIntervalSeconds: config.pollIntervalSeconds || 60,
      discoveryIntervalHours: config.discoveryIntervalHours || 24,
      recoveryScanCooldownSeconds: config.recoveryScanCooldownSeconds || 300,
      connectTimeoutMs: config.connectTimeoutMs || 400,
      responseTimeoutMs: config.responseTimeoutMs || 2000,
      transactionTimeoutSeconds: config.transactionTimeoutSeconds || 120,
      scanPorts: config.scanPorts || [20007, 20011, 40007],
      cidrs: config.cidrs || [],
      maxConcurrency: config.maxConcurrency || 32,
      currency: config.currency || "EUR"
    };
    this.registryPath = path.join(brokerDir, REGISTRY_FILE);
    this.registry = { schemaVersion: 1, nextTerminalNumber: 1, terminals: [] };
    this.sessions = new Map(); // terminalId -> ZvtSession
    this.pollTimer = null;
    this.discoveryTimer = null;
    this.lockReaperTimer = null;
    this.lastDiscoveryAt = 0;
    this.lastRecoveryScanAt = 0;
    this.locks = new Map(); // terminalId -> { requestId, posId, lockedAt }
    this.onLog = config.onLog || console.log;
    this.onTerminalStatusChange = null; // callback

    this.loadRegistry();
  }

  // --- Registry Persistence ---

  loadRegistry() {
    try {
      if (fs.existsSync(this.registryPath)) {
        const data = JSON.parse(fs.readFileSync(this.registryPath, "utf8"));
        if (data && data.schemaVersion === 1) {
          this.registry = data;
        }
      }
    } catch (e) {
      this.onLog(`REGISTRY: Failed to load: ${e.message}`);
    }
  }

  saveRegistry() {
    try {
      const tmpPath = this.registryPath + ".tmp";
      fs.writeFileSync(tmpPath, JSON.stringify(this.registry, null, 2), "utf8");
      fs.renameSync(tmpPath, this.registryPath);
    } catch (e) {
      this.onLog(`REGISTRY: Failed to save: ${e.message}`);
    }
  }

  // --- Terminal Lookup ---

  getTerminal(terminalId) {
    return this.registry.terminals.find(t => t.id === terminalId) || null;
  }

  getTerminalByIdentity(zvtTerminalId) {
    return this.registry.terminals.find(t => t.identity.terminalIdentifier === zvtTerminalId) || null;
  }

  /**
   * Get terminal list for POS clients (sanitized — no network details).
   */
  getTerminalList() {
    return this.registry.terminals.map(t => ({
      id: t.id,
      name: t.name,
      status: t.runtimeStatus || "UNKNOWN"
    }));
  }

  // --- Discovery ---

  /**
   * Run full network discovery scan.
   */
  async discover() {
    this.onLog("DISCOVERY: Starting full scan...");
    this.lastDiscoveryAt = Date.now();
    const hosts = this.getHostsToScan();
    const found = [];

    // Scan in batches
    const batchSize = this.config.maxConcurrency;
    for (let i = 0; i < hosts.length; i += batchSize) {
      const batch = hosts.slice(i, i + batchSize);
      const results = await Promise.allSettled(
        batch.flatMap(host =>
          this.config.scanPorts.map(port => this.probeEndpoint(host, port))
        )
      );
      for (const r of results) {
        if (r.status === "fulfilled" && r.value) {
          found.push(r.value);
        }
      }
    }

    this.onLog(`DISCOVERY: Found ${found.length} terminal(s)`);
    this.reconcile(found);
    this.saveRegistry();
  }

  /**
   * Probe a single endpoint with Status-Enquiry.
   */
  async probeEndpoint(ip, port) {
    const session = new ZvtSession(ip, port, {
      password: this.config.password,
      connectTimeout: this.config.connectTimeoutMs,
      responseTimeout: this.config.responseTimeoutMs,
      onLog: this.config.debug ? (dir, ...a) => this.onLog(`probe ${ip}:${port} ${dir}:`, ...a) : undefined
    });
    try {
      await session.connect();
      const status = await session.statusEnquiry();
      session.disconnect();

      // Valid ZVT terminal if we got ACK (even without full completion)
      if (status.resultCode !== null && status.resultCode !== undefined) {
        return {
          ip,
          port,
          terminalIdentifier: status.terminalIdentifier || `sim-${ip}-${port}`,
          serialNumber: status.serialNumber,
          deviceName: status.deviceName || (status.ackOnly ? "ZVT-Device" : null),
          softwareVersion: status.softwareVersion,
          deviceState: status.deviceState,
          resultCode: status.resultCode
        };
      }
    } catch (_) {
      // Not a ZVT terminal or unreachable
    }
    session.disconnect();
    return null;
  }

  /**
   * Reconcile discovered terminals with registry.
   */
  reconcile(found) {
    // Deduplicate by terminalIdentifier
    const byId = new Map();
    for (const f of found) {
      const existing = byId.get(f.terminalIdentifier);
      if (existing) {
        // Duplicate identity — mark as problem
        this.onLog(`DISCOVERY: DUPLICATE_IDENTITY ${f.terminalIdentifier} at ${existing.ip}:${existing.port} and ${f.ip}:${f.port}`);
        continue;
      }
      byId.set(f.terminalIdentifier, f);
    }

    for (const [tid, info] of byId) {
      const existing = this.getTerminalByIdentity(tid);
      if (existing) {
        // Known terminal — update network info
        existing.network.ip = info.ip;
        existing.network.port = info.port;
        existing.device.deviceName = info.deviceName || existing.device.deviceName;
        existing.device.softwareVersion = info.softwareVersion || existing.device.softwareVersion;
        existing.lastSeenAt = new Date().toISOString();
        existing.lastVerifiedAt = new Date().toISOString();
        existing.runtimeStatus = info.deviceState === 0x00 ? "AVAILABLE" : "NOT_READY";
        this.onLog(`DISCOVERY: Updated ${existing.id} (${existing.name}) → ${info.ip}:${info.port}`);
      } else {
        // New terminal
        const seq = this.registry.nextTerminalNumber;
        const newTerminal = {
          id: `terminal-${seq}`,
          name: `Terminal ${seq}`,
          sequence: seq,
          identity: {
            serialNumber: info.serialNumber || null,
            terminalIdentifier: tid
          },
          device: {
            deviceName: info.deviceName || "Unknown",
            softwareVersion: info.softwareVersion || ""
          },
          network: {
            ip: info.ip,
            port: info.port
          },
          createdAt: new Date().toISOString(),
          lastSeenAt: new Date().toISOString(),
          lastVerifiedAt: new Date().toISOString(),
          runtimeStatus: info.deviceState === 0x00 ? "AVAILABLE" : "NOT_READY"
        };
        this.registry.terminals.push(newTerminal);
        this.registry.nextTerminalNumber = seq + 1;
        this.onLog(`DISCOVERY: New terminal ${newTerminal.id} (${newTerminal.name}) at ${info.ip}:${info.port} TID=${tid}`);
      }
    }

    // Mark terminals not found as OFFLINE
    for (const t of this.registry.terminals) {
      if (!byId.has(t.identity.terminalIdentifier)) {
        if (t.runtimeStatus !== "OFFLINE") {
          t.runtimeStatus = "OFFLINE";
          this.onLog(`DISCOVERY: ${t.id} (${t.name}) → OFFLINE`);
        }
      }
    }
  }

  /**
   * Get list of IPs to scan.
   */
  getHostsToScan() {
    if (this.config.cidrs.length > 0) {
      return this.config.cidrs.flatMap(cidr => expandCidr(cidr));
    }
    // Auto-detect local subnet (fallback)
    return expandCidr("192.168.0.0/24");
  }

  // --- Polling ---

  startPolling() {
    if (this.pollTimer) return;
    const interval = this.config.pollIntervalSeconds * 1000;
    this.pollTimer = setInterval(() => this.pollAll(), interval);
    // Safety net: reap stale locks so a terminal can never be held forever if
    // an operation's promise somehow neither resolves nor rejects. Every op is
    // already bounded by connect/response/transaction timeouts and unlocked in
    // a finally, so this should never fire in practice — it is purely defensive.
    if (!this.lockReaperTimer) {
      this.lockReaperTimer = setInterval(() => this.reapStaleLocks(), 30000);
    }
    // Initial discovery if no terminals known, otherwise run one poll now so
    // each known terminal's persistent session is established + registered
    // immediately (register-and-hold) instead of waiting a full interval.
    if (this.registry.terminals.length === 0) {
      this.discover();
    } else {
      this.pollAll().catch(() => {});
    }
  }

  /**
   * Force-release any lock older than the transaction timeout plus a margin.
   * Defensive only (see startPolling). Logs when it fires.
   */
  reapStaleLocks() {
    const maxAgeMs = (this.config.transactionTimeoutSeconds * 1000) + 30000;
    const now = Date.now();
    for (const [terminalId, lock] of this.locks) {
      if (now - lock.lockedAt > maxAgeMs) {
        this.onLog(`LOCK: reaping stale lock on ${terminalId} (held ${Math.round((now - lock.lockedAt) / 1000)}s by ${lock.posId || "unknown"})`);
        this.unlock(terminalId);
      }
    }
  }

  stopPolling() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
    if (this.lockReaperTimer) {
      clearInterval(this.lockReaperTimer);
      this.lockReaperTimer = null;
    }
  }

  async pollAll() {
    for (const terminal of this.registry.terminals) {
      if (this.isLocked(terminal.id)) continue; // Skip busy terminals
      try {
        // 8a "register-and-hold": every known terminal is backed by ONE
        // persistent, registered session that stays open 24/7. Poll ENSURES
        // that session exists (connect + register once if missing/dropped) and
        // then sends a Status-Enquiry on it as the keepalive. There is NO
        // throwaway session and NO connect/disconnect churn ("login/logout")
        // per poll — poll rides the single live session and also acts as the
        // reconnect driver when the socket has dropped.
        const session = await this.getRegisteredSession(terminal.id);
        if (!session) continue; // unknown terminal (shouldn't happen here)
        const status = await session.statusEnquiry(); // 04 01 on the live socket

        // Verify identity
        if (status.terminalIdentifier && status.terminalIdentifier !== terminal.identity.terminalIdentifier) {
          terminal.runtimeStatus = "ATTENTION";
          this.onLog(`POLL: ${terminal.id} identity mismatch! Expected ${terminal.identity.terminalIdentifier}, got ${status.terminalIdentifier}`);
        } else {
          terminal.lastSeenAt = new Date().toISOString();
          // ATTENTION (e.g. after an UNKNOWN payment) is cleared by a clean poll.
          terminal.runtimeStatus = status.deviceState === 0x00 ? "AVAILABLE" : "NOT_READY";
        }
      } catch (e) {
        // The persistent session failed — evict it so the NEXT poll reconnects
        // and re-registers from scratch. Mark OFFLINE + trigger recovery scan.
        this.invalidateSession(terminal.id);
        if (terminal.runtimeStatus !== "OFFLINE") {
          terminal.runtimeStatus = "OFFLINE";
          this.onLog(`POLL: ${terminal.id} (${terminal.name}) → OFFLINE: ${e.message}`);
          this.triggerRecoveryScan();
        }
      }
      if (this.onTerminalStatusChange) this.onTerminalStatusChange();
    }
  }

  triggerRecoveryScan() {
    const now = Date.now();
    const cooldown = this.config.recoveryScanCooldownSeconds * 1000;
    if (now - this.lastRecoveryScanAt < cooldown) return;
    this.lastRecoveryScanAt = now;
    this.onLog("POLL: Triggering recovery scan");
    this.discover();
  }

  // --- Terminal Locking ---

  isLocked(terminalId) {
    return this.locks.has(terminalId);
  }

  lock(terminalId, requestId, posId) {
    if (this.locks.has(terminalId)) return false;
    this.locks.set(terminalId, { requestId, posId, lockedAt: Date.now() });
    const terminal = this.getTerminal(terminalId);
    if (terminal) terminal.runtimeStatus = "BUSY";
    if (this.onTerminalStatusChange) this.onTerminalStatusChange();
    return true;
  }

  unlock(terminalId) {
    this.locks.delete(terminalId);
    const terminal = this.getTerminal(terminalId);
    if (terminal && terminal.runtimeStatus === "BUSY") {
      terminal.runtimeStatus = "AVAILABLE";
    }
    if (this.onTerminalStatusChange) this.onTerminalStatusChange();
  }

  getLock(terminalId) {
    return this.locks.get(terminalId) || null;
  }

  // --- Session management (8a: persistent per-terminal session cache) ---
  //
  // One long-lived ZvtSession per known terminal is cached in this.sessions.
  // A payment/receipt caller obtains it via getRegisteredSession(), which
  // connects + registers exactly ONCE per connection and thereafter reuses the
  // same live socket. The cache entry is dropped automatically when the socket
  // closes or errors (see _makeSession), so the next call transparently
  // reconnects and re-registers — covering Wi-Fi drops, DHCP changes, reboots.
  //
  // Discovery probes (probeEndpoint) and lightweight poll fallbacks build their
  // OWN throwaway sessions and never touch this cache.

  /**
   * Build a fresh ZvtSession for a known terminal, wired so that a socket
   * close/error evicts it from the cache. NOT connected yet.
   */
  _makeSession(terminal) {
    const session = new ZvtSession(terminal.network.ip, terminal.network.port, {
      password: this.config.password,
      configByte: this.config.configByte,
      registrationTlvPermitPrint: this.config.registrationTlvPermitPrint,
      registrationIncludeCurrency: this.config.registrationIncludeCurrency,
      receiptParam1F04: this.config.receiptParam1F04,
      onRaw: this.onRawCapture || null,
      connectTimeout: this.config.connectTimeoutMs,
      responseTimeout: this.config.responseTimeoutMs,
      transactionTimeout: this.config.transactionTimeoutSeconds * 1000,
      onLog: (dir, ...args) => this.onLog(`ZVT ${terminal.id} ${dir}:`, ...args)
    });
    // registered flag lets getRegisteredSession() skip a second Registration
    // while the socket stays up. Cleared on disconnect (fresh object each time).
    session.registered = false;
    return session;
  }

  /**
   * Return whether a cached session exists AND its socket is currently alive.
   */
  hasLiveSession(terminalId) {
    const s = this.sessions.get(terminalId);
    return !!(s && s.socket && !s.socket.destroyed);
  }

  /**
   * Get (or create) the cached session for a known terminal. Does NOT connect
   * or register — callers decide. Returns null for unknown terminals.
   * If the cached session's socket is dead, it is replaced with a fresh one.
   */
  getSession(terminalId) {
    const terminal = this.getTerminal(terminalId);
    if (!terminal) return null;
    let session = this.sessions.get(terminalId);
    if (session && session.socket && !session.socket.destroyed) {
      return session; // healthy cached session
    }
    // Stale or none: (re)create and cache. Also re-point at the current
    // network endpoint in case discovery updated the terminal's IP/port.
    session = this._makeSession(terminal);
    this.sessions.set(terminalId, session);
    return session;
  }

  /**
   * Drop the cached session for a terminal (destroy socket, evict). Safe to
   * call when none exists. Used on protocol error / UNKNOWN so the next
   * operation starts from a clean reconnect+registration.
   */
  invalidateSession(terminalId) {
    const s = this.sessions.get(terminalId);
    if (s) {
      try { s.disconnect(); } catch (_) {}
    }
    this.sessions.delete(terminalId);
  }

  /**
   * Payment/receipt accessor: return a cached session that is CONNECTED and
   * REGISTERED, performing connect+registration only ONCE per connection.
   * On any failure the session is invalidated and the error rethrown.
   * Returns null for unknown terminals.
   */
  async getRegisteredSession(terminalId) {
    const terminal = this.getTerminal(terminalId);
    if (!terminal) return null;
    let session = this.getSession(terminalId);
    try {
      await session.connect(); // no-op if already connected
      if (!session.registered) {
        await session.registration();
        session.registered = true;
        this.onLog(`ZVT ${terminal.id}: registered (session cached for reuse)`);
      }
      return session;
    } catch (e) {
      this.invalidateSession(terminalId);
      throw e;
    }
  }

  /**
   * Verify terminal identity at its current endpoint.
   * Returns true if identity matches, false otherwise.
   * For simulator/ACK-only terminals (synthetic IDs), accept if connection succeeds.
   */
  async verifyIdentity(terminalId) {
    const terminal = this.getTerminal(terminalId);
    if (!terminal) return false;

    try {
      // Use the SINGLE persistent registered session (register-and-hold). This
      // ensures it exists / re-establishes it if the socket dropped, then does
      // a Status-Enquiry on that same live socket — no second TCP connection,
      // no throwaway. Never disconnect it here.
      const session = await this.getRegisteredSession(terminalId);
      if (!session) return false;
      const status = await session.statusEnquiry();

      // If terminal has a synthetic ID (simulator), just verify it responds
      if (terminal.identity.terminalIdentifier.startsWith("sim-")) {
        terminal.lastVerifiedAt = new Date().toISOString();
        return true;
      }

      if (status.terminalIdentifier === terminal.identity.terminalIdentifier) {
        terminal.lastVerifiedAt = new Date().toISOString();
        return true;
      }
      return false;
    } catch (_) {
      // The persistent session errored — evict it so the next op reconnects.
      this.invalidateSession(terminalId);
      return false;
    }
  }

  // --- Scheduled Discovery ---

  startDiscoverySchedule() {
    const intervalMs = this.config.discoveryIntervalHours * 3600 * 1000;
    this.discoveryTimer = setInterval(() => this.discover(), intervalMs);
  }

  stopDiscoverySchedule() {
    if (this.discoveryTimer) {
      clearInterval(this.discoveryTimer);
      this.discoveryTimer = null;
    }
  }
}

// --- CIDR Expansion Utility ---

function expandCidr(cidr) {
  const [base, prefixStr] = cidr.split("/");
  const prefix = parseInt(prefixStr, 10);
  if (prefix < 16 || prefix > 30) return []; // Safety: don't scan huge ranges

  const parts = base.split(".").map(Number);
  const baseNum = (parts[0] << 24) | (parts[1] << 16) | (parts[2] << 8) | parts[3];
  const hostBits = 32 - prefix;
  const numHosts = (1 << hostBits) - 2; // Exclude network and broadcast
  const networkAddr = baseNum & (0xFFFFFFFF << hostBits);

  const hosts = [];
  for (let i = 1; i <= numHosts; i++) {
    const ip = networkAddr + i;
    hosts.push(`${(ip >> 24) & 0xFF}.${(ip >> 16) & 0xFF}.${(ip >> 8) & 0xFF}.${ip & 0xFF}`);
  }
  return hosts;
}
