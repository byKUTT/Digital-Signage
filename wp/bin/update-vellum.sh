#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VENDOR_DIR="${PLUGIN_DIR}/vendor/vellum"
UPSTREAM_URL="https://github.com/wieslawsoltes/Vellum.git"
TEMP_DIR="$(mktemp -d)"
trap 'if [[ -n "${TEMP_DIR:-}" && -d "${TEMP_DIR}" ]]; then rm -rf -- "${TEMP_DIR}"; fi' EXIT

git clone --depth 1 "${UPSTREAM_URL}" "${TEMP_DIR}/Vellum"
for file in index.html styles.css LICENSE README.md src/app.js src/document.js src/renderer.js src/svg.js src/icons.js; do
	[[ -s "${TEMP_DIR}/Vellum/${file}" ]] || { printf 'Missing required upstream file: %s\n' "${file}" >&2; exit 1; }
done
grep -q 'window.vellum = ' "${TEMP_DIR}/Vellum/src/app.js" || { printf 'Upstream Vellum automation API is incompatible; no files changed.\n' >&2; exit 1; }
install -d "${VENDOR_DIR}/src"
for file in index.html styles.css LICENSE README.md; do
	install -m 0644 "${TEMP_DIR}/Vellum/${file}" "${VENDOR_DIR}/${file}"
done
for file in app.js document.js renderer.js svg.js icons.js; do
	install -m 0644 "${TEMP_DIR}/Vellum/src/${file}" "${VENDOR_DIR}/src/${file}"
done
git -C "${TEMP_DIR}/Vellum" rev-parse HEAD > "${VENDOR_DIR}/UPSTREAM_COMMIT"
printf 'Vellum updated to %s\n' "$(tr -d '\n' < "${VENDOR_DIR}/UPSTREAM_COMMIT")"
