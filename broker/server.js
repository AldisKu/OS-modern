import http from "http";
import { WebSocketServer } from "ws";

const PORT = process.env.PORT ? Number(process.env.PORT) : 3077;
const TOKEN = process.env.BROKER_TOKEN || "";
const POLL_URL = process.env.POLL_URL || "http://127.0.0.1/php/modernapi.php?cmd=state";
const POLL_INTERVAL = process.env.POLL_INTERVAL_MS ? Number(process.env.POLL_INTERVAL_MS) : 4000;
const PRINTER_URL = process.env.PRINTER_URL || "http://127.0.0.1/php/modernapi.php?cmd=printer_status";
const clients = new Set();

function sendAll(msg) {
  const data = JSON.stringify(msg);
  for (const ws of clients) {
    if (ws.readyState === ws.OPEN) {
      ws.send(data);
    }
  }
}

const server = http.createServer(async (req, res) => {
  if (req.method === "POST" && req.url === "/event") {
    if (TOKEN && req.headers["x-broker-token"] !== TOKEN) {
      res.writeHead(401);
      res.end("unauthorized");
      return;
    }
    let body = "";
    req.on("data", chunk => {
      body += chunk.toString();
    });
    req.on("end", () => {
      let payload = {};
      try {
        payload = JSON.parse(body || "{}") || {};
      } catch (_) {
        payload = {};
      }
      sendAll({
        type: "UPDATE_REQUIRED",
        scope: payload.scope || "TABLES",
        event: payload.event || "UNKNOWN",
        ts: payload.ts || Date.now()
      });
      res.writeHead(200, { "Content-Type": "application/json" });
      res.end(JSON.stringify({ status: "OK" }));
    });
    return;
  }

  if (req.method === "GET" && req.url === "/health") {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ status: "OK", clients: clients.size }));
    return;
  }

  res.writeHead(404);
  res.end("not found");
});

const wss = new WebSocketServer({ server });

wss.on("connection", (ws) => {
  clients.add(ws);
  ws.send(JSON.stringify({ type: "HELLO", ts: Date.now() }));

  ws.on("close", () => clients.delete(ws));
  ws.on("error", () => clients.delete(ws));
});

server.listen(PORT, () => {
  console.log(`OrderSprinter broker listening on :${PORT}`);
});

let lastVersion = null;
let lastStatus = null;
async function pollState() {
  try {
    const resp = await fetch(POLL_URL);
    if (!resp.ok) return;
    const data = await resp.json();
    if (data.status !== "OK") return;
    if (lastVersion && lastVersion !== data.version) {
      sendAll({ type: "UPDATE_REQUIRED", scope: "TABLES", event: "POLL_CHANGE", ts: Date.now() });
    }
    lastVersion = data.version;
  } catch (_) {
    // ignore polling errors
  }
}

async function pollPrinter() {
  try {
    const resp = await fetch(PRINTER_URL);
    if (!resp.ok) return;
    const data = await resp.json();
    if (data.status !== "OK") return;
    const status = {
      printer: data.msg ?? null,
      tse: data.tsestatus ?? null,
      tasksforme: data.tasksforme ?? null
    };
    if (!lastStatus || JSON.stringify(lastStatus) !== JSON.stringify(status)) {
      sendAll({ type: "STATUS_UPDATE", status, ts: Date.now() });
      lastStatus = status;
    }
  } catch (_) {
    // ignore
  }
}

setInterval(pollState, POLL_INTERVAL);
setInterval(pollPrinter, POLL_INTERVAL);
