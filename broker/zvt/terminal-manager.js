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
    // Reconnect/registration backoff: min interval between fresh connect+
    // Registration attempts per terminal, so a refusing/duplicate/again-dropping
    // terminal can never be hammered in a tight loop (register-and-hold safety).
    this.registerBackoff = new Map(); // terminalId -> lastAttemptMs
    this.registerBackoffMs = (config.registerBackoffSeconds || 10) * 1000;
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
    // Map the internal 4-state health machine to the status the POS app knows
    // (AVAILABLE / BUSY / OFFLINE). SUSPECT is reported as BUSY so the POS does
    // not offer the terminal for a new payment while liveness is unconfirmed,
    // but it is not shown as hard-OFFLINE (a health check is in progress).
    const map = (s) => {
      switch (s) {
        case "READY": return "AVAILABLE";
        case "BUSY": return "BUSY";
        case "SUSPECT": return "BUSY";
        case "OFFLINE": return "OFFLINE";
        case "ATTENTION": return "ATTENTION";
        // legacy values still used by discovery until first health cycle
        case "AVAILABLE": return "AVAILABLE";
        case "NOT_READY": return "BUSY";
        default: return "UNKNOWN";
      }
    };
    return this.registry.terminals.map(t => ({
      id: t.id,
      name: t.name,
      status: map(t.state || t.runtimeStatus)
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
    // A ZVT terminal typically listens on several ports (e.g. 20011 and 40007)
    // and a probe that only gets an ACK (no full Status-Information) cannot read
    // the real Terminal-ID. Previously we synthesized a per-port fake identity
    // ("sim-<ip>-<port>"), which turned ONE physical terminal into several
    // registry entries — and with register-and-hold those entries then fought
    // over the single ECR session the terminal allows (tight reconnect loop).
    //
    // Dedup rules:
    //  1) Prefer a real (non-sim) Terminal-ID; a real ID for an IP wins over any
    //     synthetic/ACK-only result for the same IP.
    //  2) Never register more than one terminal per physical device: collapse
    //     all results for the same IP to a single entry.
    //  3) Drop a synthetic (sim-) result entirely if a real ID exists for that
    //     IP (this run) or a terminal already exists at that IP in the registry.
    const isSim = (id) => typeof id === "string" && id.startsWith("sim-");

    // First pass: pick the best result per IP (real ID beats sim; among reals,
    // first wins; among sims, first wins).
    const byIp = new Map(); // ip -> chosen result
    for (const f of found) {
      const cur = byIp.get(f.ip);
      if (!cur) { byIp.set(f.ip, f); continue; }
      if (isSim(cur.terminalIdentifier) && !isSim(f.terminalIdentifier)) {
        byIp.set(f.ip, f); // upgrade to the real-ID result
      }
      // else keep current (real already, or both sim)
    }

    // Second pass: build the id-keyed map, but suppress sim results when a
    // terminal already exists at that IP in the registry (same physical device).
    const byId = new Map();
    for (const f of byIp.values()) {
      if (isSim(f.terminalIdentifier)) {
        const existingAtIp = this.registry.terminals.find(t => t.network && t.network.ip === f.ip);
        if (existingAtIp) {
          this.onLog(`DISCOVERY: skip synthetic ${f.terminalIdentifier} at ${f.ip}:${f.port} — terminal ${existingAtIp.id} already known at this IP`);
          continue;
        }
      }
      if (byId.has(f.terminalIdentifier)) {
        const existing = byId.get(f.terminalIdentifier);
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

  /**
   * Need-based health check (runs on the poll timer). Per spec:
   *  - Only check a terminal that is READY (idle) AND has had NO valid RX for
   *    >= healthIdleMs (~60s). A recent payment/enquiry already proved liveness.
   *  - The check itself sets BUSY while the Status-Enquiry runs (no queue: a
   *    concurrent payment is rejected with TERMINAL_BUSY).
   *  - Any valid RX (even 04 FF / 84 / 06 D1 ...) means the terminal is alive;
   *    OFFLINE is NEVER derived from an "unexpected" answer, only from the
   *    complete ABSENCE of valid communication.
   *  - No RX at all within the enquiry timeout -> SUSPECT -> verify socket ->
   *    OFFLINE + reconnect/rediscovery.
   */
  async pollAll() {
    const idleMs = (this.config.healthIdleSeconds || 60) * 1000;
    const now = Date.now();
    for (const terminal of this.registry.terminals) {
      if (this.isLocked(terminal.id)) continue; // a broker action is running
      const state = terminal.state || terminal.runtimeStatus;

      // If OFFLINE/SUSPECT (or never established): try to (re)establish the
      // persistent session. This is the reconnect driver.
      if (state === "OFFLINE" || state === "SUSPECT" || !this.hasLiveSession(terminal.id)) {
        await this.healthReconnect(terminal);
        continue;
      }

      // READY (or legacy AVAILABLE): only poll if the terminal has been quiet
      // for >= idleMs. Recent valid RX = recently proven alive, skip the poll.
      const sess = this.sessions.get(terminal.id);
      const lastRx = (sess && sess.lastValidRxAt) || 0;
      if (now - lastRx < idleMs) continue; // proven alive recently -> no poll

      await this.healthCheck(terminal);
    }
  }

  /**
   * Run a ZVT Status-Enquiry as a liveness probe on the persistent session.
   * Sets BUSY for the duration, completes the full sequence (through 06 0F),
   * then returns to READY. Classifies the outcome per the spec.
   */
  async healthCheck(terminal) {
    const session = this.sessions.get(terminal.id);
    if (!session) { await this.healthReconnect(terminal); return; }
    this.setState(terminal.id, "BUSY"); // health check occupies the terminal
    try {
      const status = await session.statusEnquiry(); // 05 01 ... through 06 0F
      // Got a valid completion. Identity check (mismatch = ATTENTION, not off).
      if (status.terminalIdentifier && status.terminalIdentifier !== terminal.identity.terminalIdentifier) {
        this.setState(terminal.id, "ATTENTION");
        this.onLog(`HEALTH: ${terminal.id} identity mismatch! Expected ${terminal.identity.terminalIdentifier}, got ${status.terminalIdentifier}`);
      } else {
        terminal.lastSeenAt = new Date().toISOString();
        this.setState(terminal.id, "READY");
      }
    } catch (e) {
      // Did ANY valid RX arrive during the attempt? If yes, the terminal is
      // alive (maybe just slow / doing a local action) -> stay reachable, do
      // NOT go OFFLINE. Only a complete absence of valid RX is a real problem.
      const sawRx = session.lastValidRxAt && (Date.now() - session.lastValidRxAt) < 15000;
      if (sawRx) {
        this.onLog(`HEALTH: ${terminal.id} enquiry did not fully complete (${e.message}) but terminal answered — keeping online`);
        this.setState(terminal.id, "READY");
        return;
      }
      // No valid communication -> SUSPECT, then confirm loss -> OFFLINE.
      this.onLog(`HEALTH: ${terminal.id} no valid RX (${e.message}) → SUSPECT`);
      this.setState(terminal.id, "SUSPECT");
      await this.healthReconnect(terminal);
    }
  }

  /**
   * SUSPECT/OFFLINE handling: drop the (dead) session and attempt a clean
   * reconnect + registration. On success -> READY; on failure -> OFFLINE +
   * recovery scan. Never hammers (getRegisteredSession has backoff).
   */
  async healthReconnect(terminal) {
    try {
      this.invalidateSession(terminal.id);
      const session = await this.getRegisteredSession(terminal.id);
      if (!session) { this.onLog(`HEALTH: ${terminal.id} no session → OFFLINE`); this.setState(terminal.id, "OFFLINE"); return; }
      // Confirm with one enquiry that the fresh session really answers.
      const status = await session.statusEnquiry();
      if (status.terminalIdentifier && status.terminalIdentifier !== terminal.identity.terminalIdentifier) {
        this.onLog(`HEALTH: ${terminal.id} reconnected but TID mismatch (${status.terminalIdentifier}) → ATTENTION`);
        this.setState(terminal.id, "ATTENTION");
      } else {
        terminal.lastSeenAt = new Date().toISOString();
        this.setState(terminal.id, "READY");
        this.onLog(`HEALTH: ${terminal.id} reconnected → READY`);
      }
    } catch (e) {
      this.invalidateSession(terminal.id);
      this.onLog(`HEALTH: ${terminal.id} (${terminal.name}) reconnect failed → OFFLINE: ${e.message}`);
      this.setState(terminal.id, "OFFLINE");
      this.triggerRecoveryScan();
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
    // A running broker ZVT action = BUSY. No queue: a second payment request
    // sees state !== READY and is rejected with TERMINAL_BUSY.
    this.setState(terminalId, "BUSY");
    return true;
  }

  unlock(terminalId) {
    this.locks.delete(terminalId);
    const terminal = this.getTerminal(terminalId);
    // Only return to READY from BUSY. If the terminal became SUSPECT/OFFLINE
    // meanwhile, leave that state for the health check / reconnect to resolve.
    if (terminal && terminal.state === "BUSY") {
      this.setState(terminalId, "READY");
    }
  }

  /**
   * Fresh readiness probe right before sending a payment. The caller already
   * holds the lock. Runs a 05 01 Status-Enquiry on the (ensured) persistent
   * session and returns { ok, reason }:
   *   ok=true  -> terminal answered and reports deviceState 0x00 (READY)
   *   ok=false -> not ready now (busy with a local action, settling, mismatch,
   *               or unreachable). Caller releases the lock and returns BUSY.
   * This does NOT change the no-queue behaviour and does NOT start any
   * background polling — readiness is checked exactly when a payment is tried.
   */
  async probeReadyForPayment(terminalId) {
    const terminal = this.getTerminal(terminalId);
    if (!terminal) return { ok: false, reason: "unknown" };
    try {
      const session = await this.getRegisteredSession(terminalId);
      if (!session) return { ok: false, reason: "no-session" };
      const status = await session.statusEnquiry(); // 05 01 -> ... -> 06 0F
      // Identity must match (guards against a different device at the IP).
      if (status.terminalIdentifier &&
          status.terminalIdentifier !== terminal.identity.terminalIdentifier) {
        this.setState(terminalId, "ATTENTION");
        return { ok: false, reason: "identity-mismatch" };
      }
      // deviceState 0x00 = ready. Anything else = terminal busy/not ready
      // (e.g. a local action at the PT). ackOnly (no completion) -> treat as
      // ready=false to be safe, the user can retry.
      if (status.ackOnly) return { ok: false, reason: "no-completion" };
      if (status.deviceState === 0x00) {
        terminal.lastSeenAt = new Date().toISOString();
        return { ok: true, reason: "ready" };
      }
      return { ok: false, reason: `deviceState=${status.deviceState}` };
    } catch (e) {
      // No valid answer -> not ready. Do NOT force OFFLINE here (a slow/busy
      // terminal is not necessarily gone); the health poll handles liveness.
      return { ok: false, reason: e.message };
    }
  }

  /**
   * Central state setter for the 4-state health machine:
   *   READY   - connected, ZVT session active, no broker action running
   *   BUSY    - a broker ZVT action is in progress (payment, enquiry, ...)
   *   SUSPECT - socket exists but a health check got no valid RX in time
   *   OFFLINE - connection lost / socket closed / reconnect required
   * Keeps runtimeStatus as a POS-facing alias and notifies on change.
   */
  setState(terminalId, state) {
    const terminal = this.getTerminal(terminalId);
    if (!terminal) return;
    if (terminal.state === state) return;
    terminal.state = state;
    terminal.runtimeStatus = state; // alias (getTerminalList maps for POS)
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

    // Fast path: a live, already-registered session — reuse is free, no backoff.
    const existing = this.sessions.get(terminalId);
    if (existing && existing.socket && !existing.socket.destroyed && existing.registered) {
      return existing;
    }

    // Slow path: we need a fresh connect + Registration. Guard it with a
    // per-terminal backoff so that a terminal which refuses / immediately
    // drops the ECR session (e.g. it only permits one session, or it is in its
    // config menu, or there are stale duplicate registry entries) can NEVER be
    // hammered in a tight connect/register loop. If we attempted too recently,
    // fail fast without touching the terminal.
    const now = Date.now();
    const last = this.registerBackoff.get(terminalId) || 0;
    if (now - last < this.registerBackoffMs) {
      throw new Error(`Register backoff active for ${terminalId} (${Math.round((this.registerBackoffMs - (now - last)) / 1000)}s left)`);
    }
    this.registerBackoff.set(terminalId, now);

    let session = this.getSession(terminalId);
    try {
      await session.connect(); // no-op if already connected
      if (!session.registered) {
        await session.registration();
        session.registered = true;
        this.onLog(`ZVT ${terminal.id}: registered (session cached for reuse)`);
      }
      // Success — clear the backoff so a later legitimate reconnect isn't delayed.
      this.registerBackoff.delete(terminalId);
      return session;
    } catch (e) {
      // Keep the backoff timestamp (set above) so repeated failures are spaced.
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
