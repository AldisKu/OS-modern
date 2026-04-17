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

## Kritische Regeln (duerfen nicht brechen)
- Keine Legacy-Dateien aendern.
- Keine gehashten Assets erzeugen.
- Rabatt nur im Warenkorb anzeigen, nie nach Bestellung.
- Preisstufe: global, Menupreise muessen bei Aenderung neu geladen werden (via `cmd=refresh_menu`).

## Bekannte fragile Bereiche
- Preisstufen-Push: muss Broker-Polling zuverlaessig triggern.
- UI-Refresh bei gleichzeitigen Zahlungen mehrerer Terminals.
- Browser-Cache (iPad Home-Screen) kann alte Assets behalten.
- iPad Safari WebSocket: Kann in CONNECTING-State steckenbleiben (v24/v25 Fixes implementiert).

## Offene Punkte / To-Do
- Lokale Konfiguration (Menuepunkt Lokale Konfiguration): dauerhaft speichern (IndexedDB oder localStorage).
- Kasse-Fehler-Handling bei Doppelzahlung: UI + Tischliste refreshen.
- iPad Broker-Registrierung: Testen auf problematischem iPad mit v25 um zu verifizieren dass Race-Condition behoben ist.

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
