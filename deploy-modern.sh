#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

WEBROOT="${WEBROOT:-}"
CANDIDATES=(
  "/var/www/webapp"
  "/var/www/html"
  "/srv/www/htdocs"
  "/srv/www"
  "/var/www"
)

if [[ -z "$WEBROOT" ]]; then
  for d in "${CANDIDATES[@]}"; do
    if [[ -f "$d/php/contenthandler.php" ]] && [[ -f "$d/waiter.html" ]]; then
      WEBROOT="$d"
      break
    fi
  done
fi

if [[ -z "$WEBROOT" ]]; then
  echo "ERROR: Webroot not found. Set WEBROOT=/path/to/webroot and rerun." >&2
  exit 1
fi

if [[ ! -d "$WEBROOT/php" ]]; then
  echo "ERROR: WEBROOT/php not found in $WEBROOT" >&2
  exit 1
fi

WEBOWNER="$(stat -c '%U' "$WEBROOT")"
WEBGROUP="$(stat -c '%G' "$WEBROOT")"

STAMP="$(date +%Y%m%d_%H%M%S)"

# Get current version from source
CURRENT_VERSION=$(grep "const APP_VERSION" "$ROOT_DIR/modern/app.js" | grep -oP "\"?\K[0-9]+" | head -1)
echo "Deploying version: $CURRENT_VERSION"

# Get previous version from deployed files (if exists)
PREVIOUS_VERSION=""
if [[ -f "$WEBROOT/modern/app.js" ]]; then
  PREVIOUS_VERSION=$(grep "const APP_VERSION" "$WEBROOT/modern/app.js" | grep -oP "\"?\K[0-9]+" | head -1 || echo "")
fi

# Detect old structure (v37 and earlier)
HAS_OLD_BROKER=false
HAS_OLD_API=false
if [[ -d "$WEBROOT/broker" ]]; then
  HAS_OLD_BROKER=true
  echo "⚠ Detected old broker structure at $WEBROOT/broker"
fi
if [[ -f "$WEBROOT/php/modernapi.php" ]]; then
  HAS_OLD_API=true
  echo "⚠ Detected old API structure at $WEBROOT/php/modernapi.php"
fi

# Backup existing modern folder if present
if [[ -d "$WEBROOT/modern" ]]; then
  echo "Backing up existing modern -> ${WEBROOT}/modern.backup.${STAMP}"
  cp -a "$WEBROOT/modern" "${WEBROOT}/modern.backup.${STAMP}"
fi

mkdir -p "$WEBROOT/modern"

# Deploy everything from modern folder (UI + API + Broker)
echo "Deploying modern files..."
rsync -a "$ROOT_DIR/modern/" "$WEBROOT/modern/"

# Clean up old versioned files from previous version
if [[ -n "$PREVIOUS_VERSION" ]] && [[ "$PREVIOUS_VERSION" != "$CURRENT_VERSION" ]]; then
  echo "Cleaning up old version files (v$PREVIOUS_VERSION)..."
  rm -f "$WEBROOT/modern/app.${PREVIOUS_VERSION}.js"
  rm -f "$WEBROOT/modern/styles.${PREVIOUS_VERSION}.css"
  rm -f "$WEBROOT/modern/customer.${PREVIOUS_VERSION}.js"
  rm -f "$WEBROOT/modern/customer.${PREVIOUS_VERSION}.css"
  rm -f "$WEBROOT/modern/customer-old.${PREVIOUS_VERSION}.js"
  rm -f "$WEBROOT/modern/customer-old.${PREVIOUS_VERSION}.css"
fi

# Handle migration from old structure (v37 and earlier)
if [[ "$HAS_OLD_BROKER" == true ]] || [[ "$HAS_OLD_API" == true ]]; then
  echo ""
  echo "=== MIGRATION: Old structure detected, cleaning up ==="
  
  # Backup old structure before deletion
  if [[ "$HAS_OLD_BROKER" == true ]]; then
    echo "Backing up old broker -> ${WEBROOT}/broker.backup.${STAMP}"
    cp -a "$WEBROOT/broker" "${WEBROOT}/broker.backup.${STAMP}"
    echo "Removing old broker structure..."
    rm -rf "$WEBROOT/broker"
  fi
  
  if [[ "$HAS_OLD_API" == true ]]; then
    echo "Backing up old API -> ${WEBROOT}/php/modernapi.php.backup.${STAMP}"
    cp "$WEBROOT/php/modernapi.php" "${WEBROOT}/php/modernapi.php.backup.${STAMP}"
    echo "Removing old API file..."
    rm -f "$WEBROOT/php/modernapi.php"
  fi
  
  echo "✓ Old structure cleaned up"
  echo ""
fi

# Fix ownership
chown -R "$WEBOWNER:$WEBGROUP" "$WEBROOT/modern"

cat <<EOF

Deploy complete.
Webroot: $WEBROOT
Owner:   $WEBOWNER:$WEBGROUP
Version: $CURRENT_VERSION
Previous: ${PREVIOUS_VERSION:-none}

=== DEPLOYMENT SUMMARY ===
✓ All files (UI, API, Broker) deployed to $WEBROOT/modern/
✓ Old version files (v$PREVIOUS_VERSION) cleaned up
$(if [[ "$HAS_OLD_BROKER" == true ]]; then echo "✓ Old broker structure removed (backup: broker.backup.${STAMP})"; fi)
$(if [[ "$HAS_OLD_API" == true ]]; then echo "✓ Old API file removed (backup: php/modernapi.php.backup.${STAMP})"; fi)

=== NEXT STEPS ===
1. Update systemd service:
   sudo nano /etc/systemd/system/ordersprinter-broker.service
   
   Change ExecStart to:
   ExecStart=/usr/bin/node $WEBROOT/modern/broker/server.js
   
   Update environment variables:
   Environment="POLL_URL=http://127.0.0.1/modern/modernapi.php?cmd=state"
   Environment="PRICELEVEL_URL=http://127.0.0.1/modern/modernapi.php?cmd=pricelevel_state"

2. Reload and restart broker:
   sudo systemctl daemon-reload
   sudo systemctl restart ordersprinter-broker

3. Verify broker is running:
   sudo systemctl status ordersprinter-broker
   curl http://127.0.0.1:3077/health

4. Test Modern UI:
   http://<SERVER-IP>/modern/

=== IMPORTANT ===
- Broker is now at: $WEBROOT/modern/broker/server.js
- API is now at: $WEBROOT/modern/modernapi.php
- Old backups saved with timestamp: $STAMP
- If something goes wrong, restore from: ${WEBROOT}/modern.backup.${STAMP}

EOF

