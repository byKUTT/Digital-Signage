<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ds-wrap">
	<h1><?php esc_html_e( 'Import / Export Channels', 'digital-signage' ); ?></h1>

	<?php if ( isset( $_GET['ds_error'] ) ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Import failed — check the file and try again.', 'digital-signage' ); ?></p></div>
	<?php endif; ?>

	<div class="ds-pairing-grid">
		<div class="ds-pairing-step">
			<h2><?php esc_html_e( 'Export a channel', 'digital-signage' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_export_channel" />
				<?php wp_nonce_field( 'ds_export_channel' ); ?>
				<select name="channel_id" required>
					<option value=""><?php esc_html_e( '— Select a channel —', 'digital-signage' ); ?></option>
					<?php foreach ( $channels as $c ) : ?>
						<option value="<?php echo esc_attr( $c->ID ); ?>"><?php echo esc_html( $c->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Download JSON', 'digital-signage' ); ?></button>
			</form>
		</div>

		<div class="ds-pairing-step">
			<h2><?php esc_html_e( 'Import a channel', 'digital-signage' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="ds_import_channel" />
				<?php wp_nonce_field( 'ds_import_channel' ); ?>
				<input type="file" name="ds_import_file" accept="application/json" required />
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'digital-signage' ); ?></button></p>
			</form>
		</div>
	</div>
</div>
