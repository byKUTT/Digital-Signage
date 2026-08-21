<?php
/**
 * Chrome-less fullscreen player shell. No theme header/footer/sidebar, no admin bar.
 * All real behavior lives in public/js/player.js, which polls the DS REST API.
 *
 * @var WP_Post $screen
 * @var string  $token
 * @var string  $rest_url
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$orientation = get_post_meta( $screen->ID, 'ds_orientation', true ) ?: 'landscape';
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>" <?php echo is_rtl() ? 'dir="rtl"' : ''; ?> data-ds-orientation="<?php echo esc_attr( $orientation ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no" />
	<title><?php echo esc_html( $screen->post_title ); ?> &mdash; <?php bloginfo( 'name' ); ?></title>
	<meta name="robots" content="noindex, nofollow" />
	<link rel="stylesheet" href="<?php echo esc_url( DS_PLUGIN_URL . 'public/css/player.css' ); ?>?v=<?php echo esc_attr( DS_VERSION ); ?>" />
</head>
<body class="ds-player-body">

	<div id="ds-start-overlay" class="ds-start-overlay">
		<div class="ds-start-inner">
			<p class="ds-start-title"><?php esc_html_e( 'Digital Signage Player', 'digital-signage' ); ?></p>
			<p class="ds-start-sub"><?php echo esc_html( $screen->post_title ); ?></p>
			<button id="ds-start-button" type="button"><?php esc_html_e( 'Click to Start', 'digital-signage' ); ?></button>
			<p class="ds-start-hint"><?php esc_html_e( 'Required once to enable fullscreen playback on this browser.', 'digital-signage' ); ?></p>
		</div>
	</div>

	<div id="ds-stage" class="ds-stage ds-layout-fullscreen">
		<div id="ds-zone-main" class="ds-zone ds-zone-main"></div>
		<div id="ds-zone-secondary" class="ds-zone ds-zone-secondary"></div>
		<div id="ds-zone-ticker" class="ds-zone ds-zone-ticker"></div>
		<div id="ds-zone-corner" class="ds-zone ds-zone-corner"></div>
	</div>

	<div id="ds-offline-indicator" class="ds-offline-indicator" hidden></div>

	<script>
		window.DS_PLAYER = {
			restUrl: <?php echo wp_json_encode( $rest_url ); ?>,
			screenId: <?php echo (int) $screen->ID; ?>,
			token: <?php echo wp_json_encode( $token ); ?>,
			orientation: <?php echo wp_json_encode( $orientation ); ?>,
			pollInterval: <?php echo (int) DS_Settings::get( 'poll_interval', 60 ); ?>,
			heartbeatInterval: <?php echo (int) DS_Settings::get( 'heartbeat_interval', 30 ); ?>
		};
	</script>
	<script src="<?php echo esc_url( DS_PLUGIN_URL . 'public/js/player.js' ); ?>?v=<?php echo esc_attr( DS_VERSION ); ?>"></script>
</body>
</html>
