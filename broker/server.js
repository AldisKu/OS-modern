import http from "http";
import { WebSocketServer } from "ws";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const PORT = process.env.PORT ? Number(process.env.PORT) : 3077;
const TOKEN = process.env.BROKER_TOKEN || "";
// modernapi.php lives under <webroot>/modern/ (everything "modern" is under
// modern/). All broker->API URLs must point there. Can be overridden per env.
const POLL_URL = process.env.POLL_URL || "http://127.0.0.1/modern/modernapi.php?cmd=state";
const CHANGES_URL = process.env.CHANGES_URL || "http://127.0.0.1/modern/modernapi.php?cmd=changes";
const POLL_INTERVAL = process.env.POLL_INTERVAL_MS ? Number(process.env.POLL_INTERVAL_MS) : 4000;
const PRICELEVEL_URL = process.env.PRICELEVEL_URL || "http://127.0.0.1/modern/modernapi.php?cmd=pricelevel_state";
const PRINTER_URL = process.env.PRINTER_URL || "http://127.0.0.1/modern/modernapi.php?cmd=printer_status";
// Debug: log change-detection + push timing (journalctl -u ordersprinter-broker).
const DEBUG_UPDATES = process.env.DEBUG_UPDATES ? process.env.DEBUG_UPDATES !== "0" : true;
function dbgU(...a) { if (DEBUG_UPDATES) console.log("[UPD]", new Date().toISOString(), ...a); }

// Map DB change-state logical scopes -> client UPDATE_REQUIRED scope.
// orders/payments/moving/cancel/tables all affect the table view -> "TABLES".
// products affects the menu/catalog -> "MENU".
function mapChangeScope(scope) {
  if (scope === "products") return "MENU";
  return "TABLES";
}
let changeLogAvailable = true;
// Per-scope watermark: greatest last_change (DATETIME(6) string) seen so far.
// Compared as opaque fixed-width strings (never parsed to a JS Date, which
// would lose microseconds). Empty on start -> first poll pushes one refresh.
const changeWatermark = {};
const clients = new Set();
let nextId = 1;
const clientsByName = new Map(); // Map of clientName -> ws

// --- Load config.json for optional modules ---
let brokerConfig = {};
try {
  const cfgPath = path.join(__dirname, "..", "config.json");
  if (fs.existsSync(cfgPath)) {
    brokerConfig = JSON.parse(fs.readFileSync(cfgPath, "utf8"));
  }
} catch (_) {}

// --- Conditionally load ZVT module ---
let zvtHandler = null;
if (brokerConfig.zvt_enabled) {
  try {
    const { initZvt } = await import("./zvt/index.js");
    zvtHandler = initZvt(brokerConfig.zvt || {}, clients, __dirname);
    console.log("[BROKER] ZVT payment module loaded");
  } catch (e) {
    console.log(`[BROKER] ZVT module failed to load: ${e.message}`);
  }
}

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
        clientName: ws.meta.clientName || "",
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
  console.log(`SEND_POS_LIST to displays: ${list.length} POS clients`);
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
  console.log(`CONNECT id=${ws.meta.id} remote=${ws.meta.remote} origin=${ws.meta.origin}`);

  ws.on("message", (raw) => {
    let msg = null;
    console.log("[MSG] raw:", raw.toString().substring(0,200));
    try { msg = JSON.parse(raw.toString()); } catch (_) { msg = null; }
    if (!msg || !msg.type) return;
    if (msg.type === "REGISTER") {
      ws.meta.role = msg.role || "unknown";
      ws.meta.deviceId = msg.deviceId || "";
      ws.meta.userId = msg.userId || "";
      ws.meta.userName = msg.userName || "";
      ws.meta.clientName = msg.clientName || "";
      
      // If this is a POS with a client name, track it for reconnection
      if (ws.meta.role === "pos" && ws.meta.clientName) {
        // If a previous connection with this name exists, close it
        const oldWs = clientsByName.get(ws.meta.clientName);
        if (oldWs && oldWs !== ws && oldWs.readyState === oldWs.OPEN) {
          console.log(`REGISTER: Closing old connection for clientName=${ws.meta.clientName}`);
          oldWs.close();
        }
        clientsByName.set(ws.meta.clientName, ws);
      }
      
      const posList = getPosList();
      console.log(
        `REGISTER id=${ws.meta.id} role=${ws.meta.role} clientName=${ws.meta.clientName} deviceId=${ws.meta.deviceId} user=${ws.meta.userName} remote=${ws.meta.remote} origin=${ws.meta.origin} | POS_LIST: ${posList.length}`
      );
      ws.send(JSON.stringify({ type: "REGISTERED", id: ws.meta.id, clientName: ws.meta.clientName, list: posList, ts: Date.now() }));
      sendPosListToDisplays();
      return;
    }
    if (msg.type === "REQUEST_POS_LIST") {
      const posList = getPosList();
      console.log(`REQUEST_POS_LIST from id=${ws.meta.id} | POS_LIST: ${posList.length}`);
      ws.send(JSON.stringify({ type: "POS_LIST", list: posList, ts: Date.now() }));
      return;
    }
    if (msg.type === "SUBSCRIBE" && ws.meta.role === "display") {
      ws.meta.targetPosId = msg.posId || null;
      ws.meta.targetClientName = msg.clientName || null;
      
      // Find the target POS (by ID or by client name)
      let posWs = null;
      if (msg.clientName) {
        posWs = clientsByName.get(msg.clientName);
      } else if (msg.posId) {
        posWs = Array.from(clients).find(c => c.meta && c.meta.role === "pos" && c.meta.id === msg.posId);
      }
      
      if (posWs && posWs.readyState === posWs.OPEN) {
        posWs.send(JSON.stringify({ type: "DISPLAY_CONNECTED", displayId: ws.meta.id, ts: Date.now() }));
        console.log(`DISPLAY_CONNECTED: display id=${ws.meta.id} connected to POS id=${posWs.meta.id} clientName=${posWs.meta.clientName}`);
      }
      return;
    }
    if (msg.type === "POS_LOGOUT" && ws.meta.role === "pos") {
      // POS is logging out - notify all connected displays
      for (const client of clients) {
        if (client.readyState !== client.OPEN) continue;
        if (!client.meta || client.meta.role !== "display") continue;
        if (client.meta.targetPosId !== ws.meta.id) continue;
        client.send(JSON.stringify({ type: "POS_OFFLINE", posId: ws.meta.id, ts: Date.now() }));
        console.log(`POS_OFFLINE: POS id=${ws.meta.id} clientName=${ws.meta.clientName} logged out, notifying display id=${client.meta.id}`);
      }
      // Remove from client name mapping
      if (ws.meta.clientName) {
        clientsByName.delete(ws.meta.clientName);
      }
      // Downgrade POS role to unknown so it doesn't appear in POS_LIST anymore
      ws.meta.role = "unknown";
      // Send updated POS_LIST to all displays (removes the logged-out POS)
      sendPosListToDisplays();
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
    // --- ZVT/Payment message routing ---
    if (zvtHandler && (
      msg.type === "REQUEST_TERMINALS" ||
      msg.type === "PAYMENT_REQUEST" ||
      msg.type === "PAYMENT_CANCEL" ||
      msg.type === "PAYMENT_CHANGE_AMOUNT" ||
      msg.type === "PAYMENT_STATUS_REQUEST" ||
      msg.type === "PRINT_CUSTOMER_RECEIPT" ||
      msg.type === "PRINT_MERCHANT_RECEIPT" ||
      msg.type === "TRIGGER_DISCOVERY" ||
      msg.type === "LIST_CARD_RECEIPTS" ||
      msg.type === "PRINT_CARD_RECEIPT" ||
      msg.type === "PRINT_ALL_MERCHANT" ||
      msg.type === "ARCHIVE_MERCHANT_RECEIPTS" ||
      msg.type === "DELETE_MERCHANT_RECEIPT" ||
      msg.type === "TERMINAL_EOD" ||
      msg.type === "TERMINAL_EOD_ALL"
    )) {
      zvtHandler.handleMessage(ws, msg).catch(e => console.log("[ZVT] handleMessage error:", e.message));
    }
  });

  ws.on("close", () => {
    clients.delete(ws);
    // Remove from client name mapping if it was a POS
    if (ws.meta && ws.meta.clientName && ws.meta.role === "pos") {
      clientsByName.delete(ws.meta.clientName);
    }
    console.log(`CLOSE id=${ws.meta && ws.meta.id ? ws.meta.id : "?"} clientName=${ws.meta && ws.meta.clientName ? ws.meta.clientName : ""} remote=${ws.meta ? ws.meta.remote : ""}`);
    // If a display disconnected, notify the POS it was connected to
    if (ws.meta && ws.meta.role === "display" && ws.meta.targetPosId) {
      const posWs = Array.from(clients).find(c => c.meta && c.meta.role === "pos" && c.meta.id === ws.meta.targetPosId);
      if (posWs && posWs.readyState === posWs.OPEN) {
        posWs.send(JSON.stringify({ type: "DISPLAY_DISCONNECTED", displayId: ws.meta.id, ts: Date.now() }));
        console.log(`DISPLAY_DISCONNECTED: display id=${ws.meta.id} disconnected from POS id=${ws.meta.targetPosId}`);
      }
    }
    sendPosListToDisplays();
  });
  ws.on("error", () => {
    clients.delete(ws);
    console.log(`ERROR id=${ws.meta && ws.meta.id ? ws.meta.id : "?"} remote=${ws.meta ? ws.meta.remote : ""}`);
    sendPosListToDisplays();
  });
});

server.listen(PORT, () => {
  console.log(`OrderSprinter broker listening on :${PORT}`);
});

let lastVersion = null;
let lastStatus = null;
let lastPriceLevelVersion = null;
// Preferred: poll the trigger-fed change-log. Returns DISTINCT logical scopes
// changed since last poll and clears them server-side. Push one UPDATE_REQUIRED
// per affected client-scope. If change-log not installed, fall back to legacy.
async function pollChangeLog() {
  const resp = await fetch(CHANGES_URL);
  if (!resp.ok) return false;
  const data = await resp.json();
  if (data.status === "ERROR" && data.code === "NO_CHANGELOG") {
    return false;
  }
  if (data.status !== "OK") return true;
  const scopes = data.scopes || {};
  const changedClientScopes = new Set();
  const changedDbScopes = [];
  for (const scope of Object.keys(scopes)) {
    const lastChange = scopes[scope]; // opaque fixed-width string
    if (!lastChange) continue;
    if (!changeWatermark[scope] || lastChange > changeWatermark[scope]) {
      changedDbScopes.push(`${scope}@${lastChange}`);
      changeWatermark[scope] = lastChange;
      changedClientScopes.add(mapChangeScope(scope));
    }
  }
  if (changedClientScopes.size > 0) {
    dbgU("change detected:", changedDbScopes.join(","), "-> push scopes:",
         [...changedClientScopes].join(","), "| clients:", clients.size);
    for (const cs of changedClientScopes) {
      sendAll({ type: "UPDATE_REQUIRED", scope: cs, event: "CHANGELOG", ts: Date.now() });
    }
  }
  return true;
}

async function pollStateLegacy() {
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

async function pollState() {
  try {
    if (changeLogAvailable) {
      const ok = await pollChangeLog();
      if (ok) return;
      changeLogAvailable = false;
    }
    await pollStateLegacy();
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
