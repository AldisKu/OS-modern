# Changelog (Modern Client)

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
