#!/usr/bin/env bash
set -u

settings_file="/etc/digital-signage-ubuntu/settings.json"
controller="/usr/local/lib/digital-signage-ubuntu/ds_ubuntu_controller.py"

if [ ! -r "$settings_file" ]; then
	exit 1
fi

configured_user="$(python3 - "$settings_file" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as handle:
    print(str(json.load(handle).get("user", "")))
PY
)"

if [ -z "$configured_user" ] || [ "${USER:-}" != "$configured_user" ]; then
	exit 0
fi

# This script is started by XDG autostart and deliberately inherits DISPLAY,
# XAUTHORITY, D-Bus, and XDG_RUNTIME_DIR from the real GDM user session.
for _attempt in $(seq 1 60); do
	if [ -n "${DISPLAY:-}" ] && xrandr --query >/dev/null 2>&1; then
		break
	fi
	sleep 2
done

if [ -z "${DISPLAY:-}" ] || ! xrandr --query >/dev/null 2>&1; then
	echo "Digital Signage: graphical Xorg session did not become ready." >&2
	exit 1
fi

xset s off >/dev/null 2>&1 || true
xset s noblank >/dev/null 2>&1 || true
xset -dpms >/dev/null 2>&1 || true
gsettings set org.gnome.desktop.session idle-delay 0 >/dev/null 2>&1 || true
gsettings set org.gnome.desktop.screensaver lock-enabled false >/dev/null 2>&1 || true
gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-ac-type 'nothing' >/dev/null 2>&1 || true
unclutter -idle 0.1 -root >/dev/null 2>&1 &

while true; do
	"$controller"
	status=$?
	echo "Digital Signage controller exited with status ${status}; restarting in 5 seconds." >&2
	sleep 5
done
