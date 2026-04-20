# State of Play (Stand, Risiken, offene Punkte)

## Fertig / Stabil
- Git ist auf Modern-Komponenten reduziert, Legacy untracked.
- `setup-runtime.sh`, `deploy-modern.sh` funktionieren.
- Broker Push fuer TABLES und MENU (Preisstufe).
- Modern UI: Start, Bestellung, Kasse, Kundendisplay laufen.
- Kundendisplay: QR/Idle-Logik implementiert.
- Kundendisplay: separate Legacy-Seite fuer alte Android-Tablets (Android 5.x) vorhanden (`modern/customer-legacy.html`).
- Preis-Popup fuer Preisprodukte funktioniert.
- Tischlayout per JSON implementiert.
- Warenkorb-Summe wird angezeigt (rechts oben in Bestellung).
- Bestellung beenden geht bei leerem Warenkorb direkt zum Start.
- False-Positive bei broker hat Update unterschlagen reduziert: Warnung erfolgt erst nach kurzer Grace-Zeit ohne Broker-Update.
- Netzwerk-Traffic reduziert (v22): refresh_menu statt bootstrap, Poll nur bei State-Aenderung, gecachte Tischdaten.
- Kundendisplay-Sync gefixt (v23): POS sendet DISPLAY_IDLE nach Bestellung/Zahlung; customer.js startet Idle-Timer nach jedem Update; customer.css kompatibel mit alten Browsern.
- iPad Broker-Registrierung gefixt (v25): Separate 'unknown' und 'pos' Registrierungsphasen, nur 'pos' nach Login, explizites State-Tracking, Debug-Logging.
- Kundendisplay POS-Auswahl gefixt (v26): Unterbrechen nicht aktive Verbindung wenn neue Kasse online kommt; zeige verbundene Kassen-ID statt 'customer.js'; klickbar zum Wechsel (modern + legacy).
- Broker ID klickbar (v27): Pointer-events enabled, cursor styling, openPosSelector() Funktion (modern + legacy).
- Kundendisplay POS-Persistierung (v28): Speichert ausgewählte Kasse in localStorage, stellt Verbindung nach Login wieder her (modern + legacy).
- Pull-Update Status gefixt (v28): Erkennt jetzt korrekt wenn Broker-Push gleichzeitig mit Pull-Update ankommt (Timestamp-Vergleich >= zu > geaendert).
- Kundendisplay POS-Logout und Offline-Erkennung (v29): POS sendet POS_LOGOUT beim Logout; Broker notifiziert Display mit POS_OFFLINE; Display geht zu Start-Screen und loescht gespeicherte Kassen-ID. POS zeigt Display-Status in Info-Zeile (OK oder -) an.
- Kundendisplay POS-Auswahl gefixt (v30): Klick auf Broker-ID zeigt immer Selection-Screen; Broker downgradet POS-Rolle zu "unknown" beim Logout und sendet aktualisierte POS_LIST; Display kann nicht mehr auf ausgeloggte Kasse zugreifen.
- Broker ID und Display-Verbindung gefixt (v31): Customer Display zeigt immer Selection-Screen (keine Auto-Reconnect); POS schließt Broker-Verbindung beim Logout um neue ID zu bekommen; Display-Status wird korrekt zurückgesetzt beim Logout; verhindert Mismatch zwischen POS und Display nach Login/Logout-Zyklus.
- Pull-Update Warnung entfernt (v32): Broker-Fallback-Warnung "broker hat Update unterschlagen" entfernt; POS vertraut auf Pull-Update wenn Broker-Push nicht ankommt; vereinfacht Fehlerbehandlung.
- Stabiler Client-Name implementiert (v33): POS generiert und speichert stabilen Client-Namen (POS-XXXXXX) in localStorage; Broker routet Nachrichten nach Client-Namen statt temporärer Broker-IDs; Kundendisplay speichert Client-Namen lokal und verbindet sich nach Reconnect automatisch wieder; zeigt "Client: <name>" statt "Broker: <id>"; löst Problem dass WebSocket-Reconnect die POS-Display-Verbindung bricht.
- Cart Plus/Minus gefixt (v34): Plus-Button erstellt neues Item (qty=1) statt unitamount zu erhöhen; Minus-Button löscht ältestes Item aus Gruppe; alle Items haben immer qty=1 für Backend; groupCartItems() gruppiert nur für Display; zeigt korrekte Menge auf Bildschirm und druckt korrekt.
- Cache-Prevention und Versionierung (v34): HTML-Dateien haben no-cache Meta-Tags; alle JS/CSS-Referenzen haben ?v=34 Query-Parameter; HTML wird immer frisch geladen; JS/CSS behalten stabile Dateinamen; erzwingt Safari iPad neue Assets zu laden.
- Predefined Comments Feature (v36): Kellner können vordefinierte Bemerkungen (z.B. "Keine Zwiebeln", "Extra Sauce") zu Produkten hinzufügen; Bemerkungen verwalten im Menü → Lokale Konfiguration; Bemerkungen sind client-spezifisch (pro Gerät) in localStorage; Product Modal zeigt Dropdown mit vordefinierten Bemerkungen + freier Text-Input; Backend druckt Bemerkungen auf Bestellbon.

## Kritische Regeln (duerfen nicht brechen)
- **JEDES UPDATE BEKOMMT NEUE VERSION!** Increment `APP_VERSION` in `app.js` und alle `?v=NN` Query-Parameter in HTML-Dateien bei JEDEM Commit.
- Keine Legacy-Dateien aendern.
- Keine gehashten Assets erzeugen.
- Rabatt nur im Warenkorb anzeigen, nie nach Bestellung.
- Preisstufe: global, Menupreise muessen bei Aenderung neu geladen werden (via `cmd=refresh_menu`).

## Versioned Filenames Strategy (Cache Busting für iPad Safari PWA)
- Verwende versionierte Dateinamen: `app.36.js`, `styles.36.css`, `customer.36.js`, etc.
- Update HTML um neue versionierte Dateinamen zu referenzieren bei jedem Version-Bump
- **IMMER vorherige Version-Dateien nach Deployment löschen** (z.B. `app.35.js`, `styles.35.css`)
- Nur aktuelle Version-Dateien behalten (+ optional ein Backup)
- Garantiert: aggressive Caching funktioniert, keine stale cache Probleme, minimaler Speicher

### Deployment Workflow für Versioned Filenames
1. Rename: `app.js` → `app.35.js`, `styles.css` → `styles.35.css`, etc.
2. Update HTML: `<script src="app.35.js"></script>`
3. Commit & push
4. Delete: `app.34.js`, `styles.34.css` (alte Version)
5. Commit deletion & push

## Bekannte fragile Bereiche
- Preisstufen-Push: muss Broker-Polling zuverlaessig triggern.
- UI-Refresh bei gleichzeitigen Zahlungen mehrerer Terminals.
- Browser-Cache (iPad Home-Screen) kann alte Assets behalten.
- iPad Safari WebSocket: Kann in CONNECTING-State steckenbleiben (v24/v25 Fixes implementiert).

## Folder Reorganization (v38 - In Progress)
- **Goal**: Consolidate all OrderSprinter Modern files into single `modern/` folder
- **Current state (v37)**: Separate `modern/`, `php/`, `broker/` folders in repository
- **Target state (v38)**: Everything in `modern/` folder:
  - `modern/` - UI files (HTML, JS, CSS)
  - `modern/modernapi.php` - API file (moved from `php/`)
  - `modern/broker/` - Broker server (moved from `broker/`)
- **Deployment**: Everything deploys to `/var/www/webapp/modern/` on target system
- **Changes made (v38)**:
  - Copied `php/modernapi.php` → `modern/modernapi.php`
  - Copied `broker/` → `modern/broker/`
  - Updated API path in `app.js`: `../php/modernapi.php` → `./modernapi.php`
  - Updated broker URLs: `http://127.0.0.1/php/modernapi.php` → `http://127.0.0.1/modern/modernapi.php`
  - Updated `deploy-modern.sh` to deploy everything from `modern/` folder
  - Renamed versioned files: v37 → v38
  - Updated all HTML files to reference v38
- **Next steps**:
  - Commit and push changes
  - Update systemd service on server to point to `/var/www/webapp/modern/broker/server.js`
  - Update systemd service environment variables (POLL_URL, PRICELEVEL_URL)
  - Test deployment on server
  - Delete old `php/` and `broker/` folders from repository (after testing)

## iPad Safari PWA Cache Issue (v35 - Gelöst mit v36)
- **Problem**: iPad PWA zeigte v35 in Status-Zeile, aber Broker wurde nicht gefunden. Nach mehrfachen Safari-Refreshes funktionierte es.
- **Root Cause**: iPad Safari PWA Cache-Verhalten - query parameter `?v=35` allein reicht nicht aus
- **Lösung implementiert (v36)**: Versioned Filenames Strategy mit automatischem Löschen alter Versionen
- **Status**: Gelöst - v36 mit versioned filenames sollte das Problem beheben

## Predefined Remarks/Notes Feature (v36 - Fertig)
- **Ziel**: Kellner können vordefinierte Bemerkungen (z.B. "Keine Zwiebeln", "Extra Sauce") zu Produkten hinzufügen
- **Implementiert (v36)**:
  - Bemerkungen verwalten im Menü → Lokale Konfiguration
  - Vordefinierte Bemerkungen in localStorage speichern (key: `modern_comments_list`)
  - Bemerkungen sind client-spezifisch (pro Gerät), nicht pro Benutzer
  - Product Modal zeigt Dropdown mit vordefinierten Bemerkungen + freier Text-Input
  - Dropdown ermöglicht schnelle Auswahl, freier Text für Spezialfälle
  - Backend druckt Bemerkungen auf Bestellbon (keine interne Verwaltung nötig)
- **Funktionalität**:
  - Menü → Lokale Konfiguration → "Bemerkungen verwalten"
  - Neue Bemerkung eingeben + "Hinzufügen" Button
  - Bemerkungen in Liste anzeigen mit Delete-Button (×)
  - Beim Produkt hinzufügen: Dropdown wählen oder manuell eingeben
  - Bemerkung wird mit Produkt in Warenkorb gespeichert

## Prod-Troubleshooting (Broker/Kundendisplay)
Symptome:
- Kundendisplay findet keine Kasse
- Meldung: Broker hat Veraenderung unterschlagen

Testschritte:
1) Broker Health:
```
curl http://<SERVER-IP>:3077/health
```
2) PHP-State erreichbar:
```
curl -X POST http://<SERVER-IP>/php/modernapi.php?cmd=state
```
3) Broker-Service pruefen:
```
sudo systemctl status ordersprinter-broker
sudo journalctl -u ordersprinter-broker -n 200
```
4) Broker-Env pruefen:
```
cat /etc/systemd/system/ordersprinter-broker.service
```
Erwartet: `POLL_URL` und `PRICELEVEL_URL` zeigen auf `http://127.0.0.1/php/modernapi.php?...`
5) Client-Broker-URL pruefen:
- `modernapi.php?cmd=config` -> Feld `broker_ws`
- muss Server-IP/Hostname enthalten (kein `127.0.0.1` von Client-Sicht)

Hinweise:
- Wenn Broker/WS nicht erreichbar: Kundendisplay sieht keine Kassen.
- Wenn Poll-Update aber kein Push: Broker down, falsche URL, Port/Firewall, WS-Block.

## Wann Broker neu starten?
- Immer nach Aenderungen an `broker/server.js` oder Broker-Konfiguration.
