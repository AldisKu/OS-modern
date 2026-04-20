
function showHash() {
  const el = document.getElementById("display-hash");
  if (el) {
    try {
      // Show connected POS client name instead of broker ID
      if (state.selectedPosId) {
        const clientName = loadSavedClientName();
        el.textContent = clientName ? `Client: ${clientName}` : `broker${state.selectedPosId}`;
        el.style.cursor = "pointer";
        el.onclick = openPosSelector;
      } else {
        const url = new URL(import.meta.url);
        el.textContent = url.pathname.split("/").pop();
      }
    } catch (_) {
      el.textContent = "customer.js";
    }
  }
}
const API = "../php/modernapi.php";

const els = {
  pairScreen: document.getElementById("pair-screen"),
  displayScreen: document.getElementById("display-screen"),
  pairSelect: document.getElementById("pair-select"),
  pairApply: document.getElementById("pair-apply"),
  pairRefresh: document.getElementById("pair-refresh"),
  displaySum: document.getElementById("display-sum"),
  displayBonTitle: document.getElementById("display-bon-title"),
  displayBonList: document.getElementById("display-bon-list"),
  displayOrderTitle: document.getElementById("display-order-title"),
  displayOrderList: document.getElementById("display-order-list"),
  displayWrap: document.getElementById("display-wrap"),
  displayQr: document.getElementById("display-qr"),
  displayQrImg: document.getElementById("display-qr-img"),
  displayQrLink: document.getElementById("display-qr-link"),
  displayIdle: document.getElementById("display-idle"),
  displayIdleLogo: document.getElementById("display-idle-logo"),
  displayIdleText: document.querySelector("#display-idle .display-idle-text")
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
  show(els.pairScreen);
  await loadServerConfig();
  connectBroker();
  
  // Try to auto-reconnect to previously connected POS
  const savedPosId = loadSavedPosId();
  const savedClientName = loadSavedClientName();
  if (savedPosId && savedClientName) {
    // Wait a moment for broker connection to establish
    setTimeout(() => {
      if (state.ws && state.ws.readyState === WebSocket.OPEN) {
        subscribeToPos(savedPosId, savedClientName);
      }
    }, 500);
  }
  
  if (els.pairRefresh) {
    els.pairRefresh.onclick = () => {
      els.pairSelect.innerHTML = "";
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "Suche...";
      opt.disabled = true;
      opt.selected = true;
      els.pairSelect.appendChild(opt);
      if (!state.ws || state.ws.readyState !== state.ws.OPEN) {
        connectBroker();
      } else {
        requestPosList();
      }
    };
  }
}

async function loadServerConfig() {
  const data = await api("config", {});
  if (data.status === "OK") {
    let url = data.broker_ws || null;
    if (url && (url.includes("127.0.0.1") || url.includes("localhost"))) {
      try {
        const host = window.location.hostname;
        url = url.replace("127.0.0.1", host).replace("localhost", host);
      } catch (_) {}
    }
    state.brokerUrl = url;
  } else {
    show(els.pairScreen);
  }
}

function connectBroker() {
  if (!state.brokerUrl) return;
  const ws = new WebSocket(state.brokerUrl);
  state.ws = ws;
  ws.onopen = () => {
    ws.send(JSON.stringify({ type: "REGISTER", role: "display" }));
    requestPosList();
  };
  ws.onclose = () => {
    // POS went offline - clear saved POS ID and go to start screen
    state.selectedPosId = null;
    clearSavedPosId();
    show(els.pairScreen);
  };
  ws.onerror = () => {
    // POS went offline - clear saved POS ID and go to start screen
    state.selectedPosId = null;
    clearSavedPosId();
    show(els.pairScreen);
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
      if (!(state.qrActiveUntil && Date.now() < state.qrActiveUntil)) {
        showIdle();
      }
      return;
    }
    if (msg.type === "POS_OFFLINE") {
      // POS went offline - only clear state.selectedPosId, keep saved client name for auto-reconnect
      if (msg.posId === state.selectedPosId) {
        state.selectedPosId = null;
        // Don't clear saved client name - we want to auto-reconnect when POS comes back
        // clearSavedPosId();
        show(els.pairScreen);
      }
      return;
    }
  };
}

function requestPosList() {
  if (state.ws && state.ws.readyState === WebSocket.OPEN) {
    state.ws.send(JSON.stringify({ type: "REQUEST_POS_LIST" }));
  }
}

function handlePosList(list) {
  if (!Array.isArray(list)) list = [];
  
  // If already connected to a POS, don't interrupt - just update the list
  if (state.selectedPosId) {
    // Check if currently connected POS is still in the list
    const stillConnected = list.some(p => p.id === state.selectedPosId);
    if (stillConnected) {
      // Stay connected, don't show selection screen
      return;
    }
    // If connected POS went offline, show selection screen
  }
  
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
    state.selectedPosId = null;
    showHash();
    return;
  }
  
  // Check if we have a saved client name and can auto-reconnect
  const savedClientName = loadSavedClientName();
  if (savedClientName) {
    // Find the POS with matching client name
    const matchingPos = list.find(p => p.clientName === savedClientName);
    if (matchingPos) {
      // Auto-reconnect to the saved POS
      subscribeToPos(matchingPos.id, savedClientName);
      return;
    }
  }
  
  // Always show selection screen - user must manually select POS
  show(els.pairScreen);
  els.pairSelect.innerHTML = "";
  list.forEach(p => {
    const label = `${p.clientName || ("broker" + p.id)} ${p.userName || ""} ${p.deviceId || ""}`.trim();
    const opt = document.createElement("option");
    opt.value = String(p.id);
    opt.dataset.clientName = p.clientName || "";
    opt.textContent = label;
    els.pairSelect.appendChild(opt);
  });
  els.pairApply.onclick = () => {
    const val = Number(els.pairSelect.value);
    const clientName = els.pairSelect.options[els.pairSelect.selectedIndex]?.dataset?.clientName || "";
    if (val) subscribeToPos(val, clientName);
  };
}

function subscribeToPos(posId, clientName) {
  state.selectedPosId = posId;
  savePosId(posId, clientName);
  if (state.ws && state.ws.readyState === WebSocket.OPEN) {
    // Send both ID and client name for compatibility
    state.ws.send(JSON.stringify({ type: "SUBSCRIBE", posId, clientName }));
  }
  show(els.displayScreen);
  showHash();
  if (document.documentElement.requestFullscreen) {
    document.documentElement.requestFullscreen().catch(() => {});
  }
  showIdle();
}

function savePosId(posId, clientName) {
  try {
    localStorage.setItem("customer_selected_pos_id", String(posId));
    if (clientName) {
      localStorage.setItem("customer_selected_client_name", String(clientName));
    }
  } catch (_) {}
}

function loadSavedPosId() {
  try {
    const saved = localStorage.getItem("customer_selected_pos_id");
    return saved ? Number(saved) : null;
  } catch (_) {
    return null;
  }
}

function loadSavedClientName() {
  try {
    return localStorage.getItem("customer_selected_client_name") || null;
  } catch (_) {
    return null;
  }
}

function clearSavedPosId() {
  try {
    localStorage.removeItem("customer_selected_pos_id");
    localStorage.removeItem("customer_selected_client_name");
  } catch (_) {}
}

function openPosSelector() {
  // Always show selection screen when clicking on broker ID
  state.selectedPosId = null;
  clearSavedPosId();
  show(els.pairScreen);
  // Request fresh POS list
  if (state.ws && state.ws.readyState === WebSocket.OPEN) {
    state.ws.send(JSON.stringify({ type: "REQUEST_POS_LIST" }));
  }
}

function handleDisplayUpdate(msg) {
  const hasActivity = msg && msg.activity === "product";
  if (state.qrActiveUntil && Date.now() < state.qrActiveUntil) {
    if (!hasActivity) {
      return;
    }
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
      els.displayBonTitle.textContent = "Bestellung:";
      els.displayBonList.innerHTML = (payload.items || []).map(i => `
        <div class="display-bon-item">
        <div class="display-row"><span>${i.qty}x ${i.name}</span><span>${Number(i.price || 0).toFixed(2)}</span></div>
        ${(i.extras && i.extras.length) ? i.extras.map(e => `<div class="display-extra">+ ${e}</div>`).join("") : ""}
        </div>
      `).join("");
    els.displayOrderTitle.textContent = "";
    els.displayOrderList.innerHTML = "";
    hideIdle();
    startIdleTimer();
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
      const bonItems = (payload.bonItems || []).map(i => ({
        ...i,
        extras: (i.extras || []).filter(e => e && e.name).map(e => ({...e, name: String(e.name).replace(/^\s*\d+\s*x\s*/i, "") }))
      }));
      const bonHtml = bonItems.map(i => `
        <div class="display-bon-item">
        <div class="display-row"><span>${i.qty}x ${i.name}</span><span>${(Number(i.price || 0) + Number(i.extrasSum || 0)).toFixed(2)}</span></div>
        ${(i.extras && i.extras.length) ? i.extras.map(e => `<div class="display-extra">+ ${e.amount} ${e.name}</div>`).join("") : (i.extrasSum > 0 ? `<div class="display-extra">+ Extras</div>` : "")}
        </div>
      `).join("");
      els.displayBonList.innerHTML = bonHtml;
    els.displayOrderTitle.textContent = "";
    els.displayOrderList.innerHTML = (payload.openItems || []).map(i => `
      <span class="display-order-item">${i.qty}x ${i.name}</span>
    `).join("");
    hideIdle();
    startIdleTimer();
    requestAnimationFrame(() => {
      if (els.displayWrap) {
        const bonList = els.displayBonList;
        const isFull = bonList && bonList.scrollHeight > bonList.clientHeight + 2;
        els.displayWrap.classList.toggle("bon-full", !!isFull);
      }
    });
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
  if (els.displayIdleLogo) {
    els.displayIdleLogo.classList.remove("hidden");
    if (els.displayIdleText) els.displayIdleText.classList.add("hidden");
    els.displayIdleLogo.onload = () => {
      if (els.displayIdleText) els.displayIdleText.classList.add("hidden");
    };
    els.displayIdleLogo.onerror = () => {
      els.displayIdleLogo.classList.add("hidden");
      if (els.displayIdleText) els.displayIdleText.classList.remove("hidden");
    };
  } else if (els.displayIdleText) {
    els.displayIdleText.classList.remove("hidden");
  }
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

showHash();
init();
