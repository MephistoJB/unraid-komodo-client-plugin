#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(cd "${SCRIPT_DIR}/.." && pwd)
META_ENV="${REPO_ROOT}/meta/plugin.env"

if [[ ! -f "${META_ENV}" ]]; then
  echo "Missing ${META_ENV}" >&2
  exit 1
fi

# shellcheck disable=SC1090
source "${META_ENV}"

bundle_name() {
  printf '%s-%s-x86_64-1.tgz' "${PLUGIN_NAME}" "${PLUGIN_VERSION}"
}

release_tag() {
  printf 'v%s' "${PLUGIN_VERSION}"
}

bundle_url() {
  printf 'https://github.com/%s/%s/releases/download/%s/%s' \
    "${GITHUB_OWNER}" "${GITHUB_REPO}" "$(release_tag)" "$(bundle_name)"
}

plugin_url() {
  printf 'https://raw.githubusercontent.com/%s/%s/main/%s.plg' \
    "${GITHUB_OWNER}" "${GITHUB_REPO}" "${PLUGIN_NAME}"
}

icon_url() {
  printf 'https://raw.githubusercontent.com/%s/%s/main/assets/icon.svg' \
    "${GITHUB_OWNER}" "${GITHUB_REPO}"
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Required command not found: $1" >&2
    exit 1
  fi
}

