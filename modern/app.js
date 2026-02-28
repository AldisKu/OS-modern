const API = "../php/modernapi.php";
let brokerUrl = window.localStorage.getItem("os_broker") || "ws://localhost:3077";
const DB_NAME = "ordersprinter-modern";
const DB_VERSION = 1;

const els = {
  loginPanel: document.getElementById("login-panel"),
  appPanel: document.getElementById("app-panel"),
  loginBtn: document.getElementById("login-btn"),
  loginUser: document.getElementById("login-user"),
  loginPass: document.getElementById("login-pass"),
  loginHint: document.getElementById("login-hint"),
  refreshAll: document.getElementById("refresh-all"),
  refreshTables: document.getElementById("refresh-tables"),
  refreshMenu: document.getElementById("refresh-menu"),
  refreshOpenItems: document.getElementById("refresh-open-items"),
  refreshRecords: document.getElementById("refresh-records"),
  roomsContainer: document.getElementById("rooms-container"),
  togoContainer: document.getElementById("togo-container"),
  userInfo: document.getElementById("user-info"),
  badgeConn: document.getElementById("badge-connection"),
  badgeBroker: document.getElementById("badge-broker"),
  badgeSync: document.getElementById("badge-sync"),
  logoutBtn: document.getElementById("logout-btn"),
  menuTypes: document.getElementById("menu-types"),
  menuItems: document.getElementById("menu-items"),
  menuSearch: document.getElementById("menu-search"),
  tableSelected: document.getElementById("table-selected"),
  cartItems: document.getElementById("cart-items"),
  cartMeta: document.getElementById("cart-meta"),
  cartTotal: document.getElementById("cart-total"),
  sendOrder: document.getElementById("send-order"),
  clearCart: document.getElementById("clear-cart"),
  printOrder: document.getElementById("print-order"),
  openItems: document.getElementById("open-items"),
  records: document.getElementById("records"),
  modal: document.getElementById("product-modal"),
  modalTitle: document.getElementById("modal-title"),
  modalClose: document.getElementById("modal-close"),
  modalQty: document.getElementById("modal-qty"),
  modalNote: document.getElementById("modal-note"),
  modalTogo: document.getElementById("modal-togo"),
  modalExtras: document.getElementById("modal-extras"),
  modalAdd: document.getElementById("modal-add")
};

let db;
let state = {
  user: null,
  config: null,
  menu: null,
  rooms: null,
  selectedType: null,
  selectedTable: null,
  cart: []
};

function setBadge(el, text, ok) {
  el.textContent = text;
  el.style.background = ok ? "#e5f6ef" : "#ffe7e7";
  el.style.color = ok ? "#1b7f5c" : "#d83f3f";
  el.style.borderColor = ok ? "#b3e2d2" : "#f0b5b5";
}

async function init() {
  db = await openDb();
  await warmFromCache();
  await loadServerConfig();
  await checkSession();
  setupHandlers();
  initBroker();
}

function setupHandlers() {
  els.loginBtn.addEventListener("click", login);
  els.refreshAll.addEventListener("click", bootstrap);
  els.refreshTables.addEventListener("click", refreshTables);
  els.refreshMenu.addEventListener("click", refreshMenu);
  els.refreshOpenItems.addEventListener("click", refreshOpenItems);
  els.refreshRecords.addEventListener("click", refreshRecords);
  els.logoutBtn.addEventListener("click", logout);
  els.menuSearch.addEventListener("input", () => renderMenuItems());
  els.sendOrder.addEventListener("click", sendOrder);
  els.clearCart.addEventListener("click", clearCart);
  els.modalClose.addEventListener("click", closeModal);
  els.modalAdd.addEventListener("click", addModalToCart);
}

async function api(cmd, body) {
  const res = await fetch(`${API}?cmd=${cmd}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {})
  });
  return res.json();
}

async function loadServerConfig() {
  try {
    const data = await api("config", {});
    if (data.status === "OK" && data.broker_ws) {
      brokerUrl = data.broker_ws;
      window.localStorage.setItem("os_broker", brokerUrl);
    }
  } catch (_) {
    // ignore - fallback to localStorage/default
  }
}

async function login() {
  const userid = Number(els.loginUser.value || 0);
  const password = els.loginPass.value || "";
  if (!userid || !password) {
    els.loginHint.textContent = "Bitte Benutzer-ID und Passwort eingeben.";
    return;
  }
  els.loginHint.textContent = "Anmeldung...";
  const payload = await api("login", { userid, password, modus: 0, time: Math.floor(Date.now() / 1000) });
  if (payload.status === "YES") {
    els.loginHint.textContent = "";
    await bootstrap();
  } else {
    els.loginHint.textContent = "Login fehlgeschlagen.";
  }
}

async function logout() {
  await api("logout", {});
  els.appPanel.classList.add("hidden");
  els.loginPanel.classList.remove("hidden");
}

async function checkSession() {
  const data = await api("session", {});
  if (data.loggedIn) {
    await bootstrap();
  } else {
    els.loginPanel.classList.remove("hidden");
    els.appPanel.classList.add("hidden");
  }
}

async function bootstrap() {
  const data = await api("bootstrap", {});
  if (data.status !== "OK") {
    setBadge(els.badgeConn, "Offline", false);
    return;
  }
  setBadge(els.badgeConn, "Online", true);
  els.loginPanel.classList.add("hidden");
  els.appPanel.classList.remove("hidden");
  setBadge(els.badgeSync, "Sync OK", true);

  state.user = data.user;
  state.config = data.config;
  state.menu = data.menu;
  state.rooms = data.rooms;

  if (state.user) {
    els.userInfo.textContent = `${state.user.name || ""} (#${state.user.id})`;
  }

  if (!state.selectedType && state.menu && state.menu.types && state.menu.types.length) {
    state.selectedType = state.menu.types[0].id;
  }

  await saveCache("config", state.config);
  await saveCache("menu", state.menu);
  await saveCache("rooms", state.rooms);

  renderRooms();
  renderTogo();
  renderMenuTypes();
  renderMenuItems();
  await refreshOpenItems();
  await refreshRecords();
}

async function refreshTables() {
  const data = await api("refresh_tables", {});
  if (data.status === "OK") {
    state.rooms = data.rooms;
    await saveCache("rooms", state.rooms);
    renderRooms();
    renderTogo();
    setBadge(els.badgeSync, "Tische aktualisiert", true);
  }
}

async function refreshMenu() {
  const data = await api("refresh_menu", {});
  if (data.status === "OK") {
    state.menu = data.menu;
    await saveCache("menu", state.menu);
    if (!state.selectedType && state.menu.types && state.menu.types.length) {
      state.selectedType = state.menu.types[0].id;
    }
    renderMenuTypes();
    renderMenuItems();
    setBadge(els.badgeSync, "Menu aktualisiert", true);
  }
}

function renderRooms() {
  if (!state.rooms || !state.rooms.roomstables) {
    return;
  }
  const html = state.rooms.roomstables.map(room => {
    const tables = (room.tables || []).map(t => {
      const price = t.pricesum ? String(t.pricesum) : "0.00";
      const active = state.selectedTable && state.selectedTable.id === t.id ? "active" : "";
      return `
        <div class="table ${active}" data-table="${t.id}" data-name="${t.name}">
          <div class="name">${t.name}</div>
          <div class="meta">${t.prodcount || 0} | ${price}</div>
        </div>
      `;
    }).join("");

    return `
      <div class="room">
        <h3>${room.name}</h3>
        <div class="tables">${tables}</div>
      </div>
    `;
  }).join("");

  els.roomsContainer.innerHTML = html;
  els.roomsContainer.querySelectorAll(".table").forEach(el => {
    el.addEventListener("click", () => selectTable({
      id: Number(el.dataset.table),
      name: el.dataset.name,
      togo: false
    }));
  });
}

function renderTogo() {
  if (!state.rooms) {
    return;
  }
  const price = state.rooms.takeawayprice ? String(state.rooms.takeawayprice) : "0.00";
  const prodcount = state.rooms.takeawayprodcount || 0;
  const active = state.selectedTable && state.selectedTable.id === 0 ? "active" : "";
  els.togoContainer.innerHTML = `
    <div class="table ${active}" data-table="0" data-name="To-Go">
      <div class="name">To-Go</div>
      <div class="meta">${prodcount} | ${price}</div>
    </div>
  `;
  els.togoContainer.querySelector(".table").addEventListener("click", () => {
    selectTable({ id: 0, name: "To-Go", togo: true });
  });
}

function renderMenuTypes() {
  if (!state.menu || !state.menu.types) {
    return;
  }
  const html = state.menu.types.map(t => {
    const active = Number(state.selectedType) === Number(t.id) ? "active" : "";
    return `<span class="type-chip ${active}" data-type="${t.id}">${t.name}</span>`;
  }).join("");
  els.menuTypes.innerHTML = html;
  els.menuTypes.querySelectorAll(".type-chip").forEach(chip => {
    chip.addEventListener("click", () => {
      state.selectedType = Number(chip.dataset.type);
      renderMenuTypes();
      renderMenuItems();
    });
  });
}

function renderMenuItems() {
  if (!state.menu || !state.menu.prods) {
    return;
  }
  const query = els.menuSearch.value.trim().toLowerCase();
  const items = state.menu.prods.filter(p => {
    const matchType = state.selectedType ? Number(p.ref) === Number(state.selectedType) : true;
    const matchQuery = query === "" || (p.longname || p.name || "").toLowerCase().includes(query);
    return matchType && matchQuery;
  });

  els.menuItems.innerHTML = items.map(p => `
    <div class="menu-item" data-prodid="${p.id}">
      <div class="name">${p.longname || p.name}</div>
      <div class="price">${p.price}</div>
    </div>
  `).join("");

  els.menuItems.querySelectorAll(".menu-item").forEach(el => {
    const prodid = Number(el.dataset.prodid);
    const prod = state.menu.prods.find(p => p.id === prodid);
    el.addEventListener("click", () => {
      if (!prod) {
        setBadge(els.badgeSync, "Produkt nicht gefunden", false);
        return;
      }
      openProductModal(prod);
    });
  });
}

function selectTable(table) {
  state.selectedTable = table;
  els.tableSelected.textContent = table.name;
  loadCartForTable();
  renderRooms();
  renderTogo();
  refreshOpenItems();
  refreshRecords();
}

function loadCartForTable() {
  if (!state.selectedTable) {
    state.cart = [];
    renderCart();
    return;
  }
  const key = `cart_${state.selectedTable.id}`;
  const raw = window.localStorage.getItem(key);
  if (raw) {
    try {
      state.cart = JSON.parse(raw) || [];
    } catch (_) {
      state.cart = [];
    }
  } else {
    state.cart = [];
  }
  renderCart();
}

function persistCart() {
  if (!state.selectedTable) {
    return;
  }
  const key = `cart_${state.selectedTable.id}`;
  window.localStorage.setItem(key, JSON.stringify(state.cart));
}

function openProductModal(prod) {
  if (!prod) {
    setBadge(els.badgeSync, "Produkt nicht gefunden", false);
    return;
  }
  if (!state.selectedTable) {
    setBadge(els.badgeSync, "Bitte Tisch wählen", false);
    return;
  }
  state.modalProduct = prod;
  els.modalTitle.textContent = prod.longname || prod.name;
  els.modalQty.value = 1;
  els.modalNote.value = "";
  els.modalTogo.checked = state.selectedTable.togo;
  const extras = (prod.extras || []).map(e => `
    <label class="toggle">
      <input type="checkbox" data-extra="${e.extraid || e.id}" data-name="${e.name}" data-price="${e.price}" />
      <span>${e.name} (+${e.price})</span>
    </label>
  `).join("");
  els.modalExtras.innerHTML = extras || "<div class=\"meta\">Keine Extras</div>";
  els.modal.classList.remove("hidden");
}

function closeModal() {
  els.modal.classList.add("hidden");
}

function addModalToCart() {
  if (!state.modalProduct) {
    return;
  }
  const qty = Number(els.modalQty.value || 1);
  const note = els.modalNote.value.trim();
  const togo = els.modalTogo.checked ? 1 : 0;
  const extras = Array.from(els.modalExtras.querySelectorAll("input[type=checkbox]:checked")).map(el => ({
    id: Number(el.dataset.extra),
    name: el.dataset.name,
    price: Number(el.dataset.price || 0),
    amount: 1
  }));

  state.cart.push({
    prodid: state.modalProduct.id,
    name: state.modalProduct.longname || state.modalProduct.name,
    price: Number(state.modalProduct.price || 0),
    unit: Number(state.modalProduct.unit || 0),
    unitamount: qty,
    note,
    togo,
    extras
  });

  persistCart();
  renderCart();
  closeModal();
}

function renderCart() {
  if (!state.cart) {
    return;
  }
  const html = state.cart.map((item, idx) => {
    const extras = item.extras.map(e => e.name).join(", ");
    const togo = item.togo ? "To-Go" : "";
    return `
      <div class="cart-item">
        <div class="name">${item.name} x${item.unitamount}</div>
        <div class="meta">${extras} ${item.note ? "| " + item.note : ""} ${togo}</div>
        <div class="actions">
          <button class="ghost" data-dec="${idx}">-</button>
          <button class="ghost" data-inc="${idx}">+</button>
          <button class="ghost" data-del="${idx}">Entfernen</button>
        </div>
      </div>
    `;
  }).join("");
  els.cartItems.innerHTML = html || "<div class=\"meta\">Warenkorb ist leer</div>";

  els.cartItems.querySelectorAll("button[data-dec]").forEach(btn => {
    btn.addEventListener("click", () => {
      const idx = Number(btn.dataset.dec);
      if (state.cart[idx].unitamount > 1) {
        state.cart[idx].unitamount -= 1;
      }
      persistCart();
      renderCart();
    });
  });
  els.cartItems.querySelectorAll("button[data-inc]").forEach(btn => {
    btn.addEventListener("click", () => {
      const idx = Number(btn.dataset.inc);
      state.cart[idx].unitamount += 1;
      persistCart();
      renderCart();
    });
  });
  els.cartItems.querySelectorAll("button[data-del]").forEach(btn => {
    btn.addEventListener("click", () => {
      const idx = Number(btn.dataset.del);
      state.cart.splice(idx, 1);
      persistCart();
      renderCart();
    });
  });

  const total = state.cart.reduce((sum, item) => {
    const extras = item.extras.reduce((esum, e) => esum + Number(e.price || 0), 0);
    return sum + (Number(item.price || 0) + extras) * Number(item.unitamount || 1);
  }, 0);

  els.cartMeta.textContent = `${state.cart.length} Artikel`;
  els.cartTotal.textContent = total.toFixed(2);
}

async function sendOrder() {
  if (!state.selectedTable) {
    setBadge(els.badgeSync, "Bitte Tisch wählen", false);
    return;
  }
  if (state.cart.length === 0) {
    setBadge(els.badgeSync, "Warenkorb ist leer", false);
    return;
  }

  const prods = state.cart.map(item => ({
    name: item.name,
    option: item.note || "",
    extras: item.extras.map(e => ({ id: e.id, name: e.name, amount: e.amount })),
    prodid: item.prodid,
    price: item.price,
    changedPrice: "NO",
    togo: item.togo,
    unit: item.unit,
    unitamount: item.unitamount,
    phase: 0,
    isminusarticle: 0
  }));

  const result = await api("order", {
    tableid: state.selectedTable.id,
    prods,
    print: els.printOrder.checked ? 1 : 0,
    payprinttype: "s",
    orderoption: ""
  });

  if (result.status === "OK") {
    state.cart = [];
    persistCart();
    renderCart();
    setBadge(els.badgeSync, "Bestellung gesendet", true);
    refreshTables();
    refreshOpenItems();
    refreshRecords();
  } else {
    setBadge(els.badgeSync, result.msg || "Fehler beim Senden", false);
  }
}

function clearCart() {
  state.cart = [];
  persistCart();
  renderCart();
}

async function refreshOpenItems() {
  if (!state.selectedTable) {
    els.openItems.innerHTML = "<div class=\"meta\">Kein Tisch gewählt</div>";
    return;
  }
  const data = await api("table_open_items", { tableid: state.selectedTable.id });
  if (data.status !== "OK") {
    els.openItems.innerHTML = "<div class=\"meta\">Keine Rechte oder Fehler</div>";
    return;
  }
  const items = data.msg || [];
  els.openItems.innerHTML = items.map(i => `
    <div class="open-item">
      <div>${i.productname}</div>
      <div class="meta">${i.status}</div>
    </div>
  `).join("") || "<div class=\"meta\">Keine offenen Positionen</div>";
}

async function refreshRecords() {
  if (!state.selectedTable) {
    els.records.innerHTML = "<div class=\"meta\">Kein Tisch gewählt</div>";
    return;
  }
  const data = await api("table_records", { tableid: state.selectedTable.id });
  if (data.status !== "OK") {
    els.records.innerHTML = "<div class=\"meta\">Keine Rechte oder Fehler</div>";
    return;
  }
  const items = data.msg || [];
  els.records.innerHTML = items.map(r => `
    <div class="record">
      <div>${r.time} | ${r.username} | ${r.action}</div>
      <div class="meta">${(r.prods || []).map(p => p.name).join(", ")}</div>
    </div>
  `).join("") || "<div class=\"meta\">Keine Records</div>";
}

async function warmFromCache() {
  const rooms = await loadCache("rooms");
  const menu = await loadCache("menu");
  if (rooms) {
    state.rooms = rooms;
    renderRooms();
    renderTogo();
  }
  if (menu) {
    state.menu = menu;
    if (!state.selectedType && menu.types && menu.types.length) {
      state.selectedType = menu.types[0].id;
    }
    renderMenuTypes();
    renderMenuItems();
  }
}

function initBroker() {
  try {
    const ws = new WebSocket(brokerUrl);
    ws.onopen = () => setBadge(els.badgeBroker, "Broker OK", true);
    ws.onclose = () => setBadge(els.badgeBroker, "Broker Offline", false);
    ws.onerror = () => setBadge(els.badgeBroker, "Broker Fehler", false);
    ws.onmessage = (event) => {
      let msg = {};
      try { msg = JSON.parse(event.data); } catch (_) { msg = {}; }
      if (msg.type === "UPDATE_REQUIRED" && msg.scope === "TABLES") {
        refreshTables();
      }
    };
  } catch (e) {
    setBadge(els.badgeBroker, "Broker Fehler", false);
  }
}

function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains("cache")) {
        db.createObjectStore("cache", { keyPath: "key" });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

function saveCache(key, value) {
  return new Promise((resolve, reject) => {
    const tx = db.transaction("cache", "readwrite");
    tx.objectStore("cache").put({ key, value, ts: Date.now() });
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

function loadCache(key) {
  return new Promise((resolve, reject) => {
    const tx = db.transaction("cache", "readonly");
    const req = tx.objectStore("cache").get(key);
    req.onsuccess = () => resolve(req.result ? req.result.value : null);
    req.onerror = () => reject(req.error);
  });
}

init();
