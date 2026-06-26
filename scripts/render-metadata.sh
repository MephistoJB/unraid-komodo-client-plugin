#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/lib.sh"

require_cmd shasum

BUNDLE_PATH="${REPO_ROOT}/dist/$(bundle_name)"
if [[ ! -f "${BUNDLE_PATH}" ]]; then
  echo "Bundle not found: ${BUNDLE_PATH}" >&2
  echo "Run scripts/build-bundle.sh first." >&2
  exit 1
fi

BUNDLE_SHA256=$(shasum -a 256 "${BUNDLE_PATH}" | awk '{print $1}')
DESCRIPTION=$(tr '\n' ' ' < "${REPO_ROOT}/meta/description.txt" | sed 's/[[:space:]]\+/ /g')
CA_REQUIRES=$(tr '\n' ' ' < "${REPO_ROOT}/meta/ca-requires.txt" | sed 's/[[:space:]]\+/ /g')
CHANGELOG_BODY=$(sed '1d' "${REPO_ROOT}/CHANGELOG.md")

cat > "${REPO_ROOT}/${PLUGIN_NAME}.plg" <<EOF
<?xml version='1.0' standalone='yes'?>
<!DOCTYPE PLUGIN [
<!ENTITY name        "${PLUGIN_NAME}">
<!ENTITY author      "${PLUGIN_AUTHOR}">
<!ENTITY version     "${PLUGIN_VERSION}">
<!ENTITY launch      "${PLUGIN_LAUNCH}">
<!ENTITY pluginURL   "$(plugin_url)">
<!ENTITY bundle      "$(bundle_name)">
<!ENTITY bundleURL   "$(bundle_url)">
<!ENTITY bundleSHA256 "${BUNDLE_SHA256}">
<!ENTITY supportURL  "${SUPPORT_URL}">
<!ENTITY projectURL  "${PROJECT_URL}">
<!ENTITY readmeURL   "${README_URL}">
<!ENTITY plugdir     "/usr/local/emhttp/plugins/&name;">
<!ENTITY cfgdir      "/boot/config/plugins/&name;">
]>

<PLUGIN name="&name;" author="&author;" version="&version;" launch="&launch;" icon="${PLUGIN_ICON}" min="${PLUGIN_MIN_UNRAID}" pluginURL="&pluginURL;" support="&supportURL;" project="&projectURL;" readme="&readmeURL;">

<DESCRIPTION>${DESCRIPTION}</DESCRIPTION>

<CHANGES><![CDATA[
${CHANGELOG_BODY}
]]></CHANGES>

<FILE Name="&cfgdir;/&bundle;">
<URL>&bundleURL;</URL>
<SHA256>&bundleSHA256;</SHA256>
</FILE>

<FILE Run="/bin/bash">
<INLINE><![CDATA[
#!/bin/bash
set -euo pipefail

PLUGIN_NAME="&name;"
PLUGIN_VERSION="&version;"
CFG_DIR="/boot/config/plugins/\${PLUGIN_NAME}"
STATE_DIR="/boot/config/komodo/periphery-agent"
BUNDLE="\${CFG_DIR}/&bundle;"
INSTALL_DIR="/usr/local/emhttp/plugins/\${PLUGIN_NAME}"
RC_SCRIPT="/etc/rc.d/rc.\${PLUGIN_NAME}"

mkdir -p "\${CFG_DIR}" "\${STATE_DIR}"

if [[ ! -f "\${BUNDLE}" ]]; then
  echo "Bundle missing: \${BUNDLE}" >&2
  exit 1
fi

if [[ -x "\${RC_SCRIPT}" ]]; then
  "\${RC_SCRIPT}" stop || true
fi

rm -rf "\${INSTALL_DIR}"
mkdir -p "\${INSTALL_DIR}"
tar -xzf "\${BUNDLE}" -C /

chmod 0755 "/usr/local/emhttp/plugins/\${PLUGIN_NAME}/scripts/"*.sh
chmod 0755 "/etc/rc.d/rc.\${PLUGIN_NAME}"

rm -f \$(ls "\${CFG_DIR}/\${PLUGIN_NAME}-"*-x86_64-1.tgz 2>/dev/null | grep -v "\${PLUGIN_VERSION}" || true)

if [[ -x "\${RC_SCRIPT}" ]]; then
  "\${RC_SCRIPT}" install-init || true
  if grep -q '^SERVICE_ENABLED="yes"' "\${CFG_DIR}/\${PLUGIN_NAME}.cfg" 2>/dev/null; then
    "\${RC_SCRIPT}" start || true
  fi
fi

echo ""
echo "----------------------------------------------------"
echo " \${PLUGIN_NAME} installed"
echo " Plugin version: \${PLUGIN_VERSION}"
echo " Komodo binary: ${KOMODO_VERSION}"
echo "----------------------------------------------------"
echo ""
]]></INLINE>
</FILE>

<FILE Run="/bin/bash" Method="remove">
<INLINE><![CDATA[
#!/bin/bash
set -euo pipefail

PLUGIN_NAME="&name;"
RC_SCRIPT="/etc/rc.d/rc.\${PLUGIN_NAME}"

if [[ -x "\${RC_SCRIPT}" ]]; then
  "\${RC_SCRIPT}" stop || true
fi

rm -f "\${RC_SCRIPT}"
rm -rf "/usr/local/emhttp/plugins/\${PLUGIN_NAME}"

echo "Persistent files preserved:"
echo "  /boot/config/plugins/\${PLUGIN_NAME}"
echo "  /boot/config/komodo/periphery-agent"
echo "Remove them manually only if you want to rotate keys and wipe config."
]]></INLINE>
</FILE>

</PLUGIN>
EOF

cat > "${REPO_ROOT}/${PLUGIN_NAME}.xml" <<EOF
<?xml version="1.0" encoding="utf-8"?>
<Plugin>
    <PluginURL>$(plugin_url)</PluginURL>
    <PluginAuthor>${PLUGIN_AUTHOR}</PluginAuthor>
    <Category>${PLUGIN_CATEGORY}</Category>
    <Date>${CA_DATE}</Date>
    <Name>${PLUGIN_TITLE}</Name>
    <Description>${DESCRIPTION}</Description>
    <Requires>${CA_REQUIRES}</Requires>
    <MinVer>${PLUGIN_MIN_UNRAID}</MinVer>
    <Project>${PROJECT_URL}</Project>
    <Support>${SUPPORT_URL}</Support>
    <Icon>$(icon_url)</Icon>
    <License>MIT</License>
</Plugin>
EOF

echo "Generated:"
echo "  ${REPO_ROOT}/${PLUGIN_NAME}.plg"
echo "  ${REPO_ROOT}/${PLUGIN_NAME}.xml"
