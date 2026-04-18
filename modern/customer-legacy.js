/* eslint-disable */
// Legacy customer display for old Android WebViews (e.g. Android 5.1.1).
// Keep this file ES5-compatible (no modules, no async/await, no arrow functions).

(function () {
  var API = "../php/modernapi.php";

  var els = {
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

  var state = {
    brokerUrl: null,
    ws: null,
    selectedPosId: null,
    idleTimer: null,
    qrActiveUntil: 0,
    qrTimer: null
  };

  function show(screen) {
    if (els.pairScreen) els.pairScreen.classList.add("hidden");
    if (els.displayScreen) els.displayScreen.classList.add("hidden");
    if (screen) screen.classList.remove("hidden");
  }

  function xhrJsonPost(url, body, cb) {
    try {
      var xhr = new XMLHttpRequest();
      xhr.open("POST", url, true);
      xhr.setRequestHeader("Content-Type", "application/json");
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        if (xhr.status < 200 || xhr.status >= 300) {
          cb(new Error("HTTP " + xhr.status), null);
          return;
        }
        try {
          cb(null, JSON.parse(xhr.responseText));
        } catch (e) {
          cb(e, null);
        }
      };
      xhr.send(JSON.stringify(body || {}));
    } catch (e) {
      cb(e, null);
    }
  }

  function api(cmd, body, cb) {
    xhrJsonPost(API + "?cmd=" + encodeURIComponent(cmd), body || {}, cb);
  }

  function setPairSelectLoading(label) {
    if (!els.pairSelect) return;
    els.pairSelect.innerHTML = "";
    var opt = document.createElement("option");
    opt.value = "";
    opt.textContent = label || "Suche...";
    opt.disabled = true;
    opt.selected = true;
    els.pairSelect.appendChild(opt);
  }

  function loadServerConfig(cb) {
    api("config", {}, function (err, data) {
      if (err || !data || data.status !== "OK") {
        state.brokerUrl = null;
        cb && cb(err || new Error("config failed"));
        return;
      }
      var url = data.broker_ws || null;
      if (url && (url.indexOf("127.0.0.1") !== -1 || url.indexOf("localhost") !== -1)) {
        try {
          var host = window.location.hostname;
          url = url.replace("127.0.0.1", host).replace("localhost", host);
        } catch (_) {}
      }
      state.brokerUrl = url;
      cb && cb(null);
    });
  }

  function connectBroker() {
    if (!state.brokerUrl) return;
    try {
      var ws = new WebSocket(state.brokerUrl);
      state.ws = ws;
      ws.onopen = function () {
        safeSend({ type: "REGISTER", role: "display" });
        requestPosList();
      };
      ws.onclose = function () {
        // POS went offline - clear saved POS ID and go to start screen
        state.selectedPosId = null;
        clearSavedPosId();
        show(els.pairScreen);
      };
      ws.onerror = function () {
        // POS went offline - clear saved POS ID and go to start screen
        state.selectedPosId = null;
        clearSavedPosId();
        show(els.pairScreen);
      };
      ws.onmessage = function (evt) {
        var msg = null;
        try { msg = JSON.parse(evt.data); } catch (_) { return; }
        if (!msg || !msg.type) return;
        if (msg.type === "POS_LIST") return handlePosList(msg.list || []);
        if (msg.type === "REGISTERED" && msg.list) return handlePosList(msg.list || []);
        if (msg.type === "DISPLAY_UPDATE") return handleDisplayUpdate(msg);
        if (msg.type === "DISPLAY_EBON") return handleEbon(msg);
        if (msg.type === "DISPLAY_IDLE") {
          if (!(state.qrActiveUntil && Date.now() < state.qrActiveUntil)) showIdle();
        }
        if (msg.type === "POS_OFFLINE") {
          // POS went offline - clear saved POS ID and go to start screen
          if (msg.posId === state.selectedPosId) {
            state.selectedPosId = null;
            clearSavedPosId();
            show(els.pairScreen);
          }
        }
      };
    } catch (_) {
      show(els.pairScreen);
    }
  }

  function safeSend(obj) {
    try {
      if (state.ws && state.ws.readyState === WebSocket.OPEN) {
        state.ws.send(JSON.stringify(obj));
      }
    } catch (_) {}
  }

  function requestPosList() {
    safeSend({ type: "REQUEST_POS_LIST" });
  }

  function handlePosList(list) {
    if (!list || !list.length) {
      show(els.pairScreen);
      setPairSelectLoading("Keine Kasse online");
      if (els.pairApply) els.pairApply.onclick = null;
      state.selectedPosId = null;
      updateDisplayHash();
      return;
    }

    // If already connected to a POS, don't interrupt - just update the list
    if (state.selectedPosId) {
      // Check if currently connected POS is still in the list
      var stillConnected = false;
      for (var i = 0; i < list.length; i++) {
        if (list[i].id === state.selectedPosId) {
          stillConnected = true;
          break;
        }
      }
      if (stillConnected) {
        // Stay connected, don't show selection screen
        return;
      }
      // If connected POS went offline, show selection screen
    }

    // Always show selection screen - user must manually select POS
    show(els.pairScreen);
    els.pairSelect.innerHTML = "";
    for (var i = 0; i < list.length; i++) {
      var p = list[i];
      var label = ((p.clientName || ("broker" + p.id)) + " " + (p.userName || "") + " " + (p.deviceId || "")).replace(/\s+/g, " ").trim();
      var opt = document.createElement("option");
      opt.value = String(p.id);
      opt.dataset.clientName = p.clientName || "";
      opt.textContent = label;
      els.pairSelect.appendChild(opt);
    }
    if (els.pairApply) {
      els.pairApply.onclick = function () {
        var val = Number(els.pairSelect.value);
        var clientName = els.pairSelect.options[els.pairSelect.selectedIndex].dataset.clientName || "";
        if (val) subscribeToPos(val, clientName);
      };
    }
  }

  function subscribeToPos(posId, clientName) {
    state.selectedPosId = posId;
    savePosId(posId, clientName);
    safeSend({ type: "SUBSCRIBE", posId: posId, clientName: clientName });
    show(els.displayScreen);
    updateDisplayHash();
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
      var saved = localStorage.getItem("customer_selected_pos_id");
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

  function updateDisplayHash() {
    var el = document.getElementById("display-hash");
    if (!el) return;
    if (state.selectedPosId) {
      var clientName = loadSavedClientName();
      el.textContent = clientName ? ("Client: " + clientName) : ("broker" + state.selectedPosId);
      el.style.cursor = "pointer";
      el.onclick = openPosSelector;
    } else {
      el.textContent = "customer-legacy";
      el.style.cursor = "default";
      el.onclick = null;
    }
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

  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function clearIdleTimer() {
    if (state.idleTimer) clearTimeout(state.idleTimer);
    state.idleTimer = null;
  }

  function startIdleTimer() {
    clearIdleTimer();
    state.idleTimer = setTimeout(function () {
      if (state.qrActiveUntil && Date.now() < state.qrActiveUntil) return;
      showIdle();
      if (els.displayQr) els.displayQr.classList.add("hidden");
      state.qrActiveUntil = 0;
    }, 30000);
  }

  function showIdle() {
    if (els.displayIdle) els.displayIdle.classList.remove("hidden");
    if (els.displayIdleLogo) {
      els.displayIdleLogo.classList.remove("hidden");
      if (els.displayIdleText) els.displayIdleText.classList.add("hidden");
      els.displayIdleLogo.onload = function () {
        if (els.displayIdleText) els.displayIdleText.classList.add("hidden");
      };
      els.displayIdleLogo.onerror = function () {
        els.displayIdleLogo.classList.add("hidden");
        if (els.displayIdleText) els.displayIdleText.classList.remove("hidden");
      };
    } else if (els.displayIdleText) {
      els.displayIdleText.classList.remove("hidden");
    }
  }

  function hideIdle() {
    if (els.displayIdle) els.displayIdle.classList.add("hidden");
  }

  function clearQrLock() {
    state.qrActiveUntil = 0;
    if (state.qrTimer) clearTimeout(state.qrTimer);
    state.qrTimer = null;
    if (els.displayQr) els.displayQr.classList.add("hidden");
  }

  function handleDisplayUpdate(msg) {
    var hasActivity = msg && msg.activity === "product";
    if (state.qrActiveUntil && Date.now() < state.qrActiveUntil) {
      if (!hasActivity) return;
      clearQrLock();
    }

    clearIdleTimer();
    if (els.displayQr) els.displayQr.classList.add("hidden");

    if (msg.mode === "order") {
      if (els.displayWrap) {
        els.displayWrap.classList.add("mode-order");
        els.displayWrap.classList.remove("mode-paydesk");
        els.displayWrap.classList.remove("bon-full");
      }
      var payload = msg.payload || { items: [], sum: "0.00" };
      if (els.displaySum) {
        els.displaySum.innerHTML =
          '<span class="display-sum-label">Summe</span>&nbsp;&nbsp;' +
          '<span class="display-sum-value">' + escapeHtml(payload.sum || "0.00") + "</span>&nbsp;" +
          '<span class="display-sum-currency">€</span>';
      }
      if (els.displayBonTitle) els.displayBonTitle.textContent = "Bestellung:";
      if (els.displayBonList) {
        var items = payload.items || [];
        var html = "";
        for (var i = 0; i < items.length; i++) {
          var it = items[i] || {};
          html += '<div class="display-bon-item">';
          html += '<div class="display-row"><span>' + escapeHtml(it.qty) + "x " + escapeHtml(it.name) + "</span><span>";
          var price = Number(it.price || 0);
          html += escapeHtml(price.toFixed(2)) + "</span></div>";
          if (it.extras && it.extras.length) {
            for (var j = 0; j < it.extras.length; j++) {
              html += '<div class="display-extra">+ ' + escapeHtml(it.extras[j]) + "</div>";
            }
          }
          html += "</div>";
        }
        els.displayBonList.innerHTML = html;
      }
      if (els.displayOrderTitle) els.displayOrderTitle.textContent = "";
      if (els.displayOrderList) els.displayOrderList.innerHTML = "";
      hideIdle();
      startIdleTimer();
      return;
    }

    if (msg.mode === "paydesk") {
      if (els.displayWrap) {
        els.displayWrap.classList.add("mode-paydesk");
        els.displayWrap.classList.remove("mode-order");
      }
      var payload2 = msg.payload || { bonItems: [], openItems: [], sum: "0.00" };
      if (els.displaySum) {
        els.displaySum.innerHTML =
          '<span class="display-sum-label">Summe</span>&nbsp;&nbsp;' +
          '<span class="display-sum-value">' + escapeHtml(payload2.sum || "0.00") + "</span>&nbsp;" +
          '<span class="display-sum-currency">€</span>';
      }
      if (els.displayBonTitle) els.displayBonTitle.textContent = "Sie bezahlen:";

      var bonItems = payload2.bonItems || [];
      var bonHtml = "";
      for (var k = 0; k < bonItems.length; k++) {
        var bi = bonItems[k] || {};
        bonHtml += '<div class="display-bon-item">';
        bonHtml += '<div class="display-row"><span>' + escapeHtml(bi.qty) + "x " + escapeHtml(bi.name) + "</span><span>";
        var biPrice = Number(bi.price || 0) + Number(bi.extrasSum || 0);
        bonHtml += escapeHtml(biPrice.toFixed(2)) + "</span></div>";
        if (bi.extras && bi.extras.length) {
          for (var m = 0; m < bi.extras.length; m++) {
            var ex = bi.extras[m] || {};
            if (!ex.name) continue;
            // Legacy payload sometimes prefixes like "2 x Extra"; strip that.
            var exName = String(ex.name).replace(/^\s*\d+\s*x\s*/i, "");
            bonHtml += '<div class="display-extra">+ ' + escapeHtml(ex.amount) + " " + escapeHtml(exName) + "</div>";
          }
        } else if (Number(bi.extrasSum || 0) > 0) {
          bonHtml += '<div class="display-extra">+ Extras</div>';
        }
        bonHtml += "</div>";
      }
      if (els.displayBonList) els.displayBonList.innerHTML = bonHtml;

      if (els.displayOrderTitle) els.displayOrderTitle.textContent = "";
      if (els.displayOrderList) {
        var openItems = payload2.openItems || [];
        var openHtml = "";
        for (var n = 0; n < openItems.length; n++) {
          var oi = openItems[n] || {};
          openHtml += '<span class="display-order-item">' + escapeHtml(oi.qty) + "x " + escapeHtml(oi.name) + "</span>";
        }
        els.displayOrderList.innerHTML = openHtml;
      }
      hideIdle();
      startIdleTimer();

      // Rough overflow detection without requestAnimationFrame dependency.
      setTimeout(function () {
        try {
          if (!els.displayWrap || !els.displayBonList) return;
          var isFull = els.displayBonList.scrollHeight > els.displayBonList.clientHeight + 2;
          els.displayWrap.classList.toggle("bon-full", !!isFull);
        } catch (_) {}
      }, 50);
    }
  }

  function handleEbon(msg) {
    var ebonUrl = msg.ebonUrl || "";
    var ebonRef = msg.ebonRef || "";
    if (!ebonUrl || !ebonRef) return;

    var link = ebonUrl.replace(/\/$/, "") + "/index.php?ebonref=" + encodeURIComponent(ebonRef);
    if (els.displayQrImg) {
      els.displayQrImg.src = "../php/utilities/osqrcode.php?cmd=link&arg=" + encodeURIComponent(link);
    }
    if (els.displayQrLink) els.displayQrLink.textContent = link;
    if (els.displayQr) els.displayQr.classList.remove("hidden");
    hideIdle();

    state.qrActiveUntil = Date.now() + 30000;
    clearIdleTimer();
    if (state.qrTimer) clearTimeout(state.qrTimer);
    state.qrTimer = setTimeout(function () {
      showIdle();
      if (els.displayQr) els.displayQr.classList.add("hidden");
      state.qrActiveUntil = 0;
    }, 30000);
  }

  function init() {
    show(els.pairScreen);
    setPairSelectLoading("Suche...");
    updateDisplayHash();

    if (els.pairRefresh) {
      els.pairRefresh.onclick = function () {
        setPairSelectLoading("Suche...");
        if (!state.ws || state.ws.readyState !== state.ws.OPEN) {
          connectBroker();
        } else {
          requestPosList();
        }
      };
    }

    loadServerConfig(function () {
      connectBroker();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
