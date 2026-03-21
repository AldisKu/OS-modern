# Arbeitsweise & Regeln (für neue Codex-Session)

## 1) Arbeitsstil (User‑Vorgaben)
- Ergebnisse pragmatisch liefern, keine langen Erklärungen.
- Bei Änderungen immer `git add` + `git commit` ausführen.
- **Kein `git push` durch Codex**, User pusht selbst.
- Legacy‑Dateien nicht ändern.

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

