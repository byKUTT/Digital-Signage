#!/usr/bin/env python3
"""Pure unit tests for the Ubuntu multi-display controller."""

import importlib.util
import pathlib
import sys
import unittest

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

    def test_failed_player_restarts_without_restarting_working_output(self):
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
        failed = FakeProcess(1)
        controller.players = {
            "dp-1": CONTROLLER.Player(working, "https://example.test/one", (0, 0, 1920, 1080)),
            "hdmi-1": CONTROLLER.Player(failed, "https://example.test/two", (1920, 0, 1920, 1080)),
        }
        controller.window_ids = lambda: set()
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
        self.assertEqual([("hdmi-1", "https://example.test/two")], launched)

    def test_chrome_launcher_exit_does_not_replace_a_live_window(self):
        class ExitedLauncher:
            def poll(self):
                return 0

            def terminate(self):
                raise AssertionError("live Chrome window must not be terminated")

        controller = CONTROLLER.Controller.__new__(CONTROLLER.Controller)
        player = CONTROLLER.Player(
            ExitedLauncher(),
            "https://example.test/one",
            (0, 0, 1920, 1080),
            "0x100001",
        )
        controller.players = {"dp-1": player}
        controller.window_ids = lambda: {"0x100001"}
        controller.launch_player = lambda _output, _url: self.fail("live window was relaunched")
        controller.sync_players(self.outputs, {"dp-1": "https://example.test/one"})
        self.assertIs(player, controller.players["dp-1"])


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
        self.assertIn("unclutter -idle 0.1 -root", launcher)
        self.assertIn('"SigninAllowed": false', installer)
        self.assertIn('"SyncDisabled": true', installer)
        self.assertIn('"PasswordManagerEnabled": false', installer)

    def test_controller_has_a_nonblocking_single_instance_lock(self):
        source = MODULE_PATH.read_text(encoding="utf-8")
        self.assertIn("fcntl.LOCK_EX | fcntl.LOCK_NB", source)


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
