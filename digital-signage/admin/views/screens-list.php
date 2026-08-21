<?php
/**
 * @var WP_Post[] $screens
 * @var object[]   $heartbeats
 * @var int        $offline_after
 * @var WP_Post[]  $channels
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<h1><?php esc_html_e( 'Screens', 'digital-signage' ); ?></h1>
			<p class="ds-app-subtitle"><?php esc_html_e( 'Every TV, tablet or kiosk browser playing your content.', 'digital-signage' ); ?></p>
		</div>
		<div class="ds-app-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screen-edit' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Add Manually', 'digital-signage' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'Pair a New Screen', 'digital-signage' ); ?></a>
		</div>
	</div>

	<?php if ( isset( $_GET['ds_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Saved.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_deleted'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Screen deleted.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<?php if ( $screens ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ds_bulk_assign_channel" />
			<?php wp_nonce_field( 'ds_bulk_assign_channel' ); ?>

			<div class="ds-table-wrap">
				<table class="ds-table">
					<thead>
						<tr>
							<th></th>
							<th><?php esc_html_e( 'Screen', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Status', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Channel', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Orientation', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Last heartbeat', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Player URL', 'digital-signage' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $screens as $screen ) : ?>
							<?php
							$hb          = $heartbeats[ $screen->ID ] ?? null;
							$status      = ( $hb && ( time() - strtotime( $hb->last_seen . ' UTC' ) ) <= $offline_after ) ? 'online' : ( $hb ? 'offline' : 'never' );
							$channel_id  = get_post_meta( $screen->ID, 'ds_channel_id', true );
							$orientation = get_post_meta( $screen->ID, 'ds_orientation', true ) ?: 'landscape';
							$token       = get_post_meta( $screen->ID, 'ds_pairing_token', true );
							?>
							<tr>
								<td><input type="checkbox" name="screen_ids[]" value="<?php echo esc_attr( $screen->ID ); ?>" /></td>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen->ID ) ); ?>"><?php echo esc_html( $screen->post_title ); ?></a></td>
								<td><span class="ds-badge ds-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
								<td><?php echo $channel_id ? esc_html( get_the_title( $channel_id ) ) : '—'; ?></td>
								<td><?php echo esc_html( ucfirst( $orientation ) ); ?></td>
								<td><?php echo $hb ? esc_html( human_time_diff( strtotime( $hb->last_seen . ' UTC' ) ) . ' ago' ) : esc_html__( 'never', 'digital-signage' ); ?></td>
								<td><?php if ( $token ) : ?><a href="<?php echo esc_url( home_url( '/signage/play/' . $token . '/' ) ); ?>" target="_blank">↗</a><?php else : ?>—<?php endif; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="ds-bulk-bar">
				<select name="channel_id" class="ds-input">
					<option value="0"><?php esc_html_e( '— Assign channel to selected —', 'digital-signage' ); ?></option>
					<?php foreach ( $channels as $ch ) : ?>
						<option value="<?php echo esc_attr( $ch->ID ); ?>"><?php echo esc_html( $ch->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="ds-btn"><?php esc_html_e( 'Apply to Selected', 'digital-signage' ); ?></button>
			</div>
		</form>
	<?php else : ?>
		<div class="ds-empty-state">
			<p><?php esc_html_e( 'No screens paired yet.', 'digital-signage' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'Pair your first Screen', 'digital-signage' ); ?></a>
		</div>
	<?php endif; ?>
</div>
