# Changelog (Modern Client)

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
