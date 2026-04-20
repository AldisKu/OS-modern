# Deployment Script Migration Handling (v38)

## Overview
The enhanced `deploy-modern.sh` script automatically handles the migration from the old folder structure (v37 and earlier) to the new consolidated structure (v38+).

## What the Script Does

### 1. **Detects Old Structure**
```bash
# Checks for old broker location
if [[ -d "$WEBROOT/broker" ]]; then
  HAS_OLD_BROKER=true
fi

# Checks for old API location
if [[ -f "$WEBROOT/php/modernapi.php" ]]; then
  HAS_OLD_API=true
fi
```

### 2. **Backs Up Everything**
Before making any changes, the script creates timestamped backups:

```
/var/www/webapp/modern.backup.20260420_152800/     # Full modern folder backup
/var/www/webapp/broker.backup.20260420_152800/     # Old broker backup
/var/www/webapp/php/modernapi.php.backup.20260420_152800  # Old API backup
```

### 3. **Deploys New Structure**
```bash
# Deploys everything from modern/ folder
rsync -a "$ROOT_DIR/modern/" "$WEBROOT/modern/"
```

This copies:
- UI files (HTML, JS, CSS)
- API file (`modernapi.php`)
- Broker server (`broker/server.js`)

### 4. **Cleans Up Old Files**
If old structure is detected:

```bash
# Remove old broker
rm -rf "$WEBROOT/broker"

# Remove old API
rm -f "$WEBROOT/php/modernapi.php"
```

### 5. **Cleans Up Old Versions**
Removes versioned files from previous version:
```bash
rm -f "$WEBROOT/modern/app.37.js"
rm -f "$WEBROOT/modern/styles.37.css"
rm -f "$WEBROOT/modern/customer.37.js"
# ... etc
```

### 6. **Sets Correct Ownership**
```bash
chown -R "$WEBOWNER:$WEBGROUP" "$WEBROOT/modern"
```

## Execution Flow

```
1. Detect WEBROOT
   ↓
2. Get current version from source (app.js)
   ↓
3. Get previous version from deployed files
   ↓
4. Detect old structure (broker/, php/modernapi.php)
   ↓
5. Backup modern folder (if exists)
   ↓
6. Deploy new modern folder
   ↓
7. Clean up old versioned files
   ↓
8. IF old structure detected:
   ├─ Backup old broker
   ├─ Backup old API
   ├─ Delete old broker
   └─ Delete old API
   ↓
9. Set ownership
   ↓
10. Display summary and next steps
```

## Example Output

```
Deploying version: 38
⚠ Detected old broker structure at /var/www/webapp/broker
⚠ Detected old API structure at /var/www/webapp/php/modernapi.php
Backing up existing modern -> /var/www/webapp/modern.backup.20260420_152800
Deploying modern files...
Cleaning up old version files (v37)...

=== MIGRATION: Old structure detected, cleaning up ===
Backing up old broker -> /var/www/webapp/broker.backup.20260420_152800
Removing old broker structure...
Backing up old API -> /var/www/webapp/php/modernapi.php.backup.20260420_152800
Removing old API file...
✓ Old structure cleaned up

Deploy complete.
Webroot: /var/www/webapp
Owner:   www-data:www-data
Version: 38
Previous: 37

=== DEPLOYMENT SUMMARY ===
✓ All files (UI, API, Broker) deployed to /var/www/webapp/modern/
✓ Old version files (v37) cleaned up
✓ Old broker structure removed (backup: broker.backup.20260420_152800)
✓ Old API file removed (backup: php/modernapi.php.backup.20260420_152800)

=== NEXT STEPS ===
1. Update systemd service...
2. Reload and restart broker...
3. Verify broker is running...
4. Test Modern UI...
```

## Rollback Procedure

If something goes wrong, you can restore from backups:

### Restore Modern Folder
```bash
STAMP="20260420_152800"  # Use the timestamp from deployment
cp -a /var/www/webapp/modern.backup.${STAMP} /var/www/webapp/modern
sudo systemctl restart ordersprinter-broker
```

### Restore Old Broker (if needed)
```bash
STAMP="20260420_152800"
cp -a /var/www/webapp/broker.backup.${STAMP} /var/www/webapp/broker
# Update systemd service to point to /var/www/webapp/broker/server.js
sudo systemctl daemon-reload
sudo systemctl restart ordersprinter-broker
```

### Restore Old API (if needed)
```bash
STAMP="20260420_152800"
cp /var/www/webapp/php/modernapi.php.backup.${STAMP} /var/www/webapp/php/modernapi.php
```

## Key Features

✓ **Automatic Detection**: Detects old structure without user intervention
✓ **Safe Backups**: Creates timestamped backups before deletion
✓ **Clean Migration**: Removes old files completely
✓ **Version Management**: Cleans up old versioned files
✓ **Clear Feedback**: Shows exactly what was done
✓ **Next Steps**: Provides clear instructions for systemd update
✓ **Reversible**: All changes can be rolled back using backups

## Important Notes

1. **Backups are kept**: Old files are backed up with timestamp, not deleted immediately
2. **Systemd update required**: Script does NOT update systemd service (manual step)
3. **Broker restart required**: Must restart broker after deployment
4. **No data loss**: All old files are preserved in backups
5. **Idempotent**: Safe to run multiple times

## Troubleshooting

### Script fails to find WEBROOT
```bash
# Manually specify WEBROOT
WEBROOT=/var/www/webapp bash deploy-modern.sh
```

### Permission denied errors
```bash
# Run with sudo if needed
sudo WEBROOT=/var/www/webapp bash deploy-modern.sh
```

### Broker won't start after deployment
```bash
# Check if systemd service was updated
cat /etc/systemd/system/ordersprinter-broker.service

# Verify broker file exists
ls -la /var/www/webapp/modern/broker/server.js

# Check broker logs
sudo journalctl -u ordersprinter-broker -n 50
```

### Need to restore old structure
```bash
# List available backups
ls -la /var/www/webapp/*.backup.*

# Restore specific backup
cp -a /var/www/webapp/modern.backup.20260420_152800 /var/www/webapp/modern
```
