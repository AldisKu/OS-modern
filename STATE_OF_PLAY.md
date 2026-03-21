# State of Play (Stand, Risiken, offene Punkte)

## Fertig / Stabil
- Git ist auf Modern‑Komponenten reduziert, Legacy untracked.
- `setup-runtime.sh`, `deploy-modern.sh` funktionieren.
- Broker Push für TABLES und MENU (Preisstufe).
- Modern UI: Start, Bestellung, Kasse, Kundendisplay laufen.
- Kundendisplay: QR/Idle‑Logik implementiert.
- Preis‑Popup für „Preisprodukte“ funktioniert.
- Tischlayout per JSON implementiert.
- Warenkorb‑Summe wird angezeigt (rechts oben in Bestellung).

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
- Konsistente Update‑Warnung bei fehlendem Broker‑Push (Client‑Poll).
- Kasse‑Fehler‑Handling bei Doppelzahlung: UI + Tischliste refreshen.

## Wann Broker neu starten?
- Immer nach Änderungen an `broker/server.js` oder Broker‑Konfiguration.
