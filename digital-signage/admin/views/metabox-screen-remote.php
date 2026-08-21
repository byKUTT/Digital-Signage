<?php
/** @var WP_Post $post */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="ds_remote_action" />
	<input type="hidden" name="screen_id" value="<?php echo esc_attr( $post->ID ); ?>" />
	<?php wp_nonce_field( 'ds_remote_action' ); ?>
	<p>
		<button type="submit" class="button" name="remote_action" value="refresh"><?php esc_html_e( 'Refresh Playlist Now', 'digital-signage' ); ?></button>
	</p>
	<p>
		<button type="submit" class="button" name="remote_action" value="reload"><?php esc_html_e( 'Reload Browser Session', 'digital-signage' ); ?></button>
	</p>
	<p class="description"><?php esc_html_e( 'Commands are delivered the next time this screen polls the API.', 'digital-signage' ); ?></p>
</form>
