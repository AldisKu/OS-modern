/**
 * Payment Service
 * Handles payment requests from POS clients, manages transaction lifecycle,
 * terminal locking, receipt buffering, and result routing.
 */

import { randomUUID } from "crypto";
import { parseFrame, parsePrintCommand, formatReceipt } from "./zvt-codec.js";
import { generateSyntheticReceipts } from "./synthetic-receipts.js";

const TEST_INJECT_RECEIPTS = process.env.ZVT_TEST_INJECT_RECEIPTS === "true";

// Payment states
const STATE = {
  REQUESTED: "REQUESTED",
  LOCKED: "LOCKED",
  VERIFYING: "VERIFYING_TERMINAL",
  DISCOVERING: "DISCOVERING",
  REGISTERING: "REGISTERING",
  AUTHORISATION_SENT: "AUTHORISATION_SENT",
  IN_PROGRESS: "IN_PROGRESS",
  SUCCESS: "SUCCESS",
  DECLINED: "DECLINED",
  CANCELLED: "CANCELLED",
  FAILED: "FAILED",
  UNKNOWN: "UNKNOWN"
};

export class PaymentService {
  constructor(terminalManager, options = {}) {
    this.tm = terminalManager;
    this.transactions = new Map(); // requestId -> transaction
    this.onLog = options.onLog || console.log;
    this.printReceipt = options.printReceipt || (async () => {}); // CUPS print function
    // Receipt routing mode:
    //  "terminal" (default) — the payment terminal prints its own receipts
    //    (ZVT config byte 0x1C). The broker does not expect receipt data, so a
    //    missing merchant receipt is normal and no warning is raised.
    //  "broker" — the terminal forwards receipts to the broker (06 D1/06 D3)
    //    for printing via CUPS. A missing merchant receipt is then a real
    //    warning (MERCHANT_RECEIPT_MISSING).
    this.receiptMode = options.receiptMode || "terminal";
    // Merchant header lines for reconstructed receipts (from DSFinV-K company data).
    this.receiptHeaderLines = options.receiptHeaderLines || [];
    // ReceiptStore: persists pulled card receipts. Optional.
    this.receiptStore = options.receiptStore || null;
  }

  /**
   * Process a payment request from a POS client.
   * @param {object} request - { requestId, amountMinor, currency, terminalId, posId, orderId }
   * @returns {object} Immediate response (state, error, etc.)
   */
  async processPayment(request) {
    const { requestId, amountMinor, terminalId, posId } = request;

    // Validate
    if (!requestId || !amountMinor || !terminalId) {
      return { requestId, state: STATE.FAILED, error: "INVALID_REQUEST" };
    }
    if (!Number.isInteger(amountMinor) || amountMinor <= 0) {
      return { requestId, state: STATE.FAILED, error: "INVALID_REQUEST", message: "Amount must be positive integer cents" };
    }

    // Idempotency check
    const existing = this.transactions.get(requestId);
    if (existing) {
      if (existing.amountMinor !== amountMinor || existing.terminalId !== terminalId) {
        return { requestId, state: STATE.FAILED, error: "IDEMPOTENCY_CONFLICT" };
      }
      // Return current state
      return this.buildResponse(existing);
    }

    // Check terminal exists
    const terminal = this.tm.getTerminal(terminalId);
    if (!terminal) {
      return { requestId, state: STATE.FAILED, error: "TERMINAL_UNAVAILABLE", message: "Terminal not found" };
    }

    // Try to lock terminal
    if (!this.tm.lock(terminalId, requestId, posId)) {
      const lock = this.tm.getLock(terminalId);
      return {
        requestId,
        state: STATE.FAILED,
        error: "TERMINAL_BUSY",
        message: `Terminal belegt (${lock?.posId || "unknown"})`
      };
    }

    // Create transaction
    const tx = {
      requestId,
      posId,
      terminalId,
      amountMinor,
      currency: request.currency || "EUR",
      orderId: request.orderId || null,
      // Optional per-request merchant header (POS may supply its own); falls
      // back to the broker's DSFinV-K header when absent.
      receiptHeaderLines: Array.isArray(request.receiptHeaderLines) ? request.receiptHeaderLines : null,
      state: STATE.LOCKED,
      createdAt: new Date().toISOString(),
      authorisationSentAt: null,
      completedAt: null,
      resultCode: null,
      receipts: {
        merchantReceipt: { lines: [] },
        customerReceipt: { lines: [] }
      },
      error: null
    };
    this.transactions.set(requestId, tx);

    // Execute payment asynchronously
    this.executePayment(tx).catch(err => {
      this.onLog(`PAYMENT ERROR ${requestId}: ${err.message}`);
    });

    return { requestId, state: STATE.IN_PROGRESS, terminalId };
  }

  /**
   * Execute the actual ZVT payment flow.
   */
  async executePayment(tx) {
    try {
      // Phase 1: Verify terminal identity
      tx.state = STATE.VERIFYING;
      const verified = await this.tm.verifyIdentity(tx.terminalId);
      if (!verified) {
        // Try recovery discovery
        tx.state = STATE.DISCOVERING;
        await this.tm.discover();
        const retryVerify = await this.tm.verifyIdentity(tx.terminalId);
        if (!retryVerify) {
          tx.state = STATE.FAILED;
          tx.error = "TERMINAL_UNAVAILABLE";
          tx.completedAt = new Date().toISOString();
          this.tm.unlock(tx.terminalId);
          return;
        }
      }

      // Phase 2: Get a CONNECTED + REGISTERED session (8a). The terminal
      // manager caches one long-lived session per terminal and registers only
      // once per connection — subsequent payments reuse the same socket with
      // no reconnect/re-registration churn.
      tx.state = STATE.REGISTERING;
      let session;
      try {
        session = await this.tm.getRegisteredSession(tx.terminalId);
      } catch (e) {
        tx.state = STATE.FAILED;
        tx.error = "PROTOCOL_ERROR";
        tx.completedAt = new Date().toISOString();
        this.tm.invalidateSession(tx.terminalId);
        this.tm.unlock(tx.terminalId);
        return;
      }
      if (!session) {
        tx.state = STATE.FAILED;
        tx.error = "TERMINAL_UNAVAILABLE";
        tx.completedAt = new Date().toISOString();
        this.tm.unlock(tx.terminalId);
        return;
      }

      // Phase 3: Send Authorisation
      tx.state = STATE.AUTHORISATION_SENT;
      tx.authorisationSentAt = new Date().toISOString();

      try {
        const result = await session.authorisation(tx.amountMinor, {
          onIntermediate: (payload) => {
            tx.state = STATE.IN_PROGRESS;
            this.onLog(`PAYMENT ${tx.requestId}: Intermediate status`);
          },
          onPrint: (printData) => {
            // Buffer receipts
            if (printData.receiptType === 0x01) {
              tx.receipts.merchantReceipt.lines.push(...printData.lines);
            } else if (printData.receiptType === 0x02) {
              tx.receipts.customerReceipt.lines.push(...printData.lines);
            } else {
              // Unknown type or 06 D1 without type — merchant by default
              tx.receipts.merchantReceipt.lines.push(...printData.lines);
            }
          }
        });

        if (result.success) {
          // Keep state IN_PROGRESS while we pull/reconstruct/save receipts, then
          // flip to SUCCESS at the very end. This prevents pollPaymentCompletion
          // from emitting PAYMENT_RESULT before the receipt availability flags
          // are known (which previously left "Kartenbeleg" disabled in the POS).
          tx.resultCode = result.resultCode;

          // Test mode: inject synthetic receipts if terminal didn't provide them
          if (TEST_INJECT_RECEIPTS && tx.receipts.merchantReceipt.lines.length === 0) {
            this.onLog(`PAYMENT ${tx.requestId}: Injecting synthetic receipts (test mode)`);
            const synthetic = generateSyntheticReceipts({
              terminalIdentifier: result.terminalIdentifier || "29001234",
              amountMinor: tx.amountMinor,
              requestId: tx.requestId
            });
            // Parse synthetic frames through normal receipt parser (source=SYNTHETIC, no ACK)
            const merchantParsed = this.parseSyntheticFrame(synthetic.merchantFrame);
            if (merchantParsed) {
              tx.receipts.merchantReceipt.lines = merchantParsed.lines;
            }
            const customerParsed = this.parseSyntheticFrame(synthetic.customerFrame);
            if (customerParsed) {
              tx.receipts.customerReceipt.lines = customerParsed.lines;
            }
          }

          // Capture the transaction details for receipt storage/reprint.
          if (result.statusInfo) {
            tx.cardName = result.statusInfo.cardName || null;
            tx.traceNumber = result.statusInfo.traceNumber || null;
            tx.receiptNumber = result.statusInfo.receiptNumber || null;
          }

          // PRIMARY receipt source is now the receipts PUSHED by the terminal
          // during authorisation (06 D1/06 D3 between 04 0F and 06 0F), which
          // authorisation() collects via onPrint. Those land in
          // tx.receipts.*.lines above.
          //
          // FALLBACK PULL: only if the terminal pushed NO merchant lines do we
          // pull via Repeat-Receipt (06 20). This now runs strictly AFTER
          // authorisation() returned on 06 0F — i.e. the terminal has released
          // master rights — so there is no overlapping-command conflict (the
          // earlier premature return at 04 0F caused the merchant-pull timeout).
          if (tx.receipts.merchantReceipt.lines.length === 0) {
            await this.pullReceipts(tx, session);
          }

          // FALLBACK: if the terminal returned nothing to pull, reconstruct a
          // summary receipt from the 04 0F Status-Information + DSFinV-K header.
          if (result.statusInfo && tx.receipts.merchantReceipt.lines.length === 0) {
            const header = tx.receiptHeaderLines && tx.receiptHeaderLines.length
              ? tx.receiptHeaderLines : this.receiptHeaderLines;
            try {
              tx.receipts.merchantReceipt.lines = formatReceipt(result.statusInfo, { headerLines: header, copyType: "merchant" });
              tx.receipts.customerReceipt.lines = formatReceipt(result.statusInfo, { headerLines: header, copyType: "customer" });
              tx.receipts.reconstructed = true;
              this.onLog(`PAYMENT ${tx.requestId}: Reconstructed receipt (fallback) from Status-Information`);
            } catch (e) {
              this.onLog(`PAYMENT ${tx.requestId}: Receipt reconstruction failed: ${e.message}`);
            }
          }

          // Persist receipts to the store (no auto-print — printing is on demand
          // via the POS receipt popup / reprint UI).
          if (this.receiptStore &&
              (tx.receipts.merchantReceipt.lines.length > 0 || tx.receipts.customerReceipt.lines.length > 0)) {
            try {
              const entry = this.receiptStore.save({
                requestId: tx.requestId,
                terminalId: tx.terminalId,
                amountMinor: tx.amountMinor,
                currency: tx.currency,
                cardName: tx.cardName,
                traceNumber: tx.traceNumber,
                receiptNumber: tx.receiptNumber,
                resultCode: tx.resultCode
              }, {
                merchantLines: tx.receipts.merchantReceipt.lines,
                customerLines: tx.receipts.customerReceipt.lines
              });
              tx.receiptId = entry.id;
            } catch (e) {
              this.onLog(`PAYMENT ${tx.requestId}: Receipt store failed: ${e.message}`);
            }
          }

          // Payment + both receipt pulls complete. 8a: DO NOT disconnect — the
          // session stays cached in the terminal manager (registered) so the
          // next payment reuses it with no reconnect/re-registration.

          // Receipts ready — NOW mark success so the PAYMENT_RESULT the POS
          // receives carries the correct receipt-availability flags.
          tx.state = STATE.SUCCESS;
          tx.completedAt = new Date().toISOString();
        } else {
          // Declined/cancelled by terminal: session is still healthy and
          // registered — keep it cached for reuse (no disconnect).
          tx.state = result.resultCode === 0x63 ? STATE.CANCELLED : STATE.DECLINED;
          tx.resultCode = result.resultCode;
          tx.completedAt = new Date().toISOString();
        }
      } catch (e) {
        // Connection lost AFTER authorisation sent — UNKNOWN state. The socket
        // is unreliable now, so evict the cached session; the next operation
        // will reconnect + re-register from scratch.
        tx.state = STATE.UNKNOWN;
        tx.error = "UNKNOWN_TRANSACTION_STATE";
        tx.completedAt = new Date().toISOString();
        this.onLog(`PAYMENT ${tx.requestId}: UNKNOWN STATE — ${e.message}. DO NOT RETRY.`);
        this.tm.invalidateSession(tx.terminalId);
        const terminal = this.tm.getTerminal(tx.terminalId);
        if (terminal) terminal.runtimeStatus = "ATTENTION";
      }
    } finally {
      this.tm.unlock(tx.terminalId);
    }
  }

  /**
   * Pull the merchant + customer receipts for a completed transaction from the
   * terminal via Repeat-Receipt (06 20), targeted at its TA number. Fills
   * tx.receipts.{merchant,customer}Receipt.lines. Best-effort: any failure
   * leaves the arrays empty so the caller can fall back to reconstruction.
   * Runs on a fresh session while the terminal is still locked for this tx.
   */
  /**
   * Pull the merchant + customer receipts for a completed transaction, reusing
   * the SAME session the payment ran on. The session is already registered and,
   * per ZVT, idle once the payment's final 06 0F completion has been ACKed — so
   * each Repeat-Receipt (06 20) is just the next sequential command. No new
   * connection, no re-registration, no timing delay.
   * @param {ZvtSession} session - the live, post-payment session
   */
  async pullReceipts(tx, session) {
    if (!session) return;
    const ta = tx.traceNumber != null ? parseInt(tx.traceNumber, 10) : null;
    const targetTA = Number.isInteger(ta) ? (ta & 0xFF) : null;
    // Receipt 1: merchant (1F01 = 02) — send, wait for its completion.
    try {
      const m = await session.repeatReceipt({ type: 0x02, fromTA: targetTA, toTA: targetTA });
      if (m && m.length) tx.receipts.merchantReceipt.lines = m;
    } catch (e) { this.onLog(`PAYMENT ${tx.requestId}: merchant receipt pull failed: ${e.message}`); }
    // Receipt 2: customer (1F01 = 03) — send, wait for its completion.
    try {
      const c = await session.repeatReceipt({ type: 0x03, fromTA: targetTA, toTA: targetTA });
      if (c && c.length) tx.receipts.customerReceipt.lines = c;
    } catch (e) { this.onLog(`PAYMENT ${tx.requestId}: customer receipt pull failed: ${e.message}`); }
    if (tx.receipts.merchantReceipt.lines.length || tx.receipts.customerReceipt.lines.length) {
      tx.receipts.pulled = true;
      this.onLog(`PAYMENT ${tx.requestId}: pulled receipts (merchant=${tx.receipts.merchantReceipt.lines.length} lines, customer=${tx.receipts.customerReceipt.lines.length} lines)`);
    }
  }

  /**
   * Cancel a payment in progress.
   */
  async cancelPayment(requestId) {
    const tx = this.transactions.get(requestId);
    if (!tx) return { error: "NOT_FOUND" };

    if (tx.state === STATE.SUCCESS || tx.state === STATE.DECLINED || tx.state === STATE.CANCELLED) {
      return { error: "ALREADY_COMPLETED", state: tx.state };
    }

    // Only abort if authorisation was sent. The Abort (06 B0) must travel on
    // the SAME live socket the authorisation is running on, so use the cached
    // session and do NOT disconnect it — the in-flight authorisation loop will
    // receive the terminal's 06 1E abort and return, and the session stays
    // cached for reuse. Only connect if there is no live socket yet.
    if (tx.authorisationSentAt) {
      const session = this.tm.getSession(tx.terminalId);
      if (session) {
        try {
          await session.connect(); // no-op if already connected
          await session.abort();
        } catch (_) {}
      }
    }

    tx.state = STATE.CANCELLED;
    tx.completedAt = new Date().toISOString();
    this.tm.unlock(tx.terminalId);
    return { requestId, state: STATE.CANCELLED };
  }

  /**
   * Change amount — abort current payment, start new one with higher amount.
   */
  async changeAmount(requestId, newAmountMinor, posId) {
    const tx = this.transactions.get(requestId);
    if (!tx) return { error: "NOT_FOUND" };

    if (newAmountMinor <= tx.amountMinor) {
      return { error: "INVALID_REQUEST", message: "New amount must be higher than original" };
    }

    // Cancel existing
    await this.cancelPayment(requestId);

    // Start new payment with new requestId
    const newRequestId = randomUUID();
    return this.processPayment({
      requestId: newRequestId,
      amountMinor: newAmountMinor,
      currency: tx.currency,
      terminalId: tx.terminalId,
      posId,
      orderId: tx.orderId
    });
  }

  /**
   * Get payment status/result.
   */
  getPaymentStatus(requestId) {
    const tx = this.transactions.get(requestId);
    if (!tx) return null;
    return this.buildResponse(tx);
  }

  /**
   * Get customer receipt for printing.
   */
  getCustomerReceipt(requestId) {
    const tx = this.transactions.get(requestId);
    if (!tx) return null;
    return tx.receipts.customerReceipt.lines;
  }

  /**
   * Get merchant receipt for reprinting.
   */
  getMerchantReceipt(requestId) {
    const tx = this.transactions.get(requestId);
    if (!tx) return null;
    return tx.receipts.merchantReceipt.lines;
  }

  /**
   * Build response object for POS.
   */
  buildResponse(tx) {
    const resp = {
      requestId: tx.requestId,
      state: tx.state,
      terminalId: tx.terminalId,
      amountMinor: tx.amountMinor
    };

    if (tx.error) resp.error = tx.error;
    if (tx.receiptId) resp.receiptId = tx.receiptId; // stored-receipt id (for reprint)

    if (tx.state === STATE.SUCCESS || tx.state === STATE.DECLINED || tx.state === STATE.CANCELLED) {
      resp.receipts = {
        merchantReceiptAvailable: tx.receipts.merchantReceipt.lines.length > 0,
        merchantReceiptPrinted: tx.receipts.merchantReceiptPrinted || false,
        customerReceiptAvailable: tx.receipts.customerReceipt.lines.length > 0
      };
    }

    // Only warn about a missing merchant receipt when we actually expect the
    // terminal to forward it to the broker. In "terminal" mode the terminal
    // prints its own receipt, so absence is normal.
    if (this.receiptMode === "broker" && tx.state === STATE.SUCCESS && !resp.receipts.merchantReceiptAvailable) {
      resp.warning = "MERCHANT_RECEIPT_MISSING";
    }

    return resp;
  }

  /**
   * Clean up old transactions (keep for session duration).
   */
  cleanup(maxAgeMs = 3600000) {
    const now = Date.now();
    for (const [id, tx] of this.transactions) {
      if (tx.completedAt && (now - new Date(tx.completedAt).getTime()) > maxAgeMs) {
        this.transactions.delete(id);
      }
    }
  }

  /**
   * Parse a synthetic 06 D3 frame through the normal receipt parser.
   * No network ACK is sent (source = SYNTHETIC).
   */
  parseSyntheticFrame(frameBuffer) {
    const frame = parseFrame(frameBuffer);
    if (!frame || frame.cmdClass !== 0x06 || frame.cmdInstr !== 0xD3) return null;
    return parsePrintCommand(0xD3, frame.payload);
  }
}

export { STATE };
