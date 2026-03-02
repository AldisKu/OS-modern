# OrderSprinter Broker

## Zweck
Der Broker verteilt Updates an alle iPads via WebSocket. Ohne Plugins nutzt er ein Polling gegen die Wrapper‑API und pusht bei Änderungen.

## Technische Voraussetzungen
- Node.js (empfohlen: LTS)
- Zugriff auf den Webserver (HTTP zu `modernapi.php`)
- systemd (für Service‑Betrieb, optional)

## Konfiguration (Polling)
Der Broker liest **Environment‑Variablen**:
- `POLL_URL` (Standard: `http://127.0.0.1/php/modernapi.php?cmd=state`)
- `POLL_INTERVAL_MS` (Standard: `4000`)
- `PORT` (Standard: `3077`)
- `BROKER_TOKEN` (optional, nur wenn du Auth nutzen willst)

**Empfohlen:** `POLL_URL` auf localhost lassen (Broker und Webserver laufen auf demselben Host).

## Start / Restart
### Einmalige Installation (systemd)
```bash
cd /var/www/webapp/broker
./install.sh
```

### Starten (Service)
```bash
sudo systemctl start ordersprinter-broker
```

### Neustart (Service)
```bash
sudo systemctl restart ordersprinter-broker
```

### Status
```bash
sudo systemctl status --no-pager ordersprinter-broker
```

### Manuell (ohne systemd)
```bash
cd /var/www/webapp/broker
node server.js
```

## Health Check
```bash
curl http://127.0.0.1:3077/health
```

## Hinweise
- Broker funktioniert **ohne** `broker.json`.
- `plugins/config.json` ist entfernt (keine Core‑Plugins aktiv).
- Der Modern‑Client aktualisiert zusätzlich alle 5 Sekunden (Polling im UI). Das ist der Fallback.
