# Changelog (Modern Client)

## 2026-02-26
- Added `php/modernapi.php` wrapper endpoints: `login`, `logout`, `session`, `bootstrap`, `refresh_tables`, `refresh_menu`, `order`, `table_open_items`, `table_records`.
- Added broker service for WebSocket fanout and HTTP event ingestion.
- Added iPad-optimized modern client with IndexedDB cache, table view, ordering flow, cart, and broker listener.
- Added broker install script and service template.

## Notes
- Core interactions remain in `modernapi.php` and broker polling.
