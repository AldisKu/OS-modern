#!/usr/bin/env bash
set -euo pipefail

BROKER_DIR="$(cd "$(dirname "$0")" && pwd)"
SERVICE_NAME="ordersprinter-broker"

if ! command -v node >/dev/null 2>&1; then
  echo "Node.js is required." >&2
  exit 1
fi

cd "$BROKER_DIR"

npm install

cat > /tmp/${SERVICE_NAME}.service <<'SERVICE'
[Unit]
Description=OrderSprinter WebSocket Broker
After=network.target

[Service]
Type=simple
WorkingDirectory=/home/aldis/ordersprinter/broker
ExecStart=/usr/bin/node /home/aldis/ordersprinter/broker/server.js
Restart=always
Environment=PORT=3077
Environment=BROKER_TOKEN=

[Install]
WantedBy=multi-user.target
SERVICE

if command -v systemctl >/dev/null 2>&1; then
  sudo mv /tmp/${SERVICE_NAME}.service /etc/systemd/system/${SERVICE_NAME}.service
  sudo systemctl daemon-reload
  sudo systemctl enable ${SERVICE_NAME}
  sudo systemctl restart ${SERVICE_NAME}
  echo "Service ${SERVICE_NAME} installed and started."
else
  echo "systemctl not found. Run broker manually with: node server.js"
fi
