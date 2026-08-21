#!/usr/bin/env bash
#
# Reverts install-kiosk.sh: removes the autostart hook, watchdog script and
# config file, and restores normal console/desktop login behavior.
#
# Usage: sudo bash uninstall-kiosk.sh [kiosk-user]

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run as root: sudo bash uninstall-kiosk.sh [user]" >&2
	exit 1
fi

KIOSK_USER="${1:-${SUDO_USER:-pi}}"
USER_HOME=$(getent passwd "$KIOSK_USER" | cut -d: -f6 || true)

echo "==> Removing autostart block from ${USER_HOME}/.bash_profile"
if [ -n "${USER_HOME:-}" ] && [ -f "${USER_HOME}/.bash_profile" ]; then
	sed -i '/# --- Digital Signage kiosk autostart ---/,/# --- end Digital Signage kiosk autostart ---/d' "${USER_HOME}/.bash_profile"
fi

echo "==> Removing watchdog script and config"
rm -f /usr/local/bin/ds-kiosk-loop.sh /etc/digital-signage-kiosk.conf

echo "==> Disabling console autologin"
if command -v raspi-config >/dev/null 2>&1; then
	raspi-config nonint do_boot_behaviour B1
else
	rm -f /etc/systemd/system/getty@tty1.service.d/autologin.conf
	systemctl daemon-reload
fi

echo ""
echo "✅ Kiosk mode removed for ${KIOSK_USER}. Reboot to apply: sudo reboot"
