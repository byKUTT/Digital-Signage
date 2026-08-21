<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$prefilled_code = isset( $_GET['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ) : '';
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screens' ) ); ?>">&larr; <?php esc_html_e( 'Screens', 'digital-signage' ); ?></a></p>
			<h1><?php esc_html_e( 'Pair a Screen', 'digital-signage' ); ?></h1>
		</div>
	</div>

	<?php if ( isset( $_GET['ds_error'] ) ) : ?>
		<div class="ds-notice ds-notice-error"><?php esc_html_e( 'That pairing code is invalid, already used, or has expired.', 'digital-signage' ); ?></div>
	<?php elseif ( $prefilled_code ) : ?>
		<div class="ds-notice"><?php esc_html_e( 'Code scanned from a screen — just name it and confirm below.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<div class="ds-two-col">
		<div class="ds-panel">
			<h2><?php esc_html_e( '1. Open the player URL on the display', 'digital-signage' ); ?></h2>
			<p><?php esc_html_e( 'On the TV/tablet/kiosk browser, navigate to any unused player URL, e.g.:', 'digital-signage' ); ?></p>
			<code class="ds-code-block"><?php echo esc_html( home_url( '/signage/play/{random-token}/' ) ); ?></code>
			<p><?php esc_html_e( 'The screen will show a 6-character pairing code full-screen. If you don\'t have a token yet, generate one below and open it on the display.', 'digital-signage' ); ?></p>
			<button type="button" class="ds-btn" id="ds-generate-token"><?php esc_html_e( 'Generate a Player URL', 'digital-signage' ); ?></button>
			<p id="ds-generated-url"></p>
		</div>

		<div class="ds-panel">
			<h2><?php esc_html_e( '2. Enter the code shown on the screen', 'digital-signage' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_pair_screen" />
				<?php wp_nonce_field( 'ds_pair_screen' ); ?>
				<div class="ds-field">
					<label for="ds_screen_name"><?php esc_html_e( 'Screen name', 'digital-signage' ); ?></label>
					<input type="text" id="ds_screen_name" name="screen_name" class="ds-input" placeholder="<?php esc_attr_e( 'e.g. Lobby TV', 'digital-signage' ); ?>" required <?php echo $prefilled_code ? 'autofocus' : ''; ?> />
				</div>
				<div class="ds-field">
					<label for="ds_code"><?php esc_html_e( 'Pairing code', 'digital-signage' ); ?></label>
					<input type="text" id="ds_code" name="code" class="ds-input ds-code-input" maxlength="6" style="text-transform:uppercase" value="<?php echo esc_attr( $prefilled_code ); ?>" required />
				</div>
				<button type="submit" class="ds-btn ds-btn-primary"><?php esc_html_e( 'Pair Screen', 'digital-signage' ); ?></button>
			</form>
		</div>
	</div>
</div>
