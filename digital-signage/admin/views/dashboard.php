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
			<h2><?php esc_html_e( 'How it works', 'digital-signage' ); ?></h2>
			<div class="ds-flow-diagram">
				<div class="ds-flow-step">
					<span class="ds-flow-icon"><?php echo DS_Icons::icon( 'channel' ); // phpcs:ignore ?></span>
					<strong><?php esc_html_e( '1. Build a Channel', 'digital-signage' ); ?></strong>
					<span><?php esc_html_e( 'A named playlist of slides — images, videos, webpages, widgets.', 'digital-signage' ); ?></span>
				</div>
				<span class="ds-flow-arrow"><?php echo DS_Icons::icon( 'arrow' ); // phpcs:ignore ?></span>
				<div class="ds-flow-step">
					<span class="ds-flow-icon"><?php echo DS_Icons::icon( 'screen' ); // phpcs:ignore ?></span>
					<strong><?php esc_html_e( '2. Pair a Screen', 'digital-signage' ); ?></strong>
					<span><?php esc_html_e( 'Any TV/tablet browser — scan a QR code or enter a short code.', 'digital-signage' ); ?></span>
				</div>
				<span class="ds-flow-arrow"><?php echo DS_Icons::icon( 'arrow' ); // phpcs:ignore ?></span>
				<div class="ds-flow-step">
					<span class="ds-flow-icon"><?php echo DS_Icons::icon( 'schedule' ); // phpcs:ignore ?></span>
					<strong><?php esc_html_e( '3. Schedule (optional)', 'digital-signage' ); ?></strong>
					<span><?php esc_html_e( 'Assign a channel directly, or set day/time rules and holiday overrides.', 'digital-signage' ); ?></span>
				</div>
			</div>
			<ol>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channel-edit' ) ); ?>"><?php esc_html_e( 'Add a Channel →', 'digital-signage' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>"><?php esc_html_e( 'Pair a Screen →', 'digital-signage' ); ?></a></li>
			</ol>
		</div>
	<?php endif; ?>

	<p class="ds-overview-line"><?php echo esc_html( sprintf( __( '%1$d screens online · %2$d screens total · %3$d channels', 'digital-signage' ), $online, count( $screens ), $channel_count ) ); ?></p>

	<?php if ( $controllers ) : ?>
		<div class="ds-panel">
			<div class="ds-panel-header">
				<h2><?php esc_html_e( 'Controller computers', 'digital-signage' ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-controllers' ) ); ?>" class="ds-btn ds-btn-small"><?php esc_html_e( 'Manage Fleet', 'digital-signage' ); ?></a>
			</div>
			<div class="ds-controller-grid">
				<?php foreach ( array_slice( $controllers, 0, 6 ) as $controller ) : ?>
					<?php
					$displays          = $controller_displays[ $controller->id ] ?? array();
					$controller_online = $controller->last_seen && ( time() - strtotime( $controller->last_seen . ' UTC' ) ) <= $offline_after;
					$telemetry         = json_decode( (string) $controller->telemetry, true );
					$telemetry         = is_array( $telemetry ) ? $telemetry : array();
					$connected_displays = array_filter( $displays, function ( $display ) { return ! empty( $display->is_connected ); } );
					?>
					<a class="ds-controller-card" data-controller-id="<?php echo esc_attr( $controller->id ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ds-controller-edit&id=' . absint( $controller->id ) ) ); ?>">
						<div class="ds-controller-card-head">
							<strong><?php echo esc_html( $controller->name ?: $controller->hostname ?: __( 'Unpaired controller', 'digital-signage' ) ); ?></strong>
							<span class="ds-badge ds-badge-<?php echo $controller_online ? 'online' : 'offline'; ?>"><?php echo esc_html( $controller_online ? __( 'Online', 'digital-signage' ) : __( 'Offline', 'digital-signage' ) ); ?></span>
						</div>
						<p><?php echo esc_html( sprintf( _n( '%d display connected right now', '%d displays connected right now', count( $connected_displays ), 'digital-signage' ), count( $connected_displays ) ) ); ?></p>
						<ul>
							<?php foreach ( $displays as $display ) : ?>
								<?php $display_hb = $display->screen_id ? ( $heartbeats[ $display->screen_id ] ?? null ) : null; $display_online = $display_hb && ( time() - strtotime( $display_hb->last_seen . ' UTC' ) ) <= $offline_after; ?>
								<li><span><?php echo esc_html( $display->label ?: $display->connector ?: $display->output_key ); ?></span><strong><?php echo esc_html( $display->screen_id ? get_the_title( $display->screen_id ) . ' · ' . ( $display_online ? __( 'running', 'digital-signage' ) : __( 'not reporting', 'digital-signage' ) ) : __( 'Unassigned', 'digital-signage' ) ); ?></strong></li>
							<?php endforeach; ?>
						</ul>
						<div class="ds-controller-metrics">
							<span><?php echo esc_html( ucfirst( $controller->platform ) ); ?></span>
							<span><?php echo esc_html( $controller->software_version ?: '—' ); ?></span>
							<span><?php echo isset( $telemetry['cpu_load_percent'] ) ? esc_html( round( (float) $telemetry['cpu_load_percent'] ) . '% CPU' ) : '—'; ?></span>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="ds-panel">
		<div class="ds-panel-header">
			<h2><?php esc_html_e( 'Screens', 'digital-signage' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screens' ) ); ?>" class="ds-btn ds-btn-small"><?php esc_html_e( 'Manage All', 'digital-signage' ); ?></a>
		</div>

		<?php if ( $screens ) : ?>
			<div class="ds-table-wrap">
				<table class="ds-table ds-screens-table">
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

	<details class="ds-disclosure"><summary><?php esc_html_e( 'More tools', 'digital-signage' ); ?></summary><div class="ds-disclosure-body ds-quick-links">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-calendar' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Calendar View', 'digital-signage' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-analytics' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Proof of Play', 'digital-signage' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-import-export' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Import / Export', 'digital-signage' ); ?></a>
	</div></details>

	<p class="ds-app-footer"><?php esc_html_e( 'Digital Signage', 'digital-signage' ); ?> v<?php echo esc_html( DS_VERSION ); ?></p>
</div>
