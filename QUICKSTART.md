# Quickstart – OrderSprinter Modern

1) Repo aktualisieren
```bash
cd /home/aldis/ordersprinter
git pull
```

2) Einmaliges Runtime‑Setup (empfohlen)
```bash
chmod +x setup-runtime.sh
sudo WEBROOT=/var/www/webapp ./setup-runtime.sh
```

3) Spätere Updates (nur Modern‑Komponenten)
```bash
sudo WEBROOT=/var/www/webapp ./deploy-modern.sh
sudo systemctl restart ordersprinter-broker
```

4) URLs
- Modern UI: `http://<SERVER-IP>/modern/`
- Kundendisplay: `http://<SERVER-IP>/modern/customer.html`
- Kundendisplay (Legacy Android 5.x): `http://<SERVER-IP>/modern/customer-legacy.html`

5) Debug
```bash
curl http://<SERVER-IP>:3077/health
curl -X POST http://<SERVER-IP>/php/modernapi.php?cmd=state
```
