#!/usr/bin/env bash
set -Eeuo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Run this command with sudo: sudo digital-signage-update" >&2
	exit 1
fi

if [ -n "${1:-}" ]; then
	echo "Usage: sudo digital-signage-update" >&2
	exit 2
fi

settings_file="/etc/digital-signage-ubuntu/settings.json"
status_file="/etc/digital-signage-ubuntu/update-status.json"
log_file="/var/log/digital-signage-ubuntu-update.log"
stage="startup"
update_completed=0

write_status() {
	python3 - "$status_file" "$1" "$2" <<'PY'
import json, os, sys, time
path, status, message = sys.argv[1:]
temporary = path + ".tmp"
with open(temporary, "w", encoding="utf-8") as handle:
    json.dump({"status": status, "message": message[:3500], "updated_at": int(time.time())}, handle, sort_keys=True)
    handle.write("\n")
os.chmod(temporary, 0o644)
os.replace(temporary, path)
PY
}

on_exit() {
	status=$?
	if [ "$update_completed" -eq 0 ]; then
		recent="$(tail -n 18 "$log_file" 2>/dev/null || true)"
		if [ "$status" -ne 0 ]; then
			write_status "failed" "Update failed during ${stage} (exit ${status}). ${recent}"
		fi
	fi
}

mkdir -p "$(dirname "$log_file")"
touch "$log_file"
chmod 640 "$log_file"
exec > >(tee -a "$log_file") 2>&1
trap on_exit EXIT

stage="reading controller settings"
if [ ! -r "$settings_file" ]; then
	echo "Ubuntu controller settings are missing. Run install-kiosk.sh first." >&2
	exit 1
fi
write_status "running" "Reading controller settings."
readarray -t settings < <(python3 - "$settings_file" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as handle:
    data = json.load(handle)
for key in ("repository_path", "branch", "remote", "site", "user"):
    value = str(data.get(key, ""))
    if "\n" in value or "\r" in value:
        raise SystemExit("Invalid setting")
    print(value)
PY
)

repository_path="${settings[0]}"
branch="${settings[1]}"
expected_remote="${settings[2]}"
site="${settings[3]}"
kiosk_user="${settings[4]}"

stage="validating the managed repository"
write_status "running" "Validating the managed Git repository."
if [ "$repository_path" != "/opt/bykutt-digital-signage" ] || [ ! -d "$repository_path/.git" ]; then
	echo "The managed Git repository is missing or unexpected: $repository_path" >&2
	exit 1
fi
if [ "$(git -C "$repository_path" remote get-url origin)" != "$expected_remote" ]; then
	echo "The managed Git origin does not match the installed controller settings." >&2
	exit 1
fi

stage="downloading the newest Git revision"
write_status "running" "Downloading the newest Digital Signage revision."
git -C "$repository_path" fetch --prune origin "$branch"

stage="synchronizing the managed source"
if [ -n "$(git -C "$repository_path" status --porcelain --untracked-files=no)" ]; then
	backup_file="/var/lib/digital-signage-ubuntu-backups/managed-source-$(date -u +%Y%m%dT%H%M%SZ).patch"
	mkdir -p "$(dirname "$backup_file")"
	git -C "$repository_path" diff --binary > "$backup_file"
	echo "Local managed-source changes were backed up to $backup_file"
fi
write_status "running" "Installing the newest Digital Signage revision."
git -C "$repository_path" checkout -B "$branch" "origin/$branch"
git -C "$repository_path" reset --hard "origin/$branch"

stage="reapplying the Ubuntu controller installation"
DS_SKIP_SERVICE_RESTART=1 bash "$repository_path/ubuntu-kiosk/install-kiosk.sh" "$site" "$kiosk_user" --upgrade

stage="stopping the previous controller process"
controller_pid="$(python3 - "$settings_file" <<'PY'
import json, pathlib, sys
with open(sys.argv[1], encoding="utf-8") as handle:
    profile_root = pathlib.Path(str(json.load(handle).get("profile_root", "")))
pid_file = profile_root / "controller.pid"
try:
    value = pid_file.read_text(encoding="utf-8").strip()
    print(value if value.isdigit() else "")
except OSError:
    print("")
PY
)"
if [ -n "$controller_pid" ] && [ -r "/proc/$controller_pid/cmdline" ]; then
	controller_command="$(tr '\0' ' ' < "/proc/$controller_pid/cmdline")"
	case "$controller_command" in
		*/digital-signage-ubuntu/ds_ubuntu_controller.py*) kill -TERM "$controller_pid" || true ;;
	esac
fi

write_status "succeeded" "Digital Signage was updated successfully. Device identity, URL, display assignments, schedules, and browser profiles were preserved."
update_completed=1
echo "Digital Signage is updated. Existing identity and settings were preserved."
systemctl reboot
