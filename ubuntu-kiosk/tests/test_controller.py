#!/usr/bin/env python3
"""Pure unit tests for the Ubuntu multi-display controller."""

import importlib.util
import pathlib
import sys
import tempfile
import unittest
from unittest import mock

MODULE_PATH = pathlib.Path(__file__).parents[1] / "ds_ubuntu_controller.py"
SPEC = importlib.util.spec_from_file_location("ds_ubuntu_controller", MODULE_PATH)
assert SPEC and SPEC.loader
CONTROLLER = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = CONTROLLER
SPEC.loader.exec_module(CONTROLLER)

GDM_PATH = pathlib.Path(__file__).parents[1] / "configure_gdm.py"
GDM_SPEC = importlib.util.spec_from_file_location("configure_gdm", GDM_PATH)
assert GDM_SPEC and GDM_SPEC.loader
GDM = importlib.util.module_from_spec(GDM_SPEC)
sys.modules[GDM_SPEC.name] = GDM
GDM_SPEC.loader.exec_module(GDM)


class OutputParsingTests(unittest.TestCase):
    def test_single_primary_output(self):
        outputs = CONTROLLER.parse_xrandr(
            "HDMI-1 connected primary 1920x1080+0+0 (normal left inverted right x axis y axis)\n"
        )
        self.assertEqual(1, len(outputs))
        self.assertEqual("hdmi-1", outputs[0].output_key)
        self.assertTrue(outputs[0].primary)
        self.assertEqual((0, 0, 1920, 1080), (outputs[0].x, outputs[0].y, outputs[0].width, outputs[0].height))

    def test_three_outputs_and_negative_coordinate(self):
        outputs = CONTROLLER.parse_xrandr(
            "DP-2 connected 1280x1024-1280+0\n"
            "DP-1 connected primary 1920x1080+0+0\n"
            "HDMI-A-0 connected 1080x1920+1920+0 right\n"
            "DP-3 disconnected\n"
        )
        self.assertEqual(["dp-1", "dp-2", "hdmi-a-0"], [item.output_key for item in outputs])
        self.assertEqual(-1280, outputs[1].x)
        self.assertEqual((1920, 0, 1080, 1920), (outputs[2].x, outputs[2].y, outputs[2].width, outputs[2].height))


class AssignmentTests(unittest.TestCase):
    def setUp(self):
        self.outputs = CONTROLLER.parse_xrandr(
            "DP-1 connected primary 1920x1080+0+0\n"
            "HDMI-1 connected 1920x1080+1920+0\n"
        )

    def test_each_output_receives_its_own_url(self):
        targets = CONTROLLER.reconcile_targets(
            self.outputs,
            [
                {"output_key": "dp-1", "player_url": "https://example.test/signage/play/one/"},
                {"output_key": "hdmi-1", "player_url": "https://example.test/signage/play/two/"},
            ],
        )
        self.assertEqual("https://example.test/signage/play/one/", targets["dp-1"])
        self.assertEqual("https://example.test/signage/play/two/", targets["hdmi-1"])

    def test_disconnected_and_unsafe_assignments_are_ignored(self):
        targets = CONTROLLER.reconcile_targets(
            self.outputs,
            [
                {"output_key": "dp-9", "player_url": "https://example.test/unused"},
                {"output_key": "dp-1", "player_url": "file:///etc/passwd"},
                {"output_key": "hdmi-1", "pairing_url": "https://example.test/pair/?kiosk=1"},
            ],
        )
        self.assertEqual({"hdmi-1": "https://example.test/pair/?kiosk=1"}, targets)

    def test_exited_launcher_never_triggers_automatic_relaunch(self):
        class FakeProcess:
            def __init__(self, return_code=None):
                self.return_code = return_code
                self.terminated = False

            def poll(self):
                return self.return_code

            def terminate(self):
                self.terminated = True
                self.return_code = 0

        controller = CONTROLLER.Controller.__new__(CONTROLLER.Controller)
        working = FakeProcess()
        handed_off = FakeProcess(0)
        controller.players = {
            "dp-1": CONTROLLER.Player(working, "https://example.test/one", (0, 0, 1920, 1080)),
            "hdmi-1": CONTROLLER.Player(handed_off, "https://example.test/two", (1920, 0, 1920, 1080)),
        }
        controller.screens_paused = False
        launched = []

        def launch(output, url):
            launched.append((output.output_key, url))
            return CONTROLLER.Player(FakeProcess(), url, (output.x, output.y, output.width, output.height))

        controller.launch_player = launch
        controller.sync_players(
            self.outputs,
            {"dp-1": "https://example.test/one", "hdmi-1": "https://example.test/two"},
        )
        self.assertIs(working, controller.players["dp-1"].process)
        self.assertIs(handed_off, controller.players["hdmi-1"].process)
        self.assertEqual([], launched)

    def test_url_change_is_an_explicit_relaunch_condition(self):
        controller = CONTROLLER.Controller.__new__(CONTROLLER.Controller)
        player = CONTROLLER.Player(object(), "https://example.test/old", (0, 0, 1920, 1080))
        controller.players = {"dp-1": player}
        controller.screens_paused = False
        stopped = []
        controller.stop_player = lambda item: stopped.append(item)
        controller.launch_player = lambda output, url: CONTROLLER.Player(object(), url, (output.x, output.y, output.width, output.height))
        controller.sync_players(self.outputs[:1], {"dp-1": "https://example.test/new"})
        self.assertEqual([player], stopped)
        self.assertEqual("https://example.test/new", controller.players["dp-1"].url)

    def test_desktop_mode_prevents_player_launch(self):
        controller = CONTROLLER.Controller.__new__(CONTROLLER.Controller)
        controller.players = {}
        controller.screens_paused = True
        controller.launch_player = lambda _output, _url: self.fail("desktop mode launched Chrome")
        controller.sync_players(self.outputs, {"dp-1": "https://example.test/one"})
        self.assertEqual({}, controller.players)

    def test_profile_process_lookup_matches_exact_profile_argument(self):
        with tempfile.TemporaryDirectory() as temporary:
            proc = pathlib.Path(temporary)
            (proc / "101").mkdir()
            (proc / "101" / "cmdline").write_bytes(b"chrome\0--user-data-dir=/profiles/dp-1\0")
            (proc / "202").mkdir()
            (proc / "202" / "cmdline").write_bytes(b"chrome\0--user-data-dir=/profiles/dp-10\0")
            self.assertEqual(
                {101},
                CONTROLLER.Controller.profile_process_ids(pathlib.Path("/profiles/dp-1"), proc),
            )


class ChromeCommandTests(unittest.TestCase):
    def test_each_output_gets_isolated_profile_and_exact_geometry(self):
        output = CONTROLLER.Output("hdmi-1", "HDMI-1", 1920, 0, 1080, 1920)
        command = CONTROLLER.chrome_command(
            "/usr/bin/google-chrome-stable",
            pathlib.Path("/profiles/hdmi-1"),
            output,
            "https://example.test/signage/play/two/",
        )
        self.assertIn("--user-data-dir=/profiles/hdmi-1", command)
        self.assertIn("--window-position=1920,0", command)
        self.assertIn("--window-size=1080,1920", command)
        self.assertIn("--kiosk", command)
        self.assertIn("--disable-sync", command)
        self.assertIn("--password-store=basic", command)
        self.assertEqual("https://example.test/signage/play/two/", command[-1])

    def test_negative_desktop_coordinates_are_preserved(self):
        output = CONTROLLER.Output("dp-2", "DP-2", -1280, -100, 1280, 1024)
        command = CONTROLLER.chrome_command(
            "/usr/bin/chromium", pathlib.Path("/profiles/dp-2"), output, "https://example.test"
        )
        self.assertIn("--window-position=-1280,-100", command)


class ValidationTests(unittest.TestCase):
    def test_site_normalization(self):
        self.assertEqual("https://example.test", CONTROLLER.validate_site_url("https://example.test/"))

    def test_site_rejects_credentials_and_query(self):
        with self.assertRaises(ValueError):
            CONTROLLER.validate_site_url("https://user:pass@example.test")
        with self.assertRaises(ValueError):
            CONTROLLER.validate_site_url("https://example.test/?bad=1")

    def test_no_automatic_reboot_mechanism_exists(self):
        source = MODULE_PATH.read_text(encoding="utf-8")
        self.assertNotIn("StartLimitAction", source)
        self.assertNotIn("reboot-force", source)
        self.assertNotIn("OnCalendar", source)

    def test_graphical_session_autostart_does_not_guess_display(self):
        root = pathlib.Path(__file__).parents[1]
        launcher = (root / "ds-ubuntu-autostart.sh").read_text(encoding="utf-8")
        installer = (root / "install-kiosk.sh").read_text(encoding="utf-8")
        desktop = (root / "autostart/bykutt-digital-signage.desktop").read_text(encoding="utf-8")
        self.assertNotIn("DISPLAY=:0", launcher)
        self.assertIn("Exec=/usr/local/bin/ds-ubuntu-autostart", desktop)
        self.assertIn("X-GNOME-Autostart-enabled=true", desktop)
        controller_source = MODULE_PATH.read_text(encoding="utf-8")
        self.assertIn('["xsetroot", "-cursor", str(bitmap), str(bitmap)]', controller_source)
        self.assertIn('["unclutter", "-idle", "0", "-jitter", "1", "-root"]', controller_source)
        self.assertIn('["xsetroot", "-cursor_name", "left_ptr"]', controller_source)
        self.assertIn('"SigninAllowed": false', installer)
        self.assertIn('"SyncDisabled": true', installer)
        self.assertIn('"PasswordManagerEnabled": false', installer)

    def test_controller_has_a_nonblocking_single_instance_lock(self):
        source = MODULE_PATH.read_text(encoding="utf-8")
        self.assertIn("fcntl.LOCK_EX | fcntl.LOCK_NB", source)

    def test_no_window_detection_or_automatic_browser_health_restart(self):
        source = MODULE_PATH.read_text(encoding="utf-8")
        self.assertNotIn("wmctrl", source)
        self.assertNotIn("window_ids", source)
        self.assertNotIn("Chrome window did not appear", source)

    def test_installer_hard_disables_sleep_and_uses_nonblocking_update_service(self):
        root = pathlib.Path(__file__).parents[1]
        installer = (root / "install-kiosk.sh").read_text(encoding="utf-8")
        root_command = (root / "digital-signage-root-command").read_text(encoding="utf-8")
        self.assertIn("AllowSuspend=no", installer)
        self.assertIn("AllowHibernation=no", installer)
        self.assertIn("IdleAction=ignore", installer)
        self.assertIn("systemctl start --no-block digital-signage-ubuntu-update.service", root_command)

    def test_remote_update_reboots_only_after_success(self):
        updater = (pathlib.Path(__file__).parents[1] / "update-kiosk.sh").read_text(encoding="utf-8")
        service = (pathlib.Path(__file__).parents[1] / "systemd/digital-signage-ubuntu-update.service").read_text(encoding="utf-8")
        self.assertIn('if [ "$restart_service" -eq 1 ]; then', updater)
        self.assertIn("systemctl reboot", updater)
        self.assertIn("digital-signage-update --no-restart", service)
        self.assertIn("show_update_screen", MODULE_PATH.read_text(encoding="utf-8"))

    def test_sleep_schedule_uses_supplied_server_epoch(self):
        controller = CONTROLLER.Controller.__new__(CONTROLLER.Controller)
        controller.display_sleeping = None
        controller.last_error = ""
        controller.log = lambda _message: None
        schedule = {"enabled": True, "timezone": "UTC", "days": [0], "wake_time": "07:00", "sleep_time": "22:00"}
        with mock.patch.object(CONTROLLER.subprocess, "run") as run:
            run.return_value.returncode = 0
            run.return_value.stderr = ""
            run.return_value.stdout = ""
            controller.apply_display_schedule(schedule, 1789340400)  # 2026-09-13 23:00 UTC.
            self.assertTrue(controller.display_sleeping)
            self.assertIn(["xset", "dpms", "force", "off"], [call.args[0] for call in run.call_args_list])

    def test_controller_supports_remote_url_switch_and_display_schedule(self):
        source = MODULE_PATH.read_text(encoding="utf-8")
        self.assertIn('if command_type == "switch_url":', source)
        self.assertIn('["xset", "dpms", "force", "off"]', source)
        self.assertIn('["xset", "dpms", "force", "on"]', source)

    def test_installer_grants_only_the_allowlisted_root_helper(self):
        root = pathlib.Path(__file__).parents[1]
        installer = (root / "install-kiosk.sh").read_text(encoding="utf-8")
        helper = (root / "digital-signage-root-command").read_text(encoding="utf-8")
        self.assertIn("NOPASSWD: /usr/local/sbin/digital-signage-root-command", installer)
        self.assertIn("sudo -n -l /usr/local/sbin/digital-signage-root-command", installer)
        self.assertIn('case "${1:-}" in', helper)
        self.assertIn("Unsupported privileged command.", helper)

    def test_wordpress_linux_update_does_not_require_a_release_asset(self):
        repo = pathlib.Path(__file__).parents[2]
        admin = (repo / "digital-signage/includes/class-ds-admin.php").read_text(encoding="utf-8")
        controller = (repo / "digital-signage/includes/class-ds-controllers.php").read_text(encoding="utf-8")
        self.assertIn("'configured_git_remote'", admin)
        self.assertIn("'linux' === $controller->platform", admin)
        self.assertIn("command_database", controller)


class GdmConfigurationTests(unittest.TestCase):
    def test_existing_daemon_settings_are_replaced_once(self):
        source = "# header\n[daemon]\n#WaylandEnable=true\nAutomaticLogin=old\n[security]\n"
        result = GDM.configure_text(source, "robin")
        self.assertEqual(1, result.count("AutomaticLogin=robin"))
        self.assertEqual(1, result.count("AutomaticLoginEnable=true"))
        self.assertEqual(1, result.count("WaylandEnable=false"))
        self.assertIn("[security]", result)

    def test_daemon_section_is_added_when_missing(self):
        result = GDM.configure_text("[security]\n", "signage")
        self.assertIn("[daemon]", result)
        self.assertIn("AutomaticLogin=signage", result)


if __name__ == "__main__":
    unittest.main()
