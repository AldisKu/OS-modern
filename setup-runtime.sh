#!/usr/bin/env bash
set -euo pipefail

# Runtime setup for OrderSprinter Modern (broker + modern UI)
# - Installs Node.js + npm if missing
# - Deploys modern components into the legacy webroot
# - Creates /var/log/ordersprinter for API logs
# - Installs/starts broker systemd service

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

need_pkg() { command -v "$1" >/dev/null 2>&1; }

NODE_MAJOR="${NODE_MAJOR:-18}"

install_nodesource_deb() {
  curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | sudo -E bash -
  sudo apt-get install -y nodejs rsync curl
}

install_nodesource_rpm() {
  curl -fsSL "https://rpm.nodesource.com/setup_${NODE_MAJOR}.x" | sudo -E bash -
  if command -v dnf >/dev/null 2>&1; then
    sudo dnf install -y nodejs rsync curl
  else
    sudo yum install -y nodejs rsync curl
  fi
}

ensure_deps() {
  if need_pkg node && need_pkg npm && need_pkg rsync; then
    return 0
  fi
  if command -v apt-get >/dev/null 2>&1; then
    install_nodesource_deb
    return 0
  fi
  if command -v dnf >/dev/null 2>&1 || command -v yum >/dev/null 2>&1; then
    install_nodesource_rpm
    return 0
  fi
  echo "ERROR: Unsupported package manager. Install nodejs, npm, rsync manually." >&2
  exit 1
}

ensure_deps

echo "Deploying modern components to $WEBROOT"
WEBROOT="$WEBROOT" "$ROOT_DIR/deploy-modern.sh"

echo "Ensuring log directory /var/log/ordersprinter"
sudo mkdir -p /var/log/ordersprinter
sudo chown "$WEBOWNER:$WEBGROUP" /var/log/ordersprinter
sudo chmod 775 /var/log/ordersprinter

echo "Installing broker service from $WEBROOT/broker"
if [[ -x "$WEBROOT/broker/install.sh" ]]; then
  (cd "$WEBROOT/broker" && ./install.sh)
else
  echo "ERROR: $WEBROOT/broker/install.sh not found or not executable." >&2
  exit 1
fi

cat <<EOF

Runtime setup complete.
Webroot: $WEBROOT
Owner:   $WEBOWNER:$WEBGROUP

Next steps:
- If you update modern or broker files, rerun:
  WEBROOT=$WEBROOT $ROOT_DIR/deploy-modern.sh
- Restart broker after updates:
  sudo systemctl restart ordersprinter-broker
EOF
