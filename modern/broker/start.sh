#!/usr/bin/env bash
set -euo pipefail

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl start ordersprinter-broker
  sudo systemctl status --no-pager ordersprinter-broker
  exit 0
fi

BROKER_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$BROKER_DIR"

if [ -f broker.pid ] && ps -p "$(cat broker.pid)" >/dev/null 2>&1; then
  echo "Broker already running (PID $(cat broker.pid))."
  exit 0
fi

nohup node server.js > broker.log 2>&1 &

echo $! > broker.pid

echo "Broker started (PID $(cat broker.pid)). Logs: broker.log"
