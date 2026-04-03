# Arbeitsweise & Regeln (für neue Codex-Session)

## 1) Arbeitsstil (User‑Vorgaben)
- Ergebnisse pragmatisch liefern, keine langen Erklärungen.
- Bei Änderungen immer `git add` + `git commit` ausführen (Codex macht das).
- **Kein `git push` durch Codex**, User pusht selbst.
- Bei jeder Änderung die App-Version hochziehen (siehe `modern/app.js` → `APP_VERSION`).
- Legacy‑Dateien nicht ändern.
 - Nach jeder Entscheidung/Anforderung/Änderung alle Kontextdateien aktualisieren:
   `CONTEXT.md`, `FULL_CONTEXT.md`, `STATE_OF_PLAY.md`, `WORKFLOW.md` (falls relevant).

## 2) Repo‑Regeln
- Repo enthält nur Modern‑Komponenten.
- `.gitignore` blockiert alle Legacy‑Dateien.
- Keine gehashten Asset‑Dateien mehr (nur `app.js`, `styles.css`, `customer.js`, `customer.css`).

## 3) Deployment
- Entwicklung im Repo.
- Deployment via `deploy-modern.sh` bzw. `setup-runtime.sh`.
- Broker neu starten nach Änderungen.

## 4) Fehlerhandling
- Wenn Client‑Poll Updates findet, aber kein Broker‑Push kam → Warnung + Log.
- Broker soll nur relevante Updates pushen (TABLES, MENU).
