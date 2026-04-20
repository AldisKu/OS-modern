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

# Backup existing modern folder if present
if [[ -d "$WEBROOT/modern" ]]; then
  echo "Backing up existing modern -> ${WEBROOT}/modern.backup.${STAMP}"
  cp -a "$WEBROOT/modern" "${WEBROOT}/modern.backup.${STAMP}"
fi

mkdir -p "$WEBROOT/modern"

# Deploy modern UI
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

# Deploy modern API
cp -a "$ROOT_DIR/php/modernapi.php" "$WEBROOT/php/modernapi.php"

# Deploy broker under webroot
mkdir -p "$WEBROOT/broker"
rsync -a "$ROOT_DIR/broker/" "$WEBROOT/broker/"

# Fix ownership
chown -R "$WEBOWNER:$WEBGROUP" "$WEBROOT/modern" "$WEBROOT/php/modernapi.php" "$WEBROOT/broker"

cat <<EOF

Deploy complete.
Webroot: $WEBROOT
Owner:   $WEBOWNER:$WEBGROUP
Version: $CURRENT_VERSION
Previous: ${PREVIOUS_VERSION:-none}

Notes:
- Old version files (v$PREVIOUS_VERSION) have been cleaned up
- If broker path changed, update systemd service to point to $WEBROOT/broker/server.js
- Restart broker after updates:
  sudo systemctl restart ordersprinter-broker
EOF
