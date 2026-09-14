"""Focused source regressions for the Screens byKUTT 4.8.1 patch."""

from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[2]


class Release481Tests(unittest.TestCase):
    def source(self, relative):
        return (ROOT / relative).read_text(encoding="utf-8")

    def test_plugin_version_is_481(self):
        plugin = self.source("wp/digital-signage.php")
        readme = self.source("wp/readme.txt")
        self.assertIn("Version:           4.8.1", plugin)
        self.assertIn("define( 'DS_VERSION', '4.8.1' );", plugin)
        self.assertIn("Stable tag: 4.8.1", readme)

    def test_player_routes_run_before_canonical_redirects(self):
        player = self.source("wp/includes/class-ds-player.php")
        self.assertIn("'maybe_render_player' ), -20", player)
        self.assertIn("(?:signage/)?preview/([0-9]+)", player)
        self.assertIn("(?:signage/)?play/([A-Za-z0-9]+)", player)

    def test_requested_login_destination_is_preserved(self):
        portal = self.source("wp/includes/class-ds-portal.php")
        self.assertIn("$target = $requested ?: $redirect_to;", portal)
        self.assertIn("wp_validate_redirect( $target, $fallback )", portal)

    def test_settings_avoids_eager_portal_datasets(self):
        portal = self.source("wp/includes/class-ds-portal.php")
        self.assertNotIn("array( 'channels', 'media', 'settings' )", portal)
        self.assertIn("array( 'channels', 'media' )", portal)
        self.assertIn("self::heartbeats_for( $screens )", portal)

    def test_slide_editor_uses_progressive_disclosure(self):
        template = self.source("wp/public/templates/portal.php")
        script = self.source("wp/public/js/portal.js")
        css = self.source("wp/public/css/portal-extras.css")
        self.assertIn('data-slide-types="image video"', template)
        self.assertIn("field.hidden = !field.dataset.slideTypes", script)
        self.assertIn(".ds-portal [hidden]{display:none!important}", css)
        self.assertIn("input.accept = `${accepted}/*`", script)

    def test_audible_video_has_gesture_recovery_and_cleanup(self):
        player = self.source("wp/public/js/player.js")
        template = self.source("wp/public/templates/player-template.php")
        self.assertIn("'NotAllowedError' === error.name", player)
        self.assertIn("video.defaultMuted = video.muted", player)
        self.assertIn("[ 'pause', 'ended', 'error', 'abort' ]", player)
        self.assertIn('id="ds-audio-button"', template)


if __name__ == "__main__":
    unittest.main()
