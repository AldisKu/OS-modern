/**
 * Standalone ZVT Repeat-Receipt (06 20) / Reprint (06 12) probe.
 * Registers, then sends a Repeat-Receipt request and dumps the RAW byte stream
 * so we can see whether the terminal returns 06 D1 / 06 D3 receipt blocks.
 *
 * SAFE: no payment. Only Registration + Repeat-Receipt (replays stored receipts).
 *
 * Usage:
 *   node probe-reprint.js <ip> <port> [password] [variant]
 *   variant: "last" (06 20 03 <pwd>)  |  "all-merchant" | "all-customer" | "all-journal"
 */

import net from "net";
import { buildRegistration, encodePassword, parseFrame, isAck, ACK } from "./zvt-codec.js";

const ip = process.argv[2];
const port = parseInt(process.argv[3], 10);
const password = process.argv[4] || "000000";
const variant = process.argv[5] || "last";
if (!ip || !port) { console.error("usage: <ip> <port> [password] [variant]"); process.exit(2); }

function hx(b) { return b.toString("hex").replace(/(..)/g, "$1 ").trim(); }
function ts() { return new Date().toISOString().substr(11, 12); }

// Build the 06 20 Repeat-Receipt frame for the chosen variant.
function buildRepeatReceipt() {
  const pwd = encodePassword(password);
  if (variant === "last") {
    // simplest: password only
    return Buffer.concat([Buffer.from([0x06, 0x20, 0x03]), pwd]);
  }
  // TLV form: 1F01 (type) + 1F02 (from TA = 0) => "all"
  let type;
  if (variant === "all-merchant") type = 0x02;
  else if (variant === "all-customer") type = 0x03;
  else if (variant === "all-journal") type = 0x05;
  else type = 0x02;
  // TLV container: 06 08 1F 01 01 <type> 1F 02 01 00
  const tlv = Buffer.from([0x06, 0x08, 0x1F, 0x01, 0x01, type, 0x1F, 0x02, 0x01, 0x00]);
  const payload = Buffer.concat([pwd, tlv]);
  return Buffer.concat([Buffer.from([0x06, 0x20, payload.length]), payload]);
}

const sock = net.createConnection({ host: ip, port }, async () => {
  console.log(`[${ts()}] connected ${ip}:${port}`);
  // 1) Register (0x9A + tag26 permit D3) so ECR-receipt routing is active
  const reg = buildRegistration(password, 0x9A, 978, { includeCurrency: true, serviceByte: 0x01, tlvPermitPrint: true });
  console.log(`[${ts()}] TX registration: ${hx(reg)}`);
  sock.write(reg);
});

let acked = false;
let regDone = false;
let total = Buffer.alloc(0);

sock.on("data", (data) => {
  total = Buffer.concat([total, data]);
  console.log(`[${ts()}] RX (${data.length}B): ${hx(data)}`);
  // ACK any completion frames from the terminal
  if (data.length >= 2 && data[0] === 0x80 && data[1] === 0x00) {
    // positive ACK — after registration ACK, send our ACK then the repeat-receipt
    if (!acked) {
      acked = true;
    }
  }
  // crude: after we see the registration completion (06 0f), ACK it and send 06 20
  if (!regDone && total.toString("hex").includes("060f")) {
    regDone = true;
    setTimeout(() => {
      console.log(`[${ts()}] TX ACK`);
      sock.write(ACK);
      const rr = buildRepeatReceipt();
      console.log(`[${ts()}] TX repeat-receipt (${variant}): ${hx(rr)}`);
      sock.write(rr);
    }, 300);
  }
  // ACK any 06 D1 / 06 D3 / 04 xx we receive so the terminal keeps streaming
  if (data.length >= 2 && (data[0] === 0x06 || data[0] === 0x04)) {
    setTimeout(() => { try { sock.write(ACK); } catch (_) {} }, 50);
  }
});

sock.on("error", (e) => console.log(`[${ts()}] ERROR ${e.message}`));
sock.on("close", () => console.log(`[${ts()}] closed`));

setTimeout(() => {
  console.log(`\n[${ts()}] === analysis ===`);
  const hexstr = total.toString("hex");
  console.log("literal 06d3:", hexstr.includes("06d3"), "| literal 06d1:", hexstr.includes("06d1"));
  // APDU split
  let buf = Buffer.from(total), n = 0, sawD3 = false, sawD1 = false;
  while (buf.length >= 3) {
    const cls = buf[0], instr = buf[1], l1 = buf[2];
    let hdr, dlen;
    if (l1 === 0xFF) { if (buf.length < 5) break; dlen = buf[3] | (buf[4] << 8); hdr = 5; } else { dlen = l1; hdr = 3; }
    const totalLen = hdr + dlen;
    if (buf.length < totalLen) { console.log(`  #${n} INCOMPLETE need ${totalLen} have ${buf.length}`); break; }
    let note = "";
    if (cls === 0x06 && instr === 0xD3) { note = " <<< D3 RECEIPT"; sawD3 = true; }
    if (cls === 0x06 && instr === 0xD1) { note = " <<< D1 RECEIPT"; sawD1 = true; }
    console.log(`  #${n++} ${cls.toString(16).padStart(2, "0")} ${instr.toString(16).padStart(2, "0")} dlen=${dlen}${note}`);
    buf = buf.slice(totalLen);
  }
  console.log(`\nVERDICT: D3 as APDU = ${sawD3} | D1 as APDU = ${sawD1}`);
  try { sock.destroy(); } catch (_) {}
  process.exit(0);
}, 10000);
