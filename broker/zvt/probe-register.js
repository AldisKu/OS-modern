/**
 * Standalone ZVT Registration probe (06 00).
 * Confirms the terminal accepts our registration config byte and returns a
 * positive Completion. No payment / no charge.
 *
 * SAFE: only sends Registration (06 00) + ACK. No Authorisation.
 *
 * Usage:
 *   node probe-register.js <ip> <port> [password] [configByteHex]
 *   node probe-register.js 192.168.0.145 20011 000000 1e
 */

import { ZvtSession } from "./zvt-session.js";

const ip = process.argv[2];
const port = parseInt(process.argv[3], 10);
const password = process.argv[4] || "000000";
const configByte = process.argv[5] ? parseInt(process.argv[5], 16) : 0x1E;

if (!ip || !port) {
  console.error("Usage: node probe-register.js <ip> <port> [password] [configByteHex]");
  process.exit(2);
}

function hex(buf) {
  return Buffer.isBuffer(buf) ? buf.toString("hex").replace(/(..)/g, "$1 ").trim() : String(buf);
}

async function main() {
  console.log(`\n[REGISTER] → ${ip}:${port}  password=${password}  configByte=0x${configByte.toString(16).padStart(2, "0")}`);
  console.log("[REGISTER] Sending 06 00 Registration (config byte controls receipt/payment routing per spec §27/§69.12)\n");

  const session = new ZvtSession(ip, port, {
    password,
    configByte,
    connectTimeout: 3000,
    responseTimeout: 5000,
    onLog: (dir, head, payload) => {
      if (dir === "TX") console.log(`  TX  ${hex(head)}`);
      else if (dir === "RX") console.log(`  RX  ${hex(head)}${payload && payload.length ? "  payload=" + hex(payload) : ""}`);
    }
  });

  try {
    await session.connect();
    console.log(`[REGISTER] TCP connected`);
    const res = await session.registration();
    session.disconnect();

    console.log("\n========== REGISTRATION RESULT ==========");
    console.log(`  resultCode : ${res.resultCode === null || res.resultCode === undefined ? "null" : "0x" + res.resultCode.toString(16).padStart(2, "0")}`);
    console.log(`  ackOnly    : ${res.ackOnly ? "yes" : "no"}`);
    if (res.terminalIdentifier) console.log(`  TID        : ${res.terminalIdentifier}`);
    console.log("=========================================\n");

    if (res.resultCode === 0x00) {
      console.log("[REGISTER] SUCCESS: terminal accepted the registration (result 00).\n");
      process.exit(0);
    } else {
      console.log(`[REGISTER] Terminal returned result 0x${(res.resultCode ?? 0xff).toString(16)}. Registration not confirmed as 00.\n`);
      process.exit(1);
    }
  } catch (e) {
    session.disconnect();
    console.error(`\n[REGISTER] FAILED: ${e.message}\n`);
    process.exit(1);
  }
}

main();
