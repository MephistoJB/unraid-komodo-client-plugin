#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

"${SCRIPT_DIR}/build-bundle.sh"
"${SCRIPT_DIR}/render-metadata.sh"

echo
echo "Release artifacts are ready in dist/ and repository metadata has been refreshed."

