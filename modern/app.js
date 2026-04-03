const API = "../php/modernapi.php";
const APP_VERSION = "05";
let brokerUrl = "ws://127.0.0.1:3077";
const BROKER_MISS_GRACE_MS = 6000;

const els = {
  loginScreen: document.getElementById("login-screen"),
  startScreen: document.getElementById("start-screen"),
  orderScreen: document.getElementById("order-screen"),
  paydeskScreen: document.getElementById("paydesk-screen"),

  loginUser: document.getElementById("login-user"),
  loginPass: document.getElementById("login-pass"),
  loginBtn: document.getElementById("login-btn"),
  loginClear: document.getElementById("login-clear"),
  loginHint: document.getElementById("login-hint"),

  statusUser: document.getElementById("status-user"),
  statusBroker: document.getElementById("status-broker"),
  statusOnline: document.getElementById("status-online"),
  statusSync: document.getElementById("status-sync"),
  statusVersion: document.getElementById("status-version"),

  orderUser: document.getElementById("order-user"),
  orderBroker: document.getElementById("order-broker"),
  orderOnline: document.getElementById("order-online"),
  orderSync: document.getElementById("order-sync"),
  orderVersion: document.getElementById("order-version"),

  paydeskUser: document.getElementById("paydesk-user"),
  paydeskOnline: document.getElementById("paydesk-online"),
  paydeskSync: document.getElementById("paydesk-sync"),

  tablesGrid: document.getElementById("tables-grid"),
  orderTableLabel: document.getElementById("order-table-label"),
  categoryRow: document.getElementById("category-row"),
  productsGrid: document.getElementById("products-grid"),
  orderItems: document.getElementById("order-items"),
  orderCartSum: document.getElementById("order-cart-sum"),

  paydeskTables: document.getElementById("paydesk-tables"),
  paydeskLeft: document.getElementById("paydesk-left"),
  paydeskOpen: document.getElementById("paydesk-open"),
  paydeskReceipt: document.getElementById("paydesk-receipt"),
  paydeskTotal: document.getElementById("paydesk-total"),
  paydeskPayments: document.getElementById("paydesk-payments"),
  paydeskTableLabel: document.getElementById("paydesk-table-label"),
  paydeskTableName: document.getElementById("paydesk-table-name"),
  paydeskOpenTitle: document.getElementById("paydesk-open-title"),
  paydeskAddAll: document.getElementById("paydesk-add-all"),
  paydeskClear: document.getElementById("paydesk-clear-receipt"),
  paydeskHost: document.getElementById("paydesk-host"),

  productModal: document.getElementById("product-modal"),
  modalTitle: document.getElementById("modal-title"),
  modalQty: document.getElementById("modal-qty"),
  modalNote: document.getElementById("modal-note"),
  modalTogo: document.getElementById("modal-togo"),
  modalExtras: document.getElementById("modal-extras"),
  modalAdd: document.getElementById("modal-add"),
  modalClose: document.getElementById("modal-close"),

  priceModal: document.getElementById("price-modal"),
  priceTitle: document.getElementById("price-title"),
  priceValue: document.getElementById("price-value"),
  priceKeypad: document.getElementById("price-keypad"),
  priceCancel: document.getElementById("price-cancel"),
  priceConfirm: document.getElementById("price-confirm"),

  confirmModal: document.getElementById("confirm-modal"),
  confirmTitle: document.getElementById("confirm-title"),
  confirmBody: document.getElementById("confirm-body"),
  confirmActions: document.getElementById("confirm-actions"),

  menuModal: document.getElementById("menu-modal"),
  menuItems: document.getElementById("menu-items"),
  menuClose: document.getElementById("menu-close"),
  recordsModal: document.getElementById("records-modal"),
  recordsTitle: document.getElementById("records-title"),
  recordsBody: document.getElementById("records-body"),
  recordsClose: document.getElementById("records-close"),
  startMessageBody: document.getElementById("start-message-body"),
  keyboard: document.getElementById("keyboard")
};

const state = {
  users: [],
  user: null,
  config: null,
  menu: null,
  rooms: null,
  selectedTable: null,
  selectedType: null,
  typeStack: [],
  cartByTable: {},
  orderExisting: [],
  payments: [],
  paydeskItems: [],
  paydeskOpen: [],
  paydeskReceipt: [],
  paydeskTable: null,
  paydeskMode: "list",
  paydeskHost: false,
  paydeskPrint: false,
  maxTableLabelWidth: 0,
  notDelivered: [],
  keyboardMode: "num",
  lastSync: "-",
  cancelUnpaidCode: "",
  discounts: { d1: 0, d2: 0, d3: 0, n1: "Rabatt 1", n2: "Rabatt 2", n3: "Rabatt 3" },
  modalExtrasSelected: [],
  localConfig: { singleExtraImmediate: true },
  userPrefs: { preferimgmobile: 0 },
  tableLayout: null,
  brokerWs: null,
  brokerId: null,
  deviceId: null,
  displayUpdateTimer: null,
  displayActivity: false,
  displaySeq: 0,
  brokerLabel: "OK",
  clientPollMs: 120000,
  lastServerVersion: null,
  lastServerVersionAt: 0,
  lastBrokerUpdateAt: 0,
  missedUpdateVersion: null,
  pendingRoomRefresh: false,
  priceEntry: null
};

function markDisplayActivity() {
  state.displayActivity = true;
  scheduleDisplayUpdate();
}

const RECORD_ACTIONS = [
  "Bestellung",
  "Rechnung",
  "Artikelstorno",
  "Rechnungsstorno",
  "Rechnungs- und Artikelstorno",
  "Tischwechsel Produktentfernung",
  "Tischwechsel Produktbuchung",
  "Lieferbon",
  "Storno Lieferbon",
  "Storno Lieferbon und Artikel"
];

function escapeHtml(value) {
  const str = value == null ? "" : String(value);
  return str
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function show(screen) {
  [els.loginScreen, els.startScreen, els.orderScreen, els.paydeskScreen].forEach(s => s.classList.add("hidden"));
  screen.classList.remove("hidden");
  if (screen !== els.startScreen && els.startMessageBody) {
    els.startMessageBody.textContent = "";
  }
  if (screen === els.startScreen && els.startMessageBody && els.startMessageBody.textContent.trim() === "") {
    els.startMessageBody.textContent = "Bereit.";
  }
  if (screen === els.startScreen) {
    sendDisplayIdle();
    // Always refresh tables when entering the start screen to align with broker/poll data
    refreshTables();
    if (state.pendingRoomRefresh) state.pendingRoomRefresh = false;
  }
}

async function api(cmd, body) {
  const res = await fetch(`${API}?cmd=${cmd}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {})
  });
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    const start = text.indexOf("{");
    const end = text.lastIndexOf("}");
    if (start !== -1 && end !== -1 && end > start) {
      try {
        return JSON.parse(text.slice(start, end + 1));
      } catch (_) {}
    }
    console.error("API JSON parse failed:", cmd, text);
    throw e;
  }
}

async function init() {
  bindMenuButtons();
  bindLogin();
  bindModals();
  await loadServerConfig();
  await loadUsers();
  await checkSession();
  initBroker();
  startPolling();
}

function initBroker() {
  if (!brokerUrl) return;
  try {
    const ws = new WebSocket(brokerUrl);
    state.brokerWs = ws;
    ws.onopen = () => {
      state.brokerLabel = "OK";
      [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = state.brokerLabel);
      registerBrokerClient();
    };
    ws.onclose = () => {
      [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = "OFF");
    };
    ws.onerror = () => {
      [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = "OFF");
    };
    ws.onmessage = async (evt) => {
      let payload = null;
      try { payload = JSON.parse(evt.data); } catch (_) { return; }
      if (payload && payload.type === "REGISTERED") {
        state.brokerId = payload.id || null;
        const label = payload.id ? `broker${payload.id}` : "OK";
        state.brokerLabel = label;
        [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = state.brokerLabel);
        return;
      }
      if (payload && payload.type === "UPDATE_REQUIRED") {
        state.lastBrokerUpdateAt = Date.now();
        const scope = String(payload.scope || "").toUpperCase();
        if (scope.includes("MENU") || scope.includes("PRICE") || scope.includes("PRICES")) {
          console.debug("Preisstufe geändert");
          await refreshMenuPrices();
        } else {
          await refreshTablesWithRetry();
          await refreshOrderIfVisible();
        }
      }
    };
  } catch (_) {}
}

async function refreshTablesWithRetry() {
  const attempt = async () => { await refreshTables(); };
  await attempt();
  // Keep a pending flag so the next navigation to start-screen refreshes again
  state.pendingRoomRefresh = true;
  // Safety retries to handle backend lag or race with push timing
  setTimeout(attempt, 1200);
  setTimeout(attempt, 2500);
}

function registerBrokerClient() {
  if (!state.brokerWs || state.brokerWs.readyState !== state.brokerWs.OPEN) return;
  if (!state.user) return;
  if (!state.deviceId) {
    const existing = localStorage.getItem("modern_device_id");
    if (existing) state.deviceId = existing;
    else {
      const rnd = Math.random().toString(36).slice(2, 6).toUpperCase();
      state.deviceId = `POS-${rnd}`;
      localStorage.setItem("modern_device_id", state.deviceId);
    }
  }
  const payload = {
    type: "REGISTER",
    role: "pos",
    deviceId: state.deviceId,
    userId: state.user?.id || "",
    userName: state.user?.name || ""
  };
  state.brokerWs.send(JSON.stringify(payload));
}

function bindMenuButtons() {
  document.querySelectorAll(".menu-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const action = btn.dataset.action;
      handleMenuAction(action, btn);
    });
  });
}

function bindLogin() {
  els.loginBtn.addEventListener("click", doLogin);
  els.loginClear.addEventListener("click", () => {
    els.loginPass.value = "";
  });
  buildKeyboard();
}

function bindModals() {
  if (els.modalClose) {
    els.modalClose.addEventListener("click", () => els.productModal.classList.add("hidden"));
  }
  els.modalAdd.addEventListener("click", addProductToCart);
  if (els.priceCancel) {
    els.priceCancel.addEventListener("click", () => {
      els.priceModal.classList.add("hidden");
      state.priceEntry = null;
    });
  }
  if (els.priceConfirm) {
    els.priceConfirm.addEventListener("click", () => {
      if (!state.priceEntry || !state.priceEntry.prod) return;
      if (!state.priceEntry.raw) {
        alert("Bitte Preis eingeben.");
        return;
      }
      const value = parsePriceEntry(state.priceEntry);
      const price = value.toFixed(2);
      const togo = state.selectedTable?.id === 0 ? 1 : 0;
      addToCartCustom(state.priceEntry.prod, [], "", 1, togo, price, true);
      els.priceModal.classList.add("hidden");
      state.priceEntry = null;
    });
  }
  if (els.priceKeypad) {
    window.priceKeyPress = handlePriceKey;
  }
  els.menuClose.addEventListener("click", () => els.menuModal.classList.add("hidden"));
  if (els.recordsClose) {
    els.recordsClose.addEventListener("click", () => els.recordsModal.classList.add("hidden"));
  }
  [els.productModal, els.confirmModal, els.menuModal, els.recordsModal, els.priceModal].filter(Boolean).forEach(modal => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        modal.classList.add("hidden");
        if (modal === els.priceModal) state.priceEntry = null;
      }
    });
  });
}

async function loadServerConfig() {
  const data = await api("config", {});
  if (data.status === "OK" && data.broker_ws) {
    brokerUrl = data.broker_ws;
    if (Number.isFinite(Number(data.client_poll_interval_ms))) {
      state.clientPollMs = Math.max(10000, Number(data.client_poll_interval_ms));
    }
  }
}

async function loadUsers() {
  const data = await api("users", {});
  if (data.users) {
    state.users = data.users;
    renderLoginUsers();
  }
}

function renderLoginUsers() {
  if (!els.loginUser || els.loginUser.tagName !== "SELECT") return;
  const select = els.loginUser;
  select.innerHTML = "";
  const placeholder = document.createElement("option");
  placeholder.value = "";
  placeholder.textContent = "Benutzer wählen";
  placeholder.disabled = true;
  placeholder.selected = true;
  select.appendChild(placeholder);
  const list = (state.users || []).map((u) => {
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

async function checkSession() {
  const data = await api("session", {});
  if (data.loggedIn) {
    state.user = data.user;
    await bootstrap();
  } else {
    show(els.loginScreen);
  }
}

async function doLogin() {
  const userid = els.loginUser.value;
  const password = els.loginPass.value;
  if (!userid || !password) {
    els.loginHint.textContent = "Bitte Benutzer-ID und Passwort";
    return;
  }
  const res = await api("login", { userid, password, modus: 0, time: Math.floor(Date.now() / 1000) });
  if (res.status === "YES") {
    els.loginHint.textContent = "";
    resetClientState();
    await bootstrap();
    registerBrokerClient();
  } else {
    els.loginHint.textContent = "Login fehlgeschlagen";
  }
}

async function bootstrap() {
  const data = await api("bootstrap", {});
  if (data.status !== "OK") return;
  state.user = data.user;
  state.config = normalizeConfig(data.config);
  state.userPrefs = data.userprefs || { preferimgmobile: 0 };
  state.menu = data.menu;
  state.rooms = data.rooms;
  state.localConfig = loadLocalConfig();
  state.tableLayout = await loadTableLayout() || state.localConfig?.tableLayout || null;
  state.typeStack = [];
  state.selectedType = topLevelTypes()[0]?.id || null;
  state.cancelUnpaidCode = state.config?.cancelunpaidcode || "";
  const parsePct = (v) => {
    if (v === null || v === undefined) return 0;
    return parseFloat(String(v).replace(",", ".")) || 0;
  };
  state.discounts = {
    d1: parsePct(state.config?.discount1),
    d2: parsePct(state.config?.discount2),
    d3: parsePct(state.config?.discount3),
    n1: state.config?.discountname1 || "Rabatt 1",
    n2: state.config?.discountname2 || "Rabatt 2",
    n3: state.config?.discountname3 || "Rabatt 3"
  };
  updateStatus();
  renderTables();
  renderCategories();
  show(els.startScreen);
  registerBrokerClient();
}

async function refreshMenuPrices() {
  const data = await api("bootstrap", {});
  if (data.status !== "OK") return;
  state.config = normalizeConfig(data.config);
  state.userPrefs = data.userprefs || { preferimgmobile: 0 };
  state.menu = data.menu;
  state.rooms = data.rooms;
  const parsePct = (v) => {
    if (v === null || v === undefined) return 0;
    return parseFloat(String(v).replace(",", ".")) || 0;
  };
  state.discounts = {
    d1: parsePct(state.config?.discount1),
    d2: parsePct(state.config?.discount2),
    d3: parsePct(state.config?.discount3),
    n1: state.config?.discountname1 || "Rabatt 1",
    n2: state.config?.discountname2 || "Rabatt 2",
    n3: state.config?.discountname3 || "Rabatt 3"
  };
  renderTables();
  renderCategories();
  renderProducts();
}

function updateStatus() {
  const name = state.user?.name || "-";
  [els.statusUser, els.orderUser, els.paydeskUser].filter(Boolean).forEach(el => el.textContent = name);
  [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = state.brokerLabel || "OK");
  [els.statusOnline, els.orderOnline, els.paydeskOnline].filter(Boolean).forEach(el => el.textContent = "OK");
  [els.statusSync, els.orderSync, els.paydeskSync].filter(Boolean).forEach(el => el.textContent = state.lastSync);
  [els.statusVersion, els.orderVersion].filter(Boolean).forEach(el => el.textContent = APP_VERSION);
}

async function logClientError(message) {
  try {
    await api("log_client", { level: "ERROR", msg: message });
  } catch (_) {}
}

function showWarnPopup(message) {
  resetConfirmActionsLayout();
  els.confirmTitle.textContent = "Hinweis";
  els.confirmBody.innerHTML = message;
  els.confirmActions.innerHTML = `<button class="primary" id="warn-ok">OK</button>`;
  els.confirmModal.classList.remove("hidden");
  els.confirmActions.querySelector("#warn-ok").onclick = () => {
    els.confirmModal.classList.add("hidden");
  };
}

function normalizeConfig(raw) {
  if (!raw) return {};
  if (typeof raw === "object") return raw;
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === "object") return parsed;
    } catch (_) {}
  }
  return {};
}

function topLevelTypes() {
  if (!state.menu?.types) return [];
  return state.menu.types.filter(t => Number(t.ref) === 0);
}

function childTypes(parentId) {
  if (!state.menu?.types) return [];
  return state.menu.types.filter(t => Number(t.ref) === Number(parentId));
}

function renderCategories() {
  const stack = state.typeStack || [];
  const btns = [];

  if (stack.length === 0) {
    const tops = topLevelTypes();
    btns.push(...tops.map(t => `<button class="category-btn down" data-cat="${t.id}">${t.name}</button>`));
  } else {
    const topId = stack[0];
    const currentId = stack[stack.length - 1];
    const top = state.menu?.types?.find(t => Number(t.id) === Number(topId));
    const current = state.menu?.types?.find(t => Number(t.id) === Number(currentId));

    btns.push(`<button class="category-btn up" data-cat="start">Kategorien</button>`);
    if (top) {
      btns.push(`<button class="category-btn up" data-cat="${top.id}">${top.name}</button>`);
    }
    if (current && (!top || Number(top.id) !== Number(current.id))) {
      btns.push(`<button class="category-btn current" data-cat="current">${current.name}</button>`);
    }

    const children = childTypes(currentId);
    btns.push(...children.map(t => `<button class="category-btn down" data-cat="${t.id}">${t.name}</button>`));
  }

  els.categoryRow.innerHTML = btns.join("");
  els.categoryRow.querySelectorAll(".category-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const id = btn.dataset.cat;
      if (id === "start") {
        state.typeStack = [];
        state.selectedType = topLevelTypes()[0]?.id || null;
      } else if (id === "current") {
        return;
      } else {
        const numId = Number(id);
        if (stack.length === 0) {
          state.typeStack = [numId];
          state.selectedType = numId;
        } else if (Number(stack[0]) === numId) {
          state.typeStack = [numId];
          state.selectedType = numId;
        } else {
          state.typeStack = [...stack, numId];
          state.selectedType = numId;
        }
      }
      renderCategories();
      renderProducts();
    });
  });
  renderProducts();
}

function renderProducts() {
  if (!state.menu?.prods) return;
  const prods = state.menu.prods.filter(p => Number(p.ref) === Number(state.selectedType));
  const showImages = Number(state.userPrefs?.preferimgmobile || state.config?.preferimgmobile || 0) === 1;
  els.productsGrid.innerHTML = prods.map(p => `
    <div class="product-card" data-id="${p.id}">
      ${showImages && Number(p.prodimageid || 0) > 0 ? `<img class="product-img" src="../php/contenthandler.php?module=products&command=getprodimage&prodid=${p.id}&size=l" alt="">` : ""}
      <div><b>${p.longname || p.name}</b></div>
      <div>${p.price}</div>
    </div>
  `).join("");
  els.productsGrid.querySelectorAll(".product-card").forEach(el => {
    const id = Number(el.dataset.id);
    const prod = state.menu.prods.find(p => Number(p.id) === id);
    el.addEventListener("click", () => {
      if (!prod) {
        alert("Produkt nicht gefunden");
        return;
      }
      markDisplayActivity();
      if (shouldEnterPrice(prod)) {
        openPriceModal(prod);
      } else if (!prod.extras || prod.extras.length === 0) {
        quickAddProduct(prod);
      } else {
        openProductModal(prod);
      }
    });
  });
}

function shouldEnterPrice(prod) {
  const unit = Number(prod.unit || 0);
  if (!Number.isFinite(unit) || unit === 0) return false;
  if (unit === 8 || unit === 9) return false;
  return true;
}

function renderTables() {
  if (!state.rooms?.roomstables) return;
  const cards = [];
  const layout = state.tableLayout;
  state.rooms.roomstables.forEach(room => {
    const roomLayout = (layout && layout.rooms && (layout.rooms[String(room.id)] || layout.rooms["default"])) || null;
    if (roomLayout && roomLayout.tables) {
      const cols = Number(roomLayout.cols || 4);
      cards.push(`<div class="room-title">${room.name}</div>`);
      cards.push(`<div class="tables-room-grid" style="grid-template-columns: repeat(${cols}, 1fr);">`);
      const extraTables = [];
      room.tables.forEach(t => {
        const code = getTableCode(t, roomLayout.tables);
        const pos = roomLayout.tables[String(t.id)] || roomLayout.tables[code] || {};
        const sum = t.pricesum || "0.00";
        const row = Number(pos.row || 0);
        const col = Number(pos.col || 0);
        const style = row > 0 && col > 0 ? `style="grid-row:${row};grid-column:${col};"` : "";
        const unpaid = Number(t.unpaidprodcount || 0) > 0 ? "unpaid" : "";
        const card = `
          <div class="table-card ${unpaid}" data-id="${t.id}" data-name="${t.name}" ${style}>
            <div class="name">${t.name}</div>
            <div class="meta">${sum}</div>
          </div>
        `;
        if (row > 0 && col > 0) {
          cards.push(card);
        } else {
          extraTables.push(card);
        }
      });
      cards.push(`</div>`);
      if (extraTables.length > 0) {
        cards.push(`<div class="tables-room-list">`);
        cards.push(extraTables.join(""));
        cards.push(`</div>`);
      }
    } else {
      cards.push(`<div class="room-title">${room.name}</div>`);
      cards.push(`<div class="tables-room-list">`);
      room.tables.forEach(t => {
        const sum = t.pricesum || "0.00";
        const unpaid = Number(t.unpaidprodcount || 0) > 0 ? "unpaid" : "";
        cards.push(`
          <div class="table-card ${unpaid}" data-id="${t.id}" data-name="${t.name}">
            <div class="name">${t.name}</div>
            <div class="meta">${sum}</div>
          </div>
        `);
      });
      cards.push(`</div>`);
      cards.push(`<div class="room-sep"></div>`);
    }
  });
  els.tablesGrid.innerHTML = cards.join("");
  els.tablesGrid.querySelectorAll(".table-card").forEach(el => {
    el.addEventListener("click", () => openOrderForTable({ id: Number(el.dataset.id), name: el.dataset.name }));
  });
  updateTableLabelWidth();
}

async function openOrderForTable(table) {
  state.selectedTable = table;
  els.orderTableLabel.textContent = table.name;
  if (state.maxTableLabelWidth > 0) {
    els.orderTableLabel.style.display = "inline-block";
    els.orderTableLabel.style.minWidth = `${state.maxTableLabelWidth}px`;
  }
  state.typeStack = [];
  state.selectedType = topLevelTypes()[0]?.id || null;
  loadCart(table.id);
  await fetchExistingOrders();
  await fetchNotDelivered();
  renderOrderItems();
  renderCategories();
  show(els.orderScreen);
}

function loadCart(tableId) {
  const raw = localStorage.getItem(`cart_${tableId}`);
  state.cartByTable[tableId] = raw ? JSON.parse(raw) : [];
}

function saveCart(tableId) {
  localStorage.setItem(`cart_${tableId}`, JSON.stringify(state.cartByTable[tableId] || []));
}

async function fetchExistingOrders() {
  if (!state.selectedTable) return;
  const data = await api("paydesk_items", { tableid: state.selectedTable.id });
  if (data.status === "OK") {
    state.orderExisting = data.msg || [];
  } else {
    state.orderExisting = [];
  }
}

async function fetchNotDelivered() {
  if (!state.selectedTable) return;
  const data = await api("table_notdelivered", { tableid: state.selectedTable.id });
  if (Array.isArray(data)) {
    state.notDelivered = data;
  } else if (data.status === "OK" && Array.isArray(data.msg)) {
    state.notDelivered = data.msg;
  } else {
    state.notDelivered = [];
  }
}

function renderOrderItems() {
  const cart = state.cartByTable[state.selectedTable.id] || [];
  const existing = state.orderExisting || [];
  const parts = [];
  if (els.orderCartSum) {
    const sum = cart.reduce((s, it) => {
      const base = Number(it.price || 0);
      const changed = Number(it.changedPrice || 0);
      const hasChangedPrice = it.changedPrice !== undefined && it.changedPrice !== null && it.changedPrice !== "NO";
      const extrasList = normalizeExtras(it);
      const extrasSum = extrasList.reduce((acc, e) => acc + (Number(e.price || 0) * Number(e.amount || 1)), 0);
      const price = (hasChangedPrice && Math.abs(changed - base) > 0.0001) ? changed : base;
      const qty = Number(it.unitamount || 1);
      return s + ((Number(price || 0) + extrasSum) * qty);
    }, 0);
    els.orderCartSum.textContent = `Summe: ${sum.toFixed(2)} €`;
  }
  const cartGroups = groupCartItems(cart);
  cartGroups.forEach(g => {
  const extraLabels = [];
    normalizeExtras(g.item).forEach(e => extraLabels.push(`+ ${e.name}`));
    if (g.item.togo) extraLabels.push("+ ToGo");
    const base = Number(g.item.price || 0);
    const changed = Number(g.item.changedPrice || 0);
    const hasChangedPrice = g.item.changedPrice !== undefined && g.item.changedPrice !== null && g.item.changedPrice !== "NO";
    const suppressDiscount = g.item.priceEntry || Number(g.item.unit || 0) !== 0;
    if (g.item.discountPct && Number(g.item.discountPct) > 0 && !suppressDiscount) {
      const pct = Number(g.item.discountPct);
      const label = g.item.discountName ? `${g.item.discountName} ${Number.isInteger(pct) ? pct : pct.toFixed(1)}%` : `Rabatt ${Number.isInteger(pct) ? pct : pct.toFixed(1)}%`;
      extraLabels.push(`+ ${label}`);
    } else if (!suppressDiscount && hasChangedPrice && base > 0 && Math.abs(changed - base) > 0.0001) {
      const pct = Math.max(0, Math.round(((base - changed) / base) * 1000) / 10);
      extraLabels.push(`+ Rabatt ${Number.isInteger(pct) ? pct : pct.toFixed(1)}%`);
    }
    const extras = extraLabels.map(t => `<div class="order-extra">${t}</div>`).join("");
    const key = encodeURIComponent(cartKey(g.item));
    parts.push(`
      <div class="order-item new" data-cart="${g.item._id}">
        <div class="order-title"><b>${g.item.name}</b></div>
        ${extras}
        <div class="order-qty" data-key="${key}">
          <button type="button" class="mini" data-act="dec">-</button>
          <span class="order-count">${g.count}</span>
          <button type="button" class="mini" data-act="inc">+</button>
        </div>
      </div>
    `);
  });
  if (cartGroups.length > 0 && existing.length > 0) {
    parts.push(`<div class="order-separator"></div>`);
  }
  const existingGroups = groupExistingItems(existing);
  existingGroups.forEach(g => {
    const extraLabels = [];
    normalizeExtras(g.item).forEach(e => extraLabels.push(`+ ${e.name}`));
    if (isTogo(g.item)) extraLabels.push("+ ToGo");
    const extras = extraLabels.map(t => `<div class="order-extra">${t}</div>`).join("");
    parts.push(`<div class="order-item existing" data-queue="${g.item.id}"><b>${g.item.longname}</b> x${g.count}${extras}</div>`);
  });
  els.orderItems.innerHTML = parts.join("");
  els.orderItems.querySelectorAll(".order-qty button").forEach(btn => {
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const wrap = btn.closest(".order-qty");
      const key = decodeURIComponent(wrap.dataset.key || "");
      if (!key) return;
      const delta = btn.dataset.act === "inc" ? 1 : -1;
      adjustCartGroup(key, delta);
    });
  });
  els.orderItems.querySelectorAll(".order-item.new").forEach(el => {
    el.addEventListener("click", () => {
      const id = Number(el.dataset.cart);
      editCartItem(id);
    });
  });
  els.orderItems.querySelectorAll(".order-item.existing").forEach(el => {
    el.addEventListener("click", () => {
      const id = Number(el.dataset.queue);
      const item = (state.orderExisting || []).find(p => Number(p.id) === id);
      if (item) showExistingItemActions(item);
    });
  });
  scheduleDisplayUpdate();
}

function getMenuBasePrice(prodid) {
  const prod = (state.menu?.prods || []).find(p => Number(p.id) === Number(prodid));
  if (!prod) return 0;
  return Number(String(prod.price || "0").replace(",", ".")) || 0;
}

function matchDiscountName(pct) {
  const candidates = [
    { pct: Number(state.discounts.d1 || 0), name: state.discounts.n1 || "Rabatt 1" },
    { pct: Number(state.discounts.d2 || 0), name: state.discounts.n2 || "Rabatt 2" },
    { pct: Number(state.discounts.d3 || 0), name: state.discounts.n3 || "Rabatt 3" }
  ].filter(c => c.pct > 0);
  let best = null;
  candidates.forEach(c => {
    const diff = Math.abs(c.pct - pct);
    if (diff <= 0.5 && (!best || diff < best.diff)) best = { ...c, diff };
  });
  return best ? best.name : "Rabatt";
}

function adjustCartGroup(key, delta) {
  if (!state.selectedTable) return;
  const cart = state.cartByTable[state.selectedTable.id] || [];
  if (delta > 0) {
    const idx = cart.findIndex(c => cartKey(c) === key);
    if (idx >= 0) {
      cart[idx].unitamount = Number(cart[idx].unitamount || 1) + 1;
      markDisplayActivity();
    }
  } else {
    for (let i = cart.length - 1; i >= 0; i--) {
      if (cartKey(cart[i]) !== key) continue;
      cart[i].unitamount = Number(cart[i].unitamount || 1) - 1;
      if (cart[i].unitamount <= 0) cart.splice(i, 1);
      break;
    }
    markDisplayActivity();
  }
  state.cartByTable[state.selectedTable.id] = cart;
  saveCart(state.selectedTable.id);
  renderOrderItems();
}

function openProductModal(prod) {
  if (!prod) return;
  els.modalTitle.textContent = prod.longname || prod.name;
  if (els.modalQty) els.modalQty.value = 1;
  if (els.modalNote) els.modalNote.value = "";
  const extrasList = (prod.extras || []).map(e => ({
    id: Number(e.extraid || e.id),
    name: e.name,
    price: Number(e.price || 0)
  }));
  state.modalExtrasSelected = [];
  if (extrasList.length === 0) {
    els.modalExtras.innerHTML = "Keine Extras";
  } else {
    els.modalExtras.innerHTML = extrasList.map(e => `
      <button type="button" class="extra-btn" data-id="${e.id}" data-name="${e.name}" data-price="${e.price}">
        ${e.name} (+${e.price})
      </button>
    `).join("");
    els.modalExtras.querySelectorAll(".extra-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = Number(btn.dataset.id);
        const name = btn.dataset.name;
        const price = Number(btn.dataset.price || 0);
        if (extrasList.length === 1 && state.localConfig?.singleExtraImmediate) {
          addToCart(prod, sanitizeExtras([{ id, name, price, amount: 1 }]), "", 1);
          els.productModal.classList.add("hidden");
          renderOrderItems();
          return;
        }
        if (btn.classList.contains("active")) {
          btn.classList.remove("active");
          state.modalExtrasSelected = state.modalExtrasSelected.filter(x => x.id !== id);
        } else {
          btn.classList.add("active");
          state.modalExtrasSelected.push({ id, name, price, amount: 1 });
        }
      });
    });
  }
  els.productModal.classList.remove("hidden");
  state.modalProduct = prod;
}

function openPriceModal(prod) {
  if (!prod) return;
  state.priceEntry = { prod, raw: "", negative: false };
  if (els.priceTitle) els.priceTitle.textContent = prod.longname || prod.name;
  updatePriceModalDisplay();
  window.priceKeyPress = handlePriceKey;
  els.priceModal.classList.remove("hidden");
}

function parsePriceEntry(entry) {
  if (!entry || !entry.raw) return 0;
  const normalized = entry.raw.replace(",", ".");
  const value = Number(normalized);
  if (!Number.isFinite(value)) return 0;
  return entry.negative ? -Math.abs(value) : value;
}

function updatePriceModalDisplay() {
  if (!state.priceEntry || !els.priceValue) return;
  const raw = state.priceEntry.raw || "";
  const sign = state.priceEntry.negative ? "-" : "";
  const display = raw ? `${sign}${raw}` : "0";
  els.priceValue.textContent = display;
}

function handlePriceKey(label, e) {
  if (e && e.preventDefault) e.preventDefault();
  if (!state.priceEntry) return;
  if (label === "Back") {
    state.priceEntry.raw = state.priceEntry.raw.slice(0, -1);
  } else if (label === "-") {
    state.priceEntry.negative = !state.priceEntry.negative;
  } else if (label === ",") {
    if (!state.priceEntry.raw.includes(",")) {
      state.priceEntry.raw = state.priceEntry.raw ? `${state.priceEntry.raw},` : "0,";
    }
  } else if (/^\d$/.test(label)) {
    const raw = state.priceEntry.raw;
    if (raw.includes(",")) {
      const [left, right] = raw.split(",");
      if (right.length >= 2) {
        updatePriceModalDisplay();
        return;
      }
      state.priceEntry.raw = `${left},${right}${label}`;
    } else if (raw.length < 9) {
      state.priceEntry.raw += label;
    }
  }
  updatePriceModalDisplay();
}

function quickAddProduct(prod) {
  addToCart(prod, [], "", 1);
}

function addProductToCart() {
  const prod = state.modalProduct;
  if (!prod) return;
  const qty = 1;
  const extras = Array.isArray(state.modalExtrasSelected) ? state.modalExtrasSelected : [];
  addToCart(prod, sanitizeExtras(extras), "", qty);
  els.productModal.classList.add("hidden");
  renderOrderItems();
}

function addToCart(prod, extras, option, qty, forceTogo) {
  const tableId = state.selectedTable.id;
  const nowTs = Date.now();
  const item = {
    _id: Date.now(),
    prodid: prod.id,
    name: prod.longname || prod.name,
    price: Number(prod.price || 0),
    unit: Number(prod.unit || 0),
    unitamount: qty,
    togo: typeof forceTogo === "number" ? forceTogo : (state.selectedTable?.id === 0 ? 1 : 0),
    option: option || "",
    extras: sanitizeExtras(extras),
    ts: nowTs
  };
  state.cartByTable[tableId] = state.cartByTable[tableId] || [];
  const cart = state.cartByTable[tableId];
  cart.push(item);
  markDisplayActivity();
  saveCart(tableId);
  renderOrderItems();
}

function addToCartCustom(prod, extras, option, qty, togo, changedPrice, priceEntryFlag) {
  const tableId = state.selectedTable.id;
  const nowTs = Date.now();
  let normalizedChanged = changedPrice || "NO";
  const base = Number(prod.price || 0);
  const cp = Number(normalizedChanged);
  if (Number.isFinite(cp) && Math.abs(cp - base) <= 0.0001) {
    normalizedChanged = "NO";
  }
  const item = {
    _id: Date.now(),
    prodid: prod.id,
    name: prod.longname || prod.name,
    price: Number(prod.price || 0),
    unit: Number(prod.unit || 0),
    unitamount: qty,
    togo: togo ? 1 : 0,
    option: option || "",
    extras: sanitizeExtras(extras),
    changedPrice: normalizedChanged,
    priceEntry: Boolean(priceEntryFlag),
    ts: nowTs
  };
  state.cartByTable[tableId] = state.cartByTable[tableId] || [];
  const cart = state.cartByTable[tableId];
  cart.push(item);
  markDisplayActivity();
  saveCart(tableId);
  renderOrderItems();
}

function resetClientState() {
  try {
    Object.keys(localStorage).forEach(k => {
      if (k.startsWith("cart_")) localStorage.removeItem(k);
    });
  } catch (_) {}
  state.user = null;
  state.config = null;
  state.menu = null;
  state.rooms = null;
  state.selectedTable = null;
  state.selectedType = null;
  state.typeStack = [];
  state.cartByTable = {};
  state.orderExisting = [];
  state.payments = [];
  state.paydeskItems = [];
  state.paydeskOpen = [];
  state.paydeskReceipt = [];
  state.paydeskTable = null;
  state.notDelivered = [];
  state.lastSync = "-";
  if (els.startMessageBody) {
    els.startMessageBody.textContent = "Bereit.";
  }
}

function cartKey(item) {
  const extras = (item.extras || []).map(e => `${e.id}:${e.amount || 1}`).sort().join("|");
  const changed = Number(item.changedPrice);
  const priceKey = Number.isFinite(changed) ? `cp:${changed.toFixed(2)}` : "cp:-";
  return [item.prodid, item.option || "", item.togo ? 1 : 0, priceKey, extras].join("#");
}

function normalizeExtras(item) {
  const extrasById = new Map((state.menu?.extras || []).map(e => [Number(e.id), { id: Number(e.id), name: e.name, price: Number(e.price || 0) }]));
  const extrasByName = new Map((state.menu?.extras || []).map(e => [String(e.name || "").toLowerCase(), { id: Number(e.id), name: e.name, price: Number(e.price || 0) }]));
  if (Array.isArray(item.extras) && item.extras.length > 0) {
    const byId = new Map((state.menu?.extras || []).map(e => [Number(e.id), e.name]));
    if (typeof item.extras[0] === "string") {
      return item.extras.map(s => {
        const m = String(s).match(/^\\s*(\\d+)\\s*x\\s*(.+)\\s*$/i);
        if (m) {
          const name = m[2].trim();
          const info = extrasByName.get(name.toLowerCase());
          const infoId = info && info.id != null ? info.id : null;
          const infoName = info && info.name ? info.name : null;
          const infoPrice = info && info.price ? info.price : 0;
          return { id: infoId !== null ? infoId : name, amount: Number(m[1]) || 1, name: infoName || name, price: infoPrice || 0 };
        }
        const name = String(s).trim();
        const info = extrasByName.get(name.toLowerCase());
        const infoId = info && info.id != null ? info.id : null;
        const infoName = info && info.name ? info.name : null;
        const infoPrice = info && info.price ? info.price : 0;
        return { id: (infoId !== null ? infoId : name) || "extra", amount: 1, name: infoName || name || "Extra", price: infoPrice || 0 };
      });
    }
    return item.extras.map(e => {
      const rawId = e.id != null ? e.id : e.extraid;
      const nameLookup = e.name ? extrasByName.get(String(e.name).toLowerCase()) : null;
      let id = rawId;
      if (id === undefined || id === null) {
        if (nameLookup && nameLookup.id != null) id = nameLookup.id;
        else id = e.name || "extra";
      }
      const name = e.name || byId.get(Number(rawId)) || (nameLookup && nameLookup.name) || (rawId ? `Extra ${rawId}` : "Extra");
      const info = extrasById.get(Number(rawId)) || nameLookup;
      return {
        id,
        amount: Number(e.amount || 1),
        name,
        price: info && info.price ? info.price : 0
      };
    });
  }
  const parseList = (val) => {
    if (Array.isArray(val)) return val;
    if (typeof val === "string") {
      return val.split(/[;,|]/).map(v => v.trim()).filter(Boolean);
    }
    return [];
  };
  const ids = parseList(item.extrasids);
  const amounts = parseList(item.extrasamounts);
  if (ids.length === 0) return [];
  const byId = new Map((state.menu?.extras || []).map(e => [Number(e.id), e.name]));
  return ids.map((id, idx) => ({
    id: Number(id),
    amount: Number(amounts[idx] || 1),
    name: byId.get(Number(id)) || `Extra ${id}`,
    price: Number((state.menu?.extras || []).find(e => Number(e.id) === Number(id))?.price || 0)
  }));
}

function sanitizeExtras(extras) {
  return (extras || [])
    .map(e => ({
      id: Number(e.id ?? e.extraid),
      name: e.name || "",
      price: Number(e.price || 0),
      amount: Number(e.amount || 1)
    }))
    .filter(e => Number.isFinite(e.id) && e.id > 0);
}

function mapExtrasToMenuIds(extras) {
  const norm = (v) => String(v || "").toLowerCase().replace(/\s+/g, " ").trim();
  const byName = new Map((state.menu?.extras || []).map(e => [norm(e.name), Number(e.id)]));
  return (extras || []).map(e => {
    let id = Number(e.id ?? e.extraid);
    if (!Number.isFinite(id)) {
      const mapped = byName.get(norm(e.name));
      if (Number.isFinite(mapped)) id = mapped;
    }
    return { ...e, id };
  });
}

function displayPriceLevel(name) {
  if (!name) return "";
  const trimmed = String(name).trim();
  if (trimmed === "A" || trimmed === "1") return state.discounts.n1 || trimmed;
  if (trimmed === "B" || trimmed === "2") return state.discounts.n2 || trimmed;
  if (trimmed === "C" || trimmed === "3") return state.discounts.n3 || trimmed;
  return trimmed;
}

function isTogo(item) {
  const v = item?.togo;
  if (v === true) return true;
  if (v === "true" || v === "yes") return true;
  return Number(v || 0) === 1;
}

function shouldShowPriceLevel(item) {
  if (!item || !item.pricelevelname) return false;
  const prod = state.menu?.prods?.find(p => Number(p.id) === Number(item.prodid));
  const unitVal = Number(item.unit || (prod ? prod.unit : 0) || 0);
  if (unitVal !== 0) return false;
  const trimmed = String(item.pricelevelname).trim();
  if (!prod) {
    return !["A", "B", "C", "1", "2", "3"].includes(trimmed);
  }
  const base = Number(prod.price || 0);
  const extrasList = normalizeExtras(item);
  const extrasSum = extrasList.reduce((sum, e) => sum + Number(e.price || 0) * Number(e.amount || 1), 0);
  const baseWithExtras = base + extrasSum;
  const price = Number(item.price || 0);
  if (["A", "B", "C", "1", "2", "3"].includes(trimmed) && extrasList.length > 0) {
    return false;
  }
  return Math.abs(baseWithExtras - price) > 0.0001;
}

function existingKey(item) {
  const extras = normalizeExtras(item).map(e => `${e.id}:${e.amount || 1}`).sort().join("|");
  const price = Number(item.price || 0);
  const priceKey = Number.isFinite(price) ? price.toFixed(2) : "";
  const level = item.pricelevelname || "";
  return [item.prodid, item.orderoption || "", isTogo(item) ? 1 : 0, priceKey, level, extras].join("#");
}

function existingKeyLoose(item) {
  const extras = normalizeExtras(item).map(e => `${e.id}:${e.amount || 1}`).sort().join("|");
  const price = Number(item.price || 0);
  const priceKey = Number.isFinite(price) ? price.toFixed(2) : "";
  return [item.prodid, item.orderoption || "", item.togo ? 1 : 0, priceKey, "", extras].join("#");
}

function groupCartItems(items) {
  const sorted = items.slice().sort((a, b) => (b.ts || 0) - (a.ts || 0));
  const groups = [];
  sorted.forEach((it, idx) => {
    const key = cartKey(it);
    const last = groups[groups.length - 1];
    if (last && last.key === key) {
      last.count += Number(it.unitamount || 1);
    } else {
      groups.push({ key, item: it, count: Number(it.unitamount || 1), first: idx });
    }
  });
  return groups;
}

function groupExistingItems(items) {
  const groups = new Map();
  items.forEach((it, idx) => {
    const key = existingKey(it);
    if (!groups.has(key)) {
      groups.set(key, { key, item: it, count: 0, first: idx });
    }
    const g = groups.get(key);
    g.count += Number(it.unitamount || 1);
  });
  return Array.from(groups.values()).sort((a, b) => {
    const an = String(a.item.longname || a.item.name || "").toLowerCase();
    const bn = String(b.item.longname || b.item.name || "").toLowerCase();
    if (an < bn) return -1;
    if (an > bn) return 1;
    return a.first - b.first;
  });
}

function resetConfirmActionsLayout() {
  els.confirmActions.classList.remove("actions-3");
}

function showExistingItemActions(item) {
  resetConfirmActionsLayout();
  markDisplayActivity();
  els.confirmActions.classList.add("actions-3");
  const key = existingKey(item);
  const groupCount = (state.orderExisting || []).filter(p => existingKey(p) === key).reduce((s, p) => s + Number(p.unitamount || 1), 0);
  const titleExtras = [];
  normalizeExtras(item).forEach(e => titleExtras.push(`+ ${e.name}`));
  if (isTogo(item)) titleExtras.push("+ ToGo");
  if (item.orderoption) titleExtras.push(`+ ${item.orderoption}`);
  els.confirmTitle.textContent = `${item.longname} (${groupCount})`;
  const codeField = state.cancelUnpaidCode ? `<input type="text" id="storno-code" class="storno-code" placeholder="Stornocode" />` : "";
  els.confirmBody.innerHTML = `
    ${titleExtras.length ? `<div class="edit-row">${titleExtras.join("<br>")}</div>` : ""}
    <div class="edit-row"><b>Anzahl</b></div>
    <div class="edit-qty-row">
      <div class="edit-qty compact">
      <button class="ghost" id="qty-dec">-1</button>
      <input type="number" id="qty-val" class="qty-small" value="1" min="1" max="99" />
      <button class="ghost" id="qty-inc">+1</button>
      </div>
      ${codeField}
    </div>
  `;
  els.confirmActions.innerHTML = `
    <button class="ghost confirm-action" id="cancel">Abbrechen</button>
    <button class="ghost confirm-action" id="remove">Entfernen</button>
  `;
  els.confirmModal.classList.remove("hidden");

  const qtyVal = els.confirmBody.querySelector("#qty-val");
  const removeBtn = els.confirmActions.querySelector("#remove");
  const updateQtyButtons = () => {
    const q = Number(qtyVal.value || 1);
    els.confirmBody.querySelector("#qty-dec").disabled = q <= 1;
    els.confirmBody.querySelector("#qty-inc").disabled = q >= 99;
    removeBtn.disabled = q > groupCount;
  };
  updateQtyButtons();
  els.confirmBody.querySelector("#qty-dec").onclick = () => { qtyVal.value = Math.max(1, Number(qtyVal.value) - 1); updateQtyButtons(); };
  els.confirmBody.querySelector("#qty-inc").onclick = () => { qtyVal.value = Math.min(99, Number(qtyVal.value) + 1); updateQtyButtons(); };
  qtyVal.oninput = updateQtyButtons;

  els.confirmActions.querySelector("#cancel").onclick = () => {
    els.confirmModal.classList.add("hidden");
  };
  els.confirmActions.querySelector("#remove").onclick = async () => {
    const qty = Math.max(1, Math.min(groupCount, Number(qtyVal.value || 1)));
    if (state.cancelUnpaidCode) {
      const codeVal = els.confirmBody.querySelector("#storno-code")?.value || "";
      if (codeVal !== state.cancelUnpaidCode) {
        alert("Stornocode falsch");
        return;
      }
    }
    const keyLoose = existingKeyLoose(item);
    const source = state.orderExisting || [];
    const candidates = source.filter(p => {
      const k = existingKey({
        prodid: p.prodid,
        orderoption: p.orderoption,
        togo: p.togo,
        price: p.price,
        pricelevelname: p.pricelevelname || "",
        extras: p.extras
      });
      if (k === key || k === keyLoose) return true;
      const kl = existingKeyLoose({
        prodid: p.prodid,
        orderoption: p.orderoption,
        togo: p.togo,
        price: p.price,
        extras: p.extras
      });
      return kl === key || kl === keyLoose;
    });
    if (candidates.length === 0 && item.id) {
      await api("remove_product", { queueid: item.id, isPaid: item.isPaid, isCooking: item.isCooking, isReady: item.isready });
      await fetchNotDelivered();
      await fetchExistingOrders();
      renderOrderItems();
      els.confirmModal.classList.add("hidden");
      return;
    }
    for (let i = 0; i < Math.min(qty, candidates.length); i++) {
      const c = candidates[i];
      await api("remove_product", {
        queueid: c.id,
        isPaid: c.isPaid || c.ispaid || 0,
        isCooking: c.isCooking || c.iscooking || 0,
        isReady: c.isready || c.isReady || 0
      });
    }
    await fetchNotDelivered();
    await fetchExistingOrders();
    renderOrderItems();
    els.confirmModal.classList.add("hidden");
  };
}

function editCartItem(id) {
  resetConfirmActionsLayout();
  const cart = state.cartByTable[state.selectedTable.id] || [];
  const item = cart.find(c => c._id === id);
  if (!item) return;
  markDisplayActivity();
  const groupCount = cart.filter(c => cartKey(c) === cartKey(item)).reduce((sum, c) => sum + Number(c.unitamount || 1), 0);
  const basePrice = Number(item.price || 0);
  const existingChanged = item.changedPrice !== undefined && item.changedPrice !== null && item.changedPrice !== "NO";
  const disc1 = state.discounts.d1;
  const disc2 = state.discounts.d2;
  const disc3 = state.discounts.d3;
  const discName1 = state.discounts.n1;
  const discName2 = state.discounts.n2;
  const discName3 = state.discounts.n3;
  const formatPct = (v) => (Number.isInteger(v) ? String(v) : v.toFixed(2));

  els.confirmTitle.textContent = item.name;
  els.confirmBody.innerHTML = `
    <div class="edit-row"><b>${item.name}</b> (aktuell: ${groupCount})</div>
    <div class="edit-row"><b>Einzelpreis</b></div>
    <div id="price-val" class="price-display">${basePrice.toFixed(2)}</div>
    <div class="edit-row togo-line">
      <button type="button" class="ghost togo-btn" id="act-togo">${item.togo ? "ToGo ✓" : "ToGo"}</button>
    </div>
    <div class="edit-row"><b>Aktion</b></div>
    <div class="edit-actions">
      <button type="button" class="ghost disc-btn" id="disc1">${discName1} ${formatPct(disc1)}%</button>
      <button type="button" class="ghost disc-btn" id="disc2">${discName2} ${formatPct(disc2)}%</button>
      <button type="button" class="ghost disc-btn" id="disc3">${discName3} ${formatPct(disc3)}%</button>
    </div>
    <div class="edit-qty">
      <button class="ghost" id="qty-dec">-1</button>
      <input type="number" id="qty-val" value="1" min="1" max="${groupCount}" />
      <button class="ghost" id="qty-inc">+1</button>
      <span class="edit-qty-max">/ ${groupCount}</span>
    </div>
    <div class="edit-row"><b>Notiz</b></div>
    <input type="text" id="note-val" value="${item.option || ""}" />
  `;
  els.confirmActions.innerHTML = `
    <button class="ghost" id="cancel">Abbruch</button>
    <button class="primary" id="apply">Ändern</button>
  `;
  els.confirmModal.classList.remove("hidden");

  const qtyVal = els.confirmBody.querySelector("#qty-val");
  const updateQtyButtons = () => {
    const q = Number(qtyVal.value || 1);
    els.confirmBody.querySelector("#qty-dec").disabled = q <= 1;
    els.confirmBody.querySelector("#qty-inc").disabled = q >= groupCount;
  };
  updateQtyButtons();
  els.confirmBody.querySelector("#qty-dec").onclick = () => { qtyVal.value = Math.max(1, Number(qtyVal.value) - 1); updateQtyButtons(); };
  els.confirmBody.querySelector("#qty-inc").onclick = () => { qtyVal.value = Math.min(groupCount, Number(qtyVal.value) + 1); updateQtyButtons(); };

  const priceVal = els.confirmBody.querySelector("#price-val");
  let currentPrice = basePrice;
  let selectedDiscountPct = null;
  let selectedDiscountName = "";
  if (item.discountPct) {
    selectedDiscountPct = Number(item.discountPct);
    selectedDiscountName = item.discountName || "";
  } else if (existingChanged && basePrice > 0) {
    const changed = Number(item.changedPrice || 0);
    const pct = Math.max(0, Math.round(((basePrice - changed) / basePrice) * 1000) / 10);
    if (pct > 0) {
      selectedDiscountPct = pct;
      selectedDiscountName = matchDiscountName(pct);
    }
  }
  const setPrice = (val) => {
    currentPrice = Number(val);
    priceVal.textContent = currentPrice.toFixed(2);
  };
  if (existingChanged) {
    const val = Number(item.changedPrice || 0);
    if (!Number.isNaN(val)) setPrice(val);
  }
  const applyDiscount = (pct, name) => {
    if (selectedDiscountPct === pct) {
      selectedDiscountPct = null;
      selectedDiscountName = "";
      setPrice(basePrice);
      els.confirmBody.querySelectorAll(".disc-btn").forEach(b => b.classList.remove("active"));
      return;
    }
    setPrice(basePrice - basePrice * pct / 100);
    selectedDiscountPct = pct;
    selectedDiscountName = name || "";
    els.confirmBody.querySelectorAll(".disc-btn").forEach(b => b.classList.remove("active"));
    const btn = els.confirmBody.querySelector(`#disc${pct === disc1 ? "1" : pct === disc2 ? "2" : "3"}`);
    if (btn) btn.classList.add("active");
  };
  els.confirmBody.querySelector("#disc1").onclick = (e) => { e.preventDefault(); applyDiscount(disc1, discName1); };
  els.confirmBody.querySelector("#disc2").onclick = (e) => { e.preventDefault(); applyDiscount(disc2, discName2); };
  els.confirmBody.querySelector("#disc3").onclick = (e) => { e.preventDefault(); applyDiscount(disc3, discName3); };
  if (selectedDiscountPct) {
    let btn = null;
    if (Math.abs(selectedDiscountPct - disc1) <= 0.01) btn = els.confirmBody.querySelector("#disc1");
    else if (Math.abs(selectedDiscountPct - disc2) <= 0.01) btn = els.confirmBody.querySelector("#disc2");
    else if (Math.abs(selectedDiscountPct - disc3) <= 0.01) btn = els.confirmBody.querySelector("#disc3");
    if (btn) btn.classList.add("active");
  }

  let togoVal = item.togo ? 1 : 0;
  els.confirmBody.querySelector("#act-togo").onclick = () => {
    togoVal = togoVal ? 0 : 1;
    els.confirmBody.querySelector("#act-togo").textContent = togoVal ? "ToGo ✓" : "ToGo";
  };

  els.confirmActions.querySelector("#cancel").onclick = () => els.confirmModal.classList.add("hidden");
  els.confirmActions.querySelector("#apply").onclick = () => {
    const qty = Math.max(1, Math.min(groupCount, Number(qtyVal.value || 1)));
    const newNote = els.confirmBody.querySelector("#note-val").value.trim();
    const newPrice = Number.isFinite(currentPrice) ? Number(currentPrice) : Number(basePrice || 0);
    const changedPrice = Math.abs(newPrice - Number(item.price || 0)) > 0.0001 ? newPrice.toFixed(2) : "NO";
    const discountPct = selectedDiscountPct ? Number(selectedDiscountPct) : null;
    const discountName = discountPct ? (selectedDiscountName || "") : "";
    const newKey = cartKey({ ...item, option: newNote, togo: togoVal, changedPrice });
    const currentKey = cartKey(item);
    const hasChanges = newKey !== currentKey;

    if (qty < groupCount) {
      // reduce original group by qty, create new item for changed subset
      let remaining = qty;
      for (let i = 0; i < cart.length && remaining > 0; i++) {
        if (cartKey(cart[i]) !== currentKey) continue;
        const take = Math.min(remaining, cart[i].unitamount);
        cart[i].unitamount -= take;
        remaining -= take;
        if (cart[i].unitamount <= 0) {
          cart.splice(i, 1);
          i--;
        }
      }
      const newItem = {
        ...item,
        _id: Date.now(),
        unitamount: qty,
        option: newNote,
        togo: togoVal,
        changedPrice,
        discountPct,
        discountName,
        ts: Date.now()
      };
      cart.unshift(newItem);
    } else if (hasChanges) {
      // replace entire group and move to top
      let total = 0;
      for (let i = cart.length - 1; i >= 0; i--) {
        if (cartKey(cart[i]) !== currentKey) continue;
        total += Number(cart[i].unitamount || 1);
        cart.splice(i, 1);
      }
      const newItem = {
        ...item,
        _id: Date.now(),
        unitamount: total,
        option: newNote,
        togo: togoVal,
        changedPrice,
        discountPct,
        discountName,
        ts: Date.now()
      };
      cart.unshift(newItem);
    }
    markDisplayActivity();
    state.cartByTable[state.selectedTable.id] = cart;
    saveCart(state.selectedTable.id);
    renderOrderItems();
    els.confirmModal.classList.add("hidden");
  };
}

async function handleMenuAction(action, btn) {
  if (action === "to-go") {
    openOrderForTable({ id: 0, name: "To-Go" });
  } else if (action === "paydesk") {
    const fromOrder = btn && btn.closest("#order-screen");
    const fromStart = btn && btn.closest("#start-screen");
    if (fromOrder && state.selectedTable) {
      const cart = state.cartByTable[state.selectedTable.id] || [];
      if (cart.length > 0) {
        await sendOrder(true, false);
      }
      await openPaydesk(state.selectedTable);
    } else if (fromStart) {
      await openPaydeskPicker();
    } else {
      await openPaydeskPicker();
    }
  } else if (action === "menu") {
    await openMenuModal();
  } else if (action === "logout") {
    if (state.selectedTable) {
      const cart = state.cartByTable[state.selectedTable.id] || [];
      if (cart.length > 0) {
        showUnsavedDialog(async () => {
          await doLogout();
        });
        return;
      }
    }
    await doLogout();
  } else if (action === "order") {
    if (state.paydeskTable) {
      openOrderForTable({ id: state.paydeskTable.id, name: state.paydeskTable.name });
    }
  } else if (action === "start") {
    if (state.selectedTable) {
      const cart = state.cartByTable[state.selectedTable.id] || [];
      if (cart.length > 0) {
        showUnsavedDialog();
        return;
      }
    }
    state.typeStack = [];
    state.selectedType = topLevelTypes()[0]?.id || null;
    renderCategories();
    show(els.startScreen);
  } else if (action === "send") {
    if (state.selectedTable) {
      const cart = state.cartByTable[state.selectedTable.id] || [];
      if (cart.length === 0) {
        state.typeStack = [];
        state.selectedType = topLevelTypes()[0]?.id || null;
        renderCategories();
        show(els.startScreen);
        return;
      }
    }
    await sendOrder(true, true);
  } else if (action === "workprint") {
    await sendOrder(true, false);
  } else if (action === "changetable") {
    await changeTableFlow();
  }
}

async function sendOrder(workprint, goStart) {
  const table = state.selectedTable;
  if (!table) return;
  const cart = state.cartByTable[table.id] || [];
  if (cart.length === 0) return;
  const prods = cart.map(c => ({
    name: c.name,
    option: c.option || "",
    extras: sanitizeExtras(c.extras).map(e => ({ id: e.id, name: e.name, amount: e.amount })),
    prodid: c.prodid,
    price: c.price,
    changedPrice: c.changedPrice || "NO",
    togo: c.togo,
    unit: c.unit,
    unitamount: c.unitamount,
    phase: 0,
    isminusarticle: 0
  }));
  const res = await api("order", { tableid: table.id, prods, print: workprint ? 1 : 0, payprinttype: "s", orderoption: "" });
  if (res.status === "OK") {
    state.cartByTable[table.id] = [];
    saveCart(table.id);
    await fetchExistingOrders();
    renderOrderItems();
    state.lastSync = new Date().toLocaleTimeString();
    updateStatus();
    if (goStart) {
      setStartMessage(`Tisch ${table.name}: Bestellung abgeschickt`, cart);
      show(els.startScreen);
    }
  } else {
    const msg = res.msg || "Bestellung fehlgeschlagen";
    if (goStart) {
      setStartMessage(`Fehler: ${msg}`, []);
      show(els.startScreen);
    }
    alert(msg);
  }
}

function scheduleDisplayUpdate() {
  if (!state.brokerWs || state.brokerWs.readyState !== state.brokerWs.OPEN) return;
  if (state.displayUpdateTimer) clearTimeout(state.displayUpdateTimer);
  state.displayUpdateTimer = setTimeout(() => {
    sendDisplaySnapshot();
  }, 150);
}

function sendDisplaySnapshot() {
  if (!state.brokerWs || state.brokerWs.readyState !== state.brokerWs.OPEN) return;
  const activity = state.displayActivity ? "product" : null;
  state.displayActivity = false;
  if (els.paydeskScreen && !els.paydeskScreen.classList.contains("hidden")) {
    const payload = buildDisplayPaydesk();
    if (!payload) return;
    state.brokerWs.send(JSON.stringify({ type: "DISPLAY_UPDATE", mode: "paydesk", payload, activity }));
    return;
  }
  if (els.orderScreen && !els.orderScreen.classList.contains("hidden")) {
    const payload = buildDisplayOrder();
    if (!payload) return;
    if (payload.items.length === 0) {
      sendDisplayIdle();
    } else {
      state.brokerWs.send(JSON.stringify({ type: "DISPLAY_UPDATE", mode: "order", payload, activity }));
    }
  }
}

function buildDisplayOrder() {
  if (!state.selectedTable) return null;
  const cart = state.cartByTable[state.selectedTable.id] || [];
  const groups = groupCartItems(cart);
  const items = groups.map(g => {
    const base = Number(g.item.price || 0);
    const changed = Number(g.item.changedPrice || 0);
    const hasChangedPrice = g.item.changedPrice !== undefined && g.item.changedPrice !== null && g.item.changedPrice !== "NO";
    const extrasList = normalizeExtras(g.item);
    const extrasSum = extrasList.reduce((s, e) => s + (Number(e.price || 0) * Number(e.amount || 1)), 0);
    const price = (hasChangedPrice && Math.abs(changed - base) > 0.0001) ? changed : base;
    const unit = Number(price || 0) + extrasSum;
    return {
      name: g.item.name,
      qty: g.count,
      price: Number(unit || 0),
      extras: extrasList.map(e => `${e.amount || 1}x ${e.name}`)
    };
  });
  const sum = items.reduce((s, i) => s + (i.price * i.qty), 0);
  return { items, sum: sum.toFixed(2) };
}

function buildDisplayPaydesk() {
  const receiptItems = (state.paydeskReceipt || []).slice().sort((a, b) => {
    const sa = Number(a._seq || 0);
    const sb = Number(b._seq || 0);
    return sb - sa;
  });
  const receiptGroups = groupPaydeskItemsAdjacent(receiptItems);
  const openGroups = groupPaydeskItems(state.paydeskOpen || []);
  const bonItems = receiptGroups.map(g => ({
    name: g.item.longname,
    qty: g.count,
    price: Number(g.item.price || 0),
    extras: normalizeExtras(g.item).map(e => ({ name: e.name, amount: Number(e.amount || 1), price: Number(e.price || 0) })),
    extrasSum: normalizeExtras(g.item).reduce((s, e) => s + (Number(e.price || 0) * Number(e.amount || 1)), 0)
  }));
  const openItems = openGroups.map(g => ({
    name: g.item.longname,
    qty: g.count
  }));
  const sum = bonItems.reduce((s, i) => s + ((Number(i.price || 0) + Number(i.extrasSum || 0)) * i.qty), 0);
  return { bonItems, openItems, sum: sum.toFixed(2) };
}

function groupPaydeskItemsAdjacent(items) {
  const groups = [];
  let lastKey = null;
  let current = null;
  items.forEach((it) => {
    const key = paydeskKey(it);
    if (lastKey === key && current) {
      current.count += 1;
    } else {
      current = { key, item: it, count: 1 };
      groups.push(current);
      lastKey = key;
    }
  });
  return groups;
}

function sendDisplayIdle() {
  if (!state.brokerWs || state.brokerWs.readyState !== state.brokerWs.OPEN) return;
  state.brokerWs.send(JSON.stringify({ type: "DISPLAY_IDLE" }));
}

function sendDisplayEbon(ebonUrl, ebonRef) {
  if (!state.brokerWs || state.brokerWs.readyState !== state.brokerWs.OPEN) return;
  state.brokerWs.send(JSON.stringify({ type: "DISPLAY_EBON", ebonUrl, ebonRef }));
}

async function doLogout() {
  await api("logout", {});
  resetClientState();
  show(els.loginScreen);
}

function showUnsavedDialog(onDone) {
  resetConfirmActionsLayout();
  els.confirmTitle.textContent = "Offene Bestellung";
  els.confirmBody.innerHTML = "Was soll mit den offenen Produkten passieren?";
  els.confirmActions.innerHTML = `
    <button class="ghost" id="discard">Verwerfen</button>
    <button class="ghost" id="assign">Zu anderem Tisch</button>
    <button class="primary" id="send">Abschicken</button>
  `;
  els.confirmModal.classList.remove("hidden");
  els.confirmActions.querySelector("#discard").onclick = async () => {
    state.cartByTable[state.selectedTable.id] = [];
    saveCart(state.selectedTable.id);
    els.confirmModal.classList.add("hidden");
    if (onDone) {
      await onDone();
      return;
    }
    state.typeStack = [];
    state.selectedType = topLevelTypes()[0]?.id || null;
    renderCategories();
    show(els.startScreen);
  };
  els.confirmActions.querySelector("#send").onclick = async () => {
    await sendOrder(true, !onDone);
    els.confirmModal.classList.add("hidden");
    if (onDone) {
      await onDone();
    }
  };
  els.confirmActions.querySelector("#assign").onclick = () => {
    els.confirmBody.innerHTML = "Ziel‑Tisch wählen";
    const list = (state.rooms?.roomstables || []).flatMap(r => r.tables).map(t => `<button class="ghost" data-id="${t.id}">${t.name}</button>`).join("");
    els.confirmActions.innerHTML = list;
    els.confirmActions.querySelectorAll("button").forEach(b => {
      b.onclick = async () => {
        const to = Number(b.dataset.id);
        state.cartByTable[to] = (state.cartByTable[to] || []).concat(state.cartByTable[state.selectedTable.id]);
        saveCart(to);
        state.cartByTable[state.selectedTable.id] = [];
        saveCart(state.selectedTable.id);
        els.confirmModal.classList.add("hidden");
        if (onDone) {
          await onDone();
          return;
        }
        openOrderForTable({ id: to, name: b.textContent });
      };
    });
  };
}

async function openPaydesk(table) {
  show(els.paydeskScreen);
  state.paydeskReceipt = [];
  if (table) {
    state.paydeskMode = "table";
    await selectPaydeskTable(table.id, table.name);
  } else {
    state.paydeskMode = "list";
    await openPaydeskPicker();
  }
  await loadPayments();
}

async function loadPaydeskTables() {
  const data = await api("refresh_tables", {});
  if (data.status !== "OK") return;
  state.rooms = data.rooms;
  const tables = [];
  (state.rooms.roomstables || []).forEach(r => r.tables.forEach(t => { if (Number(t.unpaidprodcount || 0) > 0) tables.push(t); }));
  if (Number(state.rooms.takeawayunpaidprodcount || 0) > 0) tables.push({ id: 0, name: "To-Go" });
  if (els.paydeskTables) {
    els.paydeskTables.innerHTML = tables.map(t => `<div class="paydesk-table" data-id="${t.id}">${t.name}</div>`).join("");
    els.paydeskTables.querySelectorAll(".paydesk-table").forEach(el => {
      el.onclick = () => selectPaydeskTable(Number(el.dataset.id), el.textContent);
    });
  }
}

async function selectPaydeskTable(id, name) {
  state.paydeskTable = { id, name };
  els.paydeskTableLabel.textContent = name;
  if (els.paydeskTableName) els.paydeskTableName.textContent = name;
  if (els.paydeskOpenTitle) els.paydeskOpenTitle.textContent = name;
  const data = await api("paydesk_items", { tableid: id });
  if (data.status === "OK") {
    state.paydeskOpen = data.msg || [];
    state.paydeskReceipt = [];
    renderPaydeskItems();
  }
}

function renderPaydeskItems() {
  const open = state.paydeskOpen || [];
  const receipt = state.paydeskReceipt || [];
  const openGroups = groupPaydeskItems(open);
  const receiptGroups = groupPaydeskItems(receipt);
  let total = 0;
  const hasReceipt = receipt.length > 0;
  els.paydeskOpen.innerHTML = openGroups.map(g => {
    const qty = g.count;
    const line = Number(g.item.price || 0) * qty;
    const tags = [];
    if (isTogo(g.item)) tags.push("[ToGo]");
    normalizeExtras(g.item).forEach(e => tags.push(`+ ${e.amount || 1} ${e.name}`));
    const label = `${g.item.longname}${tags.length ? " " + tags.join(" ") : ""}`;
    return `<div class="paydesk-item open" data-key="${g.key}">${label} x${qty} - ${line.toFixed(2)}</div>`;
  }).join("");
  els.paydeskReceipt.innerHTML = receiptGroups.map(g => {
    const qty = g.count;
    const line = Number(g.item.price || 0) * qty;
    total += line;
    const tags = [];
    if (isTogo(g.item)) tags.push("[ToGo]");
    normalizeExtras(g.item).forEach(e => tags.push(`+ ${e.amount || 1} ${e.name}`));
    const label = `${g.item.longname}${tags.length ? " " + tags.join(" ") : ""}`;
    return `<div class="paydesk-item receipt" data-key="${g.key}">${label} x${qty} - ${line.toFixed(2)}</div>`;
  }).join("");
  els.paydeskTotal.textContent = total.toFixed(2);
  els.paydeskPayments.querySelectorAll("button").forEach(btn => {
    btn.disabled = !hasReceipt;
  });
  els.paydeskOpen.querySelectorAll(".paydesk-item.open").forEach(el => {
    el.onclick = () => movePaydeskItemByKey(el.dataset.key, "to-receipt");
  });
  els.paydeskReceipt.querySelectorAll(".paydesk-item.receipt").forEach(el => {
    el.onclick = () => movePaydeskItemByKey(el.dataset.key, "to-open");
  });
  scheduleDisplayUpdate();
}

function movePaydeskItemByKey(key, direction) {
  if (direction === "to-receipt") {
    const idx = state.paydeskOpen.findIndex(i => paydeskKey(i) === key);
    if (idx >= 0) {
      const moved = state.paydeskOpen[idx];
      moved._seq = ++state.displaySeq;
      state.paydeskReceipt.push(moved);
      state.paydeskOpen.splice(idx, 1);
      markDisplayActivity();
    }
  } else {
    const idx = state.paydeskReceipt.findIndex(i => paydeskKey(i) === key);
    if (idx >= 0) {
      const moved = state.paydeskReceipt[idx];
      delete moved._seq;
      state.paydeskOpen.push(moved);
      state.paydeskReceipt.splice(idx, 1);
      markDisplayActivity();
    }
  }
  renderPaydeskItems();
}

function paydeskKey(item) {
  const extras = normalizeExtras(item).map(e => `${e.id}:${e.amount || 1}`).sort().join("|");
  return [item.prodid, item.togo ? 1 : 0, item.price, item.pricelevelname || "", extras].join("#");
}

function groupPaydeskItems(items) {
  const groups = new Map();
  items.forEach((it, idx) => {
    const key = paydeskKey(it);
    if (!groups.has(key)) {
      groups.set(key, { key, item: it, count: 0, first: idx });
    }
    const g = groups.get(key);
    g.count += Number(it.unitamount || 1);
  });
  return Array.from(groups.values()).sort((a, b) => {
    const an = String(a.item.longname || a.item.name || "").toLowerCase();
    const bn = String(b.item.longname || b.item.name || "").toLowerCase();
    if (an < bn) return -1;
    if (an > bn) return 1;
    return a.first - b.first;
  });
}

els.paydeskAddAll?.addEventListener("click", () => {
  (state.paydeskOpen || []).forEach(it => {
    it._seq = ++state.displaySeq;
  });
  state.paydeskReceipt = state.paydeskReceipt.concat(state.paydeskOpen);
  state.paydeskOpen = [];
  renderPaydeskItems();
});

els.paydeskClear?.addEventListener("click", () => {
  (state.paydeskReceipt || []).forEach(it => { delete it._seq; });
  state.paydeskOpen = state.paydeskOpen.concat(state.paydeskReceipt);
  state.paydeskReceipt = [];
  renderPaydeskItems();
});

async function loadPayments() {
  const data = await api("payments", {});
  const rawPayments = data.payments || [];
  const isAllowed = (id) => {
    if (!state.config) return true;
    if (Number(state.config.showpayments) === 0) return false;
    const key = `showpayment${id}`;
    if (Object.prototype.hasOwnProperty.call(state.config, key)) {
      return Number(state.config[key]) === 1;
    }
    return true;
  };
  state.payments = rawPayments.filter(p => isAllowed(Number(p.id)));
  els.paydeskPayments.innerHTML = state.payments.map(p => `
    <div class="paydesk-payment-block">
      <button class="menu-btn" data-pay="${p.id}" data-print="0">${p.name}</button>
      <button class="ghost" data-pay="${p.id}" data-print="1">Bondruck</button>
    </div>
  `).join("");
  els.paydeskPayments.querySelectorAll("button").forEach(btn => {
    btn.onclick = async () => {
      const paymentId = Number(btn.dataset.pay);
      const print = Number(btn.dataset.print || 0) === 1;
      await paydeskPay(paymentId, print);
    };
  });
}

async function paydeskPay(paymentId, print) {
  if (!state.paydeskTable) return;
  const ids = state.paydeskReceipt.map(i => i.id).join(",");
  if (!ids) return;
  const hostFlag = !!(els.paydeskHost && els.paydeskHost.classList.contains("active")) || !!state.paydeskHost;
  const res = await api("paydesk_pay", {
    ids,
    tableid: state.paydeskTable.id,
    paymentid: paymentId,
    declareready: 0,
    host: hostFlag ? 1 : 0,
    reservationid: "",
    guestinfo: "",
    intguestid: "",
    tip: 0
  });
  if (res.status === "OK") {
    await selectPaydeskTable(state.paydeskTable.id, state.paydeskTable.name);
    state.paydeskHost = false;
    if (els.paydeskHost) els.paydeskHost.classList.remove("active");
    if (res.msg && res.msg.ebonurl && res.msg.ebonref) {
      sendDisplayEbon(res.msg.ebonurl, res.msg.ebonref);
    }
    if (print && res.msg && res.msg.billid) {
      await fetch("../php/contenthandler.php?module=printqueue&command=queueReceiptPrintJob", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `billid=${encodeURIComponent(res.msg.billid)}&useaddrecprinter=1`
      });
    }
    if ((state.paydeskOpen || []).length === 0) {
      show(els.startScreen);
    }
  } else {
    alert(res.msg || "Zahlung fehlgeschlagen (evtl. bereits bezahlt). Bitte neu laden.");
    await selectPaydeskTable(state.paydeskTable.id, state.paydeskTable.name);
    await refreshTables();
  }
}

async function openPaydeskPicker(origin) {
  resetConfirmActionsLayout();
  const data = await api("refresh_tables", {});
  if (data.status !== "OK") return;
  state.rooms = data.rooms;
  const tables = [];
  (state.rooms.roomstables || []).forEach(r => r.tables.forEach(t => { if (Number(t.unpaidprodcount || 0) > 0) tables.push(t); }));
  if (Number(state.rooms.takeawayunpaidprodcount || 0) > 0) tables.push({ id: 0, name: "To-Go" });
  if (tables.length === 0) {
    alert("Keine offenen Tische");
    return;
  }
  els.confirmTitle.textContent = "Tisch wählen";
  els.confirmBody.innerHTML = "";
  els.confirmActions.innerHTML = `
    ${tables.map(t => `<button class="ghost" data-id="${t.id}" data-name="${t.name}">${t.name}</button>`).join("")}
    <button class="primary" id="paydesk-cancel">Abbrechen</button>
  `;
  els.confirmModal.classList.remove("hidden");
  els.confirmActions.querySelectorAll("button[data-id]").forEach(b => {
    b.onclick = async () => {
      const id = Number(b.dataset.id);
      const name = b.dataset.name;
      els.confirmModal.classList.add("hidden");
      await openPaydesk({ id, name });
    };
  });
  els.confirmActions.querySelector("#paydesk-cancel").onclick = () => {
    els.confirmModal.classList.add("hidden");
    if (origin !== "paydesk") {
      show(els.startScreen);
    }
  };
}

els.paydeskTableLabel?.addEventListener("click", async () => {
  await openPaydeskPicker("paydesk");
});
els.paydeskTableName?.addEventListener("click", async () => {
  await openPaydeskPicker("paydesk");
});

els.paydeskHost?.addEventListener("click", () => {
  state.paydeskHost = !state.paydeskHost;
  els.paydeskHost.classList.toggle("active", state.paydeskHost);
});

async function changeTableFlow() {
  resetConfirmActionsLayout();
  const table = state.selectedTable;
  if (!table) return;
  const data = await api("table_open_items", { tableid: table.id });
  if (data.status !== "OK") return;
  const existing = data.msg || [];
  const cart = state.cartByTable[table.id] || [];
  const tables = (state.rooms?.roomstables || []).flatMap(r => r.tables).map(t => ({ id: t.id, name: t.name }));
  tables.push({ id: 0, name: "To-Go" });

  let selectedTableId = null;
  const items = [];
  existing.forEach((it, idx) => {
    items.push({ type: "existing", id: `e_${idx}`, queueid: it.queueid, label: it.productname, checked: true });
  });
  cart.forEach((it, idx) => {
    items.push({ type: "cart", id: `c_${idx}`, cartId: it._id, label: it.name, checked: true });
  });

  els.confirmTitle.textContent = "Tisch wechseln";
  els.confirmBody.innerHTML = `
    <div class="change-table-area">
      <div class="change-table-buttons">
        ${tables.map(t => `<button class="ghost change-table-btn" data-id="${t.id}">${t.name}</button>`).join("")}
      </div>
      <div class="change-table-actions">
        <button class="primary" id="change-table-do">Wechsel</button>
        <button class="ghost" id="change-table-cancel">Abbruch</button>
      </div>
      <div class="change-table-items">
        ${items.map(it => `<label class="change-item ${it.type}"><input type="checkbox" data-id="${it.id}" checked /> ${it.label}</label>`).join("")}
      </div>
    </div>
  `;
  els.confirmActions.innerHTML = "";
  els.confirmModal.classList.remove("hidden");

  els.confirmBody.querySelectorAll(".change-table-btn").forEach(b => {
    b.onclick = () => {
      selectedTableId = Number(b.dataset.id);
      els.confirmBody.querySelectorAll(".change-table-btn").forEach(x => x.classList.remove("active"));
      b.classList.add("active");
    };
  });

  els.confirmBody.querySelector("#change-table-cancel").onclick = () => {
    els.confirmModal.classList.add("hidden");
  };

  els.confirmBody.querySelector("#change-table-do").onclick = async () => {
    if (selectedTableId === null) {
      alert("Bitte Tisch auswählen");
      return;
    }
    const selectedIds = Array.from(els.confirmBody.querySelectorAll("input[type=checkbox]:checked")).map(i => i.dataset.id);
    if (selectedIds.length === 0) {
      alert("Bitte Produkte auswählen");
      return;
    }
    const queueids = [];
    selectedIds.forEach(id => {
      const it = items.find(x => x.id === id);
      if (!it) return;
      if (it.type === "existing") {
        queueids.push(it.queueid);
      }
        if (it.type === "cart") {
          const idx = cart.findIndex(c => c._id === it.cartId);
          if (idx >= 0) {
            const [moved] = cart.splice(idx, 1);
            moved.togo = selectedTableId === 0 ? 1 : 0;
            const target = state.cartByTable[selectedTableId] || [];
            target.push(moved);
            state.cartByTable[selectedTableId] = target;
          }
        }
      });
    saveCart(table.id);
    saveCart(selectedTableId);
    if (selectedTableId !== 0 && queueids.length > 0) {
      await api("change_table", { fromTableId: table.id, toTableId: selectedTableId, queueids: queueids.join(",") });
    }
    if (selectedTableId === 0 && queueids.length > 0) {
      await api("change_table", { fromTableId: table.id, toTableId: 0, queueids: queueids.join(",") });
    }
    els.confirmModal.classList.add("hidden");
    const found = tables.find(t => Number(t.id) === Number(selectedTableId));
    const targetName = found ? found.name : (selectedTableId === 0 ? "To-Go" : String(selectedTableId));
    openOrderForTable({ id: selectedTableId, name: targetName });
  };
}

async function openMenuModal() {
  const data = await api("menu_items", {});
  if (data.menu) {
    const items = (data.menu || []).filter(m => {
      const name = (m.name || "").toLowerCase();
      return !name.includes("logout") && !name.includes("abmelden");
    });
    const recordsBtn = `<button class="menu-link-btn" data-records="1">Tischprotokoll</button>`;
    const localBtn = `<button class="menu-link-btn" data-local="1">Lokale Konfiguration</button>`;
    els.menuItems.innerHTML = recordsBtn + localBtn + items.map(m => {
      const link = normalizeMenuLink(m.link || "");
      return `<button class="menu-link-btn" data-link="${link}">${m.name}</button>`;
    }).join("");
    els.menuItems.querySelectorAll("button").forEach(b => {
      if (b.dataset.records) {
        b.onclick = () => {
          closeMenuModal();
          openTableRecords();
        };
      } else if (b.dataset.local) {
        b.onclick = () => {
          closeMenuModal();
          openLocalConfigModal();
        };
      } else {
        b.onclick = () => {
          closeMenuModal();
          window.open(b.dataset.link, "_blank");
        };
      }
    });
  }
  els.menuModal.classList.remove("hidden");
}

function closeMenuModal() {
  if (els.menuModal) els.menuModal.classList.add("hidden");
}

async function openTableRecords() {
  const table = state.selectedTable || state.paydeskTable;
  if (!table) {
    if (els.recordsTitle) els.recordsTitle.textContent = "Tischprotokoll";
    if (els.recordsBody) els.recordsBody.innerHTML = "<p>Kein Tisch ausgewählt.</p>";
    els.recordsModal.classList.remove("hidden");
    return;
  }
  if (els.recordsTitle) {
    els.recordsTitle.textContent = `Tischprotokoll: ${table.name || table.id}`;
  }
  if (els.recordsBody) {
    els.recordsBody.innerHTML = "<p>Lade...</p>";
  }
  els.recordsModal.classList.remove("hidden");
  try {
    const res = await api("table_records", { tableid: table.id });
    renderTableRecords(res);
  } catch (e) {
    if (els.recordsBody) els.recordsBody.innerHTML = "<p>Fehler beim Laden.</p>";
  }
}

function renderTableRecords(res) {
  if (!els.recordsBody) return;
  if (!res || res.status !== "OK") {
    const msg = res?.msg ? escapeHtml(res.msg) : "Fehler beim Laden.";
    els.recordsBody.innerHTML = `<p>${msg}</p>`;
    return;
  }
  const records = Array.isArray(res.msg) ? res.msg : [];
  if (records.length === 0) {
    els.recordsBody.innerHTML = "<p>Keine Einträge.</p>";
    return;
  }
  let html = "<ul>";
  records.forEach(rec => {
    const time = escapeHtml(rec.time || "");
    const user = escapeHtml(rec.username || "-");
    const actionIdx = Number(rec.action);
    const action = escapeHtml(RECORD_ACTIONS[actionIdx] || rec.action || "");
    html += `<li>${time} ${user} - <b>${action}</b>:<br>`;
    html += "<ul>";
    (rec.prods || []).forEach(p => {
      const name = escapeHtml(p.name || "");
      const comment = escapeHtml(p.comment || "");
      const extras = escapeHtml(p.extras || "");
      html += `<li>${name}`;
      if (comment) html += ` [${comment}]`;
      if (extras) html += ` (${extras})`;
      html += "</li>";
    });
    html += "</ul></li>";
  });
  html += "</ul>";
  els.recordsBody.innerHTML = html;
}

function openLocalConfigModal() {
  resetConfirmActionsLayout();
  els.confirmTitle.textContent = "Lokale Konfiguration";
  els.confirmBody.innerHTML = `
    <label class="toggle">
      <input type="checkbox" id="local-single-extra" ${state.localConfig?.singleExtraImmediate ? "checked" : ""} />
      <span>Ein extra sofort bestellen</span>
    </label>
  `;
  els.confirmActions.innerHTML = `
    <button class="ghost confirm-action" id="local-close">Schließen</button>
  `;
  els.confirmModal.classList.remove("hidden");
  els.confirmBody.querySelector("#local-single-extra").onchange = (e) => {
    state.localConfig.singleExtraImmediate = !!e.target.checked;
    saveLocalConfig();
  };
  els.confirmActions.querySelector("#local-close").onclick = () => {
    els.confirmModal.classList.add("hidden");
  };
}

function setStartMessage(title, cart) {
  if (!els.startMessageBody) return;
  const list = (cart || []).map(c => `${c.unitamount}x ${c.name}`).join(", ");
  els.startMessageBody.textContent = `${title}${list ? ": " + list : ""}`;
}

async function loadTableLayout() {
  try {
    const res = await fetch("./table-layout.json", { cache: "no-store" });
    if (!res.ok) return null;
    const data = await res.json();
    return data;
  } catch (_) {
    return null;
  }
}

function getTableCode(table, mapping) {
  if (!mapping) return null;
  const code = String(table.code || "").trim();
  if (code && mapping[code]) return code;
  const name = String(table.name || "");
  if (mapping[name]) return name;
  const keys = Object.keys(mapping);
  for (const k of keys) {
    if (k && name.includes(k)) return k;
  }
  return null;
}

function loadLocalConfig() {
  try {
    const raw = localStorage.getItem("modern_local_config");
    if (!raw) return { singleExtraImmediate: true, tableLayout: null };
    const parsed = JSON.parse(raw);
    return {
      singleExtraImmediate: parsed.singleExtraImmediate !== false,
      tableLayout: parsed.tableLayout || null
    };
  } catch (_) {
    return { singleExtraImmediate: true, tableLayout: null };
  }
}

function saveLocalConfig() {
  try {
    localStorage.setItem("modern_local_config", JSON.stringify(state.localConfig || { singleExtraImmediate: true, tableLayout: null }));
  } catch (_) {}
}

function updateTableLabelWidth() {
  const names = [];
  (state.rooms?.roomstables || []).forEach(r => r.tables.forEach(t => names.push(t.name || "")));
  if (names.length === 0) return;
  const meas = document.createElement("span");
  meas.style.position = "absolute";
  meas.style.visibility = "hidden";
  meas.style.fontWeight = "800";
  meas.style.fontSize = "20px";
  document.body.appendChild(meas);
  let max = 0;
  names.forEach(n => {
    meas.textContent = n;
    max = Math.max(max, meas.getBoundingClientRect().width);
  });
  document.body.removeChild(meas);
  state.maxTableLabelWidth = Math.min(Math.ceil(max) + 20, 260);
}

function normalizeMenuLink(link) {
  if (!link) return "#";
  let out = link;
  out = out.replace("/modern/", "/");
  out = out.replace(/^modern\//, "");
  if (out.startsWith("./")) out = out.slice(2);
  if (!out.startsWith("/") && !out.startsWith("http")) out = "/" + out;
  return out;
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

function startPolling() {
  const interval = Math.max(10000, Number(state.clientPollMs || 120000));
  setInterval(async () => {
    try {
      const stateRes = await api("state", {});
      if (stateRes && stateRes.status === "OK" && stateRes.version) {
        const now = Date.now();
        if (state.lastServerVersion && state.lastServerVersion !== stateRes.version) {
          console.debug("Preisstufe geändert");
          await refreshMenuPrices();
          const changedVersion = stateRes.version;
          const changedAt = now;
          setTimeout(async () => {
            if (state.lastServerVersion !== changedVersion) return;
            if (state.lastBrokerUpdateAt >= changedAt) return;
            if (state.missedUpdateVersion === changedVersion) return;
            state.missedUpdateVersion = changedVersion;
            await logClientError("Hinweis: broker hat Update unterschlagen, Sysadmin informieren");
            showWarnPopup("Hinweis: broker hat Update unterschlagen, Sysadmin informieren");
          }, BROKER_MISS_GRACE_MS);
          state.lastServerVersionAt = now;
        }
        if (!state.lastServerVersion) {
          state.lastServerVersionAt = now;
        }
        state.lastServerVersion = stateRes.version;
      }
    } catch (_) {}
    const data = await api("refresh_tables", {});
    if (data.status === "OK") {
      state.rooms = data.rooms;
      renderTables();
      state.lastSync = new Date().toLocaleTimeString();
      updateStatus();
    }
    if (state.selectedTable) {
      await fetchExistingOrders();
      renderOrderItems();
    }
  }, interval);
}

async function refreshTablesIfVisible() {
  if (!els.startScreen || els.startScreen.classList.contains("hidden")) return;
  await refreshTables();
}

async function refreshTables() {
  const data = await api("refresh_tables", {});
  if (data.status === "OK") {
    state.rooms = data.rooms;
    renderTables();
    state.lastSync = new Date().toLocaleTimeString();
    updateStatus();
  }
}

async function refreshOrderIfVisible() {
  if (!els.orderScreen || els.orderScreen.classList.contains("hidden")) return;
  if (!state.selectedTable) return;
  await fetchExistingOrders();
  renderOrderItems();
}

init();
