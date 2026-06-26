#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/common.sh"

load_config
ensure_layout

RUNNING="no"
PID=""
if is_running; then
  RUNNING="yes"
  PID=$(current_pid)
fi

PUBLIC_KEY=""
if [[ -f "${PUBLIC_KEY_FILE}" ]]; then
  PUBLIC_KEY=$(tr -d '\n' < "${PUBLIC_KEY_FILE}")
fi

cat <<EOF
running="${RUNNING}"
pid="${PID}"
service_enabled="${SERVICE_ENABLED:-no}"
core_address="${PERIPHERY_CORE_ADDRESS:-}"
connect_as="${PERIPHERY_CONNECT_AS:-}"
root_directory="${PERIPHERY_ROOT_DIRECTORY:-}"
public_key_file="${PUBLIC_KEY_FILE}"
public_key="${PUBLIC_KEY}"
log_file="${LOG_FILE}"
runtime_config_file="${RUNTIME_CONFIG_FILE}"
plugin_version="$(plugin_version)"
EOF
