# OrderSprinter Modern – Projektkontext (für neue Codex-Session)

Dieses Dokument fasst den aktuellen Stand, die Architektur, die wichtigsten Regeln und den Arbeitsstil zusammen. Ziel: eine neue Codex‑Session kann dieses Dokument lesen und ist sofort arbeitsfähig.

## 1) Grundziel
Modernes, iPad‑optimiertes Frontend (Modern UI) für Kellner auf Basis der bestehenden OrderSprinter‑Installation.  
Minimale Serverlast, schnelle UI, lokale Datenhaltung, Push‑Updates über Broker.

Wichtig: Legacy‑System bleibt unverändert und **wird nicht mehr über Git verwaltet**.  
Nur Modern‑Komponenten werden per Git verwaltet und in ein laufendes Legacy‑System deployed.

## 2) Architekturübersicht
Komponenten:
- Modern UI: `modern/` (Browser‑Frontend)
- Kundendisplay: `modern/customer.html` + `modern/customer.js` + `modern/customer.css`
- PHP API Wrapper: `php/modernapi.php`
- Broker (Node.js): `broker/server.js`

Deploy:
- Repo liegt z. B. in `/home/aldis/ordersprinter`
- Webroot (Legacy) z. B. `/var/www/webapp`
- Deploy erfolgt über `deploy-modern.sh` oder `setup-runtime.sh`

## 3) Git/Deployment-Regeln
- Repo enthält **nur** Modern‑Komponenten (Modern UI, Broker, `php/modernapi.php`, `deploy-modern.sh`, `setup-runtime.sh`, `install.md`).
- Alle Legacy‑Dateien bleiben lokal, sind nicht mehr getrackt.
- `.gitignore` lässt nur Modern‑Komponenten zu.
- Nach Änderungen: Codex macht `git add` + `git commit`. **Kein `git push` durch Codex**, Nutzer pusht selbst.
- Bei jeder Änderung `modern/app.js` Version hochziehen (`APP_VERSION`).

## 4) Install/Setup
- `setup-runtime.sh` installiert Node.js über NodeSource (Default `NODE_MAJOR=18`), deployt Modern‑Komponenten, richtet Log‑Ordner ein, installiert Broker‑Service.
- `deploy-modern.sh` synchronisiert `modern/`, `php/modernapi.php` und `broker/` nach Webroot.
- Broker läuft via systemd als `ordersprinter-broker`.

## 5) Broker
Datei: `broker/server.js`

Funktionen:
- WebSocket Hub für Modern UI und Kundendisplay.
- `UPDATE_REQUIRED` Push bei:
  - `POLL_URL` (state‑Version) → `scope: TABLES`
  - `PRICELEVEL_URL` (pricelevel_state) → `scope: MENU`
- Printer/TSE Status wurde entfernt/disabled in Modern UI.

Umgebungsvariablen:
- `PORT` (Default 3077)
- `POLL_URL` (Default `http://127.0.0.1/php/modernapi.php?cmd=state`)
- `POLL_INTERVAL_MS` (Default 4000)
- `PRICELEVEL_URL` (Default `http://127.0.0.1/php/modernapi.php?cmd=pricelevel_state`)

## 6) PHP API Wrapper
Datei: `php/modernapi.php`

Wichtige Commands:
- `cmd=bootstrap` liefert Nutzer, Menü, Rooms, Config, etc.
- `cmd=state` liefert Versions‑Hash
- `cmd=pricelevel_state` liefert Preisstufen‑Version
- `cmd=refresh_tables`, `cmd=queuecontent`, `cmd=order`, `cmd=paydesk_items` usw.
- Logging optional in `/var/log/ordersprinter/modernapi.log`

Wichtig:
- Keine Änderungen an Legacy‑Coredateien.
- Wrapper‑API ist die einzige neue PHP‑Datei.

## 7) Pricing & Rabatt
Preislogik:
- Preisstufe (System‑global) bestimmt **gültigen Preis pro Produkt**.
- Modern UI arbeitet mit aktuellem Preis aus `bootstrap`.
- Broker überwacht Preisstufenänderung und pusht `scope: MENU`.
- Bei Preisstufenänderung: Modern UI lädt Menü/Preise neu.

Rabatt:
- Rabatte existieren nur im Warenkorb (nicht abgeschickt).
- Nach Bestellung wird Rabatt nicht mehr angezeigt, da Preis bereits reduziert gespeichert wird.
- Rabatt in Kasse/Bestellt: nicht anzeigen, nur Preis.
- Rabatt darf **nicht** aus Text geparst werden.

## 8) Kundendisplay
Verbindung:
- Display wählt Kasse über Broker‑Session (ohne Login).
- Wenn nur eine Kasse vorhanden → automatisch verbinden.

Legacy/alte Android-Tablets:
- Separate, ES5-kompatible Seite: `modern/customer-legacy.html` (kein ES-Module/async/await; XHR statt fetch).

Anzeige‑Logik:
- Modus: Bestellung (Warenkorb), Kasse (Bon), QR‑Code nach Zahlung, Idle nach 30s.
- QR wird nach Zahlung angezeigt und bleibt sichtbar, bis Produktaktivität erfolgt.
- Produktsortierung im Bon: nach `_seq` absteigend (zuletzt geklickt oben).
- Gruppierung im Bon nur, wenn gleiche Produkte **direkt nebeneinander**.
- Extras Anzeige: `+ <n> <Extra>` (kleinere Schrift, linksbündig, eingerückt).

Assets:
- `modern/logo.png` (Logo im Kundendisplay)
- `modern/Koblenz-Book.woff2` (Schrift)

## 9) Modern UI – Kernverhalten
Navigation:
- Start (Tische), Bestellung (Tischansicht), Kasse.
- Warenkorb lokal, Bestellung senden via `cmd=order`.
- Arbeitsbon wird immer gedruckt, wenn Bestellung gesendet wird.
- Warenkorb‑Summe wird oben in der rechten Spalte angezeigt.
- „Bestellung beenden“ führt bei leerem Warenkorb zurück zum Start.
- Client‑Poll bleibt Fallback; Warnung „broker hat Update unterschlagen“ wird nur nach kurzer Grace‑Zeit ohne Broker‑Signal gezeigt (Race‑Schutz).

Warenkorb‑Sortierung:
- Reihenfolge bleibt erhalten, nur geändertes Produkt kann nach oben wandern.
- Zusammenfassen nur, wenn gleiche Produkte nebeneinander.

Extras:
- Extras‑Popup ohne Kommentar/Anzahl.
- Buttons toggle, `Bestellen` bestätigt.

Bestellte Produkte:
- Popups für Entfernen/Storno korrekt.
- Nachbestellung wurde **entfernt**.

Kasse:
- Zahlungsarten aus Legacy‑System (nicht alle anzeigen).
- Bewirtungsbeleg‑Toggle ist separat, nach Zahlung zurücksetzen.

## 10) Tischlayout (Raster)
- JSON‑Layout: `modern/table-layout.json`
- Tabelle wird anhand `code` Feld gerendert.
- Nicht gelistete, aktive Tische werden unterhalb als Liste angezeigt.

## 11) Entwickler‑Regeln
- Keine Änderungen an Legacy‑Coredateien.
- Änderungen nur in Modern‑Komponenten.
- Keine gehashten Dateinamen in `modern/`.
- Nach jeder Änderung `git add` + `git commit` ausführen, aber **kein git push** (User macht push).

## 12) Nützliche Dateien
- `install.md`: Installationsanleitung
- `deploy-modern.sh`: Deployment auf Webroot
- `setup-runtime.sh`: Runtime‑Setup mit NodeSource
