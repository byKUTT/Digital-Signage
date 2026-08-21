<?php
/** @var object|null $hb */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );
$status        = ( $hb && ( time() - strtotime( $hb->last_seen . ' UTC' ) ) <= $offline_after ) ? 'online' : ( $hb ? 'offline' : 'never' );
?>
<p><span class="ds-badge ds-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></p>
<?php if ( $hb ) : ?>
	<table class="ds-status-table">
		<tr><th><?php esc_html_e( 'Last seen', 'digital-signage' ); ?></th><td><?php echo esc_html( human_time_diff( strtotime( $hb->last_seen . ' UTC' ) ) . ' ago' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'IP address', 'digital-signage' ); ?></th><td><?php echo esc_html( $hb->ip_address ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Resolution', 'digital-signage' ); ?></th><td><?php echo esc_html( $hb->resolution ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Orientation', 'digital-signage' ); ?></th><td><?php echo esc_html( $hb->orientation ); ?></td></tr>
		<tr><th><?php esc_html_e( 'App version', 'digital-signage' ); ?></th><td><?php echo esc_html( $hb->app_version ); ?></td></tr>
	</table>
<?php else : ?>
	<p><?php esc_html_e( 'This screen has not reported in yet.', 'digital-signage' ); ?></p>
<?php endif; ?>
