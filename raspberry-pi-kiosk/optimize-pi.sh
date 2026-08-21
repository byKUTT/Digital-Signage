#!/usr/bin/env bash
# Raspberry Pi 3 performance profile for the Digital Signage kiosk.
# Safe to run repeatedly. Use --restore to remove only settings made here.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Run as root: sudo bash optimize-pi.sh [--restore]" >&2
	exit 1
fi

ACTION="${1:-apply}"
STATE_DIR="/var/lib/digital-signage-pi-optimizer"
MODEL_FILE="${DS_MODEL_FILE:-/proc/device-tree/model}"
SYSCTL_FILE="/etc/sysctl.d/90-digital-signage-pi3.conf"
JOURNAL_FILE="/etc/systemd/journald.conf.d/digital-signage-pi3.conf"
WIFI_FILE="/etc/NetworkManager/conf.d/digital-signage-wifi-powersave.conf"
GOVERNOR_SERVICE="/etc/systemd/system/ds-performance.service"
DISABLED_FILE="${STATE_DIR}/disabled-services"
CONFIG_FILE=""

if [ -f /boot/firmware/config.txt ]; then
	CONFIG_FILE="/boot/firmware/config.txt"
elif [ -f /boot/config.txt ]; then
	CONFIG_FILE="/boot/config.txt"
fi

restore_profile() {
	echo "==> Removing Digital Signage Pi performance profile"
	systemctl disable --now ds-performance.service >/dev/null 2>&1 || true
	rm -f "$GOVERNOR_SERVICE" "$SYSCTL_FILE" "$JOURNAL_FILE" "$WIFI_FILE"
	if [ -n "$CONFIG_FILE" ] && [ -f "$CONFIG_FILE" ]; then
		sed -i '/# --- Digital Signage KMS ---/,/# --- end Digital Signage KMS ---/d' "$CONFIG_FILE"
	fi
	if [ -f "$DISABLED_FILE" ]; then
		while IFS= read -r service; do
			[ -n "$service" ] && systemctl enable --now "$service" >/dev/null 2>&1 || true
		done < "$DISABLED_FILE"
	fi
	rm -rf "$STATE_DIR"
	systemctl daemon-reload
	sysctl --system >/dev/null 2>&1 || true
	systemctl restart systemd-journald >/dev/null 2>&1 || true
	echo "✅ Pi performance profile removed. Reboot to finish restoring graphics settings."
}

if [ "$ACTION" = "--restore" ]; then
	restore_profile
	exit 0
fi

MODEL="$(tr -d '\000' < "$MODEL_FILE" 2>/dev/null || true)"
if ! echo "$MODEL" | grep -qiE 'Raspberry Pi 3'; then
	echo "==> ${MODEL:-Unknown hardware}: Pi 3-specific tuning skipped."
	exit 0
fi

echo "==> Applying conservative Raspberry Pi 3 signage optimizations"
mkdir -p "$STATE_DIR" /etc/systemd/journald.conf.d /etc/NetworkManager/conf.d

# Modern Raspberry Pi OS uses the full KMS driver. Add it only when no VC4
# overlay is already selected, avoiding conflicts with an administrator's
# existing graphics configuration.
if [ -n "$CONFIG_FILE" ] && ! grep -qE '^[[:space:]]*dtoverlay=vc4-(f)?kms-v3d' "$CONFIG_FILE"; then
	cat >> "$CONFIG_FILE" <<'EOF'

# --- Digital Signage KMS ---
dtoverlay=vc4-kms-v3d
# --- end Digital Signage KMS ---
EOF
fi

cat > "$SYSCTL_FILE" <<'EOF'
# Keep interactive rendering responsive without disabling swap entirely.
vm.swappiness=10
vm.dirty_background_ratio=5
vm.dirty_ratio=15
vm.vfs_cache_pressure=50
EOF

cat > "$WIFI_FILE" <<'EOF'
[connection]
wifi.powersave=2
EOF

# Avoid continuous SD-card journal writes on an appliance. Runtime journals
# remain available through journalctl and are bounded to 32 MB.
cat > "$JOURNAL_FILE" <<'EOF'
[Journal]
Storage=volatile
RuntimeMaxUse=32M
RateLimitIntervalSec=30s
RateLimitBurst=500
EOF

cat > "$GOVERNOR_SERVICE" <<'EOF'
[Unit]
Description=Digital Signage Pi 3 performance governor
After=multi-user.target

[Service]
Type=oneshot
ExecStart=/bin/sh -c 'for governor in /sys/devices/system/cpu/cpufreq/policy*/scaling_governor; do [ -w "$governor" ] && echo performance > "$governor" || true; done'
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

# Record and disable only services unrelated to a dedicated display. Do not
# disable networking, SSH, Avahi/.local discovery, time sync or audio.
: > "$DISABLED_FILE"
for service in bluetooth.service hciuart.service cups.service cups-browsed.service ModemManager.service triggerhappy.service; do
	if systemctl is-enabled "$service" >/dev/null 2>&1; then
		echo "$service" >> "$DISABLED_FILE"
		systemctl disable --now "$service" >/dev/null 2>&1 || true
	fi
done

systemctl daemon-reload
systemctl enable --now ds-performance.service >/dev/null 2>&1 || true
sysctl --system >/dev/null 2>&1 || true
# Apply WiFi power-save immediately without restarting NetworkManager and
# interrupting the SSH session that is running this installer.
if command -v iw >/dev/null 2>&1; then
	iw dev wlan0 set power_save off >/dev/null 2>&1 || true
fi
systemctl restart systemd-journald >/dev/null 2>&1 || true

echo "✅ Raspberry Pi 3 profile applied. A reboot is required for KMS graphics changes."
