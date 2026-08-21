#!/usr/bin/env bash
#
# Digital Signage — Raspberry Pi kiosk installer.
#
# Turns a Raspberry Pi (running Raspberry Pi OS, Lite or Desktop, Bullseye or
# Bookworm) into a dedicated signage player: boots straight to a console
# autologin, starts a minimal X session, and opens the given player URL
# full-screen in Chromium with no browser chrome. If Chromium ever crashes
# or the URL is unreachable at boot, a watchdog loop relaunches it.
#
# Usage:
#   sudo bash install-kiosk.sh https://yourdomain.com/signage/play/TOKEN/ [kiosk-user]
#
# Run this ON the Raspberry Pi itself (SSH in, or use a keyboard/monitor).

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run as root: sudo bash install-kiosk.sh <player-url> [user]" >&2
	exit 1
fi

URL="${1:-}"
KIOSK_USER="${2:-${SUDO_USER:-pi}}"

if [ -z "$URL" ]; then
	echo "Usage: sudo bash install-kiosk.sh <player-url> [kiosk-user]" >&2
	echo "Example: sudo bash install-kiosk.sh https://example.com/signage/play/abc123/ pi" >&2
	exit 1
fi

if ! id "$KIOSK_USER" &>/dev/null; then
	echo "User '$KIOSK_USER' does not exist on this system." >&2
	exit 1
fi

USER_HOME=$(getent passwd "$KIOSK_USER" | cut -d: -f6)
CONF_FILE="/etc/digital-signage-kiosk.conf"
WATCHDOG="/usr/local/bin/ds-kiosk-loop.sh"

echo "==> Installing packages (this can take a few minutes)…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
# chromium package name differs across Raspberry Pi OS releases.
CHROMIUM_PKG="chromium-browser"
if ! apt-cache show chromium-browser >/dev/null 2>&1; then
	CHROMIUM_PKG="chromium"
fi
apt-get install -y --no-install-recommends \
	xserver-xorg xinit x11-xserver-utils openbox unclutter "$CHROMIUM_PKG"

CHROMIUM_BIN=$(command -v chromium-browser || command -v chromium || true)
if [ -z "$CHROMIUM_BIN" ]; then
	echo "Could not find a chromium binary after install." >&2
	exit 1
fi

echo "==> Writing config to ${CONF_FILE}"
cat > "$CONF_FILE" <<EOF
# Digital Signage kiosk configuration. Edit and reboot to apply,
# or re-run install-kiosk.sh with a new URL.
DS_KIOSK_URL="${URL}"
DS_KIOSK_CHROMIUM="${CHROMIUM_BIN}"
EOF

echo "==> Writing watchdog launcher to ${WATCHDOG}"
cat > "$WATCHDOG" <<'EOF'
#!/usr/bin/env bash
# Relaunches Chromium in kiosk mode if it ever exits/crashes, so an
# unattended screen recovers on its own instead of showing a black screen.
set -u
source /etc/digital-signage-kiosk.conf

# Give the network a moment on cold boot before the first load.
sleep 5

while true; do
	"$DS_KIOSK_CHROMIUM" \
		--kiosk \
		--noerrdialogs \
		--disable-infobars \
		--disable-session-crashed-bubble \
		--disable-translate \
		--disable-pinch \
		--overscroll-history-navigation=0 \
		--no-first-run \
		--fast --fast-start \
		--check-for-update-interval=31536000 \
		--autoplay-policy=no-user-gesture-required \
		--incognito \
		"$DS_KIOSK_URL" \
		>/tmp/ds-kiosk-chromium.log 2>&1 || true
	sleep 3
done
EOF
chmod +x "$WATCHDOG"

echo "==> Writing ~/.xinitrc for ${KIOSK_USER}"
cat > "${USER_HOME}/.xinitrc" <<'EOF'
#!/usr/bin/env bash
# Minimal kiosk X session: no panel, no window borders, no screen blanking.
xset -dpms
xset s off
xset s noblank
unclutter -idle 0.5 -root &
openbox-session &
exec /usr/local/bin/ds-kiosk-loop.sh
EOF
chmod +x "${USER_HOME}/.xinitrc"
chown "$KIOSK_USER":"$KIOSK_USER" "${USER_HOME}/.xinitrc"

echo "==> Auto-starting X on console login for ${KIOSK_USER}"
PROFILE_FILE="${USER_HOME}/.bash_profile"
START_BLOCK='
# --- Digital Signage kiosk autostart ---
if [ -z "${DISPLAY:-}" ] && [ "$(tty)" = "/dev/tty1" ]; then
	exec startx -- -nocursor
fi
# --- end Digital Signage kiosk autostart ---
'
if [ ! -f "$PROFILE_FILE" ] || ! grep -q "Digital Signage kiosk autostart" "$PROFILE_FILE"; then
	echo "$START_BLOCK" >> "$PROFILE_FILE"
	chown "$KIOSK_USER":"$KIOSK_USER" "$PROFILE_FILE"
fi

echo "==> Enabling console autologin for ${KIOSK_USER}"
if command -v raspi-config >/dev/null 2>&1; then
	raspi-config nonint do_boot_behaviour B2
	# raspi-config's B2 always targets the 'pi'/first user account; fix the
	# generated getty override if a different kiosk user was requested.
	OVERRIDE="/etc/systemd/system/getty@tty1.service.d/autologin.conf"
	if [ -f "$OVERRIDE" ] && [ "$KIOSK_USER" != "pi" ]; then
		sed -i "s/--autologin [^ ]*/--autologin ${KIOSK_USER}/" "$OVERRIDE"
		systemctl daemon-reload
	fi
else
	mkdir -p /etc/systemd/system/getty@tty1.service.d
	cat > /etc/systemd/system/getty@tty1.service.d/autologin.conf <<EOF
[Service]
ExecStart=
ExecStart=-/sbin/agetty --autologin ${KIOSK_USER} --noclear %I \$TERM
EOF
	systemctl daemon-reload
	systemctl enable getty@tty1.service
fi

echo ""
echo "✅ Installed. Player URL: ${URL}"
echo "   Kiosk user:            ${KIOSK_USER}"
echo "   Config file:           ${CONF_FILE} (edit + reboot to change the URL)"
echo "   Watchdog script:       ${WATCHDOG}"
echo ""
echo "Reboot now to start the kiosk: sudo reboot"
echo "To remove: sudo bash uninstall-kiosk.sh ${KIOSK_USER}"
