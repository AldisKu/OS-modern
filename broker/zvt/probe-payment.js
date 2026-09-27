/**
 * Standalone ZVT payment probe with configurable registration config byte.
 * Registers with the given config byte, then authorises the given amount, and
 * logs every frame plus any receipt print blocks (06 D1 / 06 D3) received.
 *
 * WARNING: this performs a REAL card transaction and will charge the card.
 *
 * Usage:
 *   node probe-payment.js <ip> <port> <amountMinor> [password] [configByteHex]
 *   node probe-payment.js 192.168.0.145 20011 1 000000 06
 */

import { ZvtSession } from "./zvt-session.js";

const ip = process.argv[2];
const port = parseInt(process.argv[3], 10);
const amountMinor = parseInt(process.argv[4], 10);
const password = process.argv[5] || "000000";
const configByte = process.argv[6] ? parseInt(process.argv[6], 16) : 0x1E;

if (!ip || !port || !Number.isInteger(amountMinor)) {
  console.error("Usage: node probe-payment.js <ip> <port> <amountMinor> [password] [configByteHex]");
  process.exit(2);
}

function hex(buf) {
  return Buffer.isBuffer(buf) ? buf.toString("hex").replace(/(..)/g, "$1 ").trim() : String(buf);
}

async function main() {
  console.log(`\n[PAY] → ${ip}:${port}  amount=${amountMinor} minor  configByte=0x${configByte.toString(16).padStart(2,"0")}`);
  console.log("[PAY] WARNING: real transaction. Present card when prompted.\n");

  const session = new ZvtSession(ip, port, {
    password,
    configByte,
    connectTimeout: 3000,
    responseTimeout: 5000,
    transactionTimeout: 120000,
    onLog: (dir, head, payload) => {
      if (dir === "TX") console.log(`  TX  ${hex(head)}`);
      else if (dir === "RX") console.log(`  RX  ${hex(head)}${payload && payload.length ? "  " + hex(payload) : ""}`);
    }
  });

  const receipts = { merchant: [], customer: [], other: [] };

  try {
    await session.connect();
    console.log("[PAY] connected; registering...");
    const reg = await session.registration();
    console.log(`[PAY] registration result=0x${(reg.resultCode ?? 0xff).toString(16)}`);

    console.log("[PAY] sending authorisation; PRESENT CARD NOW...");
    const result = await session.authorisation(amountMinor, {
      onIntermediate: () => console.log("[PAY] intermediate status"),
      onPrint: (pd) => {
        const target = pd.receiptType === 0x01 ? "merchant" : pd.receiptType === 0x02 ? "customer" : "other";
        console.log(`[PAY] PRINT BLOCK receiptType=${pd.receiptType} lines=${pd.lines.length} complete=${pd.complete}`);
        for (const l of pd.lines) console.log(`   | ${l}`);
        receipts[target].push(...pd.lines);
      }
    });
    session.disconnect();

    console.log("\n========== PAYMENT RESULT ==========");
    console.log(`  success   : ${result.success}`);
    console.log(`  resultCode: 0x${(result.resultCode ?? 0xff).toString(16).padStart(2,"0")}`);
    console.log(`  merchant receipt lines: ${receipts.merchant.length}`);
    console.log(`  customer receipt lines: ${receipts.customer.length}`);
    console.log(`  other print lines     : ${receipts.other.length}`);
    console.log("====================================\n");
    process.exit(result.success ? 0 : 1);
  } catch (e) {
    session.disconnect();
    console.error(`\n[PAY] FAILED: ${e.message}\n`);
    process.exit(1);
  }
}

main();
