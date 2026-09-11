#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Run as root: sudo bash uninstall-kiosk.sh [--purge]" >&2
	exit 1
fi

purge=0
[ "${1:-}" = "--purge" ] && purge=1

systemctl disable --now digital-signage-ubuntu.service >/dev/null 2>&1 || true
rm -f /etc/systemd/system/digital-signage-ubuntu.service
rm -f /etc/sudoers.d/digital-signage-ubuntu
rm -f /usr/local/bin/ds-ubuntu-session-wait
rm -f /usr/local/bin/ds-ubuntu-autostart
rm -f /usr/local/sbin/digital-signage-update
rm -f /usr/local/sbin/digital-signage-root-command
rm -f /etc/xdg/autostart/bykutt-digital-signage.desktop
rm -f /etc/opt/chrome/policies/managed/bykutt-digital-signage.json
rm -f /etc/chromium/policies/managed/bykutt-digital-signage.json
rm -f /etc/chromium-browser/policies/managed/bykutt-digital-signage.json
rm -rf /usr/local/lib/digital-signage-ubuntu

backup="/var/lib/digital-signage-ubuntu-backups/gdm-custom.conf"
if [ -f "$backup" ]; then
	install -m 644 "$backup" /etc/gdm3/custom.conf
fi

if [ "$purge" -eq 1 ]; then
	rm -rf /etc/digital-signage-ubuntu /opt/bykutt-digital-signage
fi

systemctl daemon-reload
echo "Ubuntu kiosk removed. Reboot to return to the restored login configuration."
