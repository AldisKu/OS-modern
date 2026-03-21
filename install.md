# OrderSprinter Modern – Installation & Konfiguration (Produktiv)

Dieses Dokument beschreibt die Komponenten, Installationsschritte und Konfiguration für die Modern‑App, den Broker und das Kundendisplay.

## 1) Komponenten / Software

Server (Linux):
- Webserver: Apache2 (oder kompatibel)
- PHP (inkl. bestehender OrderSprinter‑Installation)
- Node.js (für WebSocket‑Broker)
- systemd (für Broker‑Service)

Client (iPad / Browser):
- Safari (Kundendisplay kann als Home‑Screen WebApp verwendet werden)
- Zugriff auf Server im lokalen Netzwerk

Zusätzliche Assets (optional):
- `modern/logo.png` (Kundenanzeige Logo)
- `modern/Koblenz-Book.woff2` (Kundendisplay Schrift)

## 2) Pfade & Struktur

- Webroot OrderSprinter: `/var/www/webapp` (Beispiel)
- Modern UI: `/var/www/webapp/modern/`
- Broker: `/home/aldis/ordersprinter/broker/`
- PHP API Wrapper: `/var/www/webapp/php/modernapi.php`
- Tischlayout: `/var/www/webapp/modern/table-layout.json`

## 3) Installation – Server

### 3.1 Code aus Git aktualisieren
```bash
cd /home/aldis/ordersprinter
# git pull (oder Ihre Update‑Routine)
```

### 3.2 Modern-Deployment (ohne Legacy im Git)
Für die Option A (nur Modern-Komponenten per Git, Legacy bleibt lokal):

```bash
cd /home/aldis/ordersprinter
sudo ./deploy-modern.sh
```

Der Installer sucht automatisch den Webroot (z. B. `/var/www/webapp`).  
Falls nötig, kann der Webroot explizit gesetzt werden:

```bash
sudo WEBROOT=/var/www/webapp ./deploy-modern.sh
```

### 3.2 Modern UI bereitstellen
Stelle sicher, dass der Webserver `modern/` ausliefert (z. B. via Apache Alias oder direkt im Webroot). Beispiel URL:
- `http://<SERVER-IP>/modern/`
- Kundendisplay: `http://<SERVER-IP>/modern/customer.html`

### 3.3 PHP Wrapper aktiv
Datei vorhanden:
- `/var/www/webapp/php/modernapi.php`

Diese Datei ist die zentrale API für Modern UI.

### 3.4 Broker installieren
Broker ist ein Node‑WebSocket‑Server.

Beispiel systemd‑Service (bereits vorhanden):
`/etc/systemd/system/ordersprinter-broker.service`

Wichtig: Broker muss laufen, sonst keine Push/Display‑Funktionen.

Broker starten / neu starten:
```bash
sudo systemctl restart ordersprinter-broker
sudo systemctl status ordersprinter-broker
```

Hinweis: Wenn Broker‑Code geändert wird, **Broker neu starten**.

## 4) Konfiguration

### 4.1 Broker Konfiguration
Broker nutzt Umgebungsvariablen (systemd oder `.env`):
- `PORT` (Default: 3077)
- `POLL_URL` (Default: `http://127.0.0.1/php/modernapi.php?cmd=state`)
- `POLL_INTERVAL_MS` (Default: 4000)
- `PRINTER_URL` (Default: `http://127.0.0.1/php/modernapi.php?cmd=printer_status`)

**Empfehlung produktiv:**
- Broker läuft auf dem Server, auf dem auch PHP läuft.
- Port 3077 im LAN erreichbar.

### 4.2 Modern UI Konfiguration
Die App lädt `broker_ws` aus `modernapi.php?cmd=config`.

Wichtig für iPad Home‑Screen WebApp:
- Wenn `broker_ws` `127.0.0.1` oder `localhost` enthält, wird es automatisch auf die aktuelle Host‑IP umgeschrieben.

### 4.3 Tischlayout (optional)
Datei:
- `/var/www/webapp/modern/table-layout.json`

Wenn vorhanden, wird das Rasterlayout verwendet. Wenn nicht, wird die Tabellenliste angezeigt.

### 4.4 Assets
- Logo: `/var/www/webapp/modern/logo.png`
- Schrift: `/var/www/webapp/modern/Koblenz-Book.woff2`

## 5) Client – iPad / Safari

### 5.1 Modern UI
- Aufrufen: `http://<SERVER-IP>/modern/`
- Login mit User/Passwort

### 5.2 Kundendisplay
- Aufrufen: `http://<SERVER-IP>/modern/customer.html`
- Bei mehreren Kassen: Kasse auswählen
- Bei 1 Kasse: automatische Anzeige

**Home‑Screen WebApp:**
- Seite in Safari öffnen
- „Zum Home‑Bildschirm“ hinzufügen

Wenn die WebApp keine Kassenliste anzeigt:
- Button „Verbindung prüfen“ auf dem Kundendisplay klicken
- Falls Broker neu gestartet: Seite neu laden

## 6) Hinweise zur Cache‑Strategie

Modern UI und Kundendisplay nutzen gehashte Dateien (z. B. `app.<hash>.js`).

Nach Updates:
- Browser neu laden (normaler Reload reicht, weil Hash geändert wird)
- iPad Home‑Screen WebApp: ggf. einmal schließen und neu öffnen

## 7) Prüf‑/Troubleshooting

### 7.1 Broker erreichbar?
```bash
curl http://<SERVER-IP>:3077/health
```

### 7.2 PHP‑API erreichbar?
```bash
curl -X POST http://<SERVER-IP>/php/modernapi.php?cmd=state
```

### 7.3 Logs
- Apache: `/var/log/apache2/error.log`
- Modern API Log (wenn aktiviert): `/var/log/ordersprinter/modernapi.log`

## 8) Was nach Änderungen neu gestartet werden muss

- **Broker‑Code geändert:** `systemctl restart ordersprinter-broker`
- **PHP‑Änderungen:** Apache reload (normalerweise nicht nötig)
- **Frontend‑Änderungen:** keine Server‑Neustarts, nur Browser reload

## 9) Produktions‑Checkliste

1. Git Update durchgeführt
2. Modern UI erreichbar
3. Broker läuft und `/health` OK
4. Kundendisplay zeigt Kassenliste
5. Eine Bestellung wird korrekt im Kundendisplay angezeigt
