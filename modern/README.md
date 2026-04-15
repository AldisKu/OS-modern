# OrderSprinter Modern Client

## Komponenten
- PHP Wrapper API: `webapp/php/modernapi.php`
- Broker: `broker/server.js`
- UI: `modern/index.html`

## Installation (Server)
1. Broker installieren und starten
```bash
cd broker
./install.sh
```
Optional ohne Service:
```bash
node server.js
```

2. Client bereitstellen
- Öffne `modern/index.html` im iPad-Browser.
- Optional als Homescreen-App hinzufügen.

## API (modernapi.php)
- `login` { userid, password, modus, time }
- `logout` {}
- `session` {}
- `bootstrap` {} (einmal beim Login, liefert alles)
- `refresh_tables` {} (Tischdaten, bei Broker-Push oder Poll-Fallback)
- `refresh_menu` {} (Menü/Preise, bei Preisstufen-Änderung)
- `order` { tableid, prods, print, payprinttype, orderoption }
- `table_open_items` { tableid }
- `table_records` { tableid }

## Lokale Datenhaltung
- Menü, Tische, Config: im Speicher (`state.*`), geladen via `bootstrap`, aktualisiert via Broker-Push.
- Warenkorb pro Tisch in `localStorage` (`cart_<tableid>`)
- Kein IndexedDB, kein Service Worker — App ist online-only.

## Broker-Konfiguration
Optional: Setze `BROKER_TOKEN` in `webapp/broker/server.js`/Service und sende `X-Broker-Token`.

## Hinweise
- UI nutzt bestehende PHP-Session und Rechteverwaltung.
- Updates laufen über Broker-Push (primär) und Client-Poll (Fallback, Default 120s).
