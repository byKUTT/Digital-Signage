#!/usr/bin/env bash
#
# Digital Signage — Raspberry Pi kiosk installer.
#
# Turns a Raspberry Pi (running Raspberry Pi OS, Lite or Desktop, Bullseye or
# Bookworm) into a dedicated signage player: boots straight to a console
# autologin, starts a minimal X session, and opens this device's player URL
# full-screen in Chromium with no browser chrome. If Chromium ever crashes
# or the URL is unreachable at boot, a watchdog loop relaunches it.
#
# The device generates its own pairing token ONCE and stores it in
# /etc/digital-signage-kiosk.conf — every reboot reuses that same token, so
# the pairing code shown on screen (and the screen's identity once paired)
# never changes just because the Pi restarted. Re-running this installer is
# safe and keeps the existing token; pass --regenerate to force a new one
# (e.g. you're re-purposing this SD card for a different physical screen).
#
# Usage:
#   sudo bash install-kiosk.sh https://yourdomain.com [kiosk-user] [--regenerate]
#
# Run this ON the Raspberry Pi itself (SSH in, or use a keyboard/monitor).

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run as root: sudo bash install-kiosk.sh <site-url> [user] [--regenerate]" >&2
	exit 1
fi

SITE_URL="${1:-}"
KIOSK_USER="${2:-${SUDO_USER:-pi}}"
REGENERATE=0
for arg in "$@"; do
	[ "$arg" = "--regenerate" ] && REGENERATE=1
done
# A second positional arg that's actually the flag shouldn't be treated as a username.
[ "$KIOSK_USER" = "--regenerate" ] && KIOSK_USER="${SUDO_USER:-pi}"

if [ -z "$SITE_URL" ]; then
	echo "Usage: sudo bash install-kiosk.sh <site-url> [kiosk-user] [--regenerate]" >&2
	echo "Example: sudo bash install-kiosk.sh https://example.com pi" >&2
	exit 1
fi
SITE_URL="${SITE_URL%/}"

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

# --- Persistent device token: generate once, reuse forever (until --regenerate). ---
TOKEN=""
if [ -f "$CONF_FILE" ] && [ "$REGENERATE" -eq 0 ]; then
	# shellcheck disable=SC1090
	source "$CONF_FILE"
	TOKEN="${DS_KIOSK_TOKEN:-}"
fi
if [ -z "$TOKEN" ]; then
	TOKEN=$(tr -dc 'a-zA-Z0-9' < /dev/urandom | head -c 40)
	echo "==> Generated a new device token (this screen's permanent identity)."
else
	echo "==> Reusing existing device token from ${CONF_FILE}."
fi

URL="${SITE_URL}/signage/play/${TOKEN}/"

echo "==> Writing config to ${CONF_FILE}"
cat > "$CONF_FILE" <<EOF
# Digital Signage kiosk configuration.
# DS_KIOSK_TOKEN is this device's permanent identity — do not edit unless you
# intend to re-pair this device as a different screen. Change DS_KIOSK_SITE
# and reboot if the WordPress site moves to a new domain.
DS_KIOSK_TOKEN="${TOKEN}"
DS_KIOSK_SITE="${SITE_URL}"
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
echo "✅ Installed. Player URL (permanent for this device): ${URL}"
echo "   Kiosk user:            ${KIOSK_USER}"
echo "   Config file:           ${CONF_FILE}"
echo "   Watchdog script:       ${WATCHDOG}"
echo ""
echo "On first boot this screen will show a pairing code + QR code full-screen —"
echo "scan it or open wp-admin > Digital Signage > Pair a Screen to link it."
echo "The same code/token stays valid across every reboot."
echo ""
echo "Reboot now to start the kiosk: sudo reboot"
echo "To re-pair this device as a different screen: sudo bash install-kiosk.sh ${SITE_URL} ${KIOSK_USER} --regenerate"
echo "To remove: sudo bash uninstall-kiosk.sh ${KIOSK_USER}"
