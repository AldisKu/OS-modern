import http from "http";
import { WebSocketServer } from "ws";

const PORT = process.env.PORT ? Number(process.env.PORT) : 3077;
const TOKEN = process.env.BROKER_TOKEN || "";
const POLL_URL = process.env.POLL_URL || "http://127.0.0.1/php/modernapi.php?cmd=state";
const POLL_INTERVAL = process.env.POLL_INTERVAL_MS ? Number(process.env.POLL_INTERVAL_MS) : 4000;
const PRICELEVEL_URL = process.env.PRICELEVEL_URL || "http://127.0.0.1/php/modernapi.php?cmd=pricelevel_state";
const PRINTER_URL = process.env.PRINTER_URL || "http://127.0.0.1/php/modernapi.php?cmd=printer_status";
const clients = new Set();
let nextId = 1;

function sendAll(msg) {
  const data = JSON.stringify(msg);
  for (const ws of clients) {
    if (ws.readyState === ws.OPEN) {
      ws.send(data);
    }
  }
}

function getPosList() {
  const list = [];
  for (const ws of clients) {
    if (ws.meta && ws.meta.role === "pos") {
      list.push({
        id: ws.meta.id,
        deviceId: ws.meta.deviceId || "",
        userId: ws.meta.userId || "",
        userName: ws.meta.userName || ""
      });
    }
  }
  return list;
}

function sendPosListToDisplays() {
  const list = getPosList();
  const payload = JSON.stringify({ type: "POS_LIST", list, ts: Date.now() });
  for (const ws of clients) {
    if (ws.readyState === ws.OPEN && ws.meta && ws.meta.role === "display") {
      ws.send(payload);
    }
  }
}

const server = http.createServer(async (req, res) => {
  if (req.method === "GET" && req.url === "/clients") {
    // Debug endpoint. If a token is configured, require it.
    // If no token is configured, only allow localhost access.
    const remote = req.socket && req.socket.remoteAddress ? String(req.socket.remoteAddress) : "";
    const isLocal = remote === "127.0.0.1" || remote === "::1" || remote === "::ffff:127.0.0.1";
    if ((TOKEN && req.headers["x-broker-token"] !== TOKEN) || (!TOKEN && !isLocal)) {
      res.writeHead(403);
      res.end("forbidden");
      return;
    }
    const list = [];
    for (const ws of clients) {
      if (!ws.meta) continue;
      list.push({
        id: ws.meta.id,
        role: ws.meta.role,
        deviceId: ws.meta.deviceId || "",
        userId: ws.meta.userId || "",
        userName: ws.meta.userName || "",
        targetPosId: ws.meta.targetPosId || null,
        origin: ws.meta.origin || "",
        remote: ws.meta.remote || ""
      });
    }
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ status: "OK", clients: list, ts: Date.now() }));
    return;
  }

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

wss.on("connection", (ws, req) => {
  clients.add(ws);
  ws.meta = {
    role: "unknown",
    id: nextId++,
    origin: (req && req.headers && req.headers.origin) ? String(req.headers.origin) : "",
    remote: (req && req.socket && req.socket.remoteAddress) ? String(req.socket.remoteAddress) : ""
  };
  ws.send(JSON.stringify({ type: "HELLO", ts: Date.now() }));

  ws.on("message", (raw) => {
    let msg = null;
    try { msg = JSON.parse(raw.toString()); } catch (_) { msg = null; }
    if (!msg || !msg.type) return;
    if (msg.type === "REGISTER") {
      ws.meta.role = msg.role || "unknown";
      ws.meta.deviceId = msg.deviceId || "";
      ws.meta.userId = msg.userId || "";
      ws.meta.userName = msg.userName || "";
      console.log(
        `REGISTER id=${ws.meta.id} role=${ws.meta.role} deviceId=${ws.meta.deviceId} user=${ws.meta.userName} remote=${ws.meta.remote} origin=${ws.meta.origin}`
      );
      ws.send(JSON.stringify({ type: "REGISTERED", id: ws.meta.id, list: getPosList(), ts: Date.now() }));
      sendPosListToDisplays();
      return;
    }
    if (msg.type === "REQUEST_POS_LIST") {
      ws.send(JSON.stringify({ type: "POS_LIST", list: getPosList(), ts: Date.now() }));
      return;
    }
    if (msg.type === "SUBSCRIBE" && ws.meta.role === "display") {
      ws.meta.targetPosId = msg.posId || null;
      return;
    }
    if (msg.type === "DISPLAY_UPDATE" || msg.type === "DISPLAY_IDLE" || msg.type === "DISPLAY_EBON") {
      const posId = msg.posId || (ws.meta ? ws.meta.id : null);
      if (!posId) return;
      const data = JSON.stringify({ ...msg, posId, ts: Date.now() });
      for (const client of clients) {
        if (client.readyState !== client.OPEN) continue;
        if (!client.meta || client.meta.role !== "display") continue;
        if (client.meta.targetPosId !== posId) continue;
        client.send(data);
      }
    }
  });

  ws.on("close", () => {
    clients.delete(ws);
    sendPosListToDisplays();
  });
  ws.on("error", () => {
    clients.delete(ws);
    sendPosListToDisplays();
  });
});

server.listen(PORT, () => {
  console.log(`OrderSprinter broker listening on :${PORT}`);
});

let lastVersion = null;
let lastStatus = null;
let lastPriceLevelVersion = null;
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
  // printer/TSE status is intentionally disabled in modern UI
}

setInterval(pollState, POLL_INTERVAL);

async function pollPriceLevel() {
  try {
    const resp = await fetch(PRICELEVEL_URL);
    if (!resp.ok) return;
    const data = await resp.json();
    if (data.status !== "OK") return;
    if (lastPriceLevelVersion && lastPriceLevelVersion !== data.version) {
      sendAll({ type: "UPDATE_REQUIRED", scope: "MENU", event: "PRICELEVEL_CHANGE", ts: Date.now() });
    }
    lastPriceLevelVersion = data.version;
  } catch (_) {
    // ignore polling errors
  }
}

setInterval(pollPriceLevel, POLL_INTERVAL);
