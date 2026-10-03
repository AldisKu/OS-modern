/**
 * ZVT Terminal Session
 * Manages TCP connection and ZVT protocol dialog with a single terminal.
 * All commands are serialized — no parallel ZVT messages on one session.
 */

import net from "net";
import { parseFrame, ACK, isAck, buildStatusEnquiry, buildRegistration, buildAuthorisation, buildAbort, parseCompletion, parsePrintCommand, parseStatusInformation, buildRepeatReceipt, parsePrintLine } from "./zvt-codec.js";

const DEFAULT_CONNECT_TIMEOUT = 400;
const DEFAULT_RESPONSE_TIMEOUT = 2000;
const DEFAULT_TRANSACTION_TIMEOUT = 120000;

export class ZvtSession {
  constructor(ip, port, options = {}) {
    this.ip = ip;
    this.port = port;
    this.password = options.password || "000000";
    // Config byte 0x9A (ZVT 13.07 §2.1):
    //   0x80 = PT generates the receipt and SENDS it via print commands (06 D1/06 D3)
    //   0x10 = ECR controls payment
    //   0x08 = send intermediate status
    //   0x02 = ECR assumes payment-receipt printing
    // NOTE: the "PT sends receipt" bit is the MSB 0x80. An earlier value 0x5F
    // did NOT set it, which told the terminal "ECR constructs the receipt
    // itself" and is why no 06 D1/06 D3 print blocks were sent.
    this.configByte = options.configByte || 0x9A;
    this.currencyCode = options.currencyCode || 978;
    // Optional per-transaction receipt-parameter override (TLV 1F04). null = omit.
    this.receiptParam1F04 = options.receiptParam1F04 === undefined ? null : options.receiptParam1F04;
    // Registration layout options (see buildRegistration). Defaults match the
    // proven Portalum layout that the A960 accepts.
    this.registrationOpts = {
      includeCurrency: options.registrationIncludeCurrency !== false,
      serviceByte: options.registrationServiceByte === undefined ? 0x01 : options.registrationServiceByte,
      tlvPermitPrint: options.registrationTlvPermitPrint !== false
    };
    this.connectTimeout = options.connectTimeout || DEFAULT_CONNECT_TIMEOUT;
    this.responseTimeout = options.responseTimeout || DEFAULT_RESPONSE_TIMEOUT;
    this.transactionTimeout = options.transactionTimeout || DEFAULT_TRANSACTION_TIMEOUT;
    this.socket = null;
    this.busy = false;
    this.onLog = options.onLog || (() => {});
    this.onRaw = options.onRaw || null; // raw byte capture hook (debug)
    // --- Event-driven receive state ---
    // rxBuffer accumulates TCP bytes; complete APDUs are parsed immediately in
    // the socket 'data' handler and delivered to a waiting waitFrame() or, if
    // none is waiting yet, queued (so a frame that arrives early is never lost).
    this.rxBuffer = Buffer.alloc(0);
    this.frameQueue = [];   // parsed frames not yet consumed
    this.frameWaiters = []; // pending waitFrame() promises
    // Defensive cap: if malformed data grows the buffer without producing a
    // valid frame, close the session rather than grow memory unbounded.
    this.maxRxBuffer = options.maxRxBuffer || (1024 * 1024);
    // Liveness: timestamp of the last syntactically-valid ZVT APDU received.
    // ANY valid frame (ACK, 84 error, 04 FF, 06 D1/D3, 06 0F, 06 1E, ...) is a
    // sign of life — the TCP link is up and the ZVT process answers. The health
    // check uses this to avoid polling a terminal that just communicated.
    this.lastValidRxAt = 0;
  }

  /**
   * Connect to terminal.
   */
  async connect() {
    if (this.socket && !this.socket.destroyed) return;
    return new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        if (this.socket) this.socket.destroy();
        reject(new Error(`Connect timeout ${this.ip}:${this.port}`));
      }, this.connectTimeout);

      this.socket = net.createConnection({ host: this.ip, port: this.port }, () => {
        clearTimeout(timeout);
        // TCP-level liveness + latency: keepalive probes a dead peer even when
        // no ZVT traffic flows; noDelay avoids Nagle buffering of small APDUs.
        // This complements (does NOT replace) the ZVT 05 01 health check.
        try { this.socket.setKeepAlive(true, 30000); } catch (_) {}
        try { this.socket.setNoDelay(true); } catch (_) {}
        this.rxBuffer = Buffer.alloc(0);
        this.frameQueue = [];
        resolve();
      });

      this.socket.on("data", (data) => {
        // Raw capture (read-only, for debugging): record every chunk exactly as
        // it arrives, BEFORE any parsing. ZVT over TCP is a byte stream, so a
        // chunk may hold part of an APDU, one APDU, or several concatenated.
        if (this.onRaw) { try { this.onRaw(data, "RX"); } catch (_) {} }
        this.rxBuffer = Buffer.concat([this.rxBuffer, data]);
        this.processReceiveBuffer();
      });

      this.socket.on("error", (err) => {
        clearTimeout(timeout);
        this.rejectAllWaiters(err);
        reject(err);
      });

      this.socket.on("close", () => {
        this.socket = null;
        this.rejectAllWaiters(new Error("Connection closed"));
      });
    });
  }

  /**
   * Parse all complete APDUs currently in rxBuffer and deliver them in order.
   * Runs synchronously from the socket 'data' event — no polling.
   */
  processReceiveBuffer() {
    for (;;) {
      const frame = parseFrame(this.rxBuffer);
      if (!frame) break; // only an incomplete frame remains
      this.rxBuffer = this.rxBuffer.slice(frame.totalLength);
      // A fully-parsed APDU of ANY type is proof the terminal is alive.
      this.lastValidRxAt = Date.now();
      this.onLog("RX", Buffer.from([frame.cmdClass, frame.cmdInstr]), frame.payload);
      this.deliverFrame(frame);
    }
    // Defensive: unbounded growth from malformed data.
    if (this.rxBuffer.length > this.maxRxBuffer) {
      this.onLog(`RX buffer overflow (${this.rxBuffer.length} bytes) — closing session`);
      this.rejectAllWaiters(new Error("RX buffer overflow"));
      this.disconnect();
    }
  }

  /**
   * Hand a parsed frame to the oldest pending waiter, or queue it if none.
   */
  deliverFrame(frame) {
    const waiter = this.frameWaiters.shift();
    if (waiter) waiter.resolve(frame);
    else this.frameQueue.push(frame);
  }

  /**
   * Fail all pending waitFrame() promises (called on socket close/error).
   */
  rejectAllWaiters(err) {
    const waiters = this.frameWaiters.splice(0);
    for (const w of waiters) w.reject(err);
  }

  /**
   * Disconnect from terminal.
   */
  disconnect() {
    if (this.socket) {
      try { this.socket.destroy(); } catch (_) {}
      this.socket = null;
    }
    this.rxBuffer = Buffer.alloc(0);
    this.frameQueue = [];
    this.rejectAllWaiters(new Error("Disconnected"));
  }

  /**
   * Send raw data to terminal.
   */
  send(data) {
    if (!this.socket || this.socket.destroyed) {
      throw new Error("Not connected");
    }
    // Raw capture (TX) BEFORE the write, so the trace shows the exact bytes we
    // put on the wire (e.g. every 80 00 00 ACK) interleaved with RX. This makes
    // per-line receipt ACK provable in the raw log.
    if (this.onRaw) { try { this.onRaw(data, "TX"); } catch (_) {} }
    this.socket.write(data);
    this.onLog("TX", data);
  }

  /**
   * Wait for the next complete APDU frame. Event-driven: returns an
   * already-queued frame immediately, otherwise resolves when the socket
   * 'data' handler parses one. A single setTimeout enforces the timeout; a
   * socket close/error rejects immediately (no polling, no busy loop).
   */
  waitFrame(timeoutMs) {
    const ms = timeoutMs || this.responseTimeout;
    if (this.frameQueue.length > 0) {
      return Promise.resolve(this.frameQueue.shift());
    }
    return new Promise((resolve, reject) => {
      const waiter = { resolve: null, reject: null };
      const timer = setTimeout(() => {
        const i = this.frameWaiters.indexOf(waiter);
        if (i !== -1) this.frameWaiters.splice(i, 1);
        reject(new Error("Response timeout"));
      }, ms);
      waiter.resolve = (frame) => { clearTimeout(timer); resolve(frame); };
      waiter.reject = (err) => { clearTimeout(timer); reject(err); };
      this.frameWaiters.push(waiter);
    });
  }

  /**
   * Send a command and wait for ACK.
   * After an abort/cancel the terminal can still have an Intermediate-Status
   * (04 FF) queued or emit one before ACKing the next command. Such a frame is
   * NOT an error — ACK it and keep waiting for the real ACK. This prevents a
   * harmless post-cancel 04 FF from being misread as "Expected ACK" and
   * flipping the terminal to OFFLINE on the following Status-Enquiry.
   */
  async sendAndWaitAck(commandBuffer) {
    this.send(commandBuffer);
    const deadline = Date.now() + Math.max(this.responseTimeout * 2, 4000);
    for (;;) {
      const remaining = deadline - Date.now();
      if (remaining <= 0) throw new Error("Expected ACK, timeout");
      let frame;
      try {
        // waitFrame throws "Response timeout" when its slice elapses with no
        // frame. That is NOT the overall deadline — a slow (cold WLAN) ACK can
        // take longer than one responseTimeout slice. Swallow the per-slice
        // timeout and keep waiting until the real `deadline`, so the full ACK
        // budget (>=4s) is honoured instead of bailing after one slice. This
        // prevents a slow-but-alive terminal from being flagged SUSPECT.
        frame = await this.waitFrame(Math.min(this.responseTimeout, remaining));
      } catch (e) {
        if (e && e.message === "Response timeout" && Date.now() < deadline) continue;
        throw e; // socket close/error, or real deadline reached
      }
      if (isAck(frame)) return frame;
      // Intermediate Status (04 FF): ACK and keep waiting for the real ACK.
      if (frame.cmdClass === 0x04 && frame.cmdInstr === 0xFF) {
        this.send(ACK);
        continue;
      }
      // Any other stray frame left over from a previous (aborted) operation:
      // ACK it and keep waiting rather than treating it as a protocol error.
      this.send(ACK);
    }
  }

  /**
   * Perform Status-Enquiry.
   * Returns parsed completion data.
   * Some simulators only return ACK without Completion — handle gracefully.
   */
  async statusEnquiry() {
    await this.connect();
    const cmd = buildStatusEnquiry(this.password);
    await this.sendAndWaitAck(cmd);

    // Wait for the Completion (06 0F). Real terminals (Nexi/CCV A960) may first
    // send one or more Intermediate Status frames (04 FF) that must be ACKed and
    // skipped before the actual Completion arrives.
    try {
      const completion = await this.readCompletion(this.responseTimeout);
      // Acknowledge the Completion
      this.send(ACK);
      return parseCompletion(completion.payload);
    } catch (e) {
      // Timeout waiting for Completion — terminal responded with ACK only
      // This is valid for basic simulators; return minimal data
      return {
        resultCode: 0x00,
        terminalIdentifier: null,
        serialNumber: null,
        deviceName: null,
        softwareVersion: null,
        deviceState: 0x00,
        tlvRaw: [],
        ackOnly: true
      };
    }
  }

  /**
   * Read frames until a Completion (06 0F) or Status-Information (04 0F) arrives,
   * ACKing and skipping any Intermediate Status (04 FF) frames in between.
   * Rethrows on timeout / EOF so callers can handle ACK-only terminals.
   */
  async readCompletion(perFrameTimeout) {
    const overallDeadline = Date.now() + Math.max(perFrameTimeout * 3, 6000);
    for (;;) {
      const remaining = overallDeadline - Date.now();
      if (remaining <= 0) throw new Error("Completion timeout");
      const frame = await this.waitFrame(Math.min(perFrameTimeout, remaining));

      // Intermediate Status (04 FF): ACK and keep waiting for the real completion
      if (frame.cmdClass === 0x04 && frame.cmdInstr === 0xFF) {
        this.send(ACK);
        continue;
      }
      // Bare positive ACK: ignore and keep waiting
      if (frame.cmdClass === 0x80 && frame.cmdInstr === 0x00) {
        continue;
      }
      // Completion (06 0F) or final Status-Information (04 0F): return it
      return frame;
    }
  }

  /**
   * Perform Registration.
   * Some simulators only respond with ACK (no Completion).
   */
  async registration() {
    await this.connect();
    const cmd = buildRegistration(this.password, this.configByte, this.currencyCode, this.registrationOpts);
    await this.sendAndWaitAck(cmd);

    // Wait for Completion, skipping any Intermediate Status (04 FF) frames.
    try {
      const completion = await this.readCompletion(this.responseTimeout);
      this.send(ACK);
      return parseCompletion(completion.payload);
    } catch (e) {
      // Timeout — simulator only sent ACK, which is acceptable
      return { resultCode: 0x00, ackOnly: true };
    }
  }

  /**
   * Repeat-Receipt (06 20): pull a stored receipt of a given type, optionally
   * targeted to a single TA number, and collect its 06 D1 Print-Line text.
   * Assumes a Registration has already been performed on this session.
   *
   * @param {object} opts - { type: 0x02|0x03, fromTA, toTA }
   * @returns {string[]} receipt text lines (empty if none returned)
   */
  async repeatReceipt(opts = {}) {
    await this.connect();
    const cmd = buildRepeatReceipt(this.password, opts);
    this.send(cmd);

    // Repeat-Receipt is an administration-style operation and this A960 has
    // been observed to take >3s just to ACK. Use a generous, dedicated timeout
    // (default 10s) for BOTH the initial ACK and each subsequent frame. Do NOT
    // fall back to a short responseTimeout — a premature give-up here would let
    // the caller start another command while this operation is still active on
    // the serialized session (the bug that made two 06 20 operations overlap
    // and misattributed a merchant receipt to a customer request).
    const rcptTimeout = this.receiptTimeout || Math.max(this.responseTimeout * 5, 10000);

    // Result: collected lines and the receipt type declared by the terminal
    // via TLV 1F07 (0x01=merchant, 0x02=customer, 0x03=admin) — authoritative,
    // rather than assuming based on which copy we requested.
    const lines = [];
    let receiptType = null;
    let ackSeen = false;
    let completed = false;

    // Overall guard: initial ACK within rcptTimeout, then the whole receipt
    // stream through 06 0F within a bounded window.
    const overallDeadline = Date.now() + rcptTimeout + Math.max(this.responseTimeout * 5, 10000);

    while (!completed && Date.now() < overallDeadline) {
      // Before the ACK, allow the full rcptTimeout; after the ACK, frames
      // should stream quickly but still allow a comfortable per-frame window.
      const perFrame = ackSeen ? Math.max(this.responseTimeout * 3, 6000) : rcptTimeout;
      let frame;
      try {
        frame = await this.waitFrame(Math.min(perFrame, overallDeadline - Date.now()));
      } catch (e) {
        // Timeout. The session state is now UNCERTAIN for this operation — we
        // must NOT return control such that the caller issues another command
        // blindly. Signal the uncertainty explicitly so the caller can
        // resynchronise (e.g. drop/reconnect the session) instead of pipelining.
        const err = new Error(ackSeen ? "Repeat-Receipt frame timeout" : "Repeat-Receipt ACK timeout");
        err.zvtUncertain = true;
        throw err;
      }

      // Positive ACK (80 00): the terminal accepted the 06 20; keep reading.
      if (frame.cmdClass === 0x80 && frame.cmdInstr === 0x00) {
        ackSeen = true;
        continue;
      }
      // Print Line (06 D1): collect text, ACK
      if (frame.cmdClass === 0x06 && frame.cmdInstr === 0xD1) {
        this.send(ACK);
        const { text } = parsePrintLine(frame.payload);
        lines.push(text);
        continue;
      }
      // Print Text-Block (06 D3): collect lines + receipt type, ACK
      if (frame.cmdClass === 0x06 && frame.cmdInstr === 0xD3) {
        this.send(ACK);
        const pd = parsePrintCommand(0xD3, frame.payload);
        if (pd.receiptType !== null && receiptType === null) receiptType = pd.receiptType;
        lines.push(...pd.lines);
        continue;
      }
      // Intermediate status (04 FF): ACK, continue
      if (frame.cmdClass === 0x04 && frame.cmdInstr === 0xFF) {
        this.send(ACK);
        continue;
      }
      // Completion (06 0F): end of the Repeat-Receipt operation.
      if (frame.cmdClass === 0x06 && frame.cmdInstr === 0x0F) {
        this.send(ACK);
        completed = true;
        break;
      }
      // Negative/abort (06 1E or 84 xx): operation ended without a receipt.
      if ((frame.cmdClass === 0x06 && frame.cmdInstr === 0x1E) || frame.cmdClass === 0x84) {
        this.send(ACK);
        completed = true;
        break;
      }
      // Anything else: ACK and continue.
      this.send(ACK);
    }

    if (!completed) {
      const err = new Error("Repeat-Receipt did not complete (no 06 0F)");
      err.zvtUncertain = true;
      throw err;
    }
    return { lines, receiptType };
  }

  /**
   * Perform Authorisation (immediate capture / payment).
   * Handles intermediate status, print commands, and final completion.
   * Some simulators skip the initial ACK and send Status-Information directly.
   * @param {number} amountMinor - Amount in cents
   * @param {object} callbacks - { onIntermediate, onPrint }
   * @returns {object} Payment result
   */
  async authorisation(amountMinor, callbacks = {}) {
    await this.connect();
    const cmd = buildAuthorisation(amountMinor, this.currencyCode, { receiptParam1F04: this.receiptParam1F04 });
    this.send(cmd);

    const result = {
      success: false,
      resultCode: null,
      merchantReceipt: { lines: [] },
      customerReceipt: { lines: [] },
      terminalIdentifier: null,
      receiptNumber: null,
      traceNumber: null,
      statusInfo: null // parsed 04 0F fields for receipt reconstruction
    };

    const deadline = Date.now() + this.transactionTimeout;
    // Post-04-0F grace window: after the Status-Information is received, the
    // remaining sequence (receipt push + 06 0F) should follow quickly. If a
    // terminal (or simulator) sends NOTHING further, we must not hang for the
    // whole transaction timeout — so once status is known we wait per-frame
    // only this long before treating 04 0F as final. Each received frame
    // resets the wait (there is no fixed sleep).
    const postStatusFrameTimeout = Math.max(this.responseTimeout * 2, 5000);

    while (Date.now() < deadline) {
      const waitMs = result.statusReceived
        ? postStatusFrameTimeout
        : (deadline - Date.now());
      let frame;
      try {
        frame = await this.waitFrame(waitMs);
      } catch (e) {
        // Timeout waiting for a frame.
        if (result.statusReceived) {
          // We already have the financial result; the terminal simply didn't
          // send a separate 06 0F (or any receipt). Treat 04 0F as final.
          result.success = (result.resultCode === 0x00);
          return result;
        }
        throw e; // no status yet -> genuine transaction timeout
      }

      // ACK (80 00) — just continue waiting for actual response
      if (frame.cmdClass === 0x80 && frame.cmdInstr === 0x00) {
        continue;
      }

      // Intermediate Status (04 FF)
      if (frame.cmdClass === 0x04 && frame.cmdInstr === 0xFF) {
        this.send(ACK);
        if (callbacks.onIntermediate) {
          callbacks.onIntermediate(frame.payload);
        }
        continue;
      }

      // Print Text-Block (06 D3)
      if (frame.cmdClass === 0x06 && frame.cmdInstr === 0xD3) {
        this.send(ACK);
        const printData = parsePrintCommand(0xD3, frame.payload);
        if (printData.receiptType === 0x01) {
          result.merchantReceipt.lines.push(...printData.lines);
        } else if (printData.receiptType === 0x02) {
          result.customerReceipt.lines.push(...printData.lines);
        }
        if (callbacks.onPrint) callbacks.onPrint(printData);
        continue;
      }

      // Print Line (06 D1)
      if (frame.cmdClass === 0x06 && frame.cmdInstr === 0xD1) {
        this.send(ACK);
        const printData = parsePrintCommand(0xD1, frame.payload);
        result.merchantReceipt.lines.push(...printData.lines);
        if (callbacks.onPrint) callbacks.onPrint(printData);
        continue;
      }

      // Status-Information (04 0F) — the financial RESULT of the transaction.
      // Per ZVT (PA00P015) the defined authorisation sequence is:
      //   06 01 -> (04 FF intermediate)* -> 04 0F Status-Information
      //         -> (06 D1/06 D3 receipt push)* -> 06 0F Completion
      // The ECR does NOT regain master rights (and MUST NOT send another
      // command such as 06 20) until it has received AND acknowledged 06 0F.
      // Therefore we ACK 04 0F, remember the result, optionally surface an
      // early "approved" state to the caller, but we DO NOT return here — we
      // keep looping to collect any pushed receipt lines and to consume the
      // final 06 0F. Returning at 04 0F was a protocol bug that both missed
      // pushed receipts and caused an overlapping-command timeout on the
      // subsequent 06 20 pull.
      if (frame.cmdClass === 0x04 && frame.cmdInstr === 0x0F) {
        this.send(ACK);
        const parsed = parseCompletion(frame.payload);
        result.resultCode = parsed.resultCode;
        result.terminalIdentifier = parsed.terminalIdentifier;
        // Read-only: parse receipt fields from a COPY of the payload. This must
        // never mutate the buffer or affect the payment outcome.
        try { result.statusInfo = parseStatusInformation(Buffer.from(frame.payload)); } catch (_) { result.statusInfo = null; }
        result.statusReceived = true;
        // Let the caller surface the financial result early (terminal stays
        // BUSY until 06 0F). This does NOT end the ZVT operation.
        if (callbacks.onStatusInfo) {
          try { callbacks.onStatusInfo(result.statusInfo, result.resultCode); } catch (_) {}
        }
        continue; // keep consuming receipt push + 06 0F
      }

      // Completion (06 0F) — the ZVT operation is now finished and master
      // rights return to the ECR. This is the ONLY place authorisation()
      // returns for a completed transaction.
      if (frame.cmdClass === 0x06 && frame.cmdInstr === 0x0F) {
        this.send(ACK);
        const parsed = parseCompletion(frame.payload);
        // 06 0F may be a bare completion (no result code); keep the 04 0F result.
        if (result.resultCode === null && parsed.resultCode !== null && parsed.resultCode !== undefined) {
          result.resultCode = parsed.resultCode;
        }
        result.terminalIdentifier = parsed.terminalIdentifier || result.terminalIdentifier;
        result.success = (result.resultCode === 0x00);
        return result;
      }

      // Abort from terminal (06 1E)
      if (frame.cmdClass === 0x06 && frame.cmdInstr === 0x1E) {
        this.send(ACK);
        result.success = false;
        result.resultCode = frame.payload.length > 0 ? frame.payload[0] : 0xFF;
        return result;
      }

      // Negative response (84 xx)
      if (frame.cmdClass === 0x84) {
        result.success = false;
        result.resultCode = frame.payload.length > 0 ? frame.payload[0] : 0xFF;
        return result;
      }

      // Unknown frame — ACK and continue
      this.send(ACK);
    }

    throw new Error("Transaction timeout");
  }

  /**
   * Send Abort command.
   */
  async abort() {
    if (!this.socket || this.socket.destroyed) return;
    const cmd = buildAbort();
    this.send(cmd);
    // Wait for response (may be ACK or status)
    try {
      const frame = await this.waitFrame(this.responseTimeout);
      if (!isAck(frame)) {
        // May get a completion or status
        this.send(ACK);
      }
    } catch (_) {
      // Timeout on abort response is acceptable
    }
  }
}
