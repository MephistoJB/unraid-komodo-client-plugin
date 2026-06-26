#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/common.sh"

load_config
ensure_layout
render_runtime_config

if is_running; then
  echo "Komodo Periphery is already running."
  exit 0
fi

require_start_config
is_runtime_ready

export PERIPHERY_CORE_ADDRESS
export PERIPHERY_CONNECT_AS
export PERIPHERY_ONBOARDING_KEY="${PERIPHERY_ONBOARDING_KEY:-}"
export PERIPHERY_CORE_PUBLIC_KEYS="${PERIPHERY_CORE_PUBLIC_KEYS:-}"
nohup "${BINARY}" --config-path "${RUNTIME_CONFIG_FILE}" >> "${LOG_FILE}" 2>&1 &
echo $! > "${PID_FILE}"
sleep 2

if ! is_running; then
  echo "Komodo Periphery failed to start. See ${LOG_FILE}."
  rm -f "${PID_FILE}"
  exit 1
fi

echo "Komodo Periphery started."
