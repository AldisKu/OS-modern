/**
 * ZVT Integration Module
 * Connects the TerminalManager and PaymentService to the broker's WebSocket infrastructure.
 * Loaded conditionally based on config.
 */

import { exec, execFileSync } from "child_process";
import { appendFileSync } from "fs";
import net from "net";
import { TerminalManager } from "./terminal-manager.js";
import { PaymentService, STATE } from "./payment-service.js";
import { ReceiptStore } from "./receipt-store.js";

const CUPS_PRINTER = process.env.ZVT_CUPS_PRINTER || "EPSON_TM-T20II";
const RAW_LOG = process.env.ZVT_RAW_LOG || "/tmp/zvt-raw.log";

// ESC/POS trailer: feed a few lines, partial cut (GS V 66 10), reset (ESC @).
const ESCPOS_CUT = Buffer.from([0x1d, 0x56, 0x42, 0x0a, 0x1b, 0x40]);

/**
 * Build the print configuration (one-time setup, from config.json zvt.print).
 * Two modes:
 *   "cups" — spool via `lp` to a CUPS queue. cupsHost null = local cupsd;
 *            set cupsHost to reach a remote (possibly unadvertised) queue.
 *   "lan"  — open a raw TCP socket to the printer (JetDirect, default 9100)
 *            and write the ESC/POS bytes directly. No CUPS, no print agent.
 * Environment variables still override for quick ops changes.
 */
function buildPrintConfig(zvtConfig) {
  const p = (zvtConfig && zvtConfig.print) || {};
  return {
    mode: process.env.ZVT_PRINT_MODE || p.mode || "cups",
    cupsPrinter: process.env.ZVT_CUPS_PRINTER || p.cupsPrinter || CUPS_PRINTER,
    cupsHost: process.env.ZVT_CUPS_HOST || p.cupsHost || null, // null = local lp
    lanHost: process.env.ZVT_PRINT_LAN_HOST || p.lanHost || null,
    lanPort: Number(process.env.ZVT_PRINT_LAN_PORT || p.lanPort || 9100),
    timeoutMs: Number(p.timeoutMs || 10000)
  };
}

// Path to OrderSprinter's PHP config (for DSFinV-K company lookup at startup).
// Override with ZVT_OS_CONFIG_PHP if the webroot differs.
const OS_CONFIG_PHP = process.env.ZVT_OS_CONFIG_PHP || "/var/www/webapp/php/config.php";

/**
 * Read the merchant/company header ONCE at broker start from the cashier's
 * DSFinV-K config fields. Uses PHP CLI so the broker never sees DB credentials.
 * Returns an array of header lines (empty array on any failure).
 *
 * Precedence: explicit config.receiptHeaderLines > DSFinV-K DB fields > [].
 */
function loadCompanyHeader(config, log) {
  if (Array.isArray(config.receiptHeaderLines) && config.receiptHeaderLines.length) {
    return config.receiptHeaderLines;
  }
  try {
    const php = `include "${OS_CONFIG_PHP}";` +
      `$m=new mysqli(MYSQL_HOST,MYSQL_USER,MYSQL_PASSWORD,MYSQL_DB,MYSQL_PORT);` +
      `if($m->connect_errno){exit;}` +
      `$r=$m->query("SELECT name,setting FROM ".TAB_PREFIX."config WHERE name LIKE 'dsfinvk_%'");` +
      `$o=[];while($row=$r->fetch_assoc()){$o[$row['name']]=$row['setting'];}` +
      `echo json_encode($o);`;
    const out = execFileSync("php", ["-r", php], { timeout: 5000, encoding: "utf8" });
    const d = JSON.parse(out || "{}");
    const lines = [];
    if (d.dsfinvk_name) lines.push(d.dsfinvk_name);
    if (d.dsfinvk_street) lines.push(d.dsfinvk_street);
    const cityLine = [d.dsfinvk_postalcode, d.dsfinvk_city].filter(Boolean).join(" ");
    if (cityLine) lines.push(cityLine);
    if (d.dsfinvk_ustid) lines.push(`USt-IdNr. ${d.dsfinvk_ustid}`);
    return lines;
  } catch (e) {
    log(`Company header load failed (${e.message}); receipts will have no header`);
    return [];
  }
}

/**
 * Initialize ZVT integration.
 * @param {object} config - ZVT configuration from broker config
 * @param {Set} clients - WebSocket clients set from broker
 * @param {string} brokerDir - Directory for config/registry files
 * @returns {object} ZVT API for message handling
 */
export function initZvt(config, clients, brokerDir) {
  // Debug logging: enabled via env ZVT_DEBUG=true or config.debug=true.
  // When on, ZVT TX/RX frames are logged as hex. Off by default (spec §45).
  // Sensitive card data is never intentionally logged; hex dumps are raw frame
  // headers/payloads for protocol diagnosis only.
  const DEBUG = process.env.ZVT_DEBUG === "true" || config.debug === true;

  const hex = (b) => Buffer.isBuffer(b) ? b.toString("hex").replace(/(..)/g, "$1 ").trim() : String(b);
  const log = (...args) => {
    // Session log calls come in as ("<id> TX:"/"RX:", headBuf, payloadBuf?).
    // Only emit those hex dumps when DEBUG is on; always emit plain messages.
    const first = args[0];
    if (typeof first === "string" && /\b(TX|RX):$/.test(first)) {
      if (!DEBUG) return;
      const parts = args.slice(1).map(a => Buffer.isBuffer(a) ? hex(a) : a);
      console.log("[ZVT]", first, ...parts);
      return;
    }
    console.log("[ZVT]", ...args);
  };

  log(`Debug logging: ${DEBUG ? "ON" : "off"}`);

  const tm = new TerminalManager({
    ...config,
    debug: DEBUG,
    onLog: log
  }, brokerDir);

  // Raw ZVT capture for debugging: when ZVT_DEBUG is on, append every received
  // chunk (hex, timestamped) to a raw log so we can analyse the exact byte
  // stream offline without any more card transactions.
  if (DEBUG) {
    tm.onRawCapture = (chunk, dir = "RX") => {
      try {
        appendFileSync(RAW_LOG, `${new Date().toISOString()} ${dir} ${chunk.toString("hex")}\n`);
      } catch (_) {}
    };
    log(`Raw ZVT capture -> ${RAW_LOG}`);
  }

  // Company header for reconstructed receipts, read ONCE at broker start from
  // the cashier's DSFinV-K config (authoritative, structured company data).
  // Read via PHP CLI so the broker never handles DB credentials directly.
  const companyHeader = loadCompanyHeader(config, log);
  log(`Receipt header: ${companyHeader.length ? companyHeader.join(" | ") : "(none — reconstruction will omit header)"}`);

  // One-time print setup from config.json (zvt.print). Two modes: "cups" and
  // "lan" (direct TCP to printer:9100). Env overrides still apply.
  PRINT_CONFIG = buildPrintConfig(config);
  printLog = log;
  log(`Printing: mode=${PRINT_CONFIG.mode}` +
      (PRINT_CONFIG.mode === "lan"
        ? ` lan=${PRINT_CONFIG.lanHost || "(unset!)"}:${PRINT_CONFIG.lanPort}`
        : ` cups=${PRINT_CONFIG.cupsPrinter}${PRINT_CONFIG.cupsHost ? "@" + PRINT_CONFIG.cupsHost : " (local)"}`));

  // Persistent card-receipt store (files + index under <brokerDir>/receipts).
  const receiptStore = new ReceiptStore(brokerDir, { onLog: log });

  const ps = new PaymentService(tm, {
    onLog: log,
    // Receipt routing: "terminal" (default) = A960 prints its own receipts
    // (config byte 0x1C). Set to "broker" only if the terminal is configured
    // to forward receipts (06 D1/06 D3) for CUPS printing.
    receiptMode: config.receiptMode || "terminal",
    receiptHeaderLines: companyHeader,
    receiptStore,
    printReceipt: (lines, type, tx) => printReceipt(lines, type, tx)
  });

  // Start polling and discovery
  tm.startPolling();
  tm.startDiscoverySchedule();

  // Notify POS clients when terminal status changes
  tm.onTerminalStatusChange = () => {
    broadcastTerminalList(clients, tm);
  };

  // Periodic transaction cleanup
  setInterval(() => ps.cleanup(), 60000);

  log(`Initialized. Scanning ports: ${config.scanPorts || [20007, 20011, 40007]}`);

  return {
    /**
     * Handle incoming WebSocket message related to ZVT/payment.
     * @param {WebSocket} ws - The client WebSocket
     * @param {object} msg - Parsed message
     */
    async handleMessage(ws, msg) {
      switch (msg.type) {
        case "REQUEST_TERMINALS": {
          const list = tm.getTerminalList();
          ws.send(JSON.stringify({
            type: "TERMINAL_LIST",
            terminals: list,
            manualAvailable: true,
            ts: Date.now()
          }));
          break;
        }

        case "PAYMENT_REQUEST": {
          const result = await ps.processPayment({
            requestId: msg.requestId,
            amountMinor: msg.amountMinor,
            currency: msg.currency || "EUR",
            terminalId: msg.terminalId,
            posId: ws.meta?.clientName || ws.meta?.deviceId || String(ws.meta?.id),
            orderId: msg.orderId || null
          });

          ws.send(JSON.stringify({
            type: "PAYMENT_STATUS",
            ...result,
            ts: Date.now()
          }));

          // If payment started, poll for completion and send final result
          if (result.state === STATE.IN_PROGRESS || result.state === "IN_PROGRESS") {
            pollPaymentCompletion(ws, msg.requestId, ps);
          }
          break;
        }

        case "PAYMENT_CANCEL": {
          const result = await ps.cancelPayment(msg.requestId);
          ws.send(JSON.stringify({
            type: "PAYMENT_STATUS",
            ...result,
            ts: Date.now()
          }));
          broadcastTerminalList(clients, tm);
          break;
        }

        case "PAYMENT_CHANGE_AMOUNT": {
          const posId = ws.meta?.clientName || ws.meta?.deviceId || String(ws.meta?.id);
          const result = await ps.changeAmount(msg.requestId, msg.newAmountMinor, posId);
          ws.send(JSON.stringify({
            type: "PAYMENT_STATUS",
            ...result,
            ts: Date.now()
          }));
          if (result.state === STATE.IN_PROGRESS || result.state === "IN_PROGRESS") {
            pollPaymentCompletion(ws, result.requestId, ps);
          }
          break;
        }

        case "PAYMENT_STATUS_REQUEST": {
          const status = ps.getPaymentStatus(msg.requestId);
          if (status) {
            ws.send(JSON.stringify({ type: "PAYMENT_STATUS", ...status, ts: Date.now() }));
          } else {
            ws.send(JSON.stringify({ type: "PAYMENT_STATUS", requestId: msg.requestId, error: "NOT_FOUND", ts: Date.now() }));
          }
          break;
        }

        case "PRINT_CUSTOMER_RECEIPT": {
          const lines = ps.getCustomerReceipt(msg.requestId);
          if (lines && lines.length > 0) {
            try {
              await printReceipt(lines, "customer");
              ws.send(JSON.stringify({ type: "RECEIPT_PRINTED", requestId: msg.requestId, receiptType: "customer", success: true, ts: Date.now() }));
            } catch (e) {
              ws.send(JSON.stringify({ type: "RECEIPT_PRINTED", requestId: msg.requestId, receiptType: "customer", success: false, error: e.message, ts: Date.now() }));
            }
          } else {
            ws.send(JSON.stringify({ type: "RECEIPT_PRINTED", requestId: msg.requestId, receiptType: "customer", success: false, error: "NO_RECEIPT", ts: Date.now() }));
          }
          break;
        }

        case "PRINT_MERCHANT_RECEIPT": {
          const lines = ps.getMerchantReceipt(msg.requestId);
          if (lines && lines.length > 0) {
            try {
              await printReceipt(lines, "merchant");
              ws.send(JSON.stringify({ type: "RECEIPT_PRINTED", requestId: msg.requestId, receiptType: "merchant", success: true, ts: Date.now() }));
            } catch (e) {
              ws.send(JSON.stringify({ type: "RECEIPT_PRINTED", requestId: msg.requestId, receiptType: "merchant", success: false, error: e.message, ts: Date.now() }));
            }
          } else {
            ws.send(JSON.stringify({ type: "RECEIPT_PRINTED", requestId: msg.requestId, receiptType: "merchant", success: false, error: "NO_RECEIPT", ts: Date.now() }));
          }
          break;
        }

        case "TRIGGER_DISCOVERY": {
          await tm.discover();
          broadcastTerminalList(clients, tm);
          ws.send(JSON.stringify({ type: "DISCOVERY_COMPLETE", terminals: tm.getTerminalList(), ts: Date.now() }));
          break;
        }

        case "LIST_CARD_RECEIPTS": {
          // Return the stored card-receipt index (newest first), sanitized.
          const items = receiptStore.list().map(e => ({
            id: e.id,
            timestamp: e.timestamp,
            terminalId: e.terminalId,
            amountMinor: e.amountMinor,
            currency: e.currency,
            cardName: e.cardName,
            hasMerchant: !!e.merchantFile,
            hasCustomer: !!e.customerFile
          }));
          ws.send(JSON.stringify({ type: "CARD_RECEIPT_LIST", receipts: items, ts: Date.now() }));
          break;
        }

        case "PRINT_CARD_RECEIPT": {
          // Print a stored card receipt on the CUPS printer.
          // msg: { id, copyType: "merchant"|"customer" }
          const copyType = msg.copyType === "merchant" ? "merchant" : "customer";
          const lines = receiptStore.getReceiptLines(msg.id, copyType);
          if (lines && lines.length > 0) {
            try {
              await printReceipt(lines, copyType);
              ws.send(JSON.stringify({ type: "CARD_RECEIPT_PRINTED", id: msg.id, copyType, success: true, ts: Date.now() }));
            } catch (e) {
              ws.send(JSON.stringify({ type: "CARD_RECEIPT_PRINTED", id: msg.id, copyType, success: false, error: e.message, ts: Date.now() }));
            }
          } else {
            ws.send(JSON.stringify({ type: "CARD_RECEIPT_PRINTED", id: msg.id, copyType, success: false, error: "NOT_FOUND", ts: Date.now() }));
          }
          break;
        }

        case "PRINT_ALL_MERCHANT": {
          // Print ALL active merchant receipts as one CUPS job (no cuts).
          // Does NOT archive/delete here — the POS asks the user to confirm the
          // printout, then sends ARCHIVE_MERCHANT_RECEIPTS.
          const { lines, count } = receiptStore.getAllMerchantReceiptsCombined();
          if (count === 0) {
            ws.send(JSON.stringify({ type: "PRINT_ALL_MERCHANT_DONE", count: 0, success: true, ts: Date.now() }));
            break;
          }
          try {
            await printReceipt(lines, "merchant");
            ws.send(JSON.stringify({ type: "PRINT_ALL_MERCHANT_DONE", count, success: true, ts: Date.now() }));
          } catch (e) {
            ws.send(JSON.stringify({ type: "PRINT_ALL_MERCHANT_DONE", count, success: false, error: e.message, ts: Date.now() }));
          }
          break;
        }

        case "ARCHIVE_MERCHANT_RECEIPTS": {
          // Called after the user confirms the print-all actually printed.
          // Moves merchant receipts to the archive folder + deletes customer copies.
          const res = receiptStore.archivePrintedMerchants();
          ws.send(JSON.stringify({ type: "MERCHANT_RECEIPTS_ARCHIVED", ...res, success: true, ts: Date.now() }));
          break;
        }

        case "DELETE_MERCHANT_RECEIPT": {
          // Permanent manual delete of a single merchant receipt.
          const okDel = receiptStore.deleteMerchant(msg.id);
          ws.send(JSON.stringify({ type: "MERCHANT_RECEIPT_DELETED", id: msg.id, success: okDel, ts: Date.now() }));
          break;
        }
      }
    }
  };
}

/**
 * Poll for payment completion and send result to POS.
 */
async function pollPaymentCompletion(ws, requestId, ps) {
  const maxWait = 130000; // 130 seconds
  const start = Date.now();
  const interval = setInterval(() => {
    const status = ps.getPaymentStatus(requestId);
    if (!status) {
      clearInterval(interval);
      return;
    }
    // Send final state
    const finalStates = [STATE.SUCCESS, STATE.DECLINED, STATE.CANCELLED, STATE.FAILED, STATE.UNKNOWN];
    if (finalStates.includes(status.state)) {
      clearInterval(interval);
      if (ws.readyState === ws.OPEN) {
        ws.send(JSON.stringify({ type: "PAYMENT_RESULT", ...status, ts: Date.now() }));
      }
      return;
    }
    // Timeout
    if (Date.now() - start > maxWait) {
      clearInterval(interval);
      if (ws.readyState === ws.OPEN) {
        ws.send(JSON.stringify({ type: "PAYMENT_RESULT", requestId, state: STATE.UNKNOWN, error: "TIMEOUT", ts: Date.now() }));
      }
    }
  }, 500);
}

/**
 * Broadcast terminal list to all connected POS clients.
 */
function broadcastTerminalList(clients, tm) {
  const list = tm.getTerminalList();
  const payload = JSON.stringify({ type: "TERMINAL_LIST", terminals: list, manualAvailable: true, ts: Date.now() });
  for (const ws of clients) {
    if (ws.readyState === ws.OPEN && ws.meta && ws.meta.role === "pos") {
      ws.send(payload);
    }
  }
}

// Active print configuration, set once in initZvt() from config.json.
let PRINT_CONFIG = { mode: "cups", cupsPrinter: CUPS_PRINTER, cupsHost: null, lanHost: null, lanPort: 9100, timeoutMs: 10000 };
let printLog = () => {};

/**
 * Print receipt lines. Dispatches to the configured mode ("cups" or "lan").
 * Builds the raw ESC/POS payload (text + cut) once and hands it to the
 * transport. Returns a promise that resolves on success, rejects on failure.
 */
async function printReceipt(lines, type, tx) {
  const text = lines.join("\n") + "\n\n\n";
  const payload = Buffer.concat([Buffer.from(text, "utf8"), ESCPOS_CUT]);
  const cfg = PRINT_CONFIG;
  if (cfg.mode === "lan") {
    return printViaLan(payload, cfg);
  }
  return printViaCups(payload, cfg);
}

/**
 * CUPS transport: spool raw bytes via `lp`. Optional remote host via `-h`.
 * The printer/host are validated to a safe charset to avoid shell injection.
 */
async function printViaCups(payload, cfg) {
  const safe = (s) => String(s).replace(/[^A-Za-z0-9._:-]/g, "");
  const printer = safe(cfg.cupsPrinter);
  const hostArg = cfg.cupsHost ? ` -h ${safe(cfg.cupsHost)}` : "";
  const cmd = `lp${hostArg} -d "${printer}" -o raw`;
  return new Promise((resolve, reject) => {
    const lp = exec(cmd, { timeout: cfg.timeoutMs }, (error) => {
      if (error) reject(new Error(`CUPS print failed: ${error.message}`));
      else resolve();
    });
    lp.stdin.write(payload);
    lp.stdin.end();
  });
}

/**
 * LAN transport: open a raw TCP socket to the printer (JetDirect / port 9100)
 * and write the ESC/POS bytes directly. No CUPS and no print agent involved.
 */
async function printViaLan(payload, cfg) {
  if (!cfg.lanHost) return Promise.reject(new Error("LAN print: lanHost not configured"));
  return new Promise((resolve, reject) => {
    const socket = new net.Socket();
    let done = false;
    const finish = (err) => {
      if (done) return; done = true;
      try { socket.destroy(); } catch (_) {}
      if (err) reject(err); else resolve();
    };
    socket.setTimeout(cfg.timeoutMs);
    socket.on("timeout", () => finish(new Error(`LAN print timeout ${cfg.lanHost}:${cfg.lanPort}`)));
    socket.on("error", (e) => finish(new Error(`LAN print failed: ${e.message}`)));
    socket.connect(cfg.lanPort, cfg.lanHost, () => {
      socket.write(payload, () => {
        // Give the printer a moment to consume, then close cleanly.
        socket.end();
      });
    });
    socket.on("close", () => finish(null));
  });
}
