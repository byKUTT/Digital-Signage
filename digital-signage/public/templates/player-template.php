<?php
/**
 * Chrome-less fullscreen player shell. No theme header/footer/sidebar, no admin bar.
 * All real behavior lives in public/js/player.js, which polls the DS REST API.
 * Doubles as the wp-admin "Preview" renderer when $is_preview is true.
 *
 * @var object  $screen  WP_Post for a real screen, or a plain object with ID/post_title in preview mode.
 * @var string  $token
 * @var string  $rest_url
 * @var bool    $is_preview
 * @var int     $preview_id
 * @var string  $preview_nonce
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$is_preview    = $is_preview ?? false;
$preview_id    = $preview_id ?? 0;
$preview_nonce = $preview_nonce ?? '';
$orientation   = $is_preview ? 'auto' : ( get_post_meta( $screen->ID, 'ds_orientation', true ) ?: 'landscape' );
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>" <?php echo is_rtl() ? 'dir="rtl"' : ''; ?> data-ds-orientation="<?php echo esc_attr( $orientation ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no" />
	<title><?php echo esc_html( $screen->post_title ); ?> &mdash; <?php bloginfo( 'name' ); ?></title>
	<meta name="robots" content="noindex, nofollow" />
	<link rel="stylesheet" href="<?php echo esc_url( DS_PLUGIN_URL . 'public/css/player.css' ); ?>?v=<?php echo esc_attr( DS_VERSION ); ?>" />
	<?php if ( $is_preview ) : ?>
	<style>
		.ds-start-overlay { display: none !important; }
		.ds-preview-badge {
			position: fixed; top: 12px; left: 12px; z-index: 10001;
			background: rgba(0,0,0,.6); color:#fff; font: 500 12px/1 -apple-system,sans-serif;
			padding: 6px 12px; border-radius: 20px; letter-spacing: .03em;
		}
	</style>
	<?php endif; ?>
</head>
<body class="ds-player-body">

	<?php if ( $is_preview ) : ?>
		<div class="ds-preview-badge">👁 <?php esc_html_e( 'Live Preview', 'digital-signage' ); ?></div>
	<?php else : ?>
		<div id="ds-start-overlay" class="ds-start-overlay">
			<div class="ds-start-inner">
				<p class="ds-start-title"><?php esc_html_e( 'Digital Signage Player', 'digital-signage' ); ?></p>
				<p class="ds-start-sub"><?php echo esc_html( $screen->post_title ); ?></p>
				<button id="ds-start-button" type="button" autofocus><?php esc_html_e( 'Press OK to Start', 'digital-signage' ); ?></button>
				<p class="ds-start-hint"><?php esc_html_e( 'Required once to enable fullscreen playback on this TV or browser.', 'digital-signage' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div id="ds-stage" class="ds-stage ds-layout-fullscreen">
		<div id="ds-zone-main" class="ds-zone ds-zone-main"></div>
		<div id="ds-zone-secondary" class="ds-zone ds-zone-secondary"></div>
		<div id="ds-zone-ticker" class="ds-zone ds-zone-ticker"></div>
		<div id="ds-zone-corner" class="ds-zone ds-zone-corner"></div>
	</div>

	<div id="ds-no-channel" class="ds-no-channel" hidden><?php esc_html_e( 'No channel selected', 'digital-signage' ); ?></div>

	<div id="ds-offline-indicator" class="ds-offline-indicator" hidden></div>

	<script>
		window.DS_PLAYER = {
			restUrl: <?php echo wp_json_encode( $rest_url ); ?>,
			screenId: <?php echo (int) $screen->ID; ?>,
			token: <?php echo wp_json_encode( $token ); ?>,
			orientation: <?php echo wp_json_encode( $orientation ); ?>,
			pollInterval: <?php echo (int) DS_Settings::get( 'poll_interval', 60 ); ?>,
			heartbeatInterval: <?php echo (int) DS_Settings::get( 'heartbeat_interval', 30 ); ?>,
			isPreview: <?php echo $is_preview ? 'true' : 'false'; ?>,
			previewChannelId: <?php echo (int) $preview_id; ?>,
			nonce: <?php echo wp_json_encode( $preview_nonce ); ?>,
			appVersion: <?php echo wp_json_encode( DS_VERSION ); ?>
		};
	</script>
	<script src="<?php echo esc_url( DS_PLUGIN_URL . 'public/js/player.js' ); ?>?v=<?php echo esc_attr( DS_VERSION ); ?>"></script>
</body>
</html>
