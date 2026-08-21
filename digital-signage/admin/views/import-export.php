<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=digital-signage' ) ); ?>">&larr; <?php esc_html_e( 'Dashboard', 'digital-signage' ); ?></a></p>
			<h1><?php esc_html_e( 'Import / Export', 'digital-signage' ); ?></h1>
		</div>
	</div>

	<?php if ( isset( $_GET['ds_error'] ) ) : ?>
		<div class="ds-notice ds-notice-error"><?php esc_html_e( 'Import failed — check the file and try again.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<div class="ds-two-col">
		<div class="ds-panel">
			<h2><?php esc_html_e( 'Export a channel', 'digital-signage' ); ?></h2>
			<p class="ds-hint"><?php esc_html_e( 'Downloads the channel and all its slides as a JSON file for backup or to replicate on another WP install.', 'digital-signage' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_export_channel" />
				<?php wp_nonce_field( 'ds_export_channel' ); ?>
				<div class="ds-field">
					<select name="channel_id" class="ds-input" required>
						<option value=""><?php esc_html_e( '— Select a channel —', 'digital-signage' ); ?></option>
						<?php foreach ( $channels as $c ) : ?>
							<option value="<?php echo esc_attr( $c->ID ); ?>"><?php echo esc_html( $c->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="ds-btn ds-btn-primary"><?php esc_html_e( 'Download JSON', 'digital-signage' ); ?></button>
			</form>
		</div>

		<div class="ds-panel">
			<h2><?php esc_html_e( 'Import a channel', 'digital-signage' ); ?></h2>
			<p class="ds-hint"><?php esc_html_e( 'Creates a new channel (and its slides) from a previously exported JSON file.', 'digital-signage' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="ds_import_channel" />
				<?php wp_nonce_field( 'ds_import_channel' ); ?>
				<div class="ds-field">
					<input type="file" name="ds_import_file" accept="application/json" required />
				</div>
				<button type="submit" class="ds-btn ds-btn-primary"><?php esc_html_e( 'Import', 'digital-signage' ); ?></button>
			</form>
		</div>
	</div>
</div>
