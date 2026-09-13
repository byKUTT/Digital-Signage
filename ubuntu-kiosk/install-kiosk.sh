#!/usr/bin/env bash
set -euo pipefail

usage() {
	echo "Usage: sudo bash install-kiosk.sh [site-url] [kiosk-user] [--upgrade]" >&2
}

if [ "$(id -u)" -ne 0 ]; then
	usage
	exit 1
fi

site="${1:-https://screens.kutt.ee}"
kiosk_user="${2:-${SUDO_USER:-}}"
mode="${3:-}"
if [ -z "$kiosk_user" ] || { [ -n "$mode" ] && [ "$mode" != "--upgrade" ]; }; then
	usage
	exit 2
fi
if ! id "$kiosk_user" >/dev/null 2>&1; then
	echo "User '$kiosk_user' does not exist." >&2
	exit 1
fi
if [ ! -r /etc/os-release ] || ! ( . /etc/os-release; [ "${ID:-}" = "ubuntu" ] ); then
	echo "This installer supports Ubuntu Desktop. Use raspberry-pi-kiosk on Raspberry Pi OS." >&2
	exit 1
fi

site="$(python3 - "$site" <<'PY'
import sys, urllib.parse
value = sys.argv[1].rstrip("/")
parsed = urllib.parse.urlparse(value)
if parsed.scheme not in ("http", "https") or not parsed.netloc or parsed.username or parsed.password or parsed.query or parsed.fragment:
    raise SystemExit("Invalid site URL")
print(value)
PY
)"

source_root="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || true)"
if [ -z "$source_root" ]; then
	echo "Run the installer from a Git clone of byKUTT/Digital-Signage." >&2
	exit 1
fi
branch="$(git -C "$source_root" symbolic-ref --short HEAD 2>/dev/null || true)"
remote="$(git -C "$source_root" remote get-url origin 2>/dev/null || true)"
if [ -z "$branch" ] || [ -z "$remote" ]; then
	echo "The source checkout needs an origin remote and a branch." >&2
	exit 1
fi
case "$remote" in
	https://github.com/byKUTT/Digital-Signage.git|git@github.com:byKUTT/Digital-Signage.git) ;;
	*) echo "Refusing unexpected Git origin: $remote" >&2; exit 1 ;;
esac

export DEBIAN_FRONTEND=noninteractive
if [ "$mode" != "--upgrade" ]; then
	apt-get update
	apt-get install -y --no-install-recommends \
		python3 git ca-certificates curl sudo x11-xserver-utils unclutter dbus-x11 gdm3
else
	for required_command in python3 git sudo xrandr unclutter dbus-launch cp chown chmod tee visudo; do
		if ! command -v "$required_command" >/dev/null 2>&1; then
			echo "Upgrade cannot continue because $required_command is missing. Run the full installer once." >&2
			exit 1
		fi
	done
fi

copy_managed_file() {
	local source_path="$1"
	local destination_path="$2"
	local mode_bits="$3"
	local owner_name="${4:-root}"
	local group_name="${5:-root}"
	if [ ! -r "$source_path" ]; then
		echo "Required installer file is missing: $source_path" >&2
		exit 1
	fi
	cp -- "$source_path" "$destination_path"
	chown "$owner_name:$group_name" "$destination_path"
	chmod "$mode_bits" "$destination_path"
}

write_managed_file() {
	local destination_path="$1"
	local mode_bits="$2"
	local temporary_path
	temporary_path="$(mktemp)"
	tee "$temporary_path" >/dev/null
	cp -- "$temporary_path" "$destination_path"
	chown root:root "$destination_path"
	chmod "$mode_bits" "$destination_path"
	rm -f "$temporary_path"
}

browser_bin=""
for browser_candidate in google-chrome-stable google-chrome; do
	if command -v "$browser_candidate" >/dev/null 2>&1; then
		browser_bin="$(command -v "$browser_candidate")"
		break
	fi
done
if [ -z "$browser_bin" ] && [ "$(dpkg --print-architecture)" = "amd64" ]; then
	chrome_deb="$(mktemp --suffix=.google-chrome.deb)"
	if ! curl --fail --location --retry 3 --output "$chrome_deb" \
		https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb; then
		rm -f "$chrome_deb"
		echo "Google Chrome download failed. Check this device's DNS, gateway, and internet access." >&2
		exit 1
	fi
	apt-get install -y "$chrome_deb"
	rm -f "$chrome_deb"
	browser_bin="$(command -v google-chrome-stable || command -v google-chrome || true)"
fi
if [ -z "$browser_bin" ]; then
	for browser_candidate in chromium chromium-browser; do
		if command -v "$browser_candidate" >/dev/null 2>&1; then
			browser_bin="$(command -v "$browser_candidate")"
			break
		fi
	done
fi
if [ -z "$browser_bin" ]; then
	apt-get install -y chromium-browser
	browser_bin="$(command -v chromium || command -v chromium-browser || true)"
fi
if [ -z "$browser_bin" ]; then
	echo "Google Chrome or Chromium could not be installed." >&2
	exit 1
fi

managed_repo="/opt/bykutt-digital-signage"
if [ ! -d "$managed_repo/.git" ]; then
	git clone --single-branch --branch "$branch" "$remote" "$managed_repo"
elif [ "$source_root" != "$managed_repo" ]; then
	if [ -n "$(git -C "$managed_repo" status --porcelain)" ]; then
		echo "Managed repository has local changes; refusing to overwrite them." >&2
		exit 1
	fi
	git -C "$managed_repo" fetch origin "$branch"
	git -C "$managed_repo" checkout "$branch"
	git -C "$managed_repo" merge --ff-only "origin/$branch"
fi

install_root="/usr/local/lib/digital-signage-ubuntu"
config_root="/etc/digital-signage-ubuntu"
backup_root="/var/lib/digital-signage-ubuntu-backups"
kiosk_home="$(getent passwd "$kiosk_user" | cut -d: -f6)"
kiosk_group="$(id -gn "$kiosk_user")"
profile_root="$kiosk_home/.local/share/digital-signage-ubuntu/chrome"
mkdir -p "$install_root" "$config_root" "$backup_root"
chown root:"$kiosk_group" "$config_root"
chmod 770 "$config_root"
runuser -u "$kiosk_user" -- mkdir -p "$profile_root"
chown "$kiosk_user":"$kiosk_group" "$profile_root"
chmod 700 "$profile_root"

python3 - "$config_root/settings.json" "$site" "$kiosk_user" "$kiosk_home" "$profile_root" "$browser_bin" "$managed_repo" "$branch" "$remote" <<'PY'
import json, os, sys
path, site, user, home, profile_root, browser, repository, branch, remote = sys.argv[1:]
data = {}
if os.path.exists(path):
    with open(path, encoding="utf-8") as handle:
        existing = json.load(handle)
    if isinstance(existing, dict):
        data.update(existing)
data.update({
    "site": site,
    "user": user,
    "user_home": home,
    "profile_root": profile_root,
    "browser": browser,
    "repository_path": repository,
    "branch": branch,
    "remote": remote,
})
data.pop("firefox", None)
temporary = path + ".tmp"
with open(temporary, "w", encoding="utf-8") as handle:
    json.dump(data, handle, indent=2, sort_keys=True)
    handle.write("\n")
os.chmod(temporary, 0o644)
os.replace(temporary, path)
PY
chown root:"$kiosk_group" "$config_root/settings.json"
chmod 640 "$config_root/settings.json"

copy_managed_file "$managed_repo/ubuntu-kiosk/ds_ubuntu_controller.py" "$install_root/ds_ubuntu_controller.py" 755
copy_managed_file "$managed_repo/ubuntu-kiosk/ds-ubuntu-autostart.sh" /usr/local/bin/ds-ubuntu-autostart 755
copy_managed_file "$managed_repo/ubuntu-kiosk/update-kiosk.sh" /usr/local/sbin/digital-signage-update 755
copy_managed_file "$managed_repo/ubuntu-kiosk/digital-signage-root-command" /usr/local/sbin/digital-signage-root-command 755
copy_managed_file "$managed_repo/ubuntu-kiosk/systemd/digital-signage-ubuntu-update.service" /etc/systemd/system/digital-signage-ubuntu-update.service 644

mkdir -p /etc/opt/chrome/policies/managed /etc/chromium/policies/managed /etc/chromium-browser/policies/managed /etc/xdg/autostart
for policy_root in /etc/opt/chrome/policies/managed /etc/chromium/policies/managed /etc/chromium-browser/policies/managed; do
	write_managed_file "$policy_root/bykutt-digital-signage.json" 644 <<'POLICY'
{
  "AutofillAddressEnabled": false,
  "AutofillCreditCardEnabled": false,
  "BrowserSignin": 0,
  "BrowserAddPersonEnabled": false,
  "BrowserGuestModeEnabled": false,
  "DefaultBrowserSettingEnabled": false,
  "ForceBrowserSignin": false,
  "MetricsReportingEnabled": false,
  "PasswordManagerEnabled": false,
  "PromotionalTabsEnabled": false,
  "SigninAllowed": false,
  "SyncDisabled": true,
  "TranslateEnabled": false
}
POLICY
	done
copy_managed_file "$managed_repo/ubuntu-kiosk/autostart/bykutt-digital-signage.desktop" /etc/xdg/autostart/bykutt-digital-signage.desktop 644

gdm_config="/etc/gdm3/custom.conf"
gdm_backup="$backup_root/gdm-custom.conf"
if [ -f "$gdm_config" ] && [ ! -f "$gdm_backup" ]; then
	copy_managed_file "$gdm_config" "$gdm_backup" 600
fi
python3 "$managed_repo/ubuntu-kiosk/configure_gdm.py" "$gdm_config" "$kiosk_user"
chmod 644 "$gdm_config"

systemctl disable --now digital-signage-ubuntu.service >/dev/null 2>&1 || true
rm -f /etc/systemd/system/digital-signage-ubuntu.service /usr/local/bin/ds-ubuntu-session-wait

sudoers_file="/etc/sudoers.d/digital-signage-ubuntu"
write_managed_file "$sudoers_file" 440 <<EOF
Defaults:$kiosk_user !requiretty, listpw=never
Cmnd_Alias DIGITAL_SIGNAGE_ROOT = /usr/local/sbin/digital-signage-root-command check, /usr/local/sbin/digital-signage-root-command software-update, /usr/local/sbin/digital-signage-root-command system-update, /usr/local/sbin/digital-signage-root-command reboot, /usr/local/sbin/digital-signage-root-command diagnostic-update, /usr/local/sbin/digital-signage-root-command diagnostic-controller, /usr/local/sbin/digital-signage-root-command diagnostic-network, /usr/local/sbin/digital-signage-root-command diagnostic-system
$kiosk_user ALL=(root) NOPASSWD: DIGITAL_SIGNAGE_ROOT
EOF
visudo -cf "$sudoers_file" >/dev/null

mkdir -p /etc/systemd/logind.conf.d /etc/systemd/sleep.conf.d
write_managed_file /etc/systemd/logind.conf.d/bykutt-digital-signage.conf 644 <<'LOGIND'
[Login]
IdleAction=ignore
HandleLidSwitch=ignore
HandleLidSwitchExternalPower=ignore
HandleLidSwitchDocked=ignore
LOGIND
write_managed_file /etc/systemd/sleep.conf.d/bykutt-digital-signage.conf 644 <<'SLEEP'
[Sleep]
AllowSuspend=no
AllowHibernation=no
AllowSuspendThenHibernate=no
AllowHybridSleep=no
SLEEP

if systemctl list-unit-files ds-kiosk.service >/dev/null 2>&1; then
	systemctl disable --now ds-kiosk.service >/dev/null 2>&1 || true
fi
systemctl unmask getty@tty1.service >/dev/null 2>&1 || true
systemctl set-default graphical.target
systemctl daemon-reload
runuser -u "$kiosk_user" -- sudo -n /usr/local/sbin/digital-signage-root-command check >/dev/null

if [ "$mode" = "--upgrade" ] && [ "${DS_SKIP_SERVICE_RESTART:-0}" != "1" ]; then
	pid_file="$profile_root/controller.pid"
	if [ -r "$pid_file" ]; then
		controller_pid="$(tr -cd '0-9' < "$pid_file")"
		if [ -n "$controller_pid" ] && [ -r "/proc/$controller_pid/cmdline" ] && \
			tr '\0' ' ' < "/proc/$controller_pid/cmdline" | grep -q '/digital-signage-ubuntu/ds_ubuntu_controller.py'; then
			kill -TERM "$controller_pid" || true
		fi
	fi
fi

echo
echo "Screens byKUTT Ubuntu controller 4.6.0 is installed."
echo "No automatic reboot timer or reboot watchdog was installed."
echo "Reboot once to activate Xorg autologin: sudo reboot"
echo "Future updates: sudo digital-signage-update"
