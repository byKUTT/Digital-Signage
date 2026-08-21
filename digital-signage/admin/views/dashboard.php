<?php
/**
 * Screens dashboard: live status, current channel, resolution/orientation.
 *
 * @var WP_Post[] $screens
 * @var object[]  $heartbeats keyed by screen_id
 * @var int       $offline_after
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ds-wrap">
	<h1><?php esc_html_e( 'Digital Signage — Screens Dashboard', 'digital-signage' ); ?></h1>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Pair a New Screen', 'digital-signage' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ds_channel' ) ); ?>" class="button"><?php esc_html_e( 'Manage Channels', 'digital-signage' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-calendar' ) ); ?>" class="button"><?php esc_html_e( 'Calendar View', 'digital-signage' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-analytics' ) ); ?>" class="button"><?php esc_html_e( 'Proof of Play', 'digital-signage' ); ?></a>
	</p>

	<?php if ( ! $screens && ! get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) : ?>
		<div class="ds-quickstart">
			<h2><?php esc_html_e( 'Get started in 3 steps', 'digital-signage' ); ?></h2>
			<ol>
				<li><strong><?php esc_html_e( 'Create a Channel', 'digital-signage' ); ?></strong> — <?php esc_html_e( 'a named playlist, e.g. "Lobby Menu".', 'digital-signage' ); ?> <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ds_channel' ) ); ?>"><?php esc_html_e( 'Add a Channel →', 'digital-signage' ); ?></a></li>
				<li><strong><?php esc_html_e( 'Add Slides to it', 'digital-signage' ); ?></strong> — <?php esc_html_e( 'images, videos, webpages, or widgets, from the channel\'s edit screen.', 'digital-signage' ); ?></li>
				<li><strong><?php esc_html_e( 'Pair a Screen', 'digital-signage' ); ?></strong> — <?php esc_html_e( 'open the player URL on the TV/tablet, then confirm its code here.', 'digital-signage' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>"><?php esc_html_e( 'Pair a Screen →', 'digital-signage' ); ?></a></li>
			</ol>
		</div>
	<?php endif; ?>

	<div class="ds-stat-cards">
		<?php
		$online = 0;
		foreach ( $screens as $s ) {
			if ( 'online' === DS_Admin::screen_status( $s->ID, $heartbeats, $offline_after ) ) {
				++$online;
			}
		}
		?>
		<div class="ds-card"><span class="ds-card-num"><?php echo count( $screens ); ?></span><span><?php esc_html_e( 'Total Screens', 'digital-signage' ); ?></span></div>
		<div class="ds-card"><span class="ds-card-num ds-online"><?php echo esc_html( $online ); ?></span><span><?php esc_html_e( 'Online', 'digital-signage' ); ?></span></div>
		<div class="ds-card"><span class="ds-card-num ds-offline"><?php echo esc_html( count( $screens ) - $online ); ?></span><span><?php esc_html_e( 'Offline / Unknown', 'digital-signage' ); ?></span></div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ds_bulk_assign_channel" />
		<?php wp_nonce_field( 'ds_bulk_assign_channel' ); ?>

		<table class="widefat striped ds-screens-table">
			<thead>
				<tr>
					<th></th>
					<th><?php esc_html_e( 'Screen', 'digital-signage' ); ?></th>
					<th><?php esc_html_e( 'Status', 'digital-signage' ); ?></th>
					<th><?php esc_html_e( 'Channel', 'digital-signage' ); ?></th>
					<th><?php esc_html_e( 'Orientation', 'digital-signage' ); ?></th>
					<th><?php esc_html_e( 'Resolution', 'digital-signage' ); ?></th>
					<th><?php esc_html_e( 'IP', 'digital-signage' ); ?></th>
					<th><?php esc_html_e( 'Last Heartbeat', 'digital-signage' ); ?></th>
					<th><?php esc_html_e( 'Player URL', 'digital-signage' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $screens as $screen ) : ?>
					<?php
					$status      = DS_Admin::screen_status( $screen->ID, $heartbeats, $offline_after );
					$hb          = $heartbeats[ $screen->ID ] ?? null;
					$channel_id  = get_post_meta( $screen->ID, 'ds_channel_id', true );
					$orientation = get_post_meta( $screen->ID, 'ds_orientation', true ) ?: 'landscape';
					$token       = get_post_meta( $screen->ID, 'ds_pairing_token', true );
					?>
					<tr>
						<td><input type="checkbox" name="screen_ids[]" value="<?php echo esc_attr( $screen->ID ); ?>" /></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $screen->ID ) ); ?>"><?php echo esc_html( $screen->post_title ); ?></a></td>
						<td><span class="ds-badge ds-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
						<td><?php echo $channel_id ? esc_html( get_the_title( $channel_id ) ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( $orientation ); ?></td>
						<td><?php echo $hb ? esc_html( $hb->resolution ) : '&mdash;'; ?></td>
						<td><?php echo $hb ? esc_html( $hb->ip_address ) : '&mdash;'; ?></td>
						<td><?php echo $hb ? esc_html( human_time_diff( strtotime( $hb->last_seen . ' UTC' ) ) . ' ago' ) : esc_html__( 'never', 'digital-signage' ); ?></td>
						<td>
							<?php if ( $token ) : ?>
								<a href="<?php echo esc_url( home_url( '/signage/play/' . $token . '/' ) ); ?>" target="_blank">↗</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $screens ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No screens paired yet.', 'digital-signage' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<p class="ds-bulk-bar">
			<select name="channel_id">
				<option value="0"><?php esc_html_e( '— Assign channel to selected —', 'digital-signage' ); ?></option>
				<?php foreach ( get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) ) as $ch ) : ?>
					<option value="<?php echo esc_attr( $ch->ID ); ?>"><?php echo esc_html( $ch->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Apply to Selected', 'digital-signage' ); ?></button>
		</p>
	</form>
</div>
