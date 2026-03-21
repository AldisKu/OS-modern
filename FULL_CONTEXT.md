# OrderSprinter Modern – Vollständiger Kontext (für neue Codex‑Session)

Ziel: Eine neue Session liest diese Datei und kann ohne Rückfragen weiterarbeiten.

## A) Projektziele
- Modernes, iPad‑optimiertes Frontend für Kellner.
- Backend bleibt die bestehende OrderSprinter‑Installation (Legacy).
- Minimale Serverlast, lokale Datenhaltung (IndexedDB), Push‑Updates.
- Echtzeit‑Updates über Node‑Broker (WebSocket).
- Legacy‑Benutzerrechte/Session weiterverwenden.

## B) Architektur & Komponenten
**Modern UI**
- Pfad: `modern/`
- Dateien: `index.html`, `app.js`, `styles.css`
- Keine gehashten Assets mehr.

**Kundendisplay**
- `modern/customer.html`, `modern/customer.js`, `modern/customer.css`
- Anzeige für Kassen‑Kundenterminal (hochkant).

**PHP Wrapper**
- `php/modernapi.php` ist die einzige neue PHP‑Datei.
- Alle Legacy‑Funktionen werden über Wrapper aufgerufen.
- Legacy‑Core darf nicht verändert werden.

**Broker**
- `broker/server.js`
- WebSocket‑Hub für Modern UI und Kundendisplay.
- Pollt PHP‑State und Preisstufen‑State; pusht nur bei Änderung.
- Printer/TSE Status entfernt.

## C) Git/Deployment‑Regeln (sehr wichtig)
- Repo enthält nur Modern‑Komponenten.
- Legacy‑Dateien bleiben lokal, **nicht getrackt**.
- `.gitignore` blockiert alles außer Modern, Broker, `modernapi.php`, Skripte, Doku.
- Nach Änderungen immer `git add` + `git commit`.
- **Codex darf nicht `git push` ausführen** (User pusht).

## D) Setup/Installation (Server)
**Einmalig:**
```bash
chmod +x setup-runtime.sh
sudo WEBROOT=/var/www/webapp ./setup-runtime.sh
```
– installiert Node (NodeSource), deployt Modern, richtet Logdir ein, installiert systemd Service.

**Updates:**
```bash
sudo WEBROOT=/var/www/webapp ./deploy-modern.sh
sudo systemctl restart ordersprinter-broker
```

## E) Broker‑Details
**Env:**
- `PORT` (3077)
- `POLL_URL` → `modernapi.php?cmd=state`
- `PRICELEVEL_URL` → `modernapi.php?cmd=pricelevel_state`
- `POLL_INTERVAL_MS` (4000)

**Push‑Scopes:**
- `TABLES` bei Version‑Änderung
- `MENU` bei Preisstufenänderung

**Customer Display:**
- Kassen verbinden sich als `role=pos`.
- Displays als `role=display` + `SUBSCRIBE`.
- Broker verteilt `DISPLAY_UPDATE`, `DISPLAY_IDLE`, `DISPLAY_EBON`.

## F) Preisstufe / Rabatt (kritisch)
**Preisstufe:**
- System global, nicht pro Produkt.
- Modern bekommt im `bootstrap` nur den **aktuell gültigen Preis**.
- Broker überwacht Preisstufe → `UPDATE_REQUIRED scope: MENU`.
- Preisaktualisierung: Menü + Preise neu laden.

**Rabatt:**
- 3 frei konfigurierbare Rabatte (Name + %).
- Rabatt existiert nur im Warenkorb (nicht abgeschickt).
- Nach Bestellung: Rabatt nicht anzeigen, nur Preis.
- Preisstufe darf nicht mit Rabatt verwechselt werden.

## G) UI‑Regeln (Modern UI)
### 1) Login
- aktuell wieder klassisches Login (UserID + Passwort).
- Onscreen‑Keyboard für Passworteingabe (Ziffern/Buchstaben/Sonderzeichen).

### 2) Start‑Maske
- Split: links Tische, rechts Status/Message‑Panel.
- Tische mit offenem Betrag rot.
- ToGo nicht in Liste (oben Button).
- Tischlayout optional via `modern/table-layout.json`.
- Nicht im Layout gelistete aktive Tische unten anhängen.
- Statusinfos: Broker‑ID bleibt konstant (nicht zurück auf „broker“).
- Printer/TSE Anzeige entfernt.

### 3) Bestellung (Tischansicht)
Layout: oben Menüleiste, rechts Produktliste (20%), links Menü/Kategorien.

**Breadcrumbs:**
- „Kategorien“ (Start), aktuelle Kategorie nur einmal.
- Styles: nach oben / aktuell / tiefer.

**Produktliste rechts:**
- Bestellte Produkte + Warenkorb getrennt, optisch markiert.
- Neue Produkte oben, Trennlinie.
- Zusammenfassen nur bei identischen Extras/ToGo/Rabatt und nebeneinander.
- Warenkorb‑Summe oben in der rechten Spalte.

**Warenkorb‑Sortierung:**
- Reihenfolge bleibt stabil.
- Änderung (außer +/‑) kann Produkt nach oben bringen.
- Zusammenfassen nur, wenn gleiches Produkt direkt nebeneinander.

**Extras:**
- Popup zeigt nur Extras als Buttons, ohne Kommentar/Anzahl.
- Mehrere Extras möglich.
- „Bestellen“ bestätigt.

**Rabatt:**
- Rabatt‑Buttons toggle.
- Preis im Popup wird neu berechnet (immer vom Originalpreis).
- Rabatt nur im Warenkorb sichtbar.

**ToGo:**
- ToGo separat darstellen.

**Preisprodukte:**
- Produkte mit „Preisangabe“ öffnen Preis‑Popup (Num‑Pad inkl. `,` und `-`).

### 4) Kasse
Layout: rechts 20% Status, links Bon / Nicht bezahlt.

**Zahlungsarten:**
- Nicht alle anzeigen – nur wie in Legacy verfügbar.
- Legacy hat „Zahlung“ und „Bondruck“ → Popup zeigt erlaubte Zahlungsarten.

**Bewirtungsbeleg:**
- Toggle wirkt nur für aktuellen Zahlungsvorgang, nach Zahlung zurücksetzen.

**Bezahlen:**
- Teilzahlung möglich.
- Fehler bei Doppelzahlung: UI muss Tabelle refreshen.

## H) Tischwechsel
Popup mit Tischliste (inkl. ToGo), Produktauswahl mit Checkboxen.
Nach Wechsel:
- Anzeige zeigt neuen Tisch.
- Bei Wechsel nach ToGo: Produkte als ToGo markieren (MwSt).

## I) Kundendisplay (Details)
**Anmeldung:**
- Kein Login. Auswahl der Kasse (POS) aus Broker.
- Wenn 1 Kasse → auto.

**Modi:**
- Bestellung (nur Warenkorb)
- Kasse (Bon)
- QR‑Code nach Zahlung
- Idle nach 30s ohne Aktivität

**QR‑Logik:**
- Nach Zahlung: QR anzeigen.
- QR bleibt, bis Produktaktion erfolgt.
- Keine Unterbrechung nur weil Kasse offen bleibt.

**Darstellung:**
- Header: Logo links, rechts „Summe  <Wert> €“.
- Bestellung: ein Block, „Bestellung:“ + Liste.
- Kasse: obere Liste („Sie bezahlen:“), darunter Fließtext‑Buttons mit Bestellpositionen.
- Extras: `+ <n> <Extra>` klein, eingerückt, linksbündig.
- Produkte im Bon: Sortierung `_seq` absteigend (letzter oben).
- Gruppierung nur bei direkt benachbarten gleichen Produkten.

## J) Known Behavior/Constraints
- Keine gehashten Asset‑Dateinamen mehr.
- iPad Home‑Screen WebApp: Cache kann hängen → hard reload / schließen.
- Broker muss nach Änderungen neu gestartet werden.

## K) Wichtige Dateien
- `install.md` – Installation
- `setup-runtime.sh` – Runtime Setup (NodeSource)
- `deploy-modern.sh` – Deployment
- `CONTEXT.md`, `WORKFLOW.md`, `FULL_CONTEXT.md`, `STATE_OF_PLAY.md`
