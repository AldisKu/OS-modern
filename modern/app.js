const API = "../php/modernapi.php";
let brokerUrl = "ws://127.0.0.1:3077";

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
  statusPrinter: document.getElementById("status-printer"),
  statusTse: document.getElementById("status-tse"),

  orderUser: document.getElementById("order-user"),
  orderBroker: document.getElementById("order-broker"),
  orderOnline: document.getElementById("order-online"),
  orderSync: document.getElementById("order-sync"),
  orderPrinter: document.getElementById("order-printer"),
  orderTse: document.getElementById("order-tse"),

  paydeskUser: document.getElementById("paydesk-user"),
  paydeskOnline: document.getElementById("paydesk-online"),
  paydeskSync: document.getElementById("paydesk-sync"),

  tablesGrid: document.getElementById("tables-grid"),
  orderTableLabel: document.getElementById("order-table-label"),
  categoryRow: document.getElementById("category-row"),
  productsGrid: document.getElementById("products-grid"),
  orderItems: document.getElementById("order-items"),

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

  confirmModal: document.getElementById("confirm-modal"),
  confirmTitle: document.getElementById("confirm-title"),
  confirmBody: document.getElementById("confirm-body"),
  confirmActions: document.getElementById("confirm-actions"),

  menuModal: document.getElementById("menu-modal"),
  menuItems: document.getElementById("menu-items"),
  menuClose: document.getElementById("menu-close"),
  startMessageBody: document.getElementById("start-message-body"),

  kbdRow1: document.getElementById("kbd-row-1"),
  kbdRow2: document.getElementById("kbd-row-2"),
  kbdRow3: document.getElementById("kbd-row-3"),
  kbdRow4: document.getElementById("kbd-row-4")
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
  printerStatus: undefined,
  tseStatus: undefined,
  notDelivered: [],
  keyboardMode: "num",
  lastSync: "-",
  cancelUnpaidCode: "",
  discounts: { d1: 0, d2: 0, d3: 0, n1: "Rabatt 1", n2: "Rabatt 2", n3: "Rabatt 3" },
  modalExtrasSelected: []
};

function show(screen) {
  [els.loginScreen, els.startScreen, els.orderScreen, els.paydeskScreen].forEach(s => s.classList.add("hidden"));
  screen.classList.remove("hidden");
  if (screen !== els.startScreen && els.startMessageBody) {
    els.startMessageBody.textContent = "";
  }
  if (screen === els.startScreen && els.startMessageBody && els.startMessageBody.textContent.trim() === "") {
    els.startMessageBody.textContent = "Bereit.";
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
    ws.onopen = () => {
      [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = "OK");
    };
    ws.onclose = () => {
      [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = "OFF");
    };
    ws.onerror = () => {
      [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = "OFF");
    };
    ws.onmessage = (evt) => {
      let payload = null;
      try { payload = JSON.parse(evt.data); } catch (_) { return; }
      if (payload && payload.type === "STATUS_UPDATE" && payload.status) {
        if (payload.status.printer !== undefined) state.printerStatus = payload.status.printer;
        if (payload.status.tse !== undefined) state.tseStatus = payload.status.tse;
        updateStatus();
      }
    };
  } catch (_) {}
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
  els.menuClose.addEventListener("click", () => els.menuModal.classList.add("hidden"));
}

async function loadServerConfig() {
  const data = await api("config", {});
  if (data.status === "OK" && data.broker_ws) {
    brokerUrl = data.broker_ws;
  }
}

async function loadUsers() {
  const data = await api("users", {});
  if (data.users) {
    state.users = data.users;
  }
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
  } else {
    els.loginHint.textContent = "Login fehlgeschlagen";
  }
}

async function bootstrap() {
  const data = await api("bootstrap", {});
  if (data.status !== "OK") return;
  state.user = data.user;
  state.config = data.config;
  state.menu = data.menu;
  state.rooms = data.rooms;
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
}

function updateStatus() {
  const name = state.user?.name || "-";
  [els.statusUser, els.orderUser, els.paydeskUser].filter(Boolean).forEach(el => el.textContent = name);
  [els.statusBroker, els.orderBroker].filter(Boolean).forEach(el => el.textContent = "broker" );
  [els.statusOnline, els.orderOnline, els.paydeskOnline].filter(Boolean).forEach(el => el.textContent = "OK");
  [els.statusSync, els.orderSync, els.paydeskSync].filter(Boolean).forEach(el => el.textContent = state.lastSync);
  if (state.printerStatus !== undefined) {
    const txt = state.printerStatus === 0 ? "OK" : "DOWN";
    [els.statusPrinter, els.orderPrinter].filter(Boolean).forEach(el => el.textContent = txt);
  }
  if (state.tseStatus !== undefined) {
    const txt = state.tseStatus ? "OK" : "DOWN";
    [els.statusTse, els.orderTse].filter(Boolean).forEach(el => el.textContent = txt);
  }
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

    btns.push(`<button class="category-btn up" data-cat="start">Start</button>`);
    if (top) {
      btns.push(`<button class="category-btn up" data-cat="${top.id}">${top.name}</button>`);
    }
    if (current) {
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
  els.productsGrid.innerHTML = prods.map(p => `
    <div class="product-card" data-id="${p.id}">
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
      if (!prod.extras || prod.extras.length === 0) {
        quickAddProduct(prod);
      } else {
        openProductModal(prod);
      }
    });
  });
}

function renderTables() {
  if (!state.rooms?.roomstables) return;
  const cards = [];
  state.rooms.roomstables.forEach(room => {
    room.tables.forEach(t => {
      const sum = t.pricesum || "0.00";
      cards.push(`
        <div class="table-card" data-id="${t.id}" data-name="${t.name}">
          <div class="name">${t.name}</div>
          <div class="meta">${sum}</div>
        </div>
      `);
    });
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
  const cartGroups = groupCartItems(cart);
  cartGroups.forEach(g => {
    const extraLabels = [];
    normalizeExtras(g.item).forEach(e => extraLabels.push(`+ ${e.name}`));
    if (g.item.togo) extraLabels.push("+ ToGo");
    const changed = Number(g.item.changedPrice || 0);
    const base = Number(g.item.price || 0);
    if (changed && base > 0 && Math.abs(changed - base) > 0.0001) {
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
    if (g.item.togo) extraLabels.push("+ ToGo");
    const levelLabel = displayPriceLevel(g.item.pricelevelname);
    if (levelLabel) extraLabels.push(`+ ${levelLabel}`);
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
}

function adjustCartGroup(key, delta) {
  if (!state.selectedTable) return;
  const cart = state.cartByTable[state.selectedTable.id] || [];
  if (delta > 0) {
    const idx = cart.findIndex(c => cartKey(c) === key);
    if (idx >= 0) {
      const clone = { ...cart[idx], _id: Date.now(), unitamount: 1 };
      cart.push(clone);
    }
  } else {
    for (let i = cart.length - 1; i >= 0; i--) {
      if (cartKey(cart[i]) !== key) continue;
      cart[i].unitamount = Number(cart[i].unitamount || 1) - 1;
      if (cart[i].unitamount <= 0) cart.splice(i, 1);
      break;
    }
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
        if (extrasList.length === 1) {
          addToCart(prod, [{ id, name, price, amount: 1 }], "", 1);
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

function quickAddProduct(prod) {
  addToCart(prod, [], "", 1);
}

function addProductToCart() {
  const prod = state.modalProduct;
  if (!prod) return;
  const qty = 1;
  const extras = Array.isArray(state.modalExtrasSelected) ? state.modalExtrasSelected : [];
  addToCart(prod, extras, "", qty);
  els.productModal.classList.add("hidden");
  renderOrderItems();
}

function addToCart(prod, extras, option, qty, forceTogo) {
  const tableId = state.selectedTable.id;
  const item = {
    _id: Date.now(),
    prodid: prod.id,
    name: prod.longname || prod.name,
    price: Number(prod.price || 0),
    unit: Number(prod.unit || 0),
    unitamount: qty,
    togo: typeof forceTogo === "number" ? forceTogo : (state.selectedTable?.id === 0 ? 1 : 0),
    option: option || "",
    extras: extras || []
  };
  state.cartByTable[tableId] = state.cartByTable[tableId] || [];
  const cart = state.cartByTable[tableId];
  const key = cartKey(item);
  const idx = cart.findIndex(c => cartKey(c) === key);
  if (idx >= 0) {
    cart[idx].unitamount += item.unitamount;
  } else {
    cart.push(item);
  }
  saveCart(tableId);
  renderOrderItems();
}

function resetClientState() {
  try { localStorage.clear(); } catch (_) {}
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
  if (Array.isArray(item.extras) && item.extras.length > 0) {
    const byId = new Map((state.menu?.extras || []).map(e => [Number(e.id), e.name]));
    return item.extras.map(e => ({
      id: Number(e.id ?? e.extraid),
      amount: Number(e.amount || 1),
      name: e.name || byId.get(Number(e.id)) || `Extra ${e.id}`
    }));
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
    name: byId.get(Number(id)) || `Extra ${id}`
  }));
}

function displayPriceLevel(name) {
  if (!name) return "";
  const trimmed = String(name).trim();
  if (trimmed === "A" || trimmed === "1") return state.discounts.n1 || trimmed;
  if (trimmed === "B" || trimmed === "2") return state.discounts.n2 || trimmed;
  if (trimmed === "C" || trimmed === "3") return state.discounts.n3 || trimmed;
  return trimmed;
}

function existingKey(item) {
  const extras = normalizeExtras(item).map(e => `${e.id}:${e.amount || 1}`).sort().join("|");
  const price = Number(item.price || 0);
  const priceKey = Number.isFinite(price) ? price.toFixed(2) : "";
  const level = item.pricelevelname || "";
  return [item.prodid, item.orderoption || "", item.togo ? 1 : 0, priceKey, level, extras].join("#");
}

function existingKeyLoose(item) {
  const extras = normalizeExtras(item).map(e => `${e.id}:${e.amount || 1}`).sort().join("|");
  const price = Number(item.price || 0);
  const priceKey = Number.isFinite(price) ? price.toFixed(2) : "";
  return [item.prodid, item.orderoption || "", item.togo ? 1 : 0, priceKey, "", extras].join("#");
}

function groupCartItems(items) {
  const groups = new Map();
  items.forEach((it, idx) => {
    const key = cartKey(it);
    if (!groups.has(key)) {
      groups.set(key, { key, item: it, count: 0, first: idx });
    }
    const g = groups.get(key);
    g.count += Number(it.unitamount || 1);
  });
  return Array.from(groups.values()).sort((a, b) => a.first - b.first);
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
  return Array.from(groups.values()).sort((a, b) => a.first - b.first);
}

function resetConfirmActionsLayout() {
  els.confirmActions.classList.remove("actions-3");
}

function showExistingItemActions(item) {
  resetConfirmActionsLayout();
  els.confirmActions.classList.add("actions-3");
  const key = existingKey(item);
  const groupCount = (state.orderExisting || []).filter(p => existingKey(p) === key).reduce((s, p) => s + Number(p.unitamount || 1), 0);
  els.confirmTitle.textContent = item.longname;
  const codeField = state.cancelUnpaidCode ? `<input type="text" id="storno-code" class="storno-code" placeholder="Stornocode" />` : "";
  els.confirmBody.innerHTML = `
    <div class="edit-row"><b>${item.longname}</b> (aktuell: ${groupCount})</div>
    <div class="edit-row"><b>Anzahl</b></div>
    <div class="edit-qty-row">
      <div class="edit-qty compact">
      <button class="ghost" id="qty-dec">-1</button>
      <input type="number" id="qty-val" class="qty-small" value="1" min="1" max="${groupCount}" />
      <button class="ghost" id="qty-inc">+1</button>
      </div>
      ${codeField}
    </div>
  `;
  els.confirmActions.innerHTML = `
    <button class="ghost" id="cancel">Abbrechen</button>
    <button class="ghost" id="reorder">Nachbestellen</button>
    <button class="primary" id="remove">Entfernen</button>
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

  els.confirmActions.querySelector("#cancel").onclick = () => {
    els.confirmModal.classList.add("hidden");
  };
  els.confirmActions.querySelector("#reorder").onclick = () => {
    const qty = Math.max(1, Math.min(groupCount, Number(qtyVal.value || 1)));
    const prod = state.menu?.prods?.find(p => Number(p.id) === Number(item.prodid));
    if (prod) {
      const extras = normalizeExtras(item).map(e => ({ id: e.id, name: e.name, price: Number(e.price || 0), amount: e.amount || 1 }));
      addToCart(prod, extras, item.orderoption || "", qty, item.togo ? 1 : 0);
    }
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
    const candidates = (state.notDelivered || []).filter(p => {
      const k = existingKey({
        prodid: p.prodid,
        orderoption: p.option,
        togo: p.togo,
        price: p.price,
        pricelevelname: p.pricelevelname || "",
        extrasids: p.extrasids,
        extrasamounts: p.extrasamounts
      });
      if (k === key || k === keyLoose) return true;
      const kl = existingKeyLoose({
        prodid: p.prodid,
        orderoption: p.option,
        togo: p.togo,
        price: p.price,
        extrasids: p.extrasids,
        extrasamounts: p.extrasamounts
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
      await api("remove_product", { queueid: c.id, isPaid: c.isPaid, isCooking: c.isCooking, isReady: c.isready });
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
  const groupCount = cart.filter(c => cartKey(c) === cartKey(item)).reduce((sum, c) => sum + Number(c.unitamount || 1), 0);
  const basePrice = Number(item.price || 0);
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
    <div class="edit-row"><b>Aktion</b></div>
    <div class="edit-actions">
      <button type="button" class="ghost" id="act-togo">${item.togo ? "ToGo ✓" : "ToGo"}</button>
      <button type="button" class="ghost" id="disc1">${discName1} ${formatPct(disc1)}%</button>
      <button type="button" class="ghost" id="disc2">${discName2} ${formatPct(disc2)}%</button>
      <button type="button" class="ghost" id="disc3">${discName3} ${formatPct(disc3)}%</button>
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
  const setPrice = (val) => {
    currentPrice = Number(val);
    priceVal.textContent = currentPrice.toFixed(2);
  };
  const applyDiscount = (pct) => {
    setPrice(basePrice - basePrice * pct / 100);
  };
  els.confirmBody.querySelector("#disc1").onclick = (e) => { e.preventDefault(); applyDiscount(disc1); };
  els.confirmBody.querySelector("#disc2").onclick = (e) => { e.preventDefault(); applyDiscount(disc2); };
  els.confirmBody.querySelector("#disc3").onclick = (e) => { e.preventDefault(); applyDiscount(disc3); };

  let togoVal = item.togo ? 1 : 0;
  els.confirmBody.querySelector("#act-togo").onclick = () => {
    togoVal = togoVal ? 0 : 1;
    els.confirmBody.querySelector("#act-togo").textContent = togoVal ? "ToGo ✓" : "ToGo";
  };

  els.confirmActions.querySelector("#cancel").onclick = () => els.confirmModal.classList.add("hidden");
  els.confirmActions.querySelector("#apply").onclick = () => {
    const qty = Math.max(1, Math.min(groupCount, Number(qtyVal.value || 1)));
    const newNote = els.confirmBody.querySelector("#note-val").value.trim();
    const newPrice = Number(currentPrice || basePrice);
    const changedPrice = Math.abs(newPrice - Number(item.price || 0)) > 0.0001 ? newPrice.toFixed(2) : "NO";
    const newKey = cartKey({ ...item, option: newNote, togo: togoVal, changedPrice });
    const currentKey = cartKey(item);
    const hasChanges = newKey !== currentKey;

    if (qty < groupCount) {
      // reduce original group by qty, create/merge new item for changed subset
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
      const existingIdx = cart.findIndex(c => cartKey(c) === newKey);
      if (existingIdx >= 0) {
        cart[existingIdx].unitamount += qty;
        const [moved] = cart.splice(existingIdx, 1);
        cart.unshift(moved);
      } else {
        const newItem = {
          ...item,
          _id: Date.now(),
          unitamount: qty,
          option: newNote,
          togo: togoVal,
          changedPrice
        };
        cart.unshift(newItem);
      }
    } else if (hasChanges) {
      // replace entire group and move to top
      let total = 0;
      for (let i = cart.length - 1; i >= 0; i--) {
        if (cartKey(cart[i]) !== currentKey) continue;
        total += Number(cart[i].unitamount || 1);
        cart.splice(i, 1);
      }
      const existingIdx = cart.findIndex(c => cartKey(c) === newKey);
      if (existingIdx >= 0) {
        cart[existingIdx].unitamount += total;
        const [moved] = cart.splice(existingIdx, 1);
        cart.unshift(moved);
      } else {
        const newItem = {
          ...item,
          _id: Date.now(),
          unitamount: total,
          option: newNote,
          togo: togoVal,
          changedPrice
        };
        cart.unshift(newItem);
      }
    }
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
        showUnsavedDialog();
        return;
      }
    }
    await api("logout", {});
    resetClientState();
    show(els.loginScreen);
  } else if (action === "order") {
    if (state.paydeskTable) {
      openOrderForTable({ id: state.paydeskTable.id, name: state.paydeskTable.name });
    }
  } else if (action === "start") {
    if (state.selectedTable) {
      const cart = state.cartByTable[state.selectedTable.id] || [];
      if (cart.length > 0) {
        await sendOrder(true, true);
        return;
      }
    }
    state.typeStack = [];
    state.selectedType = topLevelTypes()[0]?.id || null;
    renderCategories();
    show(els.startScreen);
  } else if (action === "send") {
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
    extras: c.extras.map(e => ({ id: e.id, name: e.name, amount: e.amount })),
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
  }
}

function showUnsavedDialog() {
  resetConfirmActionsLayout();
  els.confirmTitle.textContent = "Offene Bestellung";
  els.confirmBody.innerHTML = "Was soll mit den offenen Produkten passieren?";
  els.confirmActions.innerHTML = `
    <button class="ghost" id="discard">Verwerfen</button>
    <button class="ghost" id="assign">Zu anderem Tisch</button>
    <button class="primary" id="send">Abschicken</button>
  `;
  els.confirmModal.classList.remove("hidden");
  els.confirmActions.querySelector("#discard").onclick = () => {
    state.cartByTable[state.selectedTable.id] = [];
    saveCart(state.selectedTable.id);
    els.confirmModal.classList.add("hidden");
    state.typeStack = [];
    state.selectedType = topLevelTypes()[0]?.id || null;
    renderCategories();
    show(els.startScreen);
  };
  els.confirmActions.querySelector("#send").onclick = async () => {
    await sendOrder(true, true);
    els.confirmModal.classList.add("hidden");
  };
  els.confirmActions.querySelector("#assign").onclick = () => {
    els.confirmBody.innerHTML = "Ziel‑Tisch wählen";
    const list = (state.rooms?.roomstables || []).flatMap(r => r.tables).map(t => `<button class="ghost" data-id="${t.id}">${t.name}</button>`).join("");
    els.confirmActions.innerHTML = list;
    els.confirmActions.querySelectorAll("button").forEach(b => {
      b.onclick = () => {
        const to = Number(b.dataset.id);
        state.cartByTable[to] = (state.cartByTable[to] || []).concat(state.cartByTable[state.selectedTable.id]);
        saveCart(to);
        state.cartByTable[state.selectedTable.id] = [];
        saveCart(state.selectedTable.id);
        openOrderForTable({ id: to, name: b.textContent });
        els.confirmModal.classList.add("hidden");
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
    if (g.item.togo) tags.push("[ToGo]");
    const levelLabel = displayPriceLevel(g.item.pricelevelname);
    if (levelLabel) tags.push(`[${levelLabel}]`);
    normalizeExtras(g.item).forEach(e => tags.push(`+${e.name}`));
    const label = `${g.item.longname}${tags.length ? " " + tags.join(" ") : ""}`;
    return `<div class="paydesk-item open" data-key="${g.key}">${label} x${qty} - ${line.toFixed(2)}</div>`;
  }).join("");
  els.paydeskReceipt.innerHTML = receiptGroups.map(g => {
    const qty = g.count;
    const line = Number(g.item.price || 0) * qty;
    total += line;
    const tags = [];
    if (g.item.togo) tags.push("[ToGo]");
    const levelLabel = displayPriceLevel(g.item.pricelevelname);
    if (levelLabel) tags.push(`[${levelLabel}]`);
    normalizeExtras(g.item).forEach(e => tags.push(`+${e.name}`));
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
}

function movePaydeskItemByKey(key, direction) {
  if (direction === "to-receipt") {
    const idx = state.paydeskOpen.findIndex(i => paydeskKey(i) === key);
    if (idx >= 0) {
      state.paydeskReceipt.push(state.paydeskOpen[idx]);
      state.paydeskOpen.splice(idx, 1);
    }
  } else {
    const idx = state.paydeskReceipt.findIndex(i => paydeskKey(i) === key);
    if (idx >= 0) {
      state.paydeskOpen.push(state.paydeskReceipt[idx]);
      state.paydeskReceipt.splice(idx, 1);
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
  return Array.from(groups.values()).sort((a, b) => a.first - b.first);
}

els.paydeskAddAll?.addEventListener("click", () => {
  state.paydeskReceipt = state.paydeskReceipt.concat(state.paydeskOpen);
  state.paydeskOpen = [];
  renderPaydeskItems();
});

els.paydeskClear?.addEventListener("click", () => {
  state.paydeskOpen = state.paydeskOpen.concat(state.paydeskReceipt);
  state.paydeskReceipt = [];
  renderPaydeskItems();
});

async function loadPayments() {
  const data = await api("payments", {});
  state.payments = data.payments || [];
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
  const res = await api("paydesk_pay", {
    ids,
    tableid: state.paydeskTable.id,
    paymentid: paymentId,
    declareready: 0,
    host: state.paydeskHost ? 1 : 0,
    reservationid: "",
    guestinfo: "",
    intguestid: "",
    tip: 0
  });
  if (res.status === "OK") {
    await selectPaydeskTable(state.paydeskTable.id, state.paydeskTable.name);
    els.paydeskReceipt.innerHTML = "";
    els.paydeskTotal.textContent = "0.00";
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
    els.menuItems.innerHTML = items.map(m => {
      const link = normalizeMenuLink(m.link || "");
      return `<button class="menu-link-btn" data-link="${link}">${m.name}</button>`;
    }).join("");
    els.menuItems.querySelectorAll("button").forEach(b => {
      b.onclick = () => window.open(b.dataset.link, "_blank");
    });
  }
  els.menuModal.classList.remove("hidden");
}

function setStartMessage(title, cart) {
  if (!els.startMessageBody) return;
  const list = (cart || []).map(c => `${c.unitamount}x ${c.name}`).join(", ");
  els.startMessageBody.textContent = `${title}${list ? ": " + list : ""}`;
}

function updateTableLabelWidth() {
  const names = [];
  (state.rooms?.roomstables || []).forEach(r => r.tables.forEach(t => names.push(t.name || "")));
  if (names.length === 0) return;
  const meas = document.createElement("span");
  meas.style.position = "absolute";
  meas.style.visibility = "hidden";
  meas.style.fontWeight = "800";
  meas.style.fontSize = "16px";
  document.body.appendChild(meas);
  let max = 0;
  names.forEach(n => {
    meas.textContent = n;
    max = Math.max(max, meas.getBoundingClientRect().width);
  });
  document.body.removeChild(meas);
  state.maxTableLabelWidth = Math.ceil(max);
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
  const num = ["1","2","3","4","5","6","7","8","9","0"];
  const alpha1 = ["Q","W","E","R","T","Y","U","I","O","P"];
  const alpha2 = ["A","S","D","F","G","H","J","K","L"];
  const alpha3 = ["Z","X","C","V","B","N","M"];

  if (state.keyboardMode === "num") {
    els.kbdRow1.innerHTML = num.map(k => `<button data-k="${k}">${k}</button>`).join("");
    els.kbdRow2.innerHTML = `<button data-k="ABC">ABC</button>`;
    els.kbdRow3.innerHTML = `<button data-k="BKSP">←</button>`;
    els.kbdRow4.innerHTML = ``;
  } else {
    els.kbdRow1.innerHTML = alpha1.map(k => `<button data-k="${k}">${k}</button>`).join("");
    els.kbdRow2.innerHTML = alpha2.map(k => `<button data-k="${k}">${k}</button>`).join("");
    els.kbdRow3.innerHTML = alpha3.map(k => `<button data-k="${k}">${k}</button>`).join("") + `<button data-k="NUM">123</button>`;
    els.kbdRow4.innerHTML = `<button data-k="BKSP">←</button>`;
  }

  document.querySelectorAll(".keyboard button").forEach(btn => {
    btn.onclick = () => {
      const key = btn.dataset.k;
      if (key === "BKSP") {
        els.loginPass.value = els.loginPass.value.slice(0, -1);
      } else if (key === "ABC") {
        state.keyboardMode = "alpha";
        renderKeyboard();
      } else if (key === "NUM") {
        state.keyboardMode = "num";
        renderKeyboard();
      } else {
        els.loginPass.value += key;
      }
    };
  });
}

function startPolling() {
  setInterval(async () => {
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
  }, 5000);
}

init();
