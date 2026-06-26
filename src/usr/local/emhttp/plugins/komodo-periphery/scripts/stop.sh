#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/common.sh"

if ! is_running; then
  rm -f "${PID_FILE}"
  echo "Komodo Periphery is not running."
  exit 0
fi

PID=$(current_pid)
kill "${PID}" 2>/dev/null || true

for _ in 1 2 3 4 5; do
  if ! kill -0 "${PID}" 2>/dev/null; then
    rm -f "${PID_FILE}"
    echo "Komodo Periphery stopped."
    exit 0
  fi
  sleep 1
done

kill -9 "${PID}" 2>/dev/null || true
rm -f "${PID_FILE}"
echo "Komodo Periphery force-stopped."

