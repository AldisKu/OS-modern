# Git Setup (Fresh Server, No `.git`, No SSH Key)

Diese Anleitung startet bei einem leeren Server ohne Git‑Repo und ohne SSH‑Key.

## 1) Voraussetzungen
```bash
sudo apt-get update
sudo apt-get install -y git
```

## 2) SSH‑Key erstellen
```bash
ssh-keygen -t ed25519 -C "ordersprinter-modern"
```
Standardpfad akzeptieren: `~/.ssh/id_ed25519`  
Passphrase optional.

Public Key anzeigen:
```bash
cat ~/.ssh/id_ed25519.pub
```
→ Den Public Key im Git‑Server (z. B. GitHub) hinterlegen.

## 3) Repo‑Ordner anlegen & klonen
```bash
mkdir -p /home/aldis/ordersprinter
cd /home/aldis/ordersprinter
git clone git@github-orders:AldisKu/orders.git .
```

## 4) Erstes Pull/Update
```bash
git pull
```

## 5) Danach: Runtime‑Setup
```bash
chmod +x setup-runtime.sh
sudo WEBROOT=/var/www/webapp ./setup-runtime.sh
```

## Hinweise
- Dieses Repo enthält **nur** Modern‑Komponenten, keine Legacy‑Dateien.
- Legacy‑Webroot bleibt unabhängig und wird über `deploy-modern.sh` synchronisiert.
