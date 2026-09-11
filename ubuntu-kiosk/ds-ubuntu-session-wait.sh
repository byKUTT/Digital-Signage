#!/usr/bin/env bash
set -euo pipefail

user_name="${DS_KIOSK_USER:?DS_KIOSK_USER is required}"
user_id="$(id -u "$user_name")"
user_home="$(getent passwd "$user_name" | cut -d: -f6)"
export DISPLAY="${DISPLAY:-:0}"
export XDG_RUNTIME_DIR="/run/user/${user_id}"
export DBUS_SESSION_BUS_ADDRESS="unix:path=${XDG_RUNTIME_DIR}/bus"

for _attempt in $(seq 1 180); do
	for authority in \
		"/run/user/${user_id}/gdm/Xauthority" \
		"${user_home}/.Xauthority"; do
		if [ -r "$authority" ]; then
			export XAUTHORITY="$authority"
			xset -display "$DISPLAY" q >/dev/null 2>&1 && break 2
		fi
	done
	sleep 2
done

if ! xset -display "$DISPLAY" q >/dev/null 2>&1; then
	echo "Xorg session for ${user_name} did not become ready." >&2
	exit 1
fi

xset -display "$DISPLAY" s off
xset -display "$DISPLAY" s noblank
xset -display "$DISPLAY" -dpms
exec /usr/local/lib/digital-signage-ubuntu/ds_ubuntu_controller.py
