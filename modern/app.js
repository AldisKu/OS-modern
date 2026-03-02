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
  paydeskItems: document.getElementById("paydesk-items"),
  paydeskTotal: document.getElementById("paydesk-total"),
  paydeskPayments: document.getElementById("paydesk-payments"),
  paydeskPay: document.getElementById("paydesk-pay"),
  paydeskTip: document.getElementById("paydesk-tip"),
  paydeskTableLabel: document.getElementById("paydesk-table-label"),

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
  paydeskTable: null,
  keyboardMode: "num",
  lastSync: "-"
};

function show(screen) {
  [els.loginScreen, els.startScreen, els.orderScreen, els.paydeskScreen].forEach(s => s.classList.add("hidden"));
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
  bindMenuButtons();
  bindLogin();
  bindModals();
  await loadServerConfig();
  await loadUsers();
  await checkSession();
  startPolling();
}

function bindMenuButtons() {
  document.querySelectorAll(".menu-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const action = btn.dataset.action;
      handleMenuAction(action);
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
  els.modalClose.addEventListener("click", () => els.productModal.classList.add("hidden"));
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
  updateStatus();
  renderTables();
  renderCategories();
  show(els.startScreen);
}

function updateStatus() {
  const name = state.user?.name || "-";
  [els.statusUser, els.orderUser, els.paydeskUser].forEach(el => el.textContent = name);
  [els.statusBroker, els.orderBroker].forEach(el => el.textContent = "poll" );
  [els.statusOnline, els.orderOnline, els.paydeskOnline].forEach(el => el.textContent = "OK");
  [els.statusSync, els.orderSync, els.paydeskSync].forEach(el => el.textContent = state.lastSync);
  [els.statusPrinter, els.orderPrinter].forEach(el => el.textContent = "-" );
  [els.statusTse, els.orderTse].forEach(el => el.textContent = "-" );
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
  const types = state.typeStack.length === 0 ? topLevelTypes() : childTypes(state.typeStack[state.typeStack.length - 1]);
  const btns = [];
  if (state.typeStack.length > 0) {
    btns.push(`<button class="category-btn" data-cat="start">Start</button>`);
  }
  btns.push(...types.map(t => `<button class="category-btn" data-cat="${t.id}">${t.name}</button>`));
  els.categoryRow.innerHTML = btns.join("");
  els.categoryRow.querySelectorAll(".category-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const id = btn.dataset.cat;
      if (id === "start") {
        state.typeStack = [];
        state.selectedType = topLevelTypes()[0]?.id || null;
      } else {
        state.typeStack.push(Number(id));
        state.selectedType = Number(id);
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
    const prod = state.menu.prods.find(p => p.id === id);
    el.addEventListener("click", () => openProductModal(prod));
  });
}

function renderTables() {
  if (!state.rooms?.roomstables) return;
  const cards = [];
  state.rooms.roomstables.forEach(room => {
    room.tables.forEach(t => {
      const sum = t.pricesum || "0.00";
      const unpaid = t.unpaidprodcount || 0;
      const total = t.prodcount || 0;
      cards.push(`
        <div class="table-card" data-id="${t.id}" data-name="${t.name}">
          <div class="name">${t.name}</div>
          <div class="meta">${unpaid}/${total} | ${sum}</div>
        </div>
      `);
    });
  });
  const togoSum = state.rooms.takeawayprice || "0.00";
  const togoUnpaid = state.rooms.takeawayunpaidprodcount || 0;
  const togoTotal = state.rooms.takeawayprodcount || 0;
  cards.push(`
    <div class="table-card" data-id="0" data-name="To-Go">
      <div class="name">To-Go</div>
      <div class="meta">${togoUnpaid}/${togoTotal} | ${togoSum}</div>
    </div>
  `);
  els.tablesGrid.innerHTML = cards.join("");
  els.tablesGrid.querySelectorAll(".table-card").forEach(el => {
    el.addEventListener("click", () => openOrderForTable({ id: Number(el.dataset.id), name: el.dataset.name }));
  });
}

async function openOrderForTable(table) {
  state.selectedTable = table;
  els.orderTableLabel.textContent = table.name;
  loadCart(table.id);
  await fetchExistingOrders();
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

function renderOrderItems() {
  const cart = state.cartByTable[state.selectedTable.id] || [];
  const existing = state.orderExisting || [];
  const parts = [];
  cart.forEach(item => {
    const extras = (item.extras || []).map(e => `<div class="order-extra">+ ${e.name}</div>`).join("");
    parts.push(`<div class="order-item new" data-cart="${item._id}"><b>${item.name}</b> x${item.unitamount}${extras}</div>`);
  });
  if (cart.length > 0 && existing.length > 0) {
    parts.push(`<div class="order-separator"></div>`);
  }
  existing.forEach(p => {
    const extras = (p.extras || []).map(e => `<div class="order-extra">+ ${e.name}</div>`).join("");
    const qty = Number(p.unitamount || 1);
    parts.push(`<div class="order-item"><b>${p.longname}</b> x${qty}${extras}</div>`);
  });
  els.orderItems.innerHTML = parts.join("");
  els.orderItems.querySelectorAll(".order-item.new").forEach(el => {
    el.addEventListener("click", () => {
      const id = Number(el.dataset.cart);
      editCartItem(id);
    });
  });
}

function openProductModal(prod) {
  if (!prod) {
    alert("Produkt nicht gefunden");
    return;
  }
  els.modalTitle.textContent = prod.longname || prod.name;
  els.modalQty.value = 1;
  els.modalNote.value = "";
  els.modalTogo.checked = state.selectedTable?.id === 0;
  const extras = (prod.extras || []).map(e => `
    <label><input type="checkbox" data-id="${e.extraid || e.id}" data-name="${e.name}" data-price="${e.price}" /> ${e.name} (+${e.price})</label>
  `).join("");
  els.modalExtras.innerHTML = extras || "Keine Extras";
  els.productModal.classList.remove("hidden");
  state.modalProduct = prod;
}

function addProductToCart() {
  const prod = state.modalProduct;
  if (!prod) return;
  const qty = Number(els.modalQty.value || 1);
  const extras = Array.from(els.modalExtras.querySelectorAll("input:checked")).map(el => ({
    id: Number(el.dataset.id),
    name: el.dataset.name,
    price: Number(el.dataset.price || 0),
    amount: 1
  }));
  const item = {
    _id: Date.now(),
    prodid: prod.id,
    name: prod.longname || prod.name,
    price: Number(prod.price || 0),
    unit: Number(prod.unit || 0),
    unitamount: qty,
    option: els.modalNote.value.trim(),
    togo: els.modalTogo.checked ? 1 : 0,
    extras
  };
  const cart = state.cartByTable[state.selectedTable.id] || [];
  cart.unshift(item);
  state.cartByTable[state.selectedTable.id] = cart;
  saveCart(state.selectedTable.id);
  els.productModal.classList.add("hidden");
  renderOrderItems();
}

function editCartItem(id) {
  const cart = state.cartByTable[state.selectedTable.id] || [];
  const item = cart.find(c => c._id === id);
  if (!item) return;
  els.confirmTitle.textContent = item.name;
  els.confirmBody.innerHTML = `
    <div>Menge: ${item.unitamount}</div>
    <div>${item.option || ""}</div>
  `;
  els.confirmActions.innerHTML = `
    <button class="ghost" id="dec">-</button>
    <button class="ghost" id="inc">+</button>
    <button class="ghost" id="del">Löschen</button>
    <button class="primary" id="close">OK</button>
  `;
  els.confirmModal.classList.remove("hidden");
  els.confirmActions.querySelector("#dec").onclick = () => { if (item.unitamount > 1) item.unitamount -= 1; saveCart(state.selectedTable.id); renderOrderItems(); };
  els.confirmActions.querySelector("#inc").onclick = () => { item.unitamount += 1; saveCart(state.selectedTable.id); renderOrderItems(); };
  els.confirmActions.querySelector("#del").onclick = () => {
    state.cartByTable[state.selectedTable.id] = cart.filter(c => c._id !== id);
    saveCart(state.selectedTable.id);
    renderOrderItems();
    els.confirmModal.classList.add("hidden");
  };
  els.confirmActions.querySelector("#close").onclick = () => els.confirmModal.classList.add("hidden");
}

async function handleMenuAction(action) {
  if (action === "to-go") {
    openOrderForTable({ id: 0, name: "To-Go" });
  } else if (action === "paydesk") {
    await openPaydesk();
  } else if (action === "menu") {
    await openMenuModal();
  } else if (action === "start") {
    if (state.selectedTable) {
      const cart = state.cartByTable[state.selectedTable.id] || [];
      if (cart.length > 0) {
        showUnsavedDialog();
        return;
      }
    }
    show(els.startScreen);
  } else if (action === "send") {
    await sendOrder(false, true);
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
    changedPrice: "NO",
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
    if (goStart) show(els.startScreen);
  }
}

function showUnsavedDialog() {
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
    show(els.startScreen);
  };
  els.confirmActions.querySelector("#send").onclick = async () => {
    await sendOrder(false, true);
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

async function openPaydesk() {
  show(els.paydeskScreen);
  await loadPaydeskTables();
  await loadPayments();
}

async function loadPaydeskTables() {
  const data = await api("refresh_tables", {});
  if (data.status !== "OK") return;
  state.rooms = data.rooms;
  const tables = [];
  (state.rooms.roomstables || []).forEach(r => r.tables.forEach(t => { if (Number(t.unpaidprodcount || 0) > 0) tables.push(t); }));
  if (Number(state.rooms.takeawayunpaidprodcount || 0) > 0) tables.push({ id: 0, name: "To-Go" });
  els.paydeskTables.innerHTML = tables.map(t => `<div class="paydesk-table" data-id="${t.id}">${t.name}</div>`).join("");
  els.paydeskTables.querySelectorAll(".paydesk-table").forEach(el => {
    el.onclick = () => selectPaydeskTable(Number(el.dataset.id), el.textContent);
  });
}

async function selectPaydeskTable(id, name) {
  state.paydeskTable = { id, name };
  els.paydeskTableLabel.textContent = name;
  const data = await api("paydesk_items", { tableid: id });
  if (data.status === "OK") {
    state.paydeskItems = data.msg || [];
    renderPaydeskItems();
  }
}

function renderPaydeskItems() {
  const items = state.paydeskItems || [];
  let total = 0;
  els.paydeskItems.innerHTML = items.map(i => {
    const qty = Number(i.unitamount || 1);
    const line = Number(i.price || 0) * qty;
    total += line;
    return `<div class="paydesk-item">${i.longname} x${qty} - ${line.toFixed(2)}</div>`;
  }).join("");
  els.paydeskTotal.textContent = total.toFixed(2);
}

async function loadPayments() {
  const data = await api("payments", {});
  state.payments = data.payments || [];
  els.paydeskPayments.innerHTML = state.payments.map(p => `<label><input type="radio" name="pay" value="${p.id}" /> ${p.name}</label>`).join("");
}

els.paydeskPay?.addEventListener("click", async () => {
  if (!state.paydeskTable) return;
  const ids = state.paydeskItems.map(i => i.id).join(",");
  const payment = document.querySelector("input[name=pay]:checked");
  if (!payment) return;
  const tip = els.paydeskTip.value || 0;
  const res = await api("paydesk_pay", {
    ids,
    tableid: state.paydeskTable.id,
    paymentid: Number(payment.value),
    declareready: 0,
    host: 0,
    reservationid: "",
    guestinfo: "",
    intguestid: "",
    tip
  });
  if (res.status === "OK") {
    await loadPaydeskTables();
    els.paydeskItems.innerHTML = "";
    els.paydeskTotal.textContent = "0.00";
  }
});

async function changeTableFlow() {
  const table = state.selectedTable;
  if (!table) return;
  const data = await api("table_open_items", { tableid: table.id });
  if (data.status !== "OK") return;
  const items = data.msg || [];
  const ids = items.map(i => i.queueid).join(",");
  els.confirmTitle.textContent = "Tisch wechseln";
  const list = (state.rooms?.roomstables || []).flatMap(r => r.tables).map(t => `<button class="ghost" data-id="${t.id}">${t.name}</button>`).join("");
  els.confirmBody.innerHTML = "Ziel‑Tisch wählen";
  els.confirmActions.innerHTML = list;
  els.confirmModal.classList.remove("hidden");
  els.confirmActions.querySelectorAll("button").forEach(b => {
    b.onclick = async () => {
      const to = Number(b.dataset.id);
      await api("change_table", { fromTableId: table.id, toTableId: to, queueids: ids });
      els.confirmModal.classList.add("hidden");
      openOrderForTable({ id: to, name: b.textContent });
    };
  });
}

async function openMenuModal() {
  const data = await api("menu_items", {});
  if (data.menu) {
    const items = data.menu || [];
    els.menuItems.innerHTML = items.map(m => `<div><a href="${m.link}" target="_blank">${m.name}</a></div>`).join("");
  }
  els.menuModal.classList.remove("hidden");
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
