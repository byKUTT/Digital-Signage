#!/usr/bin/env python3
"""Ubuntu/Xorg multi-display controller for byKUTT Digital Signage."""

from __future__ import annotations

import json
import datetime
import fcntl
import os
import platform
import re
import shutil
import signal
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError
from dataclasses import dataclass
from pathlib import Path
from typing import Any

VERSION = "4.2.0"
DEFAULT_SITE = "https://screens.kutt.ee"
DEFAULT_SETTINGS = Path("/etc/digital-signage-ubuntu/settings.json")
DEFAULT_IDENTITY = Path("/etc/digital-signage-ubuntu/identity.json")
HEARTBEAT_SECONDS = 10
DISPLAY_REFRESH_SECONDS = 5
REQUEST_TIMEOUT = 15
OUTPUT_PATTERN = re.compile(
    r"^(?P<connector>[A-Za-z0-9_.:-]+)\s+connected(?:\s+primary)?\s+"
    r"(?P<width>\d{2,5})x(?P<height>\d{2,5})(?P<x>[+-]\d+)(?P<y>[+-]\d+)"
)


@dataclass(frozen=True)
class Output:
    """One connected XRandR output."""

    output_key: str
    connector: str
    x: int
    y: int
    width: int
    height: int
    primary: bool = False

    def payload(self) -> dict[str, Any]:
        return {
            "output_key": self.output_key,
            "connector": self.connector,
            "label": self.connector,
            "geometry": f"{self.x},{self.y},{self.width},{self.height}",
            "resolution": f"{self.width}x{self.height}",
            "is_primary": self.primary,
        }


@dataclass
class Player:
    """A Chrome-family browser process assigned to one output."""

    process: subprocess.Popen[Any]
    url: str
    geometry: tuple[int, int, int, int]
    profile: Path | None = None


def stable_output_key(connector: str) -> str:
    """Match WordPress sanitize_key semantics for a connector identifier."""
    return re.sub(r"[^a-z0-9_-]", "", connector.lower())[:100]


def parse_xrandr(text: str) -> list[Output]:
    """Parse connected outputs and their desktop coordinates."""
    outputs: list[Output] = []
    for line in text.splitlines():
        match = OUTPUT_PATTERN.match(line.strip())
        if not match:
            continue
        connector = match.group("connector")
        outputs.append(
            Output(
                output_key=stable_output_key(connector),
                connector=connector,
                x=int(match.group("x")),
                y=int(match.group("y")),
                width=int(match.group("width")),
                height=int(match.group("height")),
                primary=bool(re.match(r"^[A-Za-z0-9_.:-]+\s+connected\s+primary\b", line.strip())),
            )
        )
    return sorted(outputs, key=lambda item: (not item.primary, item.x, item.y, item.connector))


def validate_site_url(value: str) -> str:
    """Return a normalized HTTP(S) origin or raise ValueError."""
    parsed = urllib.parse.urlparse(value.strip())
    if parsed.scheme not in ("http", "https") or not parsed.netloc:
        raise ValueError("site must be an http(s) URL")
    if parsed.username or parsed.password or parsed.query or parsed.fragment:
        raise ValueError("site URL must not contain credentials, a query, or a fragment")
    return value.rstrip("/")


def validate_target_url(value: str) -> str:
    """Validate a server-provided player or pairing URL."""
    parsed = urllib.parse.urlparse(value.strip())
    if parsed.scheme not in ("http", "https") or not parsed.netloc:
        raise ValueError("target must be an http(s) URL")
    if parsed.username or parsed.password:
        raise ValueError("target URL must not contain credentials")
    return value.strip()


def load_json(path: Path) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as handle:
        data = json.load(handle)
    if not isinstance(data, dict):
        raise ValueError(f"{path} must contain a JSON object")
    return data


def save_private_json(path: Path, data: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    with temporary.open("w", encoding="utf-8") as handle:
        json.dump(data, handle, indent=2, sort_keys=True)
        handle.write("\n")
    os.chmod(temporary, 0o600)
    os.replace(temporary, path)


def reconcile_targets(
    outputs: list[Output], assignments: list[dict[str, Any]]
) -> dict[str, str]:
    """Map connected output keys to their assigned or pairing URLs."""
    connected = {output.output_key for output in outputs}
    targets: dict[str, str] = {}
    for assignment in assignments:
        key = str(assignment.get("output_key", ""))
        if key not in connected:
            continue
        raw_url = assignment.get("player_url") or assignment.get("pairing_url") or ""
        try:
            targets[key] = validate_target_url(str(raw_url))
        except ValueError:
            continue
    return targets


def find_browser(configured: str = "") -> str:
    """Find a Chrome-family executable, preferring Google Chrome Stable."""
    candidates = [
        configured,
        "google-chrome-stable",
        "google-chrome",
        "chromium",
        "chromium-browser",
    ]
    for candidate in candidates:
        if not candidate:
            continue
        if os.path.isabs(candidate) and os.access(candidate, os.X_OK):
            return candidate
        resolved = shutil.which(candidate)
        if resolved:
            return resolved
    return ""


def chrome_command(browser: str, profile: Path, output: Output, url: str) -> list[str]:
    """Build an isolated, exactly positioned kiosk command for one output."""
    return [
        browser,
        f"--user-data-dir={profile}",
        f"--window-position={output.x},{output.y}",
        f"--window-size={output.width},{output.height}",
        f"--class=bykutt-signage-{output.output_key}",
        "--kiosk",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-session-crashed-bubble",
        "--disable-background-mode",
        "--disable-save-password-bubble",
        "--disable-sync",
        "--disable-translate",
        "--disable-features=Translate,TranslateUI",
        "--disable-component-update",
        "--autoplay-policy=no-user-gesture-required",
        "--disable-pinch",
        "--overscroll-history-navigation=0",
        "--password-store=basic",
        url,
    ]


class Controller:
    """Controller lifecycle, server transport, and one browser per output."""

    def __init__(self, settings_path: Path, identity_path: Path) -> None:
        self.settings_path = settings_path
        self.identity_path = identity_path
        self.settings = load_json(settings_path)
        self.site = validate_site_url(str(self.settings.get("site") or DEFAULT_SITE))
        self.user_home = Path(str(self.settings["user_home"]))
        self.profile_root = Path(
            str(self.settings.get("profile_root") or self.user_home / ".local/share/digital-signage-ubuntu")
        )
        self.browser = find_browser(str(self.settings.get("browser") or ""))
        if not self.browser:
            raise RuntimeError("Google Chrome or Chromium executable was not found")
        self.browser_name = "Google Chrome" if "google-chrome" in self.browser else "Chromium"
        self.identity = load_json(identity_path) if identity_path.exists() else {}
        self.state_path = settings_path.with_name("state.json")
        self.state = load_json(self.state_path) if self.state_path.exists() else {}
        self.screens_paused = bool(self.state.get("screens_paused", False))
        self.players: dict[str, Player] = {}
        self.cursor_process: subprocess.Popen[Any] | None = None
        self.cursor_hidden = False
        self.cursor_error = ""
        self.recent_log: list[str] = []
        self.last_error = ""
        self.force_refresh = True
        self.stop_requested = False
        self.connected_output_count = 0
        self.display_sleeping: bool | None = None
        self.server_time_offset = 0.0
        self.server_timezone = str(self.state.get("server_timezone") or "UTC")

    def log(self, message: str) -> None:
        try:
            zone = ZoneInfo(self.server_timezone)
            stamp = datetime.datetime.fromtimestamp(time.time() + self.server_time_offset, zone).strftime(
                "%Y-%m-%d %H:%M:%S "
            )
        except (KeyError, ValueError, TypeError):
            stamp = time.strftime("%Y-%m-%d %H:%M:%S ")
        line = stamp + message
        print(line, flush=True)
        self.recent_log = (self.recent_log + [line])[-30:]

    def request(
        self,
        method: str,
        path: str,
        payload: dict[str, Any] | None = None,
        authenticated: bool = True,
    ) -> dict[str, Any]:
        headers = {"Accept": "application/json", "User-Agent": f"byKUTT-Ubuntu/{VERSION}"}
        if authenticated:
            headers["X-DS-Controller-Token"] = str(self.identity.get("token", ""))
        body = None
        if payload is not None:
            headers["Content-Type"] = "application/json"
            body = json.dumps(payload).encode("utf-8")
        request = urllib.request.Request(
            self.site + path, data=body, headers=headers, method=method
        )
        with urllib.request.urlopen(request, timeout=REQUEST_TIMEOUT) as response:
            data = json.loads(response.read().decode("utf-8"))
        if not isinstance(data, dict):
            raise RuntimeError("server returned a non-object response")
        return data

    def outputs(self) -> list[Output]:
        completed = subprocess.run(
            ["xrandr", "--query"], check=True, capture_output=True, text=True, timeout=10
        )
        return parse_xrandr(completed.stdout)

    def ensure_identity(self, outputs: list[Output]) -> None:
        if self.identity.get("public_id") and self.identity.get("token"):
            return
        response = self.request(
            "POST",
            "/wp-json/ds/v1/controller/request",
            {
                "platform": "linux",
                "hostname": socket.gethostname(),
                "outputs": [output.payload() for output in outputs],
            },
            authenticated=False,
        )
        public_id = str(response.get("public_id", ""))
        token = str(response.get("token", ""))
        if not re.fullmatch(r"[a-f0-9-]{36}", public_id) or len(token) < 40:
            raise RuntimeError("server did not return a valid controller identity")
        self.identity = {"public_id": public_id, "token": token}
        save_private_json(self.identity_path, self.identity)
        self.log(f"Controller created; pairing code {response.get('code', 'unknown')}")

    def os_version(self) -> str:
        try:
            values: dict[str, str] = {}
            with Path("/etc/os-release").open("r", encoding="utf-8") as handle:
                for line in handle:
                    key, separator, value = line.partition("=")
                    if separator:
                        values[key] = value.strip().strip('"')
            return values.get("PRETTY_NAME", platform.platform())
        except OSError:
            return platform.platform()

    def telemetry(self) -> dict[str, Any]:
        disk = shutil.disk_usage("/")
        memory_total = memory_free = 0
        try:
            for line in Path("/proc/meminfo").read_text(encoding="utf-8").splitlines():
                if line.startswith("MemTotal:"):
                    memory_total = int(line.split()[1]) // 1024
                elif line.startswith("MemAvailable:"):
                    memory_free = int(line.split()[1]) // 1024
        except (OSError, ValueError, IndexError):
            pass
        update_status: dict[str, Any] = {}
        try:
            update_status = load_json(self.settings_path.with_name("update-status.json"))
        except (OSError, ValueError):
            pass
        return {
            "architecture": platform.machine(),
            "cpu_cores": os.cpu_count() or 1,
            "kernel": platform.release(),
            "browser": self.browser_name,
            "memory_total_mb": memory_total,
            "memory_free_mb": memory_free,
            "disk_total_mb": disk.total // 1024 // 1024,
            "disk_free_mb": disk.free // 1024 // 1024,
            "uptime_seconds": self.uptime_seconds(),
            "browser_running": bool(self.players),
            "screens_paused": self.screens_paused,
            "cursor_hidden": self.cursor_hidden,
            "cursor_error": self.cursor_error,
            "update_status": str(update_status.get("status", ""))[:100],
            "update_result": str(update_status.get("message", ""))[:300],
            "connected_outputs": self.connected_output_count,
            "player_processes": len(self.players),
            "os_update_supported": True,
            "suspend_supported": False,
            "rtc_wake_supported": False,
            "automatic_reboot_enabled": False,
            "display_sleeping": bool(self.display_sleeping),
            "last_error": self.last_error,
            "recent_log": self.recent_log,
        }

    def save_state(self) -> None:
        self.state["screens_paused"] = self.screens_paused
        save_private_json(self.state_path, self.state)

    def set_cursor_hidden(self, hidden: bool) -> None:
        self.cursor_error = ""
        if not hidden and self.cursor_process is not None:
            if self.cursor_process.poll() is None:
                self.cursor_process.terminate()
            self.cursor_process = None
        try:
            if hidden:
                cursor_dir = self.profile_root / "cursor"
                cursor_dir.mkdir(parents=True, exist_ok=True)
                bitmap = cursor_dir / "blank.xbm"
                bitmap.write_text(
                    "#define blank_width 1\n#define blank_height 1\n"
                    "static unsigned char blank_bits[] = { 0x00 };\n",
                    encoding="ascii",
                )
                subprocess.run(
                    ["xsetroot", "-cursor", str(bitmap), str(bitmap)],
                    check=True,
                    timeout=10,
                )
                if self.cursor_process is None or self.cursor_process.poll() is not None:
                    self.cursor_process = subprocess.Popen(
                        ["unclutter", "-idle", "0", "-jitter", "1", "-root"],
                        stdout=subprocess.DEVNULL,
                        stderr=subprocess.STDOUT,
                    )
            else:
                subprocess.run(
                    ["xsetroot", "-cursor_name", "left_ptr"],
                    check=False,
                    timeout=10,
                )
            self.cursor_hidden = hidden
        except (OSError, subprocess.SubprocessError) as error:
            self.cursor_hidden = False
            self.cursor_error = str(error)[:300]
            self.log(f"Cursor control warning: {self.cursor_error}")

    @staticmethod
    def uptime_seconds() -> int:
        try:
            return int(float(Path("/proc/uptime").read_text(encoding="utf-8").split()[0]))
        except (OSError, ValueError, IndexError):
            return 0

    def heartbeat(self, outputs: list[Output]) -> dict[str, Any]:
        return self.request(
            "POST",
            f"/wp-json/ds/v1/controller/{self.identity['public_id']}/heartbeat",
            {
                "hostname": socket.gethostname(),
                "os_version": self.os_version(),
                "agent_version": VERSION,
                "software_version": VERSION,
                "outputs": [output.payload() for output in outputs],
                "telemetry": self.telemetry(),
            },
        )

    def ack(self, command_id: int, status: str, result: str = "") -> None:
        self.request(
            "POST",
            f"/wp-json/ds/v1/controller/{self.identity['public_id']}/command/{command_id}/ack",
            {"status": status, "result": result[:4000]},
        )

    def run_root_command(self, command: str) -> tuple[bool, str]:
        completed = subprocess.run(
            ["sudo", "-n", "/usr/local/sbin/digital-signage-root-command", command],
            capture_output=True,
            text=True,
            timeout=1800,
        )
        result = (completed.stdout + completed.stderr).strip()
        return completed.returncode == 0, result

    def show_update_screen(self, outputs: list[Output]) -> None:
        """Replace signage with a local update status on every output."""
        page = self.profile_root / "updating.html"
        page.write_text(
            """<!doctype html><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>Updating</title><style>*{box-sizing:border-box}body{margin:0;background:#101512;color:#fff;font:600 clamp(28px,5vw,72px)/1.1 system-ui;display:grid;place-items:center;min-height:100vh;text-align:center}small{display:block;color:#aeb9b1;font:400 clamp(14px,1.7vw,24px)/1.4 system-ui;margin-top:18px}</style><main>Updating…<small>Please wait. This screen will restart automatically.</small></main>""",
            encoding="utf-8",
        )
        self.stop_players()
        for output in outputs:
            self.players[output.output_key] = self.launch_player(output, page.as_uri())
        self.log("Showing software update status on all displays")

    def wait_for_software_update(self, started_at: int) -> tuple[bool, str]:
        status_path = self.settings_path.with_name("update-status.json")
        deadline = time.monotonic() + 1800
        while time.monotonic() < deadline:
            try:
                status = load_json(status_path)
                if int(status.get("updated_at", 0)) >= started_at - 2:
                    state = str(status.get("status", ""))
                    message = str(status.get("message", ""))[:500]
                    if state == "succeeded":
                        return True, message
                    if state == "failed":
                        return False, message
            except (OSError, TypeError, ValueError):
                pass
            time.sleep(2)
        return False, "Software update timed out after 30 minutes"

    def apply_command(self, command: dict[str, Any]) -> None:
        command_id = int(command.get("id", 0))
        command_type = str(command.get("type", ""))
        allowed = {
            "restart_players",
            "stop_players",
            "start_players",
            "refresh_displays",
            "reboot",
            "software_update",
            "system_update",
            "switch_url",
        }
        if not command_id or command_type not in allowed:
            return
        self.ack(command_id, "running")
        if command_type == "restart_players":
            self.stop_players()
            self.force_refresh = True
            self.ack(command_id, "succeeded", "Chrome players restarted")
            return
        if command_type == "stop_players":
            self.screens_paused = True
            self.save_state()
            self.stop_players()
            self.set_cursor_hidden(False)
            self.ack(command_id, "succeeded", "Kiosk screens closed; desktop mode active")
            return
        if command_type == "start_players":
            self.screens_paused = False
            self.save_state()
            self.stop_players()
            self.set_cursor_hidden(True)
            self.force_refresh = True
            self.ack(command_id, "succeeded", "Automatic kiosk screens started")
            return
        if command_type == "refresh_displays":
            self.force_refresh = True
            self.ack(command_id, "succeeded", "Display discovery requested")
            return
        if command_type == "switch_url":
            payload = command.get("payload") if isinstance(command.get("payload"), dict) else {}
            new_site = validate_site_url(str(payload.get("site", "")))
            old_settings = dict(self.settings)
            self.settings["site"] = new_site
            save_private_json(self.settings_path, self.settings)
            save_private_json(self.identity_path, {})
            self.ack(command_id, "succeeded", f"Controller moved to {new_site}; rebooting")
            ok, result = self.run_root_command("reboot")
            if ok:
                self.stop_requested = True
            else:
                self.settings = old_settings
                save_private_json(self.settings_path, self.settings)
                save_private_json(self.identity_path, self.identity)
                raise RuntimeError(f"URL switch reboot failed; old settings restored: {result}")
            return
        if command_type == "software_update":
            self.show_update_screen(self.outputs())
            started_at = int(time.time())
            ok, result = self.run_root_command("software-update")
            if ok:
                ok, result = self.wait_for_software_update(started_at)
            self.ack(command_id, "succeeded" if ok else "failed", result)
            if ok:
                self.log("Software update completed; rebooting")
                reboot_ok, reboot_result = self.run_root_command("reboot")
                if reboot_ok:
                    self.stop_requested = True
                else:
                    raise RuntimeError(f"Update succeeded but reboot failed: {reboot_result}")
            else:
                self.stop_players()
                self.force_refresh = True
            return
        root_name = {
            "reboot": "reboot",
            "system_update": "system-update",
        }[command_type]
        ok, result = self.run_root_command(root_name)
        self.ack(command_id, "succeeded" if ok else "failed", result)

    @staticmethod
    def run_display_power_command(arguments: list[str]) -> None:
        completed = subprocess.run(arguments, check=False, capture_output=True, text=True, timeout=10)
        if completed.returncode != 0:
            detail = (completed.stderr or completed.stdout or "unknown X11 error").strip()
            raise RuntimeError(f"Display power command failed: {' '.join(arguments)}: {detail}")

    def apply_display_schedule(self, schedule: dict[str, Any] | None, server_epoch: float | None = None) -> None:
        if not isinstance(schedule, dict) or not schedule.get("enabled"):
            should_sleep = False
        else:
            try:
                zone = ZoneInfo(str(schedule.get("timezone") or "UTC"))
                now = datetime.datetime.fromtimestamp(server_epoch, zone) if server_epoch is not None else datetime.datetime.now(zone)
                days = {int(day) for day in schedule.get("days", [])}
                wake_hour, wake_minute = map(int, str(schedule.get("wake_time", "07:00")).split(":"))
                sleep_hour, sleep_minute = map(int, str(schedule.get("sleep_time", "22:00")).split(":"))
                current = now.hour * 60 + now.minute
                wake = wake_hour * 60 + wake_minute
                sleep = sleep_hour * 60 + sleep_minute
                active_day = (now.weekday() + 1) % 7 in days
                outside_window = (current < wake or current >= sleep) if wake <= sleep else (sleep <= current < wake)
                should_sleep = not active_day or outside_window
            except (KeyError, TypeError, ValueError, ZoneInfoNotFoundError) as error:
                self.last_error = f"Invalid display sleep schedule: {error}"[:300]
                return
        if self.display_sleeping is should_sleep:
            return
        if should_sleep:
            self.run_display_power_command(["xset", "+dpms"])
            self.run_display_power_command(["xset", "dpms", "force", "off"])
            self.log("Display sleep schedule turned screens off")
        else:
            self.run_display_power_command(["xset", "dpms", "force", "on"])
            self.run_display_power_command(["xset", "-dpms"])
            self.log("Display sleep schedule turned screens on")
        self.display_sleeping = should_sleep

    def launch_player(self, output: Output, url: str) -> Player:
        profile = self.profile_root / output.output_key
        profile.mkdir(parents=True, exist_ok=True)
        process = subprocess.Popen(
            chrome_command(self.browser, profile, output, url),
            stdout=subprocess.DEVNULL,
            stderr=subprocess.STDOUT,
            start_new_session=False,
        )
        self.log(f"Started {output.connector} at {output.width}x{output.height}+{output.x}+{output.y}")
        return Player(
            process=process,
            url=url,
            geometry=(output.x, output.y, output.width, output.height),
            profile=profile,
        )

    def sync_players(self, outputs: list[Output], targets: dict[str, str]) -> None:
        current = {output.output_key: output for output in outputs}
        for key in list(self.players):
            player = self.players[key]
            output = current.get(key)
            geometry = (output.x, output.y, output.width, output.height) if output else None
            if (
                not output
                or key not in targets
                or player.url != targets[key]
                or player.geometry != geometry
            ):
                self.stop_player(player)
                self.players.pop(key, None)
        if self.screens_paused:
            return
        for output in outputs:
            if output.output_key in targets and output.output_key not in self.players:
                self.players[output.output_key] = self.launch_player(
                    output, targets[output.output_key]
                )

    @staticmethod
    def profile_process_ids(profile: Path, proc_root: Path = Path("/proc")) -> set[int]:
        wanted = f"--user-data-dir={profile}".encode()
        found: set[int] = set()
        for entry in proc_root.glob("[0-9]*"):
            try:
                arguments = (entry / "cmdline").read_bytes().split(b"\0")
                if wanted in arguments:
                    found.add(int(entry.name))
            except (OSError, ValueError):
                continue
        return found

    def stop_player(self, player: Player) -> None:
        pids = self.profile_process_ids(player.profile) if player.profile else set()
        for pid in pids:
            try:
                os.kill(pid, signal.SIGTERM)
            except ProcessLookupError:
                pass
        if player.process.poll() is None:
            player.process.terminate()

    def stop_players(self) -> None:
        for player in self.players.values():
            self.stop_player(player)
        deadline = time.monotonic() + 5
        while time.monotonic() < deadline and any(
            player.process.poll() is None for player in self.players.values()
        ):
            time.sleep(0.2)
        for player in self.players.values():
            if player.process.poll() is None:
                player.process.kill()
        self.players.clear()

    def run(self) -> int:
        self.log(f"Ubuntu controller {VERSION} starting")
        signal.signal(signal.SIGTERM, lambda _signum, _frame: setattr(self, "stop_requested", True))
        signal.signal(signal.SIGINT, lambda _signum, _frame: setattr(self, "stop_requested", True))
        last_heartbeat = 0.0
        assignments: list[dict[str, Any]] = []
        schedule: dict[str, Any] | None = None
        consecutive_errors = 0
        self.set_cursor_hidden(not self.screens_paused)
        try:
            while not self.stop_requested:
                try:
                    outputs = self.outputs()
                    if not outputs:
                        raise RuntimeError("XRandR reports no connected displays")
                    self.connected_output_count = len(outputs)
                    self.ensure_identity(outputs)
                    now = time.monotonic()
                    if self.force_refresh or now - last_heartbeat >= HEARTBEAT_SECONDS:
                        state = self.heartbeat(outputs)
                        if isinstance(state.get("server_time"), (int, float)):
                            self.server_time_offset = float(state["server_time"]) - time.time()
                        received_timezone = str(state.get("server_timezone") or "UTC")
                        try:
                            ZoneInfo(received_timezone)
                            self.server_timezone = received_timezone
                            if self.state.get("server_timezone") != received_timezone:
                                self.state["server_timezone"] = received_timezone
                                save_private_json(self.state_path, self.state)
                        except (KeyError, ValueError):
                            self.server_timezone = "UTC"
                        assignments = list(state.get("assignments") or [])
                        schedule = state.get("schedule") if isinstance(state.get("schedule"), dict) else None
                        command = state.get("command")
                        if isinstance(command, dict):
                            self.apply_command(command)
                        last_heartbeat = now
                        self.force_refresh = False
                    self.sync_players(outputs, reconcile_targets(outputs, assignments))
                    self.apply_display_schedule(schedule, time.time() + self.server_time_offset)
                    consecutive_errors = 0
                    self.last_error = ""
                except (OSError, ValueError, RuntimeError, subprocess.SubprocessError, urllib.error.URLError) as error:
                    consecutive_errors += 1
                    self.last_error = str(error)[:300]
                    self.log(f"Recovery attempt {consecutive_errors}: {self.last_error}")
                    if consecutive_errors >= 12:
                        self.log("Continuing recovery without rebooting the computer")
                        consecutive_errors = 0
                time.sleep(DISPLAY_REFRESH_SECONDS)
        finally:
            self.stop_players()
            self.set_cursor_hidden(False)
        return 0


def main() -> int:
    settings = Path(os.environ.get("DS_UBUNTU_SETTINGS", str(DEFAULT_SETTINGS)))
    identity = Path(os.environ.get("DS_UBUNTU_IDENTITY", str(DEFAULT_IDENTITY)))
    lock_handle = None
    pid_path = None
    try:
        loaded_settings = load_json(settings)
        raw_profile_root = str(loaded_settings.get("profile_root") or "")
        if not raw_profile_root:
            raise RuntimeError("profile_root is missing")
        profile_root = Path(raw_profile_root)
        profile_root.mkdir(parents=True, exist_ok=True)
        lock_handle = (profile_root / "controller.lock").open("a+", encoding="utf-8")
        try:
            fcntl.flock(lock_handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError as error:
            raise RuntimeError("another Digital Signage controller is already running") from error
        pid_path = profile_root / "controller.pid"
        pid_path.write_text(f"{os.getpid()}\n", encoding="utf-8")
        return Controller(settings, identity).run()
    except (OSError, KeyError, ValueError, RuntimeError) as error:
        print(f"Digital Signage controller could not start: {error}", file=sys.stderr)
        return 1
    finally:
        if pid_path is not None:
            try:
                pid_path.unlink()
            except FileNotFoundError:
                pass
        if lock_handle is not None:
            lock_handle.close()


if __name__ == "__main__":
    raise SystemExit(main())
