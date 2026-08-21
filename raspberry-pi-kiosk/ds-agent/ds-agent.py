#!/usr/bin/env python3
"""
ds-agent — Digital Signage on-device management agent (Raspberry Pi).

Runs as a systemd service (see ds-agent.service). On an interval, it:
  1. Collects device telemetry (WiFi network/signal, CPU temperature, free
     disk space, current screen rotation/resolution, agent/OS version, and
     its own recent activity log).
  2. POSTs it to the plugin's existing heartbeat REST endpoint, piggybacking
     on the same call the browser-based player already makes — no separate
     "device" endpoint needed.
  3. Applies any pending command the response carries: change WiFi network,
     rotate or resize the screen, reboot, restart just the kiosk browser, or
     check for OS/package updates.

Standard library only — no pip install needed, so it runs unmodified on any
Raspberry Pi OS install without extra setup.
"""

import json
import os
import platform
import re
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

AGENT_VERSION = "1.0.0"
CONFIG_FILE = "/etc/digital-signage-kiosk.conf"
# How often the agent phones home. Kept short so a command queued in wp-admin
# (rotation, resolution, WiFi, reboot, ...) gets picked up quickly rather than
# sitting for up to half a minute — the request itself is a small JSON POST,
# cheap enough to send this often even on a Pi Zero.
HEARTBEAT_INTERVAL_SECONDS = 10
REQUEST_TIMEOUT_SECONDS = 15
# Rolling buffer of this agent's own recent activity (commands applied,
# errors, ...), reported with telemetry so it shows up on the Screen edit
# page in wp-admin without needing to SSH in and read the journal.
RECENT_LOG = []
RECENT_LOG_MAX = 40


def log(message):
	print("[ds-agent] %s" % message, flush=True)
	RECENT_LOG.append( "%s  %s" % ( time.strftime( "%Y-%m-%d %H:%M:%S" ), message ) )
	del RECENT_LOG[ : -RECENT_LOG_MAX ]


def read_config():
	"""Parses the simple KEY="VALUE" bash-style config install-kiosk.sh writes."""
	config = {}
	if not os.path.exists(CONFIG_FILE):
		return config
	with open(CONFIG_FILE, "r", encoding="utf-8") as f:
		for line in f:
			line = line.strip()
			if not line or line.startswith("#") or "=" not in line:
				continue
			key, _, value = line.partition("=")
			config[key.strip()] = value.strip().strip('"').strip("'")
	return config


def write_config_value(key, value):
	"""Updates a single KEY=VALUE line in the config file, preserving the rest."""
	lines = []
	found = False
	if os.path.exists(CONFIG_FILE):
		with open(CONFIG_FILE, "r", encoding="utf-8") as f:
			for line in f:
				if line.strip().startswith(key + "="):
					lines.append('%s="%s"\n' % (key, value))
					found = True
				else:
					lines.append(line)
	if not found:
		lines.append('%s="%s"\n' % (key, value))
	with open(CONFIG_FILE, "w", encoding="utf-8") as f:
		f.writelines(lines)


def run(cmd, timeout=20):
	"""Runs a command, returning (returncode, stdout). Never raises."""
	try:
		result = subprocess.run(
			cmd, capture_output=True, text=True, timeout=timeout, check=False
		)
		return result.returncode, result.stdout.strip()
	except Exception as e:  # noqa: BLE001 - agent must never crash the loop
		return 1, str(e)


def collect_wifi():
	"""Active SSID + signal strength via NetworkManager (default on Raspberry Pi OS
	Bookworm+); returns (ssid, signal) or (None, None) if nmcli/WiFi isn't available."""
	code, out = run(["nmcli", "-t", "-f", "active,ssid,signal", "dev", "wifi"])
	if code != 0:
		return None, None
	for line in out.splitlines():
		parts = line.split(":")
		if len(parts) >= 3 and parts[0] == "yes":
			return parts[1], parts[2]
	return None, None


def collect_cpu_temp():
	try:
		with open("/sys/class/thermal/thermal_zone0/temp", "r", encoding="utf-8") as f:
			return round(int(f.read().strip()) / 1000.0, 1)
	except Exception:  # noqa: BLE001
		return None


def collect_mem_free_mb():
	try:
		with open("/proc/meminfo", "r", encoding="utf-8") as f:
			for line in f:
				if line.startswith("MemAvailable:"):
					return int(line.split()[1]) // 1024
	except Exception:  # noqa: BLE001
		pass
	return None


def collect_os_version():
	try:
		with open("/etc/os-release", "r", encoding="utf-8") as f:
			for line in f:
				if line.startswith("PRETTY_NAME="):
					return line.split("=", 1)[1].strip().strip('"')
	except Exception:  # noqa: BLE001
		pass
	return platform.platform()


def collect_telemetry(config):
	ssid, signal = collect_wifi()
	telemetry = {
		"hostname": socket.gethostname(),
		"os_version": collect_os_version(),
		"agent_version": AGENT_VERSION,
		"rotation": config.get("DS_KIOSK_ROTATION", "normal"),
		"recent_log": list(RECENT_LOG),
	}
	if config.get("DS_KIOSK_RESOLUTION"):
		telemetry["resolution"] = config["DS_KIOSK_RESOLUTION"]
	if ssid:
		telemetry["wifi_ssid"] = ssid
	if signal:
		telemetry["wifi_signal"] = signal
	temp = collect_cpu_temp()
	if temp is not None:
		telemetry["cpu_temp_c"] = temp
	try:
		telemetry["disk_free_mb"] = shutil.disk_usage("/").free // (1024 * 1024)
	except Exception:  # noqa: BLE001
		pass
	mem = collect_mem_free_mb()
	if mem is not None:
		telemetry["mem_free_mb"] = mem
	return telemetry


def send_heartbeat(site, token, telemetry):
	url = "%s/wp-json/ds/v1/screen/%s/heartbeat" % (site.rstrip("/"), token)
	# Only "device" is ours to report here — resolution/orientation/channel/
	# app_version belong to the browser-based player's own heartbeat call to
	# this same endpoint; the server now ignores those fields from us
	# specifically (see class-ds-rest.php post_heartbeat), but not sending
	# placeholder values for them at all keeps that contract obvious here too.
	body = json.dumps({"device": telemetry}).encode("utf-8")

	request = urllib.request.Request(
		url, data=body, headers={"Content-Type": "application/json"}, method="POST"
	)
	with urllib.request.urlopen(request, timeout=REQUEST_TIMEOUT_SECONDS) as response:
		return json.loads(response.read().decode("utf-8"))


def apply_wifi(command):
	ssid = command.get("ssid", "")
	password = command.get("password", "")
	if not ssid:
		return
	log("Applying WiFi command: connecting to '%s'" % ssid)
	if password:
		code, out = run(["nmcli", "device", "wifi", "connect", ssid, "password", password], timeout=45)
	else:
		code, out = run(["nmcli", "device", "wifi", "connect", ssid], timeout=45)
	# The password is only ever held in this local variable/argv for the single
	# nmcli call above — never written to disk or logged.
	if code != 0:
		log("WiFi connect failed: %s" % out)
	else:
		log("WiFi connected.")


def apply_rotation(command):
	rotation = command.get("rotation", "normal")
	if rotation not in ("normal", "left", "right", "inverted"):
		rotation = "normal"
	log("Applying rotation: %s" % rotation)
	write_config_value("DS_KIOSK_ROTATION", rotation)
	# Restart the kiosk service rather than trying to xrandr the running X
	# session live from here: this agent runs as a separate systemd service
	# from ds-kiosk.service, and a bare "DISPLAY=:0 xrandr ..." call from
	# outside that session's environment can silently fail to authenticate
	# against the X server (no XAUTHORITY set for this process) with no
	# visible error — which is why rotation changes previously seemed to do
	# nothing at all. Restarting ds-kiosk.service instead runs the exact same
	# rotation-apply code in ds-kiosk-loop.sh that already runs correctly on
	# every normal boot, so it's guaranteed to pick up DS_KIOSK_ROTATION.
	code, out = run(["systemctl", "restart", "ds-kiosk.service"], timeout=15)
	if code != 0:
		log("Failed to restart ds-kiosk.service to apply rotation: %s" % out)


def apply_resolution(command):
	resolution = str(command.get("resolution", "")).strip()
	if resolution and not re.match(r"^[0-9]{2,5}x[0-9]{2,5}$", resolution):
		log("Ignoring invalid resolution: %r" % resolution)
		return
	log(
		"Applying custom resolution: %s" % resolution
		if resolution
		else "Clearing custom resolution (back to auto-detect)."
	)
	write_config_value("DS_KIOSK_RESOLUTION", resolution)
	# Same reasoning as apply_rotation: restart the whole kiosk service so the
	# resolution is applied by ds-kiosk-loop.sh's own cvt/xrandr logic, which
	# runs inside the actual kiosk X session rather than this separate agent
	# process (which has no authenticated access to that display).
	code, out = run(["systemctl", "restart", "ds-kiosk.service"], timeout=15)
	if code != 0:
		log("Failed to restart ds-kiosk.service to apply resolution: %s" % out)


def apply_reboot():
	log("Rebooting device on request from wp-admin…")
	time.sleep(1)  # Give the heartbeat response time to be logged before we go down.
	run(["reboot"], timeout=5)


def apply_restart_browser():
	log("Restarting kiosk browser…")
	# Killing the browser is enough — ds-kiosk-loop.sh's while-loop relaunches it.
	# Which process name to kill depends on DS_KIOSK_BROWSER (install-kiosk.sh
	# --browser chromium|firefox); older configs from before that option
	# existed have no such key, so fall back to chromium.
	config = read_config()
	pattern = "firefox" if config.get("DS_KIOSK_BROWSER") == "firefox" else "chromium"
	run(["pkill", "-f", pattern], timeout=5)


def apply_check_updates():
	log("Checking for OS/package updates…")
	run(["apt-get", "update"], timeout=120)
	code, out = run(["apt-get", "-y", "upgrade"], timeout=1800)
	log("Update finished (exit %d)." % code)


def apply_command(command):
	command_type = command.get("type")
	try:
		if command_type == "wifi":
			apply_wifi(command)
		elif command_type == "rotation":
			apply_rotation(command)
		elif command_type == "resolution":
			apply_resolution(command)
		elif command_type == "reboot":
			apply_reboot()
		elif command_type == "restart_browser":
			apply_restart_browser()
		elif command_type == "check_updates":
			apply_check_updates()
		else:
			log("Unknown command type: %r" % command_type)
	except Exception as e:  # noqa: BLE001 - one bad command must not kill the agent
		log("Error applying command %r: %s" % (command_type, e))


def main():
	log("Starting (version %s)" % AGENT_VERSION)
	while True:
		config = read_config()
		site = config.get("DS_KIOSK_SITE")
		token = config.get("DS_KIOSK_TOKEN")

		if not site or not token:
			log("No DS_KIOSK_SITE/DS_KIOSK_TOKEN in %s yet — waiting." % CONFIG_FILE)
			time.sleep(HEARTBEAT_INTERVAL_SECONDS)
			continue

		try:
			telemetry = collect_telemetry(config)
			response = send_heartbeat(site, token, telemetry)
			command = response.get("command") if isinstance(response, dict) else None
			if command:
				apply_command(command)
		except urllib.error.URLError as e:
			log("Heartbeat failed (site unreachable?): %s" % e)
		except Exception as e:  # noqa: BLE001 - the loop must never die
			log("Unexpected error: %s" % e)

		time.sleep(HEARTBEAT_INTERVAL_SECONDS)


if __name__ == "__main__":
	try:
		main()
	except KeyboardInterrupt:
		sys.exit(0)
