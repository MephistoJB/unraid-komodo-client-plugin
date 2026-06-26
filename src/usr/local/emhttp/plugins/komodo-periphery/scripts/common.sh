#!/bin/bash
set -euo pipefail

PLUGIN_NAME="komodo-periphery"
PLUGIN_DIR="/usr/local/emhttp/plugins/${PLUGIN_NAME}"
CONFIG_DIR="/boot/config/plugins/${PLUGIN_NAME}"
CONFIG_FILE="${CONFIG_DIR}/${PLUGIN_NAME}.cfg"
STATE_DIR="/boot/config/komodo/periphery"
KEYS_DIR="${STATE_DIR}/keys"
RUNTIME_CONFIG_DIR="${STATE_DIR}/config"
RUNTIME_CONFIG_FILE="${RUNTIME_CONFIG_DIR}/periphery.config.toml"
PID_FILE="/var/run/${PLUGIN_NAME}.pid"
LOG_FILE="/var/log/${PLUGIN_NAME}.log"
BINARY="${PLUGIN_DIR}/bin/periphery"
DEFAULT_CFG="${PLUGIN_DIR}/default.cfg"
PUBLIC_KEY_FILE="${KEYS_DIR}/periphery.pub"
PRIVATE_KEY_FILE="${KEYS_DIR}/periphery.key"

load_config() {
  if [[ -f "${DEFAULT_CFG}" ]]; then
    # shellcheck disable=SC1090
    source "${DEFAULT_CFG}"
  fi

  if [[ -f "${CONFIG_FILE}" ]]; then
    # shellcheck disable=SC1090
    source "${CONFIG_FILE}"
  fi
}

ensure_layout() {
  mkdir -p "${CONFIG_DIR}" "${STATE_DIR}" "${KEYS_DIR}" "${RUNTIME_CONFIG_DIR}" "${PERIPHERY_ROOT_DIRECTORY}"

  if [[ ! -f "${CONFIG_FILE}" ]]; then
    cp "${DEFAULT_CFG}" "${CONFIG_FILE}"
  fi
}

bool_to_toml() {
  case "${1:-no}" in
    yes|true|1|on) echo "true" ;;
    *) echo "false" ;;
  esac
}

csv_to_toml_array() {
  local input="${1:-}"
  local result=""
  local item
  IFS=',' read -ra parts <<< "${input}"
  for item in "${parts[@]}"; do
    item=$(echo "${item}" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
    [[ -z "${item}" ]] && continue
    if [[ -n "${result}" ]]; then
      result+=", "
    fi
    result+="\"${item}\""
  done
  printf '[%s]' "${result}"
}

render_runtime_config() {
  local include_mounts exclude_mounts
  local tmp_file
  include_mounts=$(csv_to_toml_array "${PERIPHERY_INCLUDE_DISK_MOUNTS:-}")
  exclude_mounts=$(csv_to_toml_array "${PERIPHERY_EXCLUDE_DISK_MOUNTS:-}")
  tmp_file=$(mktemp)

  cat > "${tmp_file}" <<EOF
root_directory = "${PERIPHERY_ROOT_DIRECTORY}"
default_terminal_command = "bash"
disable_terminals = $(bool_to_toml "${PERIPHERY_DISABLE_TERMINALS:-no}")
disable_container_terminals = $(bool_to_toml "${PERIPHERY_DISABLE_CONTAINER_TERMINALS:-no}")
include_disk_mounts = ${include_mounts}
exclude_disk_mounts = ${exclude_mounts}
private_key = "file:${PRIVATE_KEY_FILE}"
logging.level = "${PERIPHERY_LOG_LEVEL:-info}"
logging.stdio = "standard"
logging.pretty = false
pretty_startup_config = false
EOF

  if [[ ! -f "${RUNTIME_CONFIG_FILE}" ]] || ! cmp -s "${tmp_file}" "${RUNTIME_CONFIG_FILE}"; then
    mv "${tmp_file}" "${RUNTIME_CONFIG_FILE}"
  else
    rm -f "${tmp_file}"
  fi
}

is_running() {
  if [[ -f "${PID_FILE}" ]]; then
    local pid
    pid=$(cat "${PID_FILE}")
    if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
      return 0
    fi
  fi
  return 1
}

require_start_config() {
  if [[ "${SERVICE_ENABLED:-no}" != "yes" ]]; then
    echo "Service is disabled in configuration."
    return 1
  fi
  if [[ -z "${PERIPHERY_CORE_ADDRESS:-}" ]]; then
    echo "PERIPHERY_CORE_ADDRESS is required."
    return 1
  fi
  if [[ -z "${PERIPHERY_CONNECT_AS:-}" ]]; then
    echo "PERIPHERY_CONNECT_AS is required."
    return 1
  fi
  if [[ ! -x "${BINARY}" ]]; then
    echo "Periphery binary not found at ${BINARY}."
    return 1
  fi
}

current_pid() {
  [[ -f "${PID_FILE}" ]] && cat "${PID_FILE}" || true
}

plugin_version() {
  cat /usr/local/emhttp/plugins/${PLUGIN_NAME}/VERSION 2>/dev/null || echo "unknown"
}

is_runtime_ready() {
  if ! mount | grep -q 'on /mnt/user '; then
    echo "User shares are not mounted yet."
    return 1
  fi

  return 0
}
