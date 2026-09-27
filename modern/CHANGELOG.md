# Changelog (Modern Client)

## 2026-08-10 (v43) – ZVT Payment Terminal Integration
- Added: Broker-side ZVT module (broker/zvt/) — full ZVT protocol implementation:
  - zvt-codec.js: BCD, TLV, APDU framing, command builders/parsers
  - zvt-session.js: TCP session, serialized ZVT dialog, payment flow with receipts
  - terminal-manager.js: Registry, network discovery, polling, identity verification, locking
  - payment-service.js: Payment state machine, idempotency, receipt buffering, CUPS printing
  - index.js: Broker integration, WebSocket message routing
- Added: Terminal selector toggle buttons in paydesk (0 or 1 selected, persists in localStorage)
- Added: Card payment interception — if terminal selected, triggers ZVT payment via broker
- Added: Payment dialog (amount, status, "Betrag ändern" for tip, cancel, terminal busy handling)
- Added: Customer receipt prompt ("Kundenbeleg drucken? Ja/Nein") after successful payment
- Added: Receipt reprint support (PRINT_MERCHANT_RECEIPT, PRINT_CUSTOMER_RECEIPT)
- Added: Terminal picker for switching terminals when busy/unavailable
- Config: zvt_enabled, zvt.password, zvt.scanPorts, zvt.cidrs, etc. in config.json
- No terminal selected = manual mode (existing payment flow unchanged, SumUp fallback)
- Multiple POS can select same terminal; broker queues with TERMINAL_BUSY response
- Files: app.43.js, styles.43.css, broker/zvt/*, updated index.html, config.json, broker/server.js

## 2026-08-08 (v42) – Voucher System Integration
- Added: Voucher redeem flow — staff taps "Gutschein einlösen" product → modal with number entry, Bluetooth HID scanner, or camera QR scan → validates via voucher.cafekomine.de API → confirms redeem → adds product to cart.
- Added: Voucher sell flow — staff taps "Gutschein (Kauf)" product → confirms sale → receives voucher number/code from API → adds product to cart.
- Added: Variable voucher ("XXX Gutschein") — staff enters price, negative = redeem, positive = sell.
- Added: html5-qrcode library (v2.3.8) for iPad camera QR scanning.
- Added: voucher_api_url in config.json (https://voucher.cafekomine.de/api/v1).
- Files: app.42.js, styles.42.css, html5-qrcode.min.js, updated index.html and config.json.

## 2026-08-01 – Session Expiry Fix
- Fixed: iPad/Safari users getting "Benutzerrechte nicht ausreichend" after device sleep. Root cause: PHP session expired server-side (was 24 min), client still assumed logged-in state.
- Server: Increased `session.gc_maxlifetime` from 1440s (24 min) to 25200s (7 hours) in `/etc/php/8.1/apache2/php.ini`.
- Client (`app.js`): `api()` function now detects session expiry (error code "2") and redirects to login screen with message "Sitzung abgelaufen – bitte erneut anmelden" instead of showing a cryptic error.
- Server (`modernapi.php`): Order command now distinguishes between expired session and genuinely missing `right_waiter` permission — expired sessions return the standard auth error (code "2") so the client can handle it properly.

## 2026-04-16 (v23)
- Fixed: customer display now receives idle signal when POS finishes a task (order sent, payment completed).
- Fixed: customer display auto-idles after 30s of no updates (missing `startIdleTimer()` in `customer.js`).
- Fixed: POS sends explicit `DISPLAY_IDLE` after clearing cart (sendOrder) and after payment without ebon.
- Fixed: `customer.css` rewritten for old browser compatibility — replaced `inset`, `place-items`, `display: grid` with flex + explicit `top/left/right/bottom` and `-webkit-` prefixes.
- APP_VERSION bumped to 23.

## 2026-04-15 (v22)
- Reduced network traffic: `refreshMenuPrices` now uses `cmd=refresh_menu` instead of full `cmd=bootstrap`.
- Poll timer only calls `refresh_tables` when state hash changes (broker fallback), not unconditionally every cycle.
- Start screen uses cached table data; server refresh only on broker push signal.
- Paydesk picker and table list use cached `state.rooms` instead of fetching from server.
- `refreshTablesWithRetry` reduced from 4 to 2 server calls (immediate + one safety retry).
- APP_VERSION bumped to 22.

## 2026-02-26
- Added `php/modernapi.php` wrapper endpoints: `login`, `logout`, `session`, `bootstrap`, `refresh_tables`, `refresh_menu`, `order`, `table_open_items`, `table_records`.
- Added broker service for WebSocket fanout and HTTP event ingestion.
- Added iPad-optimized modern client with table view, ordering flow, cart, and broker listener.
- Added broker install script and service template.

## Notes
- Core interactions remain in `modernapi.php` and broker polling.
- Data is held in memory (`state.*`). No IndexedDB or Service Worker needed — app is online-only.
