# State of Play (Stand, Risiken, offene Punkte)

## Fertig / Stabil
- Git ist auf Modern‑Komponenten reduziert, Legacy untracked.
- `setup-runtime.sh`, `deploy-modern.sh` funktionieren.
- Broker Push für TABLES und MENU (Preisstufe).
- Start‑Screen aktualisiert jetzt sofort nach Broker‑Push (Tables werden immer neu geladen, auch wenn der Start‑Screen gerade nicht sichtbar ist).
- Modern UI: Start, Bestellung, Kasse, Kundendisplay laufen.
- Kundendisplay: QR/Idle‑Logik implementiert.
- Kundendisplay: separate Legacy-Seite fuer alte Android-Tablets (Android 5.x) vorhanden (`modern/customer-legacy.html`).
- Preis‑Popup für „Preisprodukte“ funktioniert.
- Tischlayout per JSON implementiert.
- Warenkorb‑Summe wird angezeigt (rechts oben in Bestellung).
- „Bestellung beenden“ geht bei leerem Warenkorb direkt zum Start.
- False‑Positive bei „broker hat Update unterschlagen“ reduziert: Warnung erfolgt erst nach kurzer Grace‑Zeit ohne Broker‑Update.

## Kritische Regeln (dürfen nicht brechen)
- Keine Legacy‑Dateien ändern.
- Keine gehashten Assets erzeugen.
- Rabatt nur im Warenkorb anzeigen, nie nach Bestellung.
- Preisstufe: global, Menüpreise müssen bei Änderung neu geladen werden.

## Bekannte fragile Bereiche
- Preisstufen‑Push: muss Broker‑Polling zuverlässig triggern.
- UI‑Refresh bei gleichzeitigen Zahlungen mehrerer Terminals.
- Browser‑Cache (iPad Home‑Screen) kann alte Assets behalten.

## Offene Punkte / To‑Do
- Lokale Konfiguration (Menüpunkt „Lokale Konfiguration“): dauerhaft speichern (IndexedDB oder localStorage).
- Kasse‑Fehler‑Handling bei Doppelzahlung: UI + Tischliste refreshen.

## Prod‑Troubleshooting (Broker/Kundendisplay)
Symptome:
- Kundendisplay findet keine Kasse
- Meldung: „Broker hat Veränderung unterschlagen …“

Testschritte:
1) Broker Health:
```
curl http://<SERVER-IP>:3077/health
```
2) PHP‑State erreichbar:
```
curl -X POST http://<SERVER-IP>/php/modernapi.php?cmd=state
```
3) Broker‑Service prüfen:
```
sudo systemctl status ordersprinter-broker
sudo journalctl -u ordersprinter-broker -n 200
```
4) Broker‑Env prüfen:
```
cat /etc/systemd/system/ordersprinter-broker.service
```
Erwartet: `POLL_URL` und `PRICELEVEL_URL` zeigen auf `http://127.0.0.1/php/modernapi.php?...`
5) Client‑Broker‑URL prüfen:
- `modernapi.php?cmd=config` → Feld `broker_ws`
- muss Server‑IP/Hostname enthalten (kein `127.0.0.1` von Client‑Sicht)

Hinweise:
- Wenn Broker/WS nicht erreichbar: Kundendisplay sieht keine Kassen.
- Wenn Poll‑Update aber kein Push: Broker down, falsche URL, Port/Firewall, WS‑Block.

## Wann Broker neu starten?
- Immer nach Änderungen an `broker/server.js` oder Broker‑Konfiguration.
