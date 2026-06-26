#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/lib.sh"

require_cmd curl
require_cmd tar
require_cmd shasum
require_cmd find

OUTPUT_DIR="${REPO_ROOT}/dist"
WORK_DIR="${REPO_ROOT}/build/bundle"
SRC_DIR="${REPO_ROOT}/src"
DOWNLOAD_DIR="${WORK_DIR}/downloads"
ROOTFS_DIR="${WORK_DIR}/rootfs"

rm -rf "${WORK_DIR}"
mkdir -p "${OUTPUT_DIR}" "${DOWNLOAD_DIR}" "${ROOTFS_DIR}"

echo "Preparing bundle rootfs..."
cp -R "${SRC_DIR}/." "${ROOTFS_DIR}/"

# Strip macOS AppleDouble/resource-fork artifacts so the plugin bundle stays clean on Unraid.
find "${ROOTFS_DIR}" -name '._*' -type f -delete

mkdir -p "${ROOTFS_DIR}/usr/local/emhttp/plugins/${PLUGIN_NAME}/bin"

BINARY_PATH="${DOWNLOAD_DIR}/${KOMODO_BINARY_NAME}"
echo "Downloading Komodo Periphery ${KOMODO_VERSION}..."
curl -fsSL "${KOMODO_BINARY_URL}" -o "${BINARY_PATH}"

ACTUAL_SHA256=$(shasum -a 256 "${BINARY_PATH}" | awk '{print $1}')
if [[ "${ACTUAL_SHA256}" != "${KOMODO_BINARY_SHA256}" ]]; then
  echo "Checksum mismatch for ${KOMODO_BINARY_URL}" >&2
  echo "Expected: ${KOMODO_BINARY_SHA256}" >&2
  echo "Actual:   ${ACTUAL_SHA256}" >&2
  exit 1
fi

install -m 0755 "${BINARY_PATH}" \
  "${ROOTFS_DIR}/usr/local/emhttp/plugins/${PLUGIN_NAME}/bin/periphery"

find "${ROOTFS_DIR}" -type f \( -name '*.sh' -o -path "*/event/*" -o -name "rc.${PLUGIN_NAME}" \) \
  -exec chmod 0755 {} +

find "${ROOTFS_DIR}" -type f \( -name '*.page' -o -name '*.php' -o -name '*.cfg' -o -name '*.toml' -o -name '*.md' -o -name '*.svg' \) \
  -exec chmod 0644 {} +

BUNDLE_PATH="${OUTPUT_DIR}/$(bundle_name)"
rm -f "${BUNDLE_PATH}"
echo "Creating ${BUNDLE_PATH}..."
(
  cd "${ROOTFS_DIR}"
  COPYFILE_DISABLE=1 tar --no-xattrs -czf "${BUNDLE_PATH}" .
)

echo "Bundle created:"
echo "  ${BUNDLE_PATH}"
echo "  sha256: $(shasum -a 256 "${BUNDLE_PATH}" | awk '{print $1}')"
