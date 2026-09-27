/**
 * Diagnostic: try several ZVT Registration (06 00) payload variants and report
 * which the terminal accepts (positive ACK 80 00 00) vs rejects (84 xx).
 *
 * SAFE: only sends Registration + ACK. No payment.
 *
 * Usage: node probe-register-variants.js <ip> <port> [password]
 */

import net from "net";
import { encodePassword, encodeCurrency, parseFrame, isAck } from "./zvt-codec.js";

const ip = process.argv[2];
const port = parseInt(process.argv[3], 10);
const password = process.argv[4] || "000000";
if (!ip || !port) { console.error("usage: <ip> <port> [password]"); process.exit(2); }

const pwd = encodePassword(password);
const eur = encodeCurrency(978);

function frame(payload) {
  return Buffer.concat([Buffer.from([0x06, 0x00, payload.length]), payload]);
}
function hx(b) { return b.toString("hex").replace(/(..)/g, "$1 ").trim(); }

// Variants to try
const variants = [
  { name: "password only",                 payload: pwd },
  { name: "pwd + config 0x00",              payload: Buffer.concat([pwd, Buffer.from([0x00])]) },
  { name: "pwd + config 0x1E",              payload: Buffer.concat([pwd, Buffer.from([0x1E])]) },
  { name: "pwd + config 0x1E + cc(3 byte)", payload: Buffer.concat([pwd, Buffer.from([0x1E, 0x09, 0x78, 0x00])]) },
  { name: "pwd + config 0x1E + BMP49 cc",   payload: Buffer.concat([pwd, Buffer.from([0x1E, 0x49]), eur]) },
  { name: "pwd + config 0x08 (interm only)",payload: Buffer.concat([pwd, Buffer.from([0x08])]) },
];

function trySend(v) {
  return new Promise((resolve) => {
    const sock = net.createConnection({ host: ip, port }, () => {
      const f = frame(v.payload);
      sock.write(f);
    });
    let buf = Buffer.alloc(0);
    let done = false;
    const finish = (verdict, detail) => {
      if (done) return; done = true;
      try { sock.destroy(); } catch (_) {}
      resolve({ verdict, detail });
    };
    sock.on("data", (d) => {
      buf = Buffer.concat([buf, d]);
      const fr = parseFrame(buf);
      if (fr) {
        if (isAck(fr)) finish("ACCEPT", "80 00 00");
        else if (fr.cmdClass === 0x84) finish("REJECT", `84 ${fr.cmdInstr.toString(16).padStart(2,"0")}`);
        else finish("OTHER", `${fr.cmdClass.toString(16)} ${fr.cmdInstr.toString(16)} payload=${hx(fr.payload)}`);
      }
    });
    sock.on("error", (e) => finish("ERROR", e.message));
    setTimeout(() => finish("TIMEOUT", "no response 4s"), 4000);
  });
}

async function main() {
  console.log(`\nRegistration variant probe → ${ip}:${port} (pwd=${password})\n`);
  for (const v of variants) {
    const f = frame(v.payload);
    const r = await trySend(v);
    console.log(`  [${r.verdict.padEnd(7)}] ${v.name.padEnd(30)} TX=${hx(f)}  ->  ${r.detail}`);
    await new Promise(res => setTimeout(res, 400)); // small gap between attempts
  }
  console.log("");
  process.exit(0);
}
main();
