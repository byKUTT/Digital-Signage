<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<h1><?php esc_html_e( 'Controllers', 'digital-signage' ); ?></h1>
			<p class="ds-app-subtitle"><?php esc_html_e( 'Linux and Windows computers that drive one or more independently assigned displays.', 'digital-signage' ); ?></p>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'Pair Controller', 'digital-signage' ); ?></a>
	</div>

	<?php if ( $controllers ) : ?>
		<div class="ds-controller-grid">
			<?php foreach ( $controllers as $controller ) :
				$displays  = $display_counts[ $controller->id ] ?? array();
				$connected = count( array_filter( $displays, function ( $display ) { return ! empty( $display->is_connected ); } ) );
				$online    = $controller->last_seen && ( time() - strtotime( $controller->last_seen . ' UTC' ) ) <= DS_Controllers::ONLINE_SECONDS;
				$telemetry = json_decode( (string) $controller->telemetry, true );
				?>
				<a class="ds-controller-card" data-controller-id="<?php echo esc_attr( $controller->id ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ds-controller-edit&id=' . $controller->id ) ); ?>">
					<div class="ds-controller-card-head">
						<div><strong><?php echo esc_html( $controller->name ? $controller->name : $controller->hostname ); ?></strong><span><?php echo esc_html( ucfirst( $controller->platform ) . ' · ' . $controller->hostname ); ?></span></div>
						<span class="ds-badge ds-badge-<?php echo $online ? 'online' : 'offline'; ?>"><?php echo $online ? esc_html__( 'Online', 'digital-signage' ) : esc_html__( 'Offline', 'digital-signage' ); ?></span>
					</div>
					<div class="ds-controller-metrics">
						<span><strong><?php echo esc_html( $connected ); ?></strong><?php esc_html_e( 'Connected displays', 'digital-signage' ); ?></span>
						<span><strong><?php echo esc_html( count( $displays ) ); ?></strong><?php esc_html_e( 'Configured displays', 'digital-signage' ); ?></span>
						<span><strong><?php echo esc_html( $telemetry['cpu_load_percent'] ?? '—' ); ?><?php echo isset( $telemetry['cpu_load_percent'] ) ? '%' : ''; ?></strong><?php esc_html_e( 'CPU load', 'digital-signage' ); ?></span>
						<span><strong><?php echo esc_html( $controller->software_version ?: '—' ); ?></strong><?php esc_html_e( 'Software', 'digital-signage' ); ?></span>
					</div>
					<p class="ds-hint"><?php echo $controller->last_seen ? esc_html( sprintf( __( 'Last contact %s ago', 'digital-signage' ), human_time_diff( strtotime( $controller->last_seen . ' UTC' ) ) ) ) : esc_html__( 'Never contacted', 'digital-signage' ); ?></p>
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="ds-empty-state">
			<h2><?php esc_html_e( 'No controller PCs yet', 'digital-signage' ); ?></h2>
			<p><?php esc_html_e( 'Install the Linux or Windows controller package. A code will appear on every connected monitor; enter that code on the Pairing page.', 'digital-signage' ); ?></p>
		</div>
	<?php endif; ?>
</div>
