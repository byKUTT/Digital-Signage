#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Run this command with sudo: sudo digital-signage-update" >&2
	exit 1
fi

restart_service=1
if [ "${1:-}" = "--no-restart" ]; then
	restart_service=0
elif [ -n "${1:-}" ]; then
	echo "Usage: sudo digital-signage-update [--no-restart]" >&2
	exit 2
fi

settings_file="/etc/digital-signage-ubuntu/settings.json"
status_file="/etc/digital-signage-ubuntu/update-status.json"

write_status() {
	python3 - "$status_file" "$1" "$2" <<'PY'
import json, os, sys, time
path, status, message = sys.argv[1:]
temporary = path + ".tmp"
with open(temporary, "w", encoding="utf-8") as handle:
    json.dump({"status": status, "message": message[:500], "updated_at": int(time.time())}, handle, sort_keys=True)
    handle.write("\n")
os.chmod(temporary, 0o644)
os.replace(temporary, path)
PY
}

if [ ! -r "$settings_file" ]; then
	echo "Ubuntu controller settings are missing. Run install-kiosk.sh first." >&2
	exit 1
fi
update_completed=0
on_exit() {
	status=$?
	if [ "$status" -ne 0 ] && [ "$update_completed" -eq 0 ]; then
		write_status "failed" "Update command failed with exit status ${status}. Check systemctl status digital-signage-ubuntu-update.service."
	fi
}
trap on_exit EXIT
write_status "running" "Git update is running."

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

if [ "$repository_path" != "/opt/bykutt-digital-signage" ] || [ ! -d "$repository_path/.git" ]; then
	echo "Refusing update: the managed Git repository is missing or unexpected." >&2
	exit 1
fi
if [ "$(git -C "$repository_path" remote get-url origin)" != "$expected_remote" ]; then
	echo "Refusing update: the Git origin changed." >&2
	exit 1
fi
if [ -n "$(git -C "$repository_path" status --porcelain)" ]; then
	echo "Refusing update: the managed Git repository has local changes." >&2
	exit 1
fi

git -C "$repository_path" fetch --prune origin "$branch"
if ! git -C "$repository_path" merge-base --is-ancestor HEAD "origin/$branch"; then
	echo "Refusing update: the local checkout diverged from origin/$branch." >&2
	exit 1
fi
git -C "$repository_path" checkout "$branch"
git -C "$repository_path" merge --ff-only "origin/$branch"

DS_SKIP_SERVICE_RESTART=1 bash "$repository_path/ubuntu-kiosk/install-kiosk.sh" "$site" "$kiosk_user" --upgrade

if [ "$restart_service" -eq 1 ]; then
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
fi

write_status "succeeded" "Digital Signage update completed and settings were preserved."
update_completed=1
echo "Digital Signage is updated. Existing identity and settings were preserved."
