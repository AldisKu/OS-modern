# Deployment Quick Reference - v38

## One-Command Deployment

```bash
WEBROOT=/var/www/webapp bash orders/orders/deploy-modern.sh
```

## What Happens Automatically

✓ Detects old structure (`/broker`, `/php/modernapi.php`)
✓ Backs up everything with timestamp
✓ Deploys new structure to `/var/www/webapp/modern/`
✓ Removes old files
✓ Cleans up old versioned files
✓ Sets correct ownership

## After Deployment (Manual Steps)

### 1. Update Systemd Service
```bash
sudo nano /etc/systemd/system/ordersprinter-broker.service
```

**Change these lines:**
```ini
# OLD
ExecStart=/usr/bin/node /var/www/webapp/broker/server.js
Environment="POLL_URL=http://127.0.0.1/php/modernapi.php?cmd=state"
Environment="PRICELEVEL_URL=http://127.0.0.1/php/modernapi.php?cmd=pricelevel_state"

# NEW
ExecStart=/usr/bin/node /var/www/webapp/modern/broker/server.js
Environment="POLL_URL=http://127.0.0.1/modern/modernapi.php?cmd=state"
Environment="PRICELEVEL_URL=http://127.0.0.1/modern/modernapi.php?cmd=pricelevel_state"
```

### 2. Restart Broker
```bash
sudo systemctl daemon-reload
sudo systemctl restart ordersprinter-broker
```

### 3. Verify
```bash
# Check broker status
sudo systemctl status ordersprinter-broker

# Check broker health
curl http://127.0.0.1:3077/health

# Test UI
# Open: http://<SERVER-IP>/modern/
```

## File Locations After Deployment

| Component | Old Location | New Location |
|-----------|--------------|--------------|
| UI Files | `/modern/` | `/modern/` |
| API | `/php/modernapi.php` | `/modern/modernapi.php` |
| Broker | `/broker/server.js` | `/modern/broker/server.js` |

## Backups Created

All backups are timestamped (e.g., `20260420_152800`):

```
/var/www/webapp/modern.backup.TIMESTAMP/
/var/www/webapp/broker.backup.TIMESTAMP/
/var/www/webapp/php/modernapi.php.backup.TIMESTAMP
```

## Rollback (if needed)

```bash
# Restore modern folder
STAMP="20260420_152800"  # Use actual timestamp
cp -a /var/www/webapp/modern.backup.${STAMP} /var/www/webapp/modern

# Restore old broker (if needed)
cp -a /var/www/webapp/broker.backup.${STAMP} /var/www/webapp/broker

# Restore old API (if needed)
cp /var/www/webapp/php/modernapi.php.backup.${STAMP} /var/www/webapp/php/modernapi.php

# Restart broker
sudo systemctl restart ordersprinter-broker
```

## Troubleshooting

### Broker won't start
```bash
# Check logs
sudo journalctl -u ordersprinter-broker -n 50

# Verify file exists
ls -la /var/www/webapp/modern/broker/server.js

# Check systemd service
cat /etc/systemd/system/ordersprinter-broker.service
```

### API not responding
```bash
# Test API
curl -X POST http://127.0.0.1/modern/modernapi.php?cmd=config

# Check PHP logs
tail -f /var/log/php-fpm.log
```

### Customer display not connecting
```bash
# Check broker health
curl http://127.0.0.1:3077/health

# Check broker logs
sudo journalctl -u ordersprinter-broker -f
```

## Documentation

- **Full deployment guide**: `FOLDER_REORGANIZATION_v38.md`
- **Migration details**: `DEPLOYMENT_SCRIPT_MIGRATION_v38.md`
- **Project status**: `STATE_OF_PLAY.md`
