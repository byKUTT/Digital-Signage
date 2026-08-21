<?php
/**
 * @var int          $id
 * @var WP_Post|null $screen
 * @var int          $channel_id
 * @var string       $orientation
 * @var string       $token
 * @var WP_Post[]    $channels
 * @var WP_Term[]    $locations
 * @var int[]        $current_loc
 * @var object|null  $heartbeat
 * @var array|null   $device  Decoded device_info JSON — only present for hardware
 *                             running raspberry-pi-kiosk/ds-agent.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );
$status        = ( $heartbeat && ( time() - strtotime( $heartbeat->last_seen . ' UTC' ) ) <= $offline_after ) ? 'online' : ( $heartbeat ? 'offline' : 'never' );
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screens' ) ); ?>">&larr; <?php esc_html_e( 'Screens', 'digital-signage' ); ?></a></p>
			<h1><?php echo $id ? esc_html( $screen->post_title ) : esc_html__( 'New Screen', 'digital-signage' ); ?></h1>
		</div>
		<?php if ( $id ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this screen?', 'digital-signage' ) ); ?>');">
				<input type="hidden" name="action" value="ds_delete_screen" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
				<?php wp_nonce_field( 'ds_delete_screen' ); ?>
				<button type="submit" class="ds-btn ds-btn-danger"><?php esc_html_e( 'Delete', 'digital-signage' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<?php if ( isset( $_GET['ds_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Screen saved.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_paired'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Screen paired! Finish setting it up below.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_device_queued'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Command queued — it will be applied the next time this device checks in.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_error'] ) && 'wifi_ssid' === $_GET['ds_error'] ) : ?>
		<div class="ds-notice ds-notice-error"><?php esc_html_e( 'Enter a network name (SSID) to connect to.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<div class="ds-two-col">
		<div class="ds-panel">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_save_screen" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
				<?php wp_nonce_field( 'ds_save_screen' ); ?>

				<div class="ds-field">
					<label for="title"><?php esc_html_e( 'Screen name', 'digital-signage' ); ?></label>
					<input type="text" id="title" name="title" class="ds-input" value="<?php echo $id ? esc_attr( $screen->post_title ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Lobby TV', 'digital-signage' ); ?>" required />
				</div>

				<div class="ds-field">
					<label for="channel_id"><?php esc_html_e( 'Assigned channel', 'digital-signage' ); ?></label>
					<select id="channel_id" name="channel_id" class="ds-input">
						<option value="0"><?php esc_html_e( '— None —', 'digital-signage' ); ?></option>
						<?php foreach ( $channels as $c ) : ?>
							<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $channel_id, $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="ds-hint"><?php esc_html_e( 'Default channel; a Schedule can override this at specific times.', 'digital-signage' ); ?></span>
				</div>

				<div class="ds-field">
					<label for="orientation"><?php esc_html_e( 'Orientation', 'digital-signage' ); ?></label>
					<select id="orientation" name="orientation" class="ds-input">
						<option value="landscape" <?php selected( $orientation, 'landscape' ); ?>><?php esc_html_e( 'Landscape', 'digital-signage' ); ?></option>
						<option value="portrait" <?php selected( $orientation, 'portrait' ); ?>><?php esc_html_e( 'Portrait', 'digital-signage' ); ?></option>
						<option value="auto" <?php selected( $orientation, 'auto' ); ?>><?php esc_html_e( 'Auto-detect', 'digital-signage' ); ?></option>
					</select>
				</div>

				<div class="ds-field">
					<label for="location"><?php esc_html_e( 'Location', 'digital-signage' ); ?></label>
					<select id="location" name="location" class="ds-input">
						<option value="0"><?php esc_html_e( '— None —', 'digital-signage' ); ?></option>
						<?php foreach ( $locations as $loc ) : ?>
							<option value="<?php echo esc_attr( $loc->term_id ); ?>" <?php selected( in_array( $loc->term_id, $current_loc, true ) ); ?>><?php echo esc_html( $loc->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<button type="submit" class="ds-btn ds-btn-primary"><?php echo $id ? esc_html__( 'Save Changes', 'digital-signage' ) : esc_html__( 'Create Screen', 'digital-signage' ); ?></button>
			</form>
		</div>

		<?php if ( $id ) : ?>
			<div class="ds-panel">
				<h3><?php esc_html_e( 'Live status', 'digital-signage' ); ?></h3>
				<p><span class="ds-badge ds-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></p>
				<?php if ( $heartbeat ) : ?>
					<table class="ds-kv-table">
						<tr><th><?php esc_html_e( 'Last seen', 'digital-signage' ); ?></th><td><?php echo esc_html( human_time_diff( strtotime( $heartbeat->last_seen . ' UTC' ) ) . ' ago' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'IP address', 'digital-signage' ); ?></th><td><?php echo esc_html( $heartbeat->ip_address ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Resolution', 'digital-signage' ); ?></th><td><?php echo esc_html( $heartbeat->resolution ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Orientation reported', 'digital-signage' ); ?></th><td><?php echo esc_html( $heartbeat->orientation ); ?></td></tr>
					</table>
				<?php else : ?>
					<p class="ds-hint"><?php esc_html_e( 'This screen has not reported in yet.', 'digital-signage' ); ?></p>
				<?php endif; ?>

				<?php if ( $token ) : ?>
					<h3><?php esc_html_e( 'Player URL', 'digital-signage' ); ?></h3>
					<div class="ds-url-box">
						<code><?php echo esc_html( home_url( '/signage/play/' . $token . '/' ) ); ?></code>
						<a href="<?php echo esc_url( home_url( '/signage/play/' . $token . '/' ) ); ?>" target="_blank" class="ds-btn ds-btn-small"><?php esc_html_e( 'Open', 'digital-signage' ); ?></a>
					</div>
				<?php else : ?>
					<div class="ds-notice"><?php esc_html_e( 'Not paired yet.', 'digital-signage' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-pairing' ) ); ?>"><?php esc_html_e( 'Pair this screen', 'digital-signage' ); ?></a></div>
				<?php endif; ?>

				<h3><?php esc_html_e( 'Remote control', 'digital-signage' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ds-remote-actions">
					<input type="hidden" name="action" value="ds_remote_action" />
					<input type="hidden" name="screen_id" value="<?php echo esc_attr( $id ); ?>" />
					<?php wp_nonce_field( 'ds_remote_action' ); ?>
					<button type="submit" class="ds-btn" name="remote_action" value="refresh"><?php esc_html_e( 'Refresh Playlist Now', 'digital-signage' ); ?></button>
					<button type="submit" class="ds-btn" name="remote_action" value="reload"><?php esc_html_e( 'Reload Browser Session', 'digital-signage' ); ?></button>
				</form>
				<p class="ds-hint"><?php esc_html_e( 'Delivered next time the screen polls for updates.', 'digital-signage' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $id && $device ) : ?>
		<div class="ds-panel">
			<div class="ds-panel-header">
				<h2><?php esc_html_e( 'Device', 'digital-signage' ); ?></h2>
				<span class="ds-badge ds-badge-online"><?php esc_html_e( 'Agent connected', 'digital-signage' ); ?></span>
			</div>
			<p class="ds-app-subtitle"><?php esc_html_e( 'This screen is running the Raspberry Pi agent — WiFi, screen rotation and the device itself can be managed from here, no physical access needed.', 'digital-signage' ); ?></p>

			<table class="ds-kv-table">
				<?php if ( ! empty( $device['wifi_ssid'] ) ) : ?>
					<tr><th><?php esc_html_e( 'WiFi network', 'digital-signage' ); ?></th><td><?php echo esc_html( $device['wifi_ssid'] ); ?><?php echo isset( $device['wifi_signal'] ) ? ' (' . esc_html( $device['wifi_signal'] ) . '%)' : ''; ?></td></tr>
				<?php endif; ?>
				<?php if ( isset( $device['cpu_temp_c'] ) ) : ?>
					<tr><th><?php esc_html_e( 'CPU temperature', 'digital-signage' ); ?></th><td><?php echo esc_html( $device['cpu_temp_c'] ); ?>°C</td></tr>
				<?php endif; ?>
				<?php if ( isset( $device['disk_free_mb'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Free disk space', 'digital-signage' ); ?></th><td><?php echo esc_html( round( $device['disk_free_mb'] / 1024, 1 ) ); ?> GB</td></tr>
				<?php endif; ?>
				<?php if ( ! empty( $device['rotation'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Screen rotation', 'digital-signage' ); ?></th><td><?php echo esc_html( ucfirst( $device['rotation'] ) ); ?></td></tr>
				<?php endif; ?>
				<?php if ( ! empty( $device['agent_version'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Agent version', 'digital-signage' ); ?></th><td><?php echo esc_html( $device['agent_version'] ); ?></td></tr>
				<?php endif; ?>
			</table>

			<div class="ds-two-col">
				<div>
					<h3><?php esc_html_e( 'WiFi', 'digital-signage' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ds_device_command" />
						<input type="hidden" name="command_type" value="wifi" />
						<input type="hidden" name="screen_id" value="<?php echo esc_attr( $id ); ?>" />
						<?php wp_nonce_field( 'ds_device_command' ); ?>
						<div class="ds-field">
							<label for="wifi_ssid"><?php esc_html_e( 'Network name (SSID)', 'digital-signage' ); ?></label>
							<input type="text" id="wifi_ssid" name="wifi_ssid" class="ds-input" />
						</div>
						<div class="ds-field">
							<label for="wifi_password"><?php esc_html_e( 'Password', 'digital-signage' ); ?></label>
							<input type="password" id="wifi_password" name="wifi_password" class="ds-input" autocomplete="new-password" />
							<span class="ds-hint"><?php esc_html_e( 'Sent once to the device and applied — never stored on the device or in WordPress after that.', 'digital-signage' ); ?></span>
						</div>
						<button type="submit" class="ds-btn"><?php esc_html_e( 'Connect to this Network', 'digital-signage' ); ?></button>
					</form>
				</div>

				<div>
					<h3><?php esc_html_e( 'Screen rotation', 'digital-signage' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ds_device_command" />
						<input type="hidden" name="command_type" value="rotation" />
						<input type="hidden" name="screen_id" value="<?php echo esc_attr( $id ); ?>" />
						<?php wp_nonce_field( 'ds_device_command' ); ?>
						<div class="ds-field">
							<select name="rotation" class="ds-input">
								<option value="normal"><?php esc_html_e( 'Normal', 'digital-signage' ); ?></option>
								<option value="left"><?php esc_html_e( 'Rotate left (90°)', 'digital-signage' ); ?></option>
								<option value="right"><?php esc_html_e( 'Rotate right (90°)', 'digital-signage' ); ?></option>
								<option value="inverted"><?php esc_html_e( 'Upside down (180°)', 'digital-signage' ); ?></option>
							</select>
						</div>
						<button type="submit" class="ds-btn"><?php esc_html_e( 'Apply Rotation', 'digital-signage' ); ?></button>
					</form>

					<h3><?php esc_html_e( 'Device control', 'digital-signage' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ds-remote-actions">
						<input type="hidden" name="action" value="ds_device_command" />
						<input type="hidden" name="screen_id" value="<?php echo esc_attr( $id ); ?>" />
						<?php wp_nonce_field( 'ds_device_command' ); ?>
						<button type="submit" class="ds-btn" name="command_type" value="restart_browser"><?php esc_html_e( 'Restart Browser', 'digital-signage' ); ?></button>
						<button type="submit" class="ds-btn" name="command_type" value="check_updates"><?php esc_html_e( 'Check for Updates', 'digital-signage' ); ?></button>
						<button type="submit" class="ds-btn ds-btn-danger" name="command_type" value="reboot" onclick="return confirm('<?php echo esc_js( __( 'Reboot this device now?', 'digital-signage' ) ); ?>');"><?php esc_html_e( 'Reboot Device', 'digital-signage' ); ?></button>
					</form>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
