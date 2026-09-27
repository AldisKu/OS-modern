/**
 * Standalone ZVT Status-Enquiry probe.
 * Connects to a terminal, sends 05 01 Status-Enquiry (service-byte 0x06),
 * and prints the parsed device identity (name, serial, TID, device state).
 *
 * SAFE: read-only. No Registration, no Authorisation, no payment.
 *
 * Usage:
 *   node probe-status.js <ip> <port> [password]
 *   node probe-status.js 192.168.0.145 20011 000000
 */

import { ZvtSession } from "./zvt-session.js";

const ip = process.argv[2];
const port = parseInt(process.argv[3], 10);
const password = process.argv[4] || "000000";

if (!ip || !port) {
  console.error("Usage: node probe-status.js <ip> <port> [password]");
  process.exit(2);
}

const DEVICE_STATE = {
  0x00: "Ready",
  0x01: "Initialization needed",
  0x02: "No keys loaded",
  0x03: "Fraud"
};

function hex(buf) {
  return Buffer.isBuffer(buf)
    ? buf.toString("hex").replace(/(..)/g, "$1 ").trim()
    : String(buf);
}

async function main() {
  console.log(`\n[PROBE] Status-Enquiry → ${ip}:${port}  (password=${password})`);
  console.log("[PROBE] Sending 05 01 with service-byte 0x06 (request TLV extended status)\n");

  const session = new ZvtSession(ip, port, {
    password,
    connectTimeout: 3000,
    responseTimeout: 4000,
    onLog: (dir, head, payload) => {
      if (dir === "TX") console.log(`  TX  ${hex(head)}`);
      else if (dir === "RX") console.log(`  RX  ${hex(head)}${payload && payload.length ? "  payload=" + hex(payload) : ""}`);
    }
  });

  try {
    await session.connect();
    console.log(`[PROBE] TCP connected to ${ip}:${port}`);
    const status = await session.statusEnquiry();
    session.disconnect();

    console.log("\n========== STATUS-ENQUIRY RESULT ==========");
    console.log(`  resultCode         : ${status.resultCode === null ? "null" : "0x" + status.resultCode.toString(16).padStart(2, "0")}`);
    console.log(`  ackOnly            : ${status.ackOnly ? "yes (terminal returned only ACK, no Completion)" : "no"}`);
    console.log(`  deviceName         : ${status.deviceName ?? "-"}`);
    console.log(`  serialNumber       : ${status.serialNumber ?? "-"}`);
    console.log(`  terminalIdentifier : ${status.terminalIdentifier ?? "-"}`);
    console.log(`  softwareVersion    : ${status.softwareVersion ?? "-"}`);
    const ds = status.deviceState;
    console.log(`  deviceState        : ${ds === null || ds === undefined ? "-" : "0x" + ds.toString(16).padStart(2, "0") + " (" + (DEVICE_STATE[ds] || "unknown") + ")"}`);
    console.log("===========================================\n");

    if (status.ackOnly) {
      console.log("[PROBE] Note: terminal ACKed but sent no Completion/TLV. Either the port");
      console.log("        is not the ZVT payment interface, or the password/service-byte was");
      console.log("        not accepted. Try another port or verify the ZVT password.\n");
    } else {
      console.log("[PROBE] SUCCESS: got a ZVT Completion. This is a ZVT terminal.\n");
    }
    process.exit(0);
  } catch (e) {
    session.disconnect();
    console.error(`\n[PROBE] FAILED: ${e.message}\n`);
    process.exit(1);
  }
}

main();
