#!/usr/bin/env bash
#
# Reverts install-kiosk.sh: removes ds-kiosk.service, the watchdog script,
# ds-agent, the setup portal and the config file, and restores normal
# console login behavior on tty1.
#
# Usage: sudo bash uninstall-kiosk.sh [kiosk-user]

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run as root: sudo bash uninstall-kiosk.sh [user]" >&2
	exit 1
fi

KIOSK_USER="${1:-${SUDO_USER:-pi}}"
USER_HOME=$(getent passwd "$KIOSK_USER" | cut -d: -f6 || true)

echo "==> Removing kiosk service"
systemctl disable --now ds-kiosk.service >/dev/null 2>&1 || true
rm -f /etc/systemd/system/ds-kiosk.service

echo "==> Removing any leftover autostart block from ${USER_HOME}/.bash_profile"
if [ -n "${USER_HOME:-}" ] && [ -f "${USER_HOME}/.bash_profile" ]; then
	sed -i '/# --- Digital Signage kiosk autostart ---/,/# --- end Digital Signage kiosk autostart ---/d' "${USER_HOME}/.bash_profile"
fi

echo "==> Removing watchdog script and config"
rm -f /usr/local/bin/ds-kiosk-loop.sh /etc/digital-signage-kiosk.conf

echo "==> Removing device-management agent and setup portal"
systemctl disable --now ds-agent.service >/dev/null 2>&1 || true
systemctl disable --now ds-setup.service >/dev/null 2>&1 || true
rm -f /etc/systemd/system/ds-agent.service /etc/systemd/system/ds-setup.service
rm -f /usr/local/bin/ds-agent.py /usr/local/bin/ds-setup-portal.py
rm -f /usr/local/bin/ds-setup-ap-up.sh /usr/local/bin/ds-setup-ap-down.sh
rm -f /usr/local/bin/install-kiosk.sh
nmcli connection delete ds-setup-ap >/dev/null 2>&1 || true

echo "==> Restoring normal console login on tty1"
rm -f /etc/systemd/system/getty@tty1.service.d/autologin.conf
if command -v raspi-config >/dev/null 2>&1; then
	raspi-config nonint do_boot_behaviour B1 >/dev/null 2>&1 || true
fi
systemctl enable getty@tty1.service >/dev/null 2>&1 || true
systemctl daemon-reload

echo ""
echo "✅ Kiosk mode removed for ${KIOSK_USER}. Reboot to apply: sudo reboot"
