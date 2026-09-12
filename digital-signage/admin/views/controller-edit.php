<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$online = $controller->last_seen && ( time() - strtotime( $controller->last_seen . ' UTC' ) ) <= DS_Controllers::ONLINE_SECONDS;
$telemetry_labels = array(
	'cpu_model' => __( 'CPU', 'digital-signage' ), 'cpu_cores' => __( 'CPU cores', 'digital-signage' ), 'cpu_load_percent' => __( 'CPU load %', 'digital-signage' ),
	'cpu_temp_c' => __( 'CPU temperature °C', 'digital-signage' ), 'memory_total_mb' => __( 'Memory total MB', 'digital-signage' ), 'memory_free_mb' => __( 'Memory free MB', 'digital-signage' ),
	'disk_total_mb' => __( 'Disk total MB', 'digital-signage' ), 'disk_free_mb' => __( 'Disk free MB', 'digital-signage' ), 'uptime_seconds' => __( 'Uptime seconds', 'digital-signage' ),
	'gpu' => __( 'Graphics', 'digital-signage' ), 'browser' => __( 'Browser', 'digital-signage' ), 'architecture' => __( 'Architecture', 'digital-signage' ), 'kernel' => __( 'Kernel', 'digital-signage' ),
	'network' => __( 'Network', 'digital-signage' ), 'browser_running' => __( 'Players launched', 'digital-signage' ), 'screens_paused' => __( 'Desktop mode active', 'digital-signage' ),
	'cursor_hidden' => __( 'Cursor hidden', 'digital-signage' ), 'cursor_error' => __( 'Cursor control', 'digital-signage' ),
	'os_update_supported' => __( 'OS updates supported', 'digital-signage' ),
	'connected_outputs' => __( 'Connected outputs', 'digital-signage' ), 'player_processes' => __( 'Browser player processes', 'digital-signage' ),
	'update_status' => __( 'Software update status', 'digital-signage' ), 'update_result' => __( 'Software update result', 'digital-signage' ),
	'automatic_reboot_enabled' => __( 'Automatic computer reboot', 'digital-signage' ),
);
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-controllers' ) ); ?>">&larr; <?php esc_html_e( 'Controllers', 'digital-signage' ); ?></a></p>
			<h1><?php echo esc_html( $controller->name ? $controller->name : $controller->hostname ); ?></h1>
			<p class="ds-app-subtitle"><?php echo esc_html( ucfirst( $controller->platform ) . ' · ' . $controller->hostname . ' · ' . $controller->os_version ); ?></p>
		</div>
		<span class="ds-badge ds-badge-<?php echo $online ? 'online' : 'offline'; ?>"><?php echo $online ? esc_html__( 'Online', 'digital-signage' ) : esc_html__( 'Offline', 'digital-signage' ); ?></span>
	</div>
	<?php if ( isset( $_GET['ds_command_queued'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Command queued. The controller will run it on its next check-in.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_saved'] ) || isset( $_GET['ds_schedule_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Controller changes saved.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_error'] ) ) : ?>
		<?php
		$error_code = sanitize_key( wp_unslash( $_GET['ds_error'] ) );
		$error_messages = array(
			'update_asset'    => __( 'The update package could not be verified. Try again or update this controller from its terminal.', 'digital-signage' ),
			'update_newer'    => __( 'This controller reports a newer version than the available update.', 'digital-signage' ),
			'update_active'   => __( 'An update for this controller is already queued or running.', 'digital-signage' ),
			'command_database' => __( 'WordPress could not save the command. Deactivate and reactivate the plugin once to repair its database tables.', 'digital-signage' ),
			'ds_command_type' => __( 'That controller command is not supported.', 'digital-signage' ),
		);
		$error_message = $error_messages[ $error_code ] ?? __( 'The controller command could not be queued. Try again and check the command history.', 'digital-signage' );
		?>
		<div class="ds-notice ds-notice-error"><?php echo esc_html( $error_message ); ?></div>
	<?php endif; ?>

	<div class="ds-controller-summary">
		<span><strong><?php echo esc_html( count( array_filter( $displays, function ( $display ) { return ! empty( $display->is_connected ); } ) ) ); ?></strong><?php esc_html_e( 'Displays connected', 'digital-signage' ); ?></span>
		<span><strong><?php echo esc_html( $controller->agent_version ?: '—' ); ?></strong><?php esc_html_e( 'Agent version', 'digital-signage' ); ?></span>
		<span><strong><?php echo esc_html( $controller->software_version ?: '—' ); ?></strong><?php esc_html_e( 'Software version', 'digital-signage' ); ?></span>
		<span><strong><?php echo esc_html( $controller->ip_address ?: '—' ); ?></strong><?php esc_html_e( 'IP address', 'digital-signage' ); ?></span>
	</div>

	<div class="ds-panel">
		<h2><?php esc_html_e( 'Displays and Screen assignments', 'digital-signage' ); ?></h2>
		<p class="ds-hint"><?php esc_html_e( 'Every physical output launches its own isolated player. Assign a different Screen—and therefore a different channel or schedule—to each output.', 'digital-signage' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ds_save_controller" />
			<input type="hidden" name="controller_id" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( 'ds_save_controller' ); ?>
			<div class="ds-field"><label for="controller_name"><?php esc_html_e( 'Controller name', 'digital-signage' ); ?></label><input class="ds-input" id="controller_name" name="controller_name" value="<?php echo esc_attr( $controller->name ); ?>" required /></div>
			<div class="ds-table-wrap"><table class="ds-table"><thead><tr><th><?php esc_html_e( 'Output', 'digital-signage' ); ?></th><th><?php esc_html_e( 'State', 'digital-signage' ); ?></th><th><?php esc_html_e( 'Geometry', 'digital-signage' ); ?></th><th><?php esc_html_e( 'Assigned Screen', 'digital-signage' ); ?></th><th><?php esc_html_e( 'Current channel', 'digital-signage' ); ?></th></tr></thead><tbody>
			<?php foreach ( $displays as $display ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $display->label ?: $display->connector ); ?></strong><br><code><?php echo esc_html( $display->connector . ' · ' . $display->output_key ); ?></code></td>
					<td><span class="ds-badge ds-badge-<?php echo $display->is_connected ? 'online' : 'offline'; ?>"><?php echo $display->is_connected ? esc_html__( 'Connected', 'digital-signage' ) : esc_html__( 'Disconnected', 'digital-signage' ); ?></span></td>
					<td><?php echo esc_html( $display->resolution . ( $display->geometry ? ' · ' . $display->geometry : '' ) ); ?><?php echo $display->is_primary ? '<br><small>' . esc_html__( 'Primary', 'digital-signage' ) . '</small>' : ''; ?></td>
					<td><select class="ds-input" name="display_screen_ids[<?php echo esc_attr( $display->id ); ?>]"><option value="0"><?php esc_html_e( '— Unassigned —', 'digital-signage' ); ?></option><?php foreach ( $screens as $screen ) : ?><option value="<?php echo esc_attr( $screen->ID ); ?>" <?php selected( (int) $display->screen_id, (int) $screen->ID ); ?>><?php echo esc_html( $screen->post_title ); ?></option><?php endforeach; ?></select></td>
					<td><?php if ( $display->screen_id ) : $channel_id = absint( get_post_meta( $display->screen_id, 'ds_channel_id', true ) ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screen-edit&id=' . $display->screen_id ) ); ?>"><?php echo esc_html( $channel_id ? get_the_title( $channel_id ) : __( 'No channel', 'digital-signage' ) ); ?></a><?php else : ?>—<?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table></div>
			<button class="ds-btn ds-btn-primary" type="submit"><?php esc_html_e( 'Save Controller', 'digital-signage' ); ?></button>
		</form>
	</div>

	<div class="ds-panel ds-controller-primary-actions">
		<h2><?php esc_html_e( 'Kiosk controls', 'digital-signage' ); ?></h2>
		<p class="ds-hint"><?php echo ! empty( $telemetry['screens_paused'] ) ? esc_html__( 'Desktop mode is active. Start the kiosk when setup work is finished.', 'digital-signage' ) : esc_html__( 'Kiosk screens are active and the cursor should be hidden.', 'digital-signage' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ds-controller-actions">
				<input type="hidden" name="action" value="ds_controller_command" /><input type="hidden" name="controller_id" value="<?php echo esc_attr( $id ); ?>" /><?php wp_nonce_field( 'ds_controller_command' ); ?>
				<?php if ( ! empty( $telemetry['screens_paused'] ) ) : ?>
					<button class="ds-btn ds-btn-primary" name="command_type" value="start_players"><?php esc_html_e( 'Start Kiosk Screens', 'digital-signage' ); ?></button>
				<?php else : ?>
					<button class="ds-btn ds-btn-primary" name="command_type" value="stop_players" onclick="return confirm('<?php echo esc_js( __( 'Close all kiosk screens and show the desktop?', 'digital-signage' ) ); ?>');"><?php esc_html_e( 'Show Desktop', 'digital-signage' ); ?></button>
				<?php endif; ?>
				<button class="ds-btn" name="command_type" value="restart_players"><?php esc_html_e( 'Restart Screens', 'digital-signage' ); ?></button>
				<button class="ds-btn" name="command_type" value="software_update"><?php esc_html_e( 'Update Signage Software', 'digital-signage' ); ?></button>
			</form>
	</div>

	<details class="ds-disclosure">
		<summary><?php esc_html_e( 'Device details and advanced actions', 'digital-signage' ); ?></summary>
		<div class="ds-disclosure-body ds-two-col">
			<div><h2><?php esc_html_e( 'Device status', 'digital-signage' ); ?></h2><table class="ds-kv-table">
			<?php foreach ( $telemetry_labels as $key => $label ) : if ( ! array_key_exists( $key, (array) $telemetry ) ) { continue; } $value = is_bool( $telemetry[ $key ] ) ? ( $telemetry[ $key ] ? __( 'Yes', 'digital-signage' ) : __( 'No', 'digital-signage' ) ) : $telemetry[ $key ]; ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr><?php endforeach; ?>
			<tr><th><?php esc_html_e( 'Last contact', 'digital-signage' ); ?></th><td><?php echo $controller->last_seen ? esc_html( $controller->last_seen . ' UTC' ) : '—'; ?></td></tr></table></div>
			<div><h2><?php esc_html_e( 'Advanced actions', 'digital-signage' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ds-controller-actions"><input type="hidden" name="action" value="ds_controller_command" /><input type="hidden" name="controller_id" value="<?php echo esc_attr( $id ); ?>" /><?php wp_nonce_field( 'ds_controller_command' ); ?><button class="ds-btn" name="command_type" value="refresh_displays"><?php esc_html_e( 'Detect Displays', 'digital-signage' ); ?></button><?php if ( ! empty( $telemetry['os_update_supported'] ) ) : ?><button class="ds-btn" name="command_type" value="system_update"><?php esc_html_e( 'Update Ubuntu', 'digital-signage' ); ?></button><?php endif; ?><button class="ds-btn ds-btn-danger" name="command_type" value="reboot" onclick="return confirm('<?php echo esc_js( __( 'Reboot this controller?', 'digital-signage' ) ); ?>');"><?php esc_html_e( 'Reboot', 'digital-signage' ); ?></button></form></div>
		</div>
	</details>

	<details class="ds-disclosure">
		<summary><?php esc_html_e( 'Command history and logs', 'digital-signage' ); ?></summary>
		<div class="ds-disclosure-body"><h2><?php esc_html_e( 'Command history', 'digital-signage' ); ?></h2>
		<div class="ds-table-wrap"><table class="ds-table"><thead><tr><th><?php esc_html_e( 'Command', 'digital-signage' ); ?></th><th><?php esc_html_e( 'Status', 'digital-signage' ); ?></th><th><?php esc_html_e( 'Requested', 'digital-signage' ); ?></th><th><?php esc_html_e( 'Result', 'digital-signage' ); ?></th></tr></thead><tbody><?php foreach ( $commands as $command ) : ?><tr><td><?php echo esc_html( str_replace( '_', ' ', ucfirst( $command->command_type ) ) ); ?></td><td><span class="ds-command-status ds-command-<?php echo esc_attr( $command->status ); ?>"><?php echo esc_html( ucfirst( $command->status ) ); ?></span></td><td><?php echo esc_html( $command->created_at . ' UTC' ); ?></td><td><?php echo esc_html( $command->result ?: '—' ); ?></td></tr><?php endforeach; ?><?php if ( ! $commands ) : ?><tr><td colspan="4"><?php esc_html_e( 'No commands sent yet.', 'digital-signage' ); ?></td></tr><?php endif; ?></tbody></table></div>
		<?php if ( ! empty( $telemetry['recent_log'] ) ) : ?><h2><?php esc_html_e( 'Recent activity', 'digital-signage' ); ?></h2><pre class="ds-device-log"><?php echo esc_html( implode( "\n", array_reverse( $telemetry['recent_log'] ) ) ); ?></pre><?php endif; ?></div>
	</details>
</div>
