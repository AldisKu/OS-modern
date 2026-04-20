# Folder Reorganization v38 - Deployment Guide

## Overview
Version 38 consolidates all OrderSprinter Modern files into a single `modern/` folder structure. This simplifies deployment and file organization.

### What Changed
- **Before (v37)**: Separate `modern/`, `php/`, `broker/` folders
- **After (v38)**: Everything in `modern/` folder:
  - `modern/` - UI files (HTML, JS, CSS)
  - `modern/modernapi.php` - API file
  - `modern/broker/` - Broker server

### Deployment Target
All files deploy to `/var/www/webapp/modern/` on the target system.

## Deployment Steps

### 1. Pull Latest Changes
```bash
cd /var/www/webapp
git pull
```

### 2. Run Deployment Script
```bash
WEBROOT=/var/www/webapp bash orders/orders/deploy-modern.sh
```

The script will:
- Deploy all files from `modern/` folder to `/var/www/webapp/modern/`
- Clean up old versioned files (v37)
- Set correct ownership

### 3. Update Systemd Service

Edit the broker systemd service:
```bash
sudo nano /etc/systemd/system/ordersprinter-broker.service
```

Update the `ExecStart` path and environment variables:

**OLD:**
```ini
ExecStart=/usr/bin/node /var/www/webapp/broker/server.js
Environment="POLL_URL=http://127.0.0.1/php/modernapi.php?cmd=state"
Environment="PRICELEVEL_URL=http://127.0.0.1/php/modernapi.php?cmd=pricelevel_state"
```

**NEW:**
```ini
ExecStart=/usr/bin/node /var/www/webapp/modern/broker/server.js
Environment="POLL_URL=http://127.0.0.1/modern/modernapi.php?cmd=state"
Environment="PRICELEVEL_URL=http://127.0.0.1/modern/modernapi.php?cmd=pricelevel_state"
```

### 4. Reload Systemd and Restart Broker
```bash
sudo systemctl daemon-reload
sudo systemctl restart ordersprinter-broker
```

### 5. Verify Broker is Running
```bash
sudo systemctl status ordersprinter-broker
curl http://127.0.0.1:3077/health
```

Expected output:
```json
{"status":"OK","clients":0}
```

### 6. Test Modern UI
- Open browser: `http://<SERVER-IP>/modern/`
- Login and verify functionality
- Check browser console for errors

### 7. Test Customer Display
- Open: `http://<SERVER-IP>/modern/customer.html`
- Verify display connects to POS

## Verification Checklist

- [ ] Deployment script completed without errors
- [ ] Broker service restarted successfully
- [ ] Broker health check returns OK
- [ ] Modern UI loads and functions
- [ ] Customer display connects to POS
- [ ] Orders can be placed and printed
- [ ] Payments work correctly

## Rollback (if needed)

If something goes wrong, the deployment script creates a backup:

```bash
# List available backups
ls -la /var/www/webapp/modern.backup.*

# Restore from backup
cp -a /var/www/webapp/modern.backup.YYYYMMDD_HHMMSS /var/www/webapp/modern

# Restart broker
sudo systemctl restart ordersprinter-broker
```

## File Structure After Deployment

```
/var/www/webapp/modern/
├── index.html                 # Main POS UI
├── customer.html              # Customer display
├── customer-old.html          # Legacy customer display
├── app.38.js                  # Main app (versioned)
├── styles.38.css              # Styles (versioned)
├── customer.38.js             # Customer display JS (versioned)
├── customer.38.css            # Customer display CSS (versioned)
├── customer-old.38.js         # Legacy customer JS (versioned)
├── customer-old.38.css        # Legacy customer CSS (versioned)
├── modernapi.php              # API endpoint
├── config.json                # Configuration
├── broker/
│   ├── server.js              # Broker server
│   ├── package.json           # Node dependencies
│   ├── install.sh             # Installation script
│   ├── start.sh               # Start script
│   └── restart.sh             # Restart script
└── [other assets]
```

## Notes

- **API Path**: Changed from `/php/modernapi.php` to `/modern/modernapi.php`
- **Broker Path**: Changed from `/var/www/webapp/broker/server.js` to `/var/www/webapp/modern/broker/server.js`
- **Version**: Incremented to v38 with versioned filenames
- **Old Folders**: The `php/` and `broker/` folders in the repository are now deprecated but kept for reference

## Troubleshooting

### Broker won't start
```bash
# Check systemd service status
sudo systemctl status ordersprinter-broker

# Check logs
sudo journalctl -u ordersprinter-broker -n 50

# Verify Node.js is installed
node --version

# Verify broker dependencies
cd /var/www/webapp/modern/broker
npm install
```

### API not responding
```bash
# Test API directly
curl -X POST http://127.0.0.1/modern/modernapi.php?cmd=config

# Check PHP error logs
tail -f /var/log/php-fpm.log
```

### Customer display not connecting
```bash
# Check broker health
curl http://127.0.0.1:3077/health

# Check broker logs
sudo journalctl -u ordersprinter-broker -f

# Verify WebSocket URL in browser console
# Should be: ws://<SERVER-IP>:3077
```

## Questions?

Refer to:
- `STATE_OF_PLAY.md` - Project status and known issues
- `deploy-modern.sh` - Deployment script with detailed comments
- `modern/broker/README.md` - Broker-specific documentation
