<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = DS_Settings::get_all();
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<h1><?php esc_html_e( 'Settings', 'digital-signage' ); ?></h1>
			<p class="ds-app-subtitle"><?php esc_html_e( 'Global defaults — every value below can still be overridden per slide.', 'digital-signage' ); ?></p>
		</div>
	</div>
	<?php if ( isset( $_GET['ds_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Settings saved.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_team_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Digital Signage Team updated.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ds_save_settings" />
		<?php wp_nonce_field( 'ds_save_settings' ); ?>

		<div class="ds-panel">
			<h2><?php esc_html_e( 'Default slide durations (seconds)', 'digital-signage' ); ?></h2>
			<p class="ds-hint"><?php esc_html_e( 'Used whenever a slide does not set its own duration override.', 'digital-signage' ); ?></p>
			<div class="ds-settings-grid">
				<?php
				$types = array(
					'image'   => __( 'Image', 'digital-signage' ),
					'video'   => __( 'Video (when not "play full length")', 'digital-signage' ),
					'webpage' => __( 'Webpage / iframe', 'digital-signage' ),
					'html'    => __( 'Custom HTML block', 'digital-signage' ),
					'rss'     => __( 'RSS / feed ticker', 'digital-signage' ),
					'weather' => __( 'Weather widget', 'digital-signage' ),
					'clock'   => __( 'Clock / date', 'digital-signage' ),
					'pdf'     => __( 'PDF page', 'digital-signage' ),
					'social'  => __( 'Social media embed', 'digital-signage' ),
					'infinite_scroll' => __( 'Infinite scroll gallery', 'digital-signage' ),
				);
				foreach ( $types as $key => $label ) :
					?>
					<div class="ds-field">
						<label for="ds_duration_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
						<input type="number" min="1" id="ds_duration_<?php echo esc_attr( $key ); ?>" name="ds_settings[duration_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ 'duration_' . $key ] ); ?>" class="ds-input ds-input-small" />
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="ds-panel">
			<h2><?php esc_html_e( 'Playback & connectivity', 'digital-signage' ); ?></h2>
			<div class="ds-settings-grid">
				<div class="ds-field">
					<label for="ds_transition"><?php esc_html_e( 'Default transition', 'digital-signage' ); ?></label>
					<select id="ds_transition" name="ds_settings[transition]" class="ds-input">
						<?php foreach ( array( 'none', 'fade', 'slide', 'zoom' ) as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $s['transition'], $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ds-field">
					<label for="ds_poll_interval"><?php esc_html_e( 'Player poll interval (seconds)', 'digital-signage' ); ?></label>
					<input type="number" min="10" id="ds_poll_interval" name="ds_settings[poll_interval]" value="<?php echo esc_attr( $s['poll_interval'] ); ?>" class="ds-input ds-input-small" />
					<span class="ds-hint"><?php esc_html_e( 'How often each player checks for schedule/content changes.', 'digital-signage' ); ?></span>
				</div>
				<div class="ds-field">
					<label for="ds_heartbeat_interval"><?php esc_html_e( 'Heartbeat interval (seconds)', 'digital-signage' ); ?></label>
					<input type="number" min="10" id="ds_heartbeat_interval" name="ds_settings[heartbeat_interval]" value="<?php echo esc_attr( $s['heartbeat_interval'] ); ?>" class="ds-input ds-input-small" />
				</div>
				<div class="ds-field">
					<label for="ds_offline_status_sec"><?php esc_html_e( 'Mark offline after (seconds without heartbeat)', 'digital-signage' ); ?></label>
					<input type="number" min="30" id="ds_offline_status_sec" name="ds_settings[offline_status_sec]" value="<?php echo esc_attr( $s['offline_status_sec'] ); ?>" class="ds-input ds-input-small" />
				</div>
				<div class="ds-field">
					<label for="ds_timezone"><?php esc_html_e( 'Scheduling time zone', 'digital-signage' ); ?></label>
					<input type="text" id="ds_timezone" name="ds_settings[timezone]" value="<?php echo esc_attr( $s['timezone'] ); ?>" class="ds-input" list="ds-admin-timezones" autocomplete="off" placeholder="Search city" />
					<datalist id="ds-admin-timezones"><?php foreach ( timezone_identifiers_list() as $timezone_name ) : $parts = explode( '/', $timezone_name ); ?><option value="<?php echo esc_attr( $timezone_name ); ?>"><?php echo esc_html( str_replace( '_', ' ', end( $parts ) ) ); ?></option><?php endforeach; ?></datalist>
				</div>
			</div>
		</div>

		<button type="submit" class="ds-btn ds-btn-primary ds-btn-large"><?php esc_html_e( 'Save Settings', 'digital-signage' ); ?></button>
	</form>

	<?php if ( $can_manage_team ) : ?>
		<div class="ds-panel">
			<h2><?php esc_html_e( 'Digital Signage Team', 'digital-signage' ); ?></h2>
			<p class="ds-hint"><?php esc_html_e( 'Selected users share every channel, screen, slide, schedule, calendar, analytics page and Digital Signage setting. This does not make them WordPress administrators.', 'digital-signage' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_save_team" />
				<?php wp_nonce_field( 'ds_save_team' ); ?>
				<div class="ds-settings-grid">
					<?php foreach ( $team_users as $user ) : ?>
						<label class="ds-field">
							<span><input type="checkbox" name="team_user_ids[]" value="<?php echo esc_attr( $user->ID ); ?>" <?php checked( in_array( (int) $user->ID, $team_member_ids, true ) ); ?> /> <?php echo esc_html( $user->display_name ); ?></span>
							<span class="ds-hint"><?php echo esc_html( $user->user_email ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<button type="submit" class="ds-btn ds-btn-primary"><?php esc_html_e( 'Save Team', 'digital-signage' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<div class="ds-panel">
		<h2><?php esc_html_e( 'Data tools', 'digital-signage' ); ?></h2>
		<div class="ds-app-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-import-export' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Import / Export a Channel', 'digital-signage' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-analytics' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Proof of Play Log', 'digital-signage' ); ?></a>
		</div>
	</div>

	<p class="ds-app-footer"><?php esc_html_e( 'Digital Signage', 'digital-signage' ); ?> v<?php echo esc_html( DS_VERSION ); ?></p>
</div>
