# Arbeitsweise & Regeln (für neue Codex-Session)

## 1) Arbeitsstil (User-Vorgaben)
- Ergebnisse pragmatisch liefern, keine langen Erklärungen.
- Bei Änderungen immer `git add` + `git commit` ausführen.
- **Kein `git push` durch Codex/Kiro**, User pusht selbst.
- Bei jeder Änderung die App-Version hochziehen (siehe `modern/app.js` → `APP_VERSION`).
- Legacy-Dateien nicht ändern.
- Nach jeder Entscheidung/Anforderung/Änderung alle Kontextdateien aktualisieren:
  `CONTEXT.md`, `FULL_CONTEXT.md`, `STATE_OF_PLAY.md`, `WORKFLOW.md` (falls relevant).

## 2) Repo-Regeln
- Repo enthält nur Modern-Komponenten.
- `.gitignore` blockiert alle Legacy-Dateien.
- Keine gehashten Asset-Dateien mehr (nur `app.js`, `styles.css`, `customer.js`, `customer.css`).

## 3) Deployment
- Entwicklung im Repo.
- Deployment via `deploy-modern.sh` bzw. `setup-runtime.sh`.
- Broker neu starten nach Änderungen.

## 4) Fehlerhandling
- Wenn Client-Poll Updates findet, aber kein Broker-Push kam → Warnung + Log.
- Broker soll nur relevante Updates pushen (TABLES, MENU).

## 5) Daten-Strategie (ab v22)
- `bootstrap` wird einmal beim Login geladen. Menü, Tische, Config bleiben im Speicher.
- Tischdaten: nur bei Broker-Push oder Poll-Fallback (State-Hash geändert) vom Server geholt.
- Menü/Preise: nur bei Broker-Push (`scope: MENU`) neu geladen via `cmd=refresh_menu`.
- Navigation zwischen Screens nutzt gecachte Daten, kein Server-Roundtrip.
- Kein `cmd=bootstrap` außer beim Login. Kein redundantes `refresh_tables` bei Screen-Wechsel.
