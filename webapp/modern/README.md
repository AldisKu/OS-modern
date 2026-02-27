# OrderSprinter Modern Client

## Komponenten
- PHP Wrapper API: `webapp/php/modernapi.php`
- PHP Plugin Emitter: `webapp/plugins/OSEventBroker.php`
- Plugin Config: `webapp/plugins/config.json`
- Broker: `webapp/broker/server.js`
- UI: `webapp/modern/index.html`

## Installation (Server)
1. Broker installieren und starten
```bash
cd webapp/broker
./install.sh
```
Optional ohne Service:
```bash
node server.js
```

2. Plugin konfigurieren
- `webapp/plugins/config.json`
- Setze `broker_url` auf `http://<server-ip>:3077/event`

3. Client bereitstellen
- Öffne `webapp/modern/index.html` im iPad-Browser.
- Optional als Homescreen-App hinzufügen.

## API (modernapi.php)
- `login` { userid, password, modus, time }
- `logout` {}
- `session` {}
- `bootstrap` {}
- `refresh_tables` {}
- `refresh_menu` {}
- `order` { tableid, prods, print, payprinttype, orderoption }
- `table_open_items` { tableid }
- `table_records` { tableid }

## Lokale Datenhaltung
- IndexedDB: `ordersprinter-modern`
- Store: `cache` (`config`, `menu`, `rooms`)
- Warenkorb pro Tisch in `localStorage` (`cart_<tableid>`)

## Broker-Konfiguration
Optional: Setze `BROKER_TOKEN` in `webapp/broker/server.js`/Service und sende `X-Broker-Token`.

## Hinweise
- UI nutzt bestehende PHP-Session und Rechteverwaltung.
- Events: `afterOrderSaved` + `afterPayment` triggern `UPDATE_REQUIRED`.
