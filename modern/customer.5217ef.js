const API = "../php/modernapi.php";

const els = {
  pairScreen: document.getElementById("pair-screen"),
  displayScreen: document.getElementById("display-screen"),
  pairSelect: document.getElementById("pair-select"),
  pairApply: document.getElementById("pair-apply"),
  displaySum: document.getElementById("display-sum"),
  displayBonTitle: document.getElementById("display-bon-title"),
  displayBonList: document.getElementById("display-bon-list"),
  displayOrderTitle: document.getElementById("display-order-title"),
  displayOrderList: document.getElementById("display-order-list"),
  displayWrap: document.getElementById("display-wrap"),
  displayQr: document.getElementById("display-qr"),
  displayQrImg: document.getElementById("display-qr-img"),
  displayQrLink: document.getElementById("display-qr-link"),
  displayIdle: document.getElementById("display-idle")
};

const state = {
  brokerUrl: null,
  ws: null,
  selectedPosId: null,
  idleTimer: null,
  lastMode: null,
  qrActiveUntil: 0,
  qrTimer: null
};

function show(screen) {
  [els.pairScreen, els.displayScreen].forEach(s => s.classList.add("hidden"));
  screen.classList.remove("hidden");
}

async function api(cmd, body) {
  const res = await fetch(`${API}?cmd=${cmd}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {})
  });
  return res.json();
}

async function init() {
  await loadServerConfig();
  connectBroker();
}

async function loadServerConfig() {
  const data = await api("config", {});
  if (data.status === "OK") {
    state.brokerUrl = data.broker_ws || null;
  }
}


function connectBroker() {
  if (!state.brokerUrl) return;
  const ws = new WebSocket(state.brokerUrl);
  state.ws = ws;
  ws.onopen = () => {
    ws.send(JSON.stringify({ type: "REGISTER", role: "display" }));
  };
  ws.onmessage = (evt) => {
    let msg = null;
    try { msg = JSON.parse(evt.data); } catch (_) { return; }
    if (!msg) return;
    if (msg.type === "POS_LIST") {
      handlePosList(msg.list || []);
      return;
    }
    if (msg.type === "REGISTERED" && msg.list) {
      handlePosList(msg.list || []);
      return;
    }
    if (msg.type === "DISPLAY_UPDATE") {
      handleDisplayUpdate(msg);
      return;
    }
    if (msg.type === "DISPLAY_EBON") {
      handleEbon(msg);
      return;
    }
    if (msg.type === "DISPLAY_IDLE") {
      showIdle();
      return;
    }
  };
}

function handlePosList(list) {
  if (!Array.isArray(list)) list = [];
  if (list.length === 0) {
    show(els.pairScreen);
    els.pairSelect.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "Keine Kasse online";
    opt.disabled = true;
    opt.selected = true;
    els.pairSelect.appendChild(opt);
    els.pairApply.onclick = null;
    return;
  }
  if (list.length === 1) {
    subscribeToPos(list[0].id);
    return;
  }
  show(els.pairScreen);
  els.pairSelect.innerHTML = "";
  list.forEach(p => {
    const label = `broker${p.id} ${p.userName || ""} ${p.deviceId || ""}`.trim();
    const opt = document.createElement("option");
    opt.value = String(p.id);
    opt.textContent = label;
    els.pairSelect.appendChild(opt);
  });
  els.pairApply.onclick = () => {
    const val = Number(els.pairSelect.value);
    if (val) subscribeToPos(val);
  };
}

function subscribeToPos(posId) {
  state.selectedPosId = posId;
  if (state.ws && state.ws.readyState === state.ws.OPEN) {
    state.ws.send(JSON.stringify({ type: "SUBSCRIBE", posId }));
  }
  show(els.displayScreen);
  if (document.documentElement.requestFullscreen) {
    document.documentElement.requestFullscreen().catch(() => {});
  }
  showIdle();
}

function handleDisplayUpdate(msg) {
  if (state.qrActiveUntil && Date.now() < state.qrActiveUntil) {
    clearQrLock();
  }
  clearIdleTimer();
  els.displayQr.classList.add("hidden");
    if (msg.mode === "order") {
      state.lastMode = "order";
      if (els.displayWrap) {
        els.displayWrap.classList.add("mode-order");
        els.displayWrap.classList.remove("mode-paydesk");
        els.displayWrap.classList.remove("bon-full");
      }
      const payload = msg.payload || { items: [], sum: "0.00" };
      els.displaySum.innerHTML = `<span class="display-sum-label">Summe</span>&nbsp;&nbsp;<span class="display-sum-value">${payload.sum || "0.00"}</span>&nbsp;<span class="display-sum-currency">€</span>`;
      els.displayBonTitle.textContent = "Bestellung";
      els.displayBonList.innerHTML = (payload.items || []).map(i => `
        <div class="display-bon-item">
        <span>${i.qty}x ${i.name}</span>
        <span>${Number(i.price || 0).toFixed(2)}</span>
        ${(i.extras && i.extras.length) ? i.extras.map(e => `<div class="display-extra">+ ${e}</div>`).join("") : ""}
        </div>
      `).join("");
    els.displayOrderTitle.textContent = "";
    els.displayOrderList.innerHTML = "";
    hideIdle();
    return;
  }
    if (msg.mode === "paydesk") {
      state.lastMode = "paydesk";
      if (els.displayWrap) {
        els.displayWrap.classList.add("mode-paydesk");
        els.displayWrap.classList.remove("mode-order");
      }
      const payload = msg.payload || { bonItems: [], openItems: [], sum: "0.00" };
      els.displaySum.innerHTML = `<span class="display-sum-label">Summe</span>&nbsp;&nbsp;<span class="display-sum-value">${payload.sum || "0.00"}</span>&nbsp;<span class="display-sum-currency">€</span>`;
      els.displayBonTitle.textContent = "Sie bezahlen:";
      els.displayBonList.innerHTML = (payload.bonItems || []).map(i => `
        <div class="display-bon-item">
        <span>${i.qty}x ${i.name}</span>
        <span>${(Number(i.price || 0) + Number(i.extrasSum || 0)).toFixed(2)}</span>
        ${(i.extras && i.extras.length) ? i.extras.map(e => `<div class="display-extra">+ ${e.amount}x ${e.name}${(e.price && e.price > 0) ? " (" + Number(e.price).toFixed(2) + ")" : ""}</div>`).join("") : ""}
        </div>
      `).join("");
    els.displayOrderTitle.textContent = "Ihre Bestellung:";
    els.displayOrderList.innerHTML = (payload.openItems || []).map(i => `
      <span class="display-order-item">${i.qty}x ${i.name}</span>
    `).join("");
    hideIdle();
    if (els.displayWrap) {
      const bonList = els.displayBonList;
      const isFull = bonList && bonList.scrollHeight > bonList.clientHeight + 2;
      els.displayWrap.classList.toggle("bon-full", !!isFull);
    }
  }
}

function handleEbon(msg) {
  const ebonUrl = msg.ebonUrl || "";
  const ebonRef = msg.ebonRef || "";
  if (!ebonUrl || !ebonRef) return;
  const link = `${ebonUrl.replace(/\/$/, "")}/index.php?ebonref=${encodeURIComponent(ebonRef)}`;
  els.displayQrImg.src = `../php/utilities/osqrcode.php?cmd=link&arg=${encodeURIComponent(link)}`;
  els.displayQrLink.textContent = link;
  els.displayQr.classList.remove("hidden");
  hideIdle();
  state.qrActiveUntil = Date.now() + 30000;
  clearIdleTimer();
  if (state.qrTimer) clearTimeout(state.qrTimer);
  state.qrTimer = setTimeout(() => {
    showIdle();
    els.displayQr.classList.add("hidden");
    state.qrActiveUntil = 0;
  }, 30000);
}

function clearQrLock() {
  state.qrActiveUntil = 0;
  if (state.qrTimer) clearTimeout(state.qrTimer);
  state.qrTimer = null;
  els.displayQr.classList.add("hidden");
}

function showIdle() {
  els.displayIdle.classList.remove("hidden");
}

function hideIdle() {
  els.displayIdle.classList.add("hidden");
}

function startIdleTimer() {
  clearIdleTimer();
  state.idleTimer = setTimeout(() => {
    if (state.qrActiveUntil && Date.now() < state.qrActiveUntil) return;
    showIdle();
    els.displayQr.classList.add("hidden");
    state.qrActiveUntil = 0;
  }, 30000);
}

function clearIdleTimer() {
  if (state.idleTimer) clearTimeout(state.idleTimer);
  state.idleTimer = null;
}

init();
