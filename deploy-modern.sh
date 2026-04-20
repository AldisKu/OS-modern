#!/usr/bin/env bash
set -euo pipefail

# Parse command line arguments
VERBOSE=false
for arg in "$@"; do
  if [[ "$arg" == "--v" ]]; then
    VERBOSE=true
  fi
done

# ROOT_DIR is where the deploy script is located (the git repository root)
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
    if [[ "$VERBOSE" == true ]]; then
      echo "[VERBOSE] Checking candidate: $d"
    fi
    if [[ -f "$d/php/contenthandler.php" ]] && [[ -f "$d/waiter.html" ]]; then
      WEBROOT="$d"
      if [[ "$VERBOSE" == true ]]; then
        echo "[VERBOSE] ✓ Found WEBROOT: $WEBROOT"
      fi
      break
    fi
  done
fi

if [[ -z "$WEBROOT" ]]; then
  echo "ERROR: Webroot not found. Set WEBROOT=/path/to/webroot and rerun." >&2
  exit 1
fi

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] WEBROOT identified: $WEBROOT"
fi

if [[ ! -d "$WEBROOT/php" ]]; then
  echo "ERROR: WEBROOT/php not found in $WEBROOT" >&2
  exit 1
fi

WEBOWNER="$(stat -c '%U' "$WEBROOT")"
WEBGROUP="$(stat -c '%G' "$WEBROOT")"

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Web owner: $WEBOWNER:$WEBGROUP"
fi

STAMP="$(date +%Y%m%d_%H%M%S)"

# Get current version from source (versioned filename)
CURRENT_VERSION=$(ls "$ROOT_DIR/modern/app."*.js 2>/dev/null | grep -oP 'app\.\K[0-9]+' | head -1 || echo "unknown")
echo "Deploying version: $CURRENT_VERSION"
echo "Source: $ROOT_DIR/modern"
echo "Target: $WEBROOT/modern"

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Source directory contents:"
  ls -lh "$ROOT_DIR/modern/" | head -20
fi

# Detect old structure (v37 and earlier)
HAS_OLD_BROKER=false
HAS_OLD_API=false
if [[ -d "$WEBROOT/broker" ]]; then
  HAS_OLD_BROKER=true
  echo "⚠ Detected old broker structure at $WEBROOT/broker"
  if [[ "$VERBOSE" == true ]]; then
    echo "[VERBOSE] Old broker found - will be backed up and removed"
  fi
fi
if [[ -f "$WEBROOT/php/modernapi.php" ]]; then
  HAS_OLD_API=true
  echo "⚠ Detected old API structure at $WEBROOT/php/modernapi.php"
  if [[ "$VERBOSE" == true ]]; then
    echo "[VERBOSE] Old API found - will be backed up and removed"
  fi
fi

# Backup existing modern folder if present
if [[ -d "$WEBROOT/modern" ]]; then
  echo "Backing up existing modern -> ${WEBROOT}/modern.backup.${STAMP}"
  if [[ "$VERBOSE" == true ]]; then
    echo "[VERBOSE] Executing: cp -a $WEBROOT/modern ${WEBROOT}/modern.backup.${STAMP}"
  fi
  cp -a "$WEBROOT/modern" "${WEBROOT}/modern.backup.${STAMP}"
  if [[ "$VERBOSE" == true ]]; then
    echo "[VERBOSE] ✓ Backup complete"
  fi
fi

mkdir -p "$WEBROOT/modern"

# Fix ownership of deployment folder BEFORE copying (in case previous run left root-owned files)
if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Fixing ownership before copy: chown -R $WEBOWNER:$WEBGROUP $WEBROOT/modern"
fi
chown -R "$WEBOWNER:$WEBGROUP" "$WEBROOT/modern"

# Deploy from git repository to webroot using rsync
echo "Deploying modern files from git repository..."
if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Executing: rsync -a --delete $ROOT_DIR/modern/ $WEBROOT/modern/ --exclude=.git --exclude=node_modules"
fi
rsync -a --delete "$ROOT_DIR/modern/" "$WEBROOT/modern/" \
  --exclude='.git' \
  --exclude='node_modules'

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] ✓ Rsync complete"
  echo "[VERBOSE] Deployment folder contents after rsync:"
  ls -lh "$WEBROOT/modern/" | head -20
fi

# Clean up old/unused asset files in deployment folder
echo "Cleaning up old/unused files..."
if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Removing hashed files..."
fi

# Remove hashed files (old build artifacts) - only match 8+ hex chars to avoid versioned files
rm -f "$WEBROOT/modern/app."[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9]*.js
rm -f "$WEBROOT/modern/styles."[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9]*.css
rm -f "$WEBROOT/modern/customer."[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9]*.js
rm -f "$WEBROOT/modern/customer."[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9]*.css
rm -f "$WEBROOT/modern/customer-old."[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9]*.js
rm -f "$WEBROOT/modern/customer-old."[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9]*.css

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Removing non-versioned files..."
fi

# Remove non-versioned files (should only have versioned files)
rm -f "$WEBROOT/modern/app.js"
rm -f "$WEBROOT/modern/styles.css"
rm -f "$WEBROOT/modern/customer.js"
rm -f "$WEBROOT/modern/customer.css"
rm -f "$WEBROOT/modern/customer-old.js"
rm -f "$WEBROOT/modern/customer-old.css"

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Removing legacy files..."
fi

# Remove legacy files
rm -f "$WEBROOT/modern/customer-legacy.html"
rm -f "$WEBROOT/modern/customer-legacy."*.js
rm -f "$WEBROOT/modern/customer-legacy."*.css

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] ✓ Cleanup complete"
fi

# Handle migration from old structure (v37 and earlier)
if [[ "$HAS_OLD_BROKER" == true ]] || [[ "$HAS_OLD_API" == true ]]; then
  echo ""
  echo "=== MIGRATION: Old structure detected, cleaning up ==="
  
  # Backup old structure before deletion
  if [[ "$HAS_OLD_BROKER" == true ]]; then
    echo "Backing up old broker -> ${WEBROOT}/broker.backup.${STAMP}"
    if [[ "$VERBOSE" == true ]]; then
      echo "[VERBOSE] Executing: cp -a $WEBROOT/broker ${WEBROOT}/broker.backup.${STAMP}"
    fi
    cp -a "$WEBROOT/broker" "${WEBROOT}/broker.backup.${STAMP}"
    echo "Removing old broker structure..."
    if [[ "$VERBOSE" == true ]]; then
      echo "[VERBOSE] Executing: rm -rf $WEBROOT/broker"
    fi
    rm -rf "$WEBROOT/broker"
    if [[ "$VERBOSE" == true ]]; then
      echo "[VERBOSE] ✓ Old broker removed"
    fi
  fi
  
  if [[ "$HAS_OLD_API" == true ]]; then
    echo "Backing up old API -> ${WEBROOT}/php/modernapi.php.backup.${STAMP}"
    if [[ "$VERBOSE" == true ]]; then
      echo "[VERBOSE] Executing: cp $WEBROOT/php/modernapi.php ${WEBROOT}/php/modernapi.php.backup.${STAMP}"
    fi
    cp "$WEBROOT/php/modernapi.php" "${WEBROOT}/php/modernapi.php.backup.${STAMP}"
    echo "Removing old API file..."
    if [[ "$VERBOSE" == true ]]; then
      echo "[VERBOSE] Executing: rm -f $WEBROOT/php/modernapi.php"
    fi
    rm -f "$WEBROOT/php/modernapi.php"
    if [[ "$VERBOSE" == true ]]; then
      echo "[VERBOSE] ✓ Old API removed"
    fi
  fi
  
  echo "✓ Old structure cleaned up"
  echo ""
fi

# Fix ownership
if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Setting ownership: chown -R $WEBOWNER:$WEBGROUP $WEBROOT/modern"
fi
chown -R "$WEBOWNER:$WEBGROUP" "$WEBROOT/modern"

if [[ "$VERBOSE" == true ]]; then
  echo "[VERBOSE] Final deployment folder contents:"
  ls -lh "$WEBROOT/modern/"
fi

cat <<EOF

Deploy complete.
Webroot: $WEBROOT
Owner:   $WEBOWNER:$WEBGROUP
Version: $CURRENT_VERSION

=== DEPLOYMENT SUMMARY ===
✓ All files deployed from git repository
✓ Old/unused asset files cleaned up
✓ Versioned assets in place (app.$CURRENT_VERSION.js, etc.)
$(if [[ "$HAS_OLD_BROKER" == true ]]; then echo "✓ Old broker structure removed (backup: broker.backup.${STAMP})"; fi)
$(if [[ "$HAS_OLD_API" == true ]]; then echo "✓ Old API file removed (backup: php/modernapi.php.backup.${STAMP})"; fi)

=== IMPORTANT ===
- Broker is at: $WEBROOT/modern/broker/server.js
- API is at: $WEBROOT/modern/modernapi.php
- Old backups saved with timestamp: $STAMP

EOF

