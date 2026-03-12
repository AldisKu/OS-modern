const API = "../php/modernapi.php";

const els = {
  loginScreen: document.getElementById("login-screen"),
  pairScreen: document.getElementById("pair-screen"),
  displayScreen: document.getElementById("display-screen"),
  loginUser: document.getElementById("login-user"),
  loginPass: document.getElementById("login-pass"),
  loginBtn: document.getElementById("login-btn"),
  loginClear: document.getElementById("login-clear"),
  loginHint: document.getElementById("login-hint"),
  keyboard: document.getElementById("keyboard"),
  pairList: document.getElementById("pair-list"),
  displaySum: document.getElementById("display-sum"),
  displayBonTitle: document.getElementById("display-bon-title"),
  displayBonList: document.getElementById("display-bon-list"),
  displayOrderTitle: document.getElementById("display-order-title"),
  displayOrderList: document.getElementById("display-order-list"),
  displayQr: document.getElementById("display-qr"),
  displayQrImg: document.getElementById("display-qr-img"),
  displayQrLink: document.getElementById("display-qr-link"),
  displayIdle: document.getElementById("display-idle")
};

const state = {
  users: [],
  brokerUrl: null,
  ws: null,
  selectedPosId: null,
  idleTimer: null,
  lastMode: null,
  keyboardMode: "num"
};

function show(screen) {
  [els.loginScreen, els.pairScreen, els.displayScreen].forEach(s => s.classList.add("hidden"));
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
  els.loginBtn.onclick = doLogin;
  els.loginClear.onclick = () => { els.loginPass.value = ""; };
  buildKeyboard();
  await loadServerConfig();
  await loadUsers();
  await checkSession();
}

async function checkSession() {
  const data = await api("session", {});
  if (data.loggedIn) {
    connectBroker();
  } else {
    show(els.loginScreen);
  }
}

async function loadServerConfig() {
  const data = await api("config", {});
  if (data.status === "OK") {
    state.brokerUrl = data.broker_ws || null;
  }
}

async function loadUsers() {
  const data = await api("users", {});
  if (!data.users) return;
  state.users = data.users;
  renderLoginUsers();
}

function renderLoginUsers() {
  const select = els.loginUser;
  select.innerHTML = "";
  const placeholder = document.createElement("option");
  placeholder.value = "";
  placeholder.textContent = "Benutzer wählen";
  placeholder.disabled = true;
  placeholder.selected = true;
  select.appendChild(placeholder);
  const list = (state.users || []).map(u => {
    const id = u.id ?? u.userid ?? u.user_id ?? u.uid ?? u[0];
    const username = u.username ?? u.user ?? u.login ?? u.loginname ?? u.useridname;
    const name = username || u.name || u.fullname || u[1];
    return { id, name };
  }).filter(u => u.id !== undefined && u.id !== null && u.name);
  list.sort((a, b) => String(a.name).localeCompare(String(b.name), "de"));
  list.forEach(u => {
    const opt = document.createElement("option");
    opt.value = String(u.id);
    opt.textContent = u.name;
    select.appendChild(opt);
  });
}

async function doLogin() {
  const userid = els.loginUser.value;
  const password = els.loginPass.value;
  if (!userid || !password) {
    els.loginHint.textContent = "Bitte Benutzer und Passwort";
    return;
  }
  const res = await api("login", { userid, password, modus: 0, time: Math.floor(Date.now() / 1000) });
  if (res.status === "YES") {
    els.loginHint.textContent = "";
    connectBroker();
  } else {
    els.loginHint.textContent = "Login fehlgeschlagen";
  }
}

function buildKeyboard() {
  renderKeyboard();
}

function renderKeyboard() {
  if (!els.keyboard) return;
  const mode = state.keyboardMode;
  const rows = [];
  const pushRow = (labels, rowMode) => {
    rows.push(labels.map(label => {
      let key = label;
      if (rowMode === "num") {
        if (label === "a") key = "SWITCH_ALPHA";
        if (label === "#") key = "SWITCH_SYM";
      }
      if (rowMode === "alpha") {
        if (label === "ABC") key = "SWITCH_ABC";
        if (label === "#") key = "SWITCH_SYM";
      }
      if (rowMode === "sym") {
        if (label === "a") key = "SWITCH_ALPHA";
      }
      return { label, key };
    }));
  };

  if (mode === "num") {
    pushRow(["1", "2", "3"], "num");
    pushRow(["4", "5", "6"], "num");
    pushRow(["7", "8", "9"], "num");
    pushRow(["0", "a", "#"], "num");
  } else if (mode === "sym") {
    pushRow(["§", "!", "$", "+"], "sym");
    pushRow(["%", "&", "/", "-"], "sym");
    pushRow(["(", ")", "=", "#"], "sym");
    pushRow(["?", "_", "1", "a"], "sym");
  } else {
    const upper = mode === "alpha-upper";
    const letters = "abcdefghijklmnopqrstuvwxyz".split("");
    const alpha = upper ? letters.map(l => l.toUpperCase()) : letters;
    const full = alpha.concat(["1", "#"]);
    for (let i = 0; i < full.length; i += 4) {
      pushRow(full.slice(i, i + 4), "alpha");
    }
    pushRow(["ABC"], "alpha");
  }

  els.keyboard.innerHTML = "";
  rows.forEach(row => {
    const rowEl = document.createElement("div");
    rowEl.className = "keyboard-row";
    row.forEach(k => {
      const btn = document.createElement("button");
      btn.dataset.k = k.key;
      btn.textContent = k.label;
      rowEl.appendChild(btn);
    });
    els.keyboard.appendChild(rowEl);
  });

  els.keyboard.querySelectorAll("button").forEach(btn => {
    btn.onclick = () => {
      const key = btn.dataset.k;
      if (key === "SWITCH_ALPHA") {
        state.keyboardMode = "alpha-lower";
        renderKeyboard();
        return;
      }
      if (key === "SWITCH_SYM") {
        state.keyboardMode = "sym";
        renderKeyboard();
        return;
      }
      if (key === "SWITCH_ABC") {
        state.keyboardMode = state.keyboardMode === "alpha-upper" ? "alpha-lower" : "alpha-upper";
        renderKeyboard();
        return;
      }
      els.loginPass.value += key;
    };
  });
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
    els.pairList.innerHTML = "<div>Keine Kasse online.</div>";
    return;
  }
  if (list.length === 1) {
    subscribeToPos(list[0].id);
    return;
  }
  show(els.pairScreen);
  els.pairList.innerHTML = list.map(p => {
    const label = `broker${p.id} ${p.userName || ""} ${p.deviceId || ""}`.trim();
    return `<button data-id="${p.id}">${label}</button>`;
  }).join("");
  els.pairList.querySelectorAll("button").forEach(btn => {
    btn.onclick = () => subscribeToPos(Number(btn.dataset.id));
  });
}

function subscribeToPos(posId) {
  state.selectedPosId = posId;
  if (state.ws && state.ws.readyState === state.ws.OPEN) {
    state.ws.send(JSON.stringify({ type: "SUBSCRIBE", posId }));
  }
  show(els.displayScreen);
  showIdle();
}

function handleDisplayUpdate(msg) {
  clearIdleTimer();
  els.displayQr.classList.add("hidden");
  if (msg.mode === "order") {
    state.lastMode = "order";
    const payload = msg.payload || { items: [], sum: "0.00" };
    els.displaySum.textContent = payload.sum || "0.00";
    els.displayBonTitle.textContent = "Bestellung";
    els.displayBonList.innerHTML = (payload.items || []).map(i => `
      <div class="display-bon-item">
        <span>${i.qty}x ${i.name}</span>
        <span>${Number(i.price || 0).toFixed(2)}</span>
      </div>
    `).join("");
    els.displayOrderTitle.textContent = "";
    els.displayOrderList.innerHTML = "";
    hideIdle();
    return;
  }
  if (msg.mode === "paydesk") {
    state.lastMode = "paydesk";
    const payload = msg.payload || { bonItems: [], openItems: [], sum: "0.00" };
    els.displaySum.textContent = payload.sum || "0.00";
    els.displayBonTitle.textContent = "Bon";
    els.displayBonList.innerHTML = (payload.bonItems || []).map(i => `
      <div class="display-bon-item">
        <span>${i.qty}x ${i.name}</span>
        <span>${Number(i.price || 0).toFixed(2)}</span>
      </div>
    `).join("");
    els.displayOrderTitle.textContent = "Bestellt";
    els.displayOrderList.innerHTML = (payload.openItems || []).map(i => `
      <span class="display-order-item">${i.qty}x ${i.name}</span>
    `).join("");
    hideIdle();
  }
}

function handleEbon(msg) {
  const ebonUrl = msg.ebonUrl || "";
  const ebonRef = msg.ebonRef || "";
  if (!ebonUrl || !ebonRef) return;
  const link = `${ebonUrl.replace(/\\/$/, "")}/index.php?ebonref=${encodeURIComponent(ebonRef)}`;
  els.displayQrImg.src = `../php/utilities/osqrcode.php?cmd=link&arg=${encodeURIComponent(link)}`;
  els.displayQrLink.textContent = link;
  els.displayQr.classList.remove("hidden");
  hideIdle();
  startIdleTimer();
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
    showIdle();
    els.displayQr.classList.add("hidden");
  }, 30000);
}

function clearIdleTimer() {
  if (state.idleTimer) clearTimeout(state.idleTimer);
  state.idleTimer = null;
}

init();
