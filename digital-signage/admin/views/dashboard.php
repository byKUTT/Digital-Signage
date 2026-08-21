<?php
/**
 * Overview: at-a-glance screen status plus quick actions into the rest of the app.
 *
 * @var WP_Post[] $screens
 * @var object[]  $heartbeats keyed by screen_id
 * @var int       $offline_after
 * @var int       $channel_count
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$online = 0;
foreach ( $screens as $s ) {
	$hb = $heartbeats[ $s->ID ] ?? null;
	if ( $hb && ( time() - strtotime( $hb->last_seen . ' UTC' ) ) <= $offline_after ) {
		++$online;
	}
}
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<h1><?php esc_html_e( 'Digital Signage', 'digital-signage' ); ?></h1>
			<p class="ds-app-subtitle"><?php esc_html_e( 'Overview of your channels and screens.', 'digital-signage' ); ?></p>
		</div>
		<div class="ds-app-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channel-edit' ) ); ?>" class="ds-btn"><?php esc_html_e( 'New Channel', 'digital-signage' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'Pair a New Screen', 'digital-signage' ); ?></a>
		</div>
	</div>

	<?php if ( ! $screens && ! $channel_count ) : ?>
		<div class="ds-quickstart">
			<h2><?php esc_html_e( 'Get started in 3 steps', 'digital-signage' ); ?></h2>
			<ol>
				<li><strong><?php esc_html_e( 'Create a Channel', 'digital-signage' ); ?></strong> — <?php esc_html_e( 'a named playlist, e.g. "Lobby Menu".', 'digital-signage' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channel-edit' ) ); ?>"><?php esc_html_e( 'Add a Channel →', 'digital-signage' ); ?></a></li>
				<li><strong><?php esc_html_e( 'Add Slides to it', 'digital-signage' ); ?></strong> — <?php esc_html_e( 'images, videos, webpages, or widgets, right from the channel\'s page.', 'digital-signage' ); ?></li>
				<li><strong><?php esc_html_e( 'Pair a Screen', 'digital-signage' ); ?></strong> — <?php esc_html_e( 'open the player URL on the TV/tablet, then confirm its code here.', 'digital-signage' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>"><?php esc_html_e( 'Pair a Screen →', 'digital-signage' ); ?></a></li>
			</ol>
		</div>
	<?php endif; ?>

	<div class="ds-card-grid ds-stat-grid">
		<div class="ds-stat-card"><span class="ds-stat-num"><?php echo esc_html( $channel_count ); ?></span><span class="ds-stat-label"><?php esc_html_e( 'Channels', 'digital-signage' ); ?></span></div>
		<div class="ds-stat-card"><span class="ds-stat-num"><?php echo count( $screens ); ?></span><span class="ds-stat-label"><?php esc_html_e( 'Screens', 'digital-signage' ); ?></span></div>
		<div class="ds-stat-card"><span class="ds-stat-num ds-text-online"><?php echo esc_html( $online ); ?></span><span class="ds-stat-label"><?php esc_html_e( 'Online', 'digital-signage' ); ?></span></div>
		<div class="ds-stat-card"><span class="ds-stat-num ds-text-offline"><?php echo esc_html( count( $screens ) - $online ); ?></span><span class="ds-stat-label"><?php esc_html_e( 'Offline / Unknown', 'digital-signage' ); ?></span></div>
	</div>

	<div class="ds-panel">
		<div class="ds-panel-header">
			<h2><?php esc_html_e( 'Screens', 'digital-signage' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screens' ) ); ?>" class="ds-btn ds-btn-small"><?php esc_html_e( 'Manage All', 'digital-signage' ); ?></a>
		</div>

		<?php if ( $screens ) : ?>
			<div class="ds-table-wrap">
				<table class="ds-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Screen', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Status', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Channel', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Last heartbeat', 'digital-signage' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $screens, 0, 8 ) as $screen ) : ?>
							<?php
							$hb         = $heartbeats[ $screen->ID ] ?? null;
							$status     = ( $hb && ( time() - strtotime( $hb->last_seen . ' UTC' ) ) <= $offline_after ) ? 'online' : ( $hb ? 'offline' : 'never' );
							$channel_id = get_post_meta( $screen->ID, 'ds_channel_id', true );
							?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen->ID ) ); ?>"><?php echo esc_html( $screen->post_title ); ?></a></td>
								<td><span class="ds-badge ds-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
								<td><?php echo $channel_id ? esc_html( get_the_title( $channel_id ) ) : '—'; ?></td>
								<td><?php echo $hb ? esc_html( human_time_diff( strtotime( $hb->last_seen . ' UTC' ) ) . ' ago' ) : esc_html__( 'never', 'digital-signage' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="ds-hint"><?php esc_html_e( 'No screens paired yet.', 'digital-signage' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="ds-quick-links">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-calendar' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Calendar View', 'digital-signage' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-analytics' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Proof of Play', 'digital-signage' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-import-export' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Import / Export', 'digital-signage' ); ?></a>
	</div>

	<p class="ds-app-footer"><?php esc_html_e( 'Digital Signage CMS', 'digital-signage' ); ?> v<?php echo esc_html( DS_VERSION ); ?> &middot; <?php esc_html_e( 'by', 'digital-signage' ); ?> <a href="https://github.com/byKUTT" target="_blank" rel="noopener">byKUTT</a></p>
</div>
