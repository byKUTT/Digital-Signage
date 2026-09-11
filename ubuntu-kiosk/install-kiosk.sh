#!/usr/bin/env bash
set -euo pipefail

usage() {
	echo "Usage: sudo bash install-kiosk.sh <site-url> <kiosk-user> [--upgrade]" >&2
}

if [ "$(id -u)" -ne 0 ]; then
	usage
	exit 1
fi

site="${1:-}"
kiosk_user="${2:-}"
mode="${3:-}"
if [ -z "$site" ] || [ -z "$kiosk_user" ] || { [ -n "$mode" ] && [ "$mode" != "--upgrade" ]; }; then
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
apt-get update
apt-get install -y --no-install-recommends \
	python3 git x11-xserver-utils wmctrl xdotool dbus-x11 gdm3

firefox_bin="$(command -v firefox || true)"
if [ -z "$firefox_bin" ]; then
	apt-get install -y firefox
	firefox_bin="$(command -v firefox || true)"
fi
if [ -z "$firefox_bin" ]; then
	echo "Firefox could not be installed." >&2
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
if command -v snap >/dev/null 2>&1 && snap list firefox >/dev/null 2>&1; then
	profile_root="$kiosk_home/snap/firefox/common/digital-signage"
else
	profile_root="$kiosk_home/.local/share/digital-signage-ubuntu"
fi
mkdir -p "$install_root" "$config_root" "$backup_root"
chown root:"$kiosk_group" "$config_root"
chmod 770 "$config_root"
runuser -u "$kiosk_user" -- mkdir -p "$profile_root"
chown "$kiosk_user":"$kiosk_group" "$profile_root"
chmod 700 "$profile_root"

if [ "$mode" != "--upgrade" ] || [ ! -f "$config_root/settings.json" ]; then
	python3 - "$config_root/settings.json" "$site" "$kiosk_user" "$kiosk_home" "$profile_root" "$firefox_bin" "$managed_repo" "$branch" "$remote" <<'PY'
import json, os, sys
path, site, user, home, profile_root, firefox, repository, branch, remote = sys.argv[1:]
data = {
    "site": site,
    "user": user,
    "user_home": home,
    "profile_root": profile_root,
    "firefox": firefox,
    "repository_path": repository,
    "branch": branch,
    "remote": remote,
}
temporary = path + ".tmp"
with open(temporary, "w", encoding="utf-8") as handle:
    json.dump(data, handle, indent=2, sort_keys=True)
    handle.write("\n")
os.chmod(temporary, 0o644)
os.replace(temporary, path)
PY
	chown root:"$kiosk_group" "$config_root/settings.json"
	chmod 640 "$config_root/settings.json"
fi

install -o root -g root -m 755 "$managed_repo/ubuntu-kiosk/ds_ubuntu_controller.py" \
	"$install_root/ds_ubuntu_controller.py"
install -o root -g root -m 755 "$managed_repo/ubuntu-kiosk/ds-ubuntu-session-wait.sh" \
	/usr/local/bin/ds-ubuntu-session-wait
install -o root -g root -m 755 "$managed_repo/ubuntu-kiosk/update-kiosk.sh" \
	/usr/local/sbin/digital-signage-update
install -o root -g root -m 755 "$managed_repo/ubuntu-kiosk/digital-signage-root-command" \
	/usr/local/sbin/digital-signage-root-command

mkdir -p /etc/firefox/policies
install -o root -g root -m 644 /dev/stdin /etc/firefox/policies/policies.json <<'POLICY'
{
  "policies": {
    "DisableAppUpdate": true,
    "DisableFirefoxAccounts": true,
    "DisableFirefoxStudies": true,
    "DisablePocket": true,
    "DisableTelemetry": true,
    "DontCheckDefaultBrowser": true,
    "OfferToSaveLogins": false,
    "PasswordManagerEnabled": false,
    "UserMessaging": {
      "ExtensionRecommendations": false,
      "FeatureRecommendations": false,
      "WhatsNew": false
    }
  }
}
POLICY

gdm_config="/etc/gdm3/custom.conf"
gdm_backup="$backup_root/gdm-custom.conf"
if [ -f "$gdm_config" ] && [ ! -f "$gdm_backup" ]; then
	install -m 600 "$gdm_config" "$gdm_backup"
fi
python3 "$managed_repo/ubuntu-kiosk/configure_gdm.py" "$gdm_config" "$kiosk_user"
chmod 644 "$gdm_config"

service_template="$managed_repo/ubuntu-kiosk/systemd/digital-signage-ubuntu.service"
python3 - "$service_template" /etc/systemd/system/digital-signage-ubuntu.service \
	"$kiosk_user" "$kiosk_group" "$kiosk_home" <<'PY'
import pathlib, sys
source, target, user, group, home = sys.argv[1:]
text = pathlib.Path(source).read_text(encoding="utf-8")
text = text.replace("@@KIOSK_USER@@", user).replace("@@KIOSK_GROUP@@", group).replace("@@USER_HOME@@", home)
pathlib.Path(target).write_text(text, encoding="utf-8")
PY
chmod 644 /etc/systemd/system/digital-signage-ubuntu.service

sudoers_file="/etc/sudoers.d/digital-signage-ubuntu"
install -o root -g root -m 440 /dev/stdin "$sudoers_file" <<EOF
$kiosk_user ALL=(root) NOPASSWD: /usr/local/sbin/digital-signage-root-command reboot, /usr/local/sbin/digital-signage-root-command software-update, /usr/local/sbin/digital-signage-root-command system-update
EOF
visudo -cf "$sudoers_file" >/dev/null

if systemctl list-unit-files ds-kiosk.service >/dev/null 2>&1; then
	systemctl disable --now ds-kiosk.service >/dev/null 2>&1 || true
fi
systemctl unmask getty@tty1.service >/dev/null 2>&1 || true
systemctl set-default graphical.target
systemctl daemon-reload
systemctl enable digital-signage-ubuntu.service

if [ "$mode" = "--upgrade" ] && [ "${DS_SKIP_SERVICE_RESTART:-0}" != "1" ]; then
	systemctl try-restart digital-signage-ubuntu.service || true
fi

echo
echo "Digital Signage Ubuntu controller 3.2.0 is installed."
echo "No automatic reboot timer or reboot watchdog was installed."
echo "Reboot once to activate Xorg autologin: sudo reboot"
echo "Future updates: sudo digital-signage-update"
