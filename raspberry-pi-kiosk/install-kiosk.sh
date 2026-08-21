#!/usr/bin/env bash
#
# Digital Signage — Raspberry Pi kiosk installer.
#
# Turns a Raspberry Pi (running Raspberry Pi OS, Lite or Desktop, Bullseye or
# Bookworm) into a dedicated signage player: a systemd service (ds-kiosk,
# no console autologin involved) takes tty1 straight to a minimal X session
# and opens this device's player URL full-screen in Chromium with no browser
# chrome. If Chromium ever crashes or the URL is unreachable at boot, a
# watchdog loop relaunches it.
#
# Also deploys ds-agent, a small background service that lets this device be
# managed remotely from the plugin's admin UI — WiFi network, screen
# rotation, reboot, restart-the-browser and check-for-updates, all from the
# Screen's edit page in wp-admin, no SSH/keyboard needed after this point.
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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
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
	xserver-xorg xinit x11-xserver-utils openbox unclutter python3 "$CHROMIUM_PKG"

CHROMIUM_BIN=$(command -v chromium-browser || command -v chromium || true)
if [ -z "$CHROMIUM_BIN" ]; then
	echo "Could not find a chromium binary after install." >&2
	exit 1
fi

# --- Persistent device token: generate once, reuse forever (until --regenerate). ---
TOKEN=""
ROTATION="normal"
if [ -f "$CONF_FILE" ] && [ "$REGENERATE" -eq 0 ]; then
	# shellcheck disable=SC1090
	source "$CONF_FILE"
	TOKEN="${DS_KIOSK_TOKEN:-}"
	ROTATION="${DS_KIOSK_ROTATION:-normal}"
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
# and reboot if the WordPress site moves to a new domain. DS_KIOSK_ROTATION
# is normally managed remotely from wp-admin (Screen edit page > Device).
DS_KIOSK_TOKEN="${TOKEN}"
DS_KIOSK_SITE="${SITE_URL}"
DS_KIOSK_URL="${URL}"
DS_KIOSK_CHROMIUM="${CHROMIUM_BIN}"
DS_KIOSK_ROTATION="${ROTATION}"
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

# Apply any rotation set remotely from wp-admin (Screen edit page > Device)
# before the browser starts, so it opens already in the right orientation.
if [ "${DS_KIOSK_ROTATION:-normal}" != "normal" ]; then
	for output in $(xrandr --listmonitors 2>/dev/null | awk '/ /{print $NF}'); do
		xrandr --output "$output" --rotate "$DS_KIOSK_ROTATION" 2>/dev/null || true
	done
fi

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

# --- Clean up the old (pre-2.3.1) autostart mechanism if present: a login-shell
# ~/.bash_profile hook + console autologin. It depended on the exact shell,
# login-file-sourcing behavior and getty timing, which didn't reliably start X
# on every Raspberry Pi OS build. Replaced below by a systemd service that
# owns tty1 directly — no login shell, autologin or .bash_profile involved. ---
PROFILE_FILE="${USER_HOME}/.bash_profile"
if [ -f "$PROFILE_FILE" ] && grep -q "Digital Signage kiosk autostart" "$PROFILE_FILE"; then
	sed -i '/# --- Digital Signage kiosk autostart ---/,/# --- end Digital Signage kiosk autostart ---/d' "$PROFILE_FILE"
fi
if command -v raspi-config >/dev/null 2>&1; then
	raspi-config nonint do_boot_behaviour B1 >/dev/null 2>&1 || true
fi
rm -f /etc/systemd/system/getty@tty1.service.d/autologin.conf
systemctl daemon-reload 2>/dev/null || true

echo "==> Installing ds-kiosk.service (owns tty1, starts X directly — no autologin needed)"
cat > /etc/systemd/system/ds-kiosk.service <<EOF
[Unit]
Description=Digital Signage kiosk (X + Chromium)
After=network-online.target systemd-user-sessions.service getty@tty1.service
Wants=network-online.target
Conflicts=getty@tty1.service

[Service]
Type=simple
User=${KIOSK_USER}
PAMName=login
TTYPath=/dev/tty1
StandardInput=tty
StandardOutput=journal
StandardError=journal
UtmpIdentifier=tty1
Restart=always
RestartSec=3
ExecStart=/usr/bin/startx ${USER_HOME}/.xinitrc -- vt1 -nocursor

[Install]
WantedBy=graphical.target multi-user.target
EOF
systemctl daemon-reload
# The two units both want tty1; disabling the getty (Conflicts= above handles
# runtime, this stops it winning a future boot race) hands the console to us.
systemctl disable getty@tty1.service >/dev/null 2>&1 || true
systemctl enable ds-kiosk.service

# --- Remote device management agent (WiFi, rotation, reboot, updates from wp-admin) ---
AGENT_INSTALLED=0
if [ -f "${SCRIPT_DIR}/ds-agent/ds-agent.py" ]; then
	echo "==> Installing the device-management agent (ds-agent)…"
	install -m 755 "${SCRIPT_DIR}/ds-agent/ds-agent.py" /usr/local/bin/ds-agent.py
	install -m 644 "${SCRIPT_DIR}/ds-agent/ds-agent.service" /etc/systemd/system/ds-agent.service
	systemctl daemon-reload
	systemctl enable --now ds-agent.service
	AGENT_INSTALLED=1

	if ! command -v nmcli >/dev/null 2>&1; then
		echo "   ⚠ nmcli not found — remote WiFi changes need NetworkManager"
		echo "     (the default network stack on Raspberry Pi OS Bookworm and newer)."
		echo "     Rotation, reboot, restart-browser and update commands still work."
	fi
else
	echo "==> Skipping ds-agent install (ds-agent/ directory not found next to this script)."
	echo "    Download the full raspberry-pi-kiosk folder (not just install-kiosk.sh) to get"
	echo "    remote WiFi/rotation/reboot management from wp-admin."
fi

# --- First-boot setup portal (only ever fires again if the config file above is
#     removed — e.g. wiping the SD card's config to re-provision this device by
#     WiFi hotspot instead of SSH). Harmless to install even when unused. ---
if [ -f "${SCRIPT_DIR}/setup-portal/ds-setup-portal.py" ]; then
	install -m 755 "${SCRIPT_DIR}/setup-portal/ds-setup-portal.py" /usr/local/bin/ds-setup-portal.py
	install -m 755 "${SCRIPT_DIR}/setup-portal/ds-setup-ap-up.sh" /usr/local/bin/ds-setup-ap-up.sh
	install -m 755 "${SCRIPT_DIR}/setup-portal/ds-setup-ap-down.sh" /usr/local/bin/ds-setup-ap-down.sh
	install -m 644 "${SCRIPT_DIR}/setup-portal/ds-setup.service" /etc/systemd/system/ds-setup.service
	# Also make this installer itself reachable by that portal's handoff step.
	install -m 755 "${SCRIPT_DIR}/install-kiosk.sh" /usr/local/bin/install-kiosk.sh
	systemctl daemon-reload
	systemctl enable ds-setup.service >/dev/null 2>&1 || true
fi

echo ""
echo "✅ Installed. Player URL (permanent for this device): ${URL}"
echo "   Kiosk user:            ${KIOSK_USER}"
echo "   Config file:           ${CONF_FILE}"
echo "   Watchdog script:       ${WATCHDOG}"
echo "   Kiosk service:         ds-kiosk.service (owns tty1, starts X directly)"
if [ "$AGENT_INSTALLED" -eq 1 ]; then
	echo "   Device agent:          installed and running (ds-agent.service)"
fi
echo ""
echo "On first boot this screen will show a pairing code + QR code full-screen —"
echo "scan it or open wp-admin > Digital Signage > Pair a Screen to link it."
echo "The same code/token stays valid across every reboot."
if [ "$AGENT_INSTALLED" -eq 1 ]; then
	echo ""
	echo "Once paired, open this screen in wp-admin (Digital Signage > Screens) to see a"
	echo "'Device' panel — change its WiFi network, rotate the display, restart the"
	echo "browser, reboot, or check for updates, all remotely."
fi
echo ""
echo "Reboot now to start the kiosk: sudo reboot"
echo "Already rebooted and stuck at a terminal from an older install? Start it now"
echo "without rebooting again: sudo systemctl start ds-kiosk.service"
echo "Check its status/logs any time: sudo systemctl status ds-kiosk ; sudo journalctl -u ds-kiosk -f"
echo "To re-pair this device as a different screen: sudo bash install-kiosk.sh ${SITE_URL} ${KIOSK_USER} --regenerate"
echo "To remove: sudo bash uninstall-kiosk.sh ${KIOSK_USER}"
