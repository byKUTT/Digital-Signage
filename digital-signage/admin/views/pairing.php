<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ds-wrap">
	<h1><?php esc_html_e( 'Pair a Screen', 'digital-signage' ); ?></h1>

	<?php if ( isset( $_GET['ds_error'] ) ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'That pairing code is invalid, already used, or has expired.', 'digital-signage' ); ?></p></div>
	<?php endif; ?>

	<div class="ds-pairing-grid">
		<div class="ds-pairing-step">
			<h2><?php esc_html_e( '1. Open the player URL on the display', 'digital-signage' ); ?></h2>
			<p><?php esc_html_e( 'On the TV/tablet/kiosk browser, navigate to any unused player URL, e.g.:', 'digital-signage' ); ?></p>
			<code><?php echo esc_html( home_url( '/signage/play/{random-token}/' ) ); ?></code>
			<p><?php esc_html_e( 'The screen will show a 6-character pairing code full-screen. If you don\'t have a token yet, generate one below and open it on the display.', 'digital-signage' ); ?></p>
			<p>
				<button type="button" class="button" id="ds-generate-token"><?php esc_html_e( 'Generate a Player URL', 'digital-signage' ); ?></button>
				<span id="ds-generated-url"></span>
			</p>
		</div>

		<div class="ds-pairing-step">
			<h2><?php esc_html_e( '2. Enter the code shown on the screen', 'digital-signage' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_pair_screen" />
				<?php wp_nonce_field( 'ds_pair_screen' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="ds_screen_name"><?php esc_html_e( 'Screen name', 'digital-signage' ); ?></label></th>
						<td><input type="text" id="ds_screen_name" name="screen_name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Lobby TV', 'digital-signage' ); ?>" required /></td>
					</tr>
					<tr>
						<th><label for="ds_code"><?php esc_html_e( 'Pairing code', 'digital-signage' ); ?></label></th>
						<td><input type="text" id="ds_code" name="code" class="regular-text ds-code-input" maxlength="6" style="text-transform:uppercase" required /></td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Pair Screen', 'digital-signage' ); ?></button></p>
			</form>
		</div>
	</div>
</div>
