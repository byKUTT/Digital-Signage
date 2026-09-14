#!/usr/bin/env python3
"""Pure unit tests for the Ubuntu multi-display controller."""

import importlib.util
import pathlib
import sys
import tempfile
import unittest
import urllib.error
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
                {"output_key": "dp-1", "player_url": "https://example.test/play/one/"},
                {"output_key": "hdmi-1", "player_url": "https://example.test/play/two/"},
            ],
        )
        self.assertEqual("https://example.test/play/one/", targets["dp-1"])
        self.assertEqual("https://example.test/play/two/", targets["hdmi-1"])

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
            "https://example.test/play/two/",
        )
        self.assertIn("--user-data-dir=/profiles/hdmi-1", command)
        self.assertIn("--window-position=1920,0", command)
        self.assertIn("--window-size=1080,1920", command)
        self.assertIn("--kiosk", command)
        self.assertIn("--disable-sync", command)
        self.assertIn("--password-store=basic", command)
        self.assertEqual("https://example.test/play/two/", command[-1])

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

    def test_deleted_controller_forgets_only_stale_identity(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = pathlib.Path(temporary)
            settings = root / "settings.json"
            identity = root / "identity.json"
            settings.write_text(
                '{"site":"https://example.test","user_home":"/tmp","profile_root":"/tmp/profiles"}',
                encoding="utf-8",
            )
            identity.write_text(
                '{"public_id":"11111111-1111-1111-1111-111111111111","token":"' + "x" * 64 + '"}',
                encoding="utf-8",
            )
            with mock.patch.object(CONTROLLER, "find_browser", return_value="/usr/bin/chromium"):
                controller = CONTROLLER.Controller(settings, identity)
            error = urllib.error.HTTPError(
                "https://example.test/heartbeat", 401, "Unauthorized", {}, None
            )
            with mock.patch.object(CONTROLLER.urllib.request, "urlopen", side_effect=error):
                with self.assertRaises(CONTROLLER.ControllerRegistrationLost):
                    controller.heartbeat([])
            self.assertEqual({}, controller.identity)
            self.assertFalse(identity.exists())
            self.assertEqual("https://example.test", controller.site)

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
        self.assertIn("systemctl reboot", updater)
        self.assertNotIn("--no-restart", updater)
        self.assertIn("ExecStart=/usr/local/sbin/digital-signage-update", service)
        self.assertIn("show_update_screen", MODULE_PATH.read_text(encoding="utf-8"))

    def test_remote_update_reports_stage_and_skips_apt_during_upgrade(self):
        root = pathlib.Path(__file__).parents[1]
        updater = (root / "update-kiosk.sh").read_text(encoding="utf-8")
        installer = (root / "install-kiosk.sh").read_text(encoding="utf-8")
        self.assertIn('Update failed during ${stage}', updater)
        self.assertIn('git -C "$repository_path" reset --hard "origin/$branch"', updater)
        self.assertIn('if [ "$mode" != "--upgrade" ]; then', installer)

    def test_remote_diagnostics_are_fixed_and_not_an_arbitrary_shell(self):
        root = pathlib.Path(__file__).parents[1]
        source = MODULE_PATH.read_text(encoding="utf-8")
        helper = (root / "digital-signage-root-command").read_text(encoding="utf-8")
        self.assertIn('"update": "diagnostic-update"', source)
        self.assertIn("diagnostic-network)", helper)
        self.assertIn("diagnostic-system)", helper)
        self.assertIn("Unsupported privileged command.", helper)

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
        self.assertIn("Cmnd_Alias DIGITAL_SIGNAGE_ROOT", installer)
        self.assertIn("$kiosk_user ALL=(root) NOPASSWD: DIGITAL_SIGNAGE_ROOT", installer)
        self.assertIn("sudo -n /usr/local/sbin/digital-signage-root-command check", installer)
        self.assertIn('case "${1:-}" in', helper)
        self.assertIn("Unsupported privileged command.", helper)

    def test_wordpress_linux_update_does_not_require_a_release_asset(self):
        repo = pathlib.Path(__file__).parents[2]
        admin = (repo / "wp/includes/class-ds-admin.php").read_text(encoding="utf-8")
        controller = (repo / "wp/includes/class-ds-controllers.php").read_text(encoding="utf-8")
        self.assertIn("'configured_git_remote'", admin)
        self.assertIn("'linux' === $controller->platform", admin)
        self.assertIn("command_database", controller)

    def test_new_controllers_are_hidden_until_paired_and_owned_after_pairing(self):
        repo = pathlib.Path(__file__).parents[2]
        controllers = (repo / "wp/includes/class-ds-controllers.php").read_text(encoding="utf-8")
        groups = (repo / "wp/includes/class-ds-groups.php").read_text(encoding="utf-8")
        portal = (repo / "wp/includes/class-ds-portal.php").read_text(encoding="utf-8")
        self.assertIn("WHERE paired_at IS NOT NULL", controllers)
        self.assertNotIn("self::attach_legacy_screen( $controller_id", controllers)
        self.assertIn("set_controller_owner", controllers)
        self.assertIn("ensure_user_group", groups)
        self.assertIn("get_current_user_id(), $group_id", portal)

    def test_pairing_and_no_channel_states_link_to_authenticated_portal(self):
        repo = pathlib.Path(__file__).parents[2]
        player = (repo / "wp/includes/class-ds-player.php").read_text(encoding="utf-8")
        template = (repo / "wp/public/templates/player-template.php").read_text(encoding="utf-8")
        controller_template = (repo / "wp/public/templates/controller-pairing.php").read_text(encoding="utf-8")
        self.assertIn("DS_Portal::url( 'pair'", player)
        self.assertIn("QR code to reconnect this screen", template)
        self.assertIn("data.manage_url", controller_template)

    def test_screens_kutt_ee_is_the_default_install_target(self):
        installer = (pathlib.Path(__file__).parents[1] / "install-kiosk.sh").read_text(encoding="utf-8")
        self.assertIn('site="${1:-https://screens.kutt.ee}"', installer)

    def test_installer_executes_a_real_passwordless_privilege_check(self):
        installer = (pathlib.Path(__file__).parents[1] / "install-kiosk.sh").read_text(encoding="utf-8")
        self.assertIn("Cmnd_Alias DIGITAL_SIGNAGE_ROOT", installer)
        self.assertIn('sudo -n /usr/local/sbin/digital-signage-root-command check', installer)
        self.assertNotIn('sudo -n -l /usr/local/sbin/digital-signage-root-command "$allowed_action"', installer)

    def test_spotify_access_is_controller_scoped_and_not_a_signage_role(self):
        repo = pathlib.Path(__file__).parents[2]
        spotify = (repo / "wp/includes/class-ds-spotify.php").read_text(encoding="utf-8")
        roles = (repo / "wp/includes/class-ds-roles.php").read_text(encoding="utf-8")
        self.assertIn("ds_spotify_controller_ids", spotify)
        self.assertIn("control_digital_signage_spotify", roles)
        self.assertIn("in_array( $controller_id, self::user_controller_ids(), true )", spotify)
        self.assertNotIn("sdk.scdn.co", spotify)

    def test_template_catalog_has_four_variations_and_two_orientations(self):
        repo = pathlib.Path(__file__).parents[2]
        designer = (repo / "wp/public/js/designer.js").read_text(encoding="utf-8")
        portal = (repo / "wp/public/templates/portal.php").read_text(encoding="utf-8")
        generated = repo / "wp/public/templates/designs"
        self.assertEqual(32, len(list(generated.glob("*-*-*.json"))))
        self.assertIn("loadTemplate", designer)
        self.assertIn("assetFiles", designer)
        self.assertIn("Four visual families", portal)

    def test_screens_bykutt_dashboard_uses_the_new_responsive_brand_system(self):
        repo = pathlib.Path(__file__).parents[2]
        portal = (repo / "wp/public/templates/portal.php").read_text(encoding="utf-8")
        styles = (repo / "wp/public/css/ui-refresh.css").read_text(encoding="utf-8")
        self.assertIn("screens-bykutt-mark.svg", portal)
        self.assertIn("Every screen, in sync.", portal)
        self.assertIn("--ds-canvas:#081712", styles)
        self.assertIn("--ds-accent:#e9ff54", styles)
        self.assertIn("@media(max-width:720px)", styles)

    def test_local_licensed_music_is_group_scoped_and_ducks_slide_audio(self):
        repo = pathlib.Path(__file__).parents[2]
        music = (repo / "wp/includes/class-ds-music.php").read_text(encoding="utf-8")
        player = (repo / "wp/public/js/player.js").read_text(encoding="utf-8")
        portal = (repo / "wp/public/templates/portal.php").read_text(encoding="utf-8")
        self.assertIn("DS_Groups::post_group_id", music)
        self.assertIn("'duck_ms' => 1500", music)
        self.assertIn("fadeMusic( 0, state.music.duckMs )", player)
        self.assertIn("fadeMusic( state.music.volume, state.music.duckMs )", player)
        self.assertIn("I confirm this group may play this track commercially.", portal)

    def test_spotify_controller_output_and_audio_ducking(self):
        repo = pathlib.Path(__file__).parents[2]
        spotify = (repo / "wp/includes/class-ds-spotify.php").read_text(encoding="utf-8")
        player = (repo / "wp/public/js/player.js").read_text(encoding="utf-8")
        self.assertIn("controller_player_config", spotify)
        self.assertIn("spotify-player.js", player)
        self.assertIn("fadeSpotify( 0, 1500 )", player)

    def test_group_media_picker_quota_and_single_slide_playback(self):
        repo = pathlib.Path(__file__).parents[2]
        storage = (repo / "wp/includes/class-ds-storage.php").read_text(encoding="utf-8")
        portal_js = (repo / "wp/public/js/portal.js").read_text(encoding="utf-8")
        player = (repo / "wp/public/js/player.js").read_text(encoding="utf-8")
        self.assertIn("DEFAULT_LIMIT = 2147483648", storage)
        self.assertIn("ds_media_upload", storage)
        self.assertNotIn("window.wp?.media", portal_js)
        self.assertIn("media-library-dialog", portal_js)
        self.assertIn("if ( singleItem ) { return; }", player)
        self.assertIn("video.loop = true", player)

    def test_designer_matches_channel_resolution_and_publishes_cover_media(self):
        repo = pathlib.Path(__file__).parents[2]
        designer_js = (repo / "wp/public/js/designer.js").read_text(encoding="utf-8")
        designer_php = (repo / "wp/includes/class-ds-designer.php").read_text(encoding="utf-8")
        self.assertIn("resizeForChannel", designer_js)
        self.assertIn("config.channelFormats", designer_js)
        self.assertIn("'fit' => 'cover'", designer_php)

    def test_installer_does_not_depend_on_the_install_binary(self):
        installer = (pathlib.Path(__file__).parents[1] / "install-kiosk.sh").read_text(encoding="utf-8")
        self.assertIn("copy_managed_file", installer)
        self.assertIn("write_managed_file", installer)
        self.assertNotIn("install -o", installer)

    def test_browser_screens_have_stable_urls_and_group_preview_access(self):
        repo = pathlib.Path(__file__).parents[2]
        crud = (repo / "wp/includes/class-ds-crud.php").read_text(encoding="utf-8")
        player = (repo / "wp/includes/class-ds-player.php").read_text(encoding="utf-8")
        portal = (repo / "wp/public/templates/portal.php").read_text(encoding="utf-8")
        self.assertIn("generate_screen_token", crud)
        self.assertIn("DS_Groups::can_access_post( $channel_id )", player)
        self.assertIn("Copy browser URL", portal)
        self.assertIn("No controller is required", portal)

    def test_group_screen_limits_and_capacity_requests_are_enforced(self):
        repo = pathlib.Path(__file__).parents[2]
        groups = (repo / "wp/includes/class-ds-groups.php").read_text(encoding="utf-8")
        portal = (repo / "wp/includes/class-ds-portal.php").read_text(encoding="utf-8")
        controllers = (repo / "wp/includes/class-ds-controllers.php").read_text(encoding="utf-8")
        self.assertIn("DEFAULT_SCREEN_LIMIT = 2", groups)
        self.assertIn("request_capacity", groups)
        self.assertIn("! DS_Groups::can_create_screen", portal)
        self.assertIn("! DS_Groups::can_create_screen", controllers)

    def test_wp_admin_is_operations_only_and_has_copy_ready_guides(self):
        repo = pathlib.Path(__file__).parents[2]
        admin = (repo / "wp/includes/class-ds-admin.php").read_text(encoding="utf-8")
        guide = (repo / "wp/admin/views/guides.php").read_text(encoding="utf-8")
        menu_block = admin[admin.index("public function menu()") : admin.index("public function body_class")]
        self.assertNotIn("ds-channels", menu_block)
        self.assertNotIn("ds-screens", menu_block)
        self.assertIn("ds-guides", menu_block)
        self.assertIn("sudo digital-signage-update && sudo reboot", guide)

    def test_recaptcha_is_optional_and_verified_server_side(self):
        repo = pathlib.Path(__file__).parents[2]
        captcha = (repo / "wp/includes/class-ds-recaptcha.php").read_text(encoding="utf-8")
        auth = (repo / "wp/includes/class-ds-auth.php").read_text(encoding="utf-8")
        template = (repo / "wp/public/templates/auth.php").read_text(encoding="utf-8")
        self.assertIn("recaptcha/api/siteverify", captcha)
        self.assertIn("DS_Recaptcha::verify", auth)
        self.assertIn("g-recaptcha-response", auth)
        self.assertIn("g-recaptcha", template)


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
