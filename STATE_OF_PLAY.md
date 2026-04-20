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

## Kritische Regeln (duerfen nicht brechen)
- **JEDES UPDATE BEKOMMT NEUE VERSION!** Increment `APP_VERSION` in `app.js` und alle `?v=NN` Query-Parameter in HTML-Dateien bei JEDEM Commit.
- Keine Legacy-Dateien aendern.
- Keine gehashten Assets erzeugen.
- Rabatt nur im Warenkorb anzeigen, nie nach Bestellung.
- Preisstufe: global, Menupreise muessen bei Aenderung neu geladen werden (via `cmd=refresh_menu`).

## Versioned Filenames Strategy (Cache Busting für iPad Safari PWA)
- Verwende versionierte Dateinamen: `app.35.js`, `styles.35.css`, `customer.35.js`, etc.
- Update HTML um neue versionierte Dateinamen zu referenzieren bei jedem Version-Bump
- **IMMER vorherige Version-Dateien nach Deployment löschen** (z.B. `app.34.js`, `styles.34.css`)
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

## Offene Punkte / To-Do
- Lokale Konfiguration (Menuepunkt Lokale Konfiguration): dauerhaft speichern (IndexedDB oder localStorage).
- Kasse-Fehler-Handling bei Doppelzahlung: UI + Tischliste refreshen.
- iPad Broker-Registrierung: Testen auf problematischem iPad mit v25 um zu verifizieren dass Race-Condition behoben ist.
- iPad Broker-Registrierung: Broker Logs sammeln um Root-Cause zu identifizieren (siehe DEBUGGING_GUIDE_iPad_Broker.md).
- **Broker sollte Änderungen an Tischen zu ALLEN POS broadcasten**: Wenn eine Kasse eine Änderung macht (Bestellung, Zahlung, etc.), soll Broker UPDATE_REQUIRED an alle anderen POS senden. POS sollte eigene Änderungen (vom Broker zurückgesendet) korrekt verarbeiten können.
- **Stable Client Name testen**: v33 mit stabilen Client-Namen testen - POS backgrounding/return sollte Verbindung zu Display halten.

## iPad Safari PWA Cache Issue (v35)
- **Problem**: iPad PWA zeigte v35 in Status-Zeile, aber Broker wurde nicht gefunden. Nach mehrfachen Safari-Refreshes funktionierte es.
- **Root Cause**: Wahrscheinlich iPad Safari PWA Cache-Verhalten - query parameter `?v=35` allein reicht nicht aus
- **Lösung implementiert**: Versioned Filenames Strategy (siehe unten)
- **Status**: Zu testen auf iPad PWA nach nächstem Deployment

## Predefined Remarks/Notes Feature (In Progress)
- **Ziel**: Kellner können vordefinierte Bemerkungen (z.B. "Keine Zwiebeln", "Extra Sauce") zu Produkten hinzufügen
- **Current State**:
  - Item `option` Feld existiert bereits (für Notizen)
  - Edit-Modal zeigt Notiz-Input
  - Keine Notiz-Input beim Hinzufügen neuer Items
  - **Keine vordefinierte Remarks-Liste vorhanden**
- **Nächste Schritte**:
  - User stellt Original-Quellcode zur Verfügung
  - Analysieren wo predefined remarks in Legacy-System gespeichert sind
  - Remarks zu bootstrap API hinzufügen
  - UI implementieren (Predefined-Buttons oder Dropdown)

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
