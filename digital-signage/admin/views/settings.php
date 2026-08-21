<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = DS_Settings::get_all();
?>
<div class="wrap ds-wrap">
	<h1><?php esc_html_e( 'Digital Signage Settings', 'digital-signage' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'ds_settings_group' ); ?>

		<h2><?php esc_html_e( 'Default slide durations (seconds)', 'digital-signage' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Used whenever a slide does not set its own duration override.', 'digital-signage' ); ?></p>
		<table class="form-table">
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
			);
			foreach ( $types as $key => $label ) :
				?>
				<tr>
					<th><label for="ds_duration_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="number" min="1" id="ds_duration_<?php echo esc_attr( $key ); ?>" name="ds_settings[duration_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ 'duration_' . $key ] ); ?>" class="small-text" /></td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Playback & connectivity', 'digital-signage' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="ds_transition"><?php esc_html_e( 'Default transition', 'digital-signage' ); ?></label></th>
				<td>
					<select id="ds_transition" name="ds_settings[transition]">
						<?php foreach ( array( 'none', 'fade', 'slide', 'zoom' ) as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $s['transition'], $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ds_poll_interval"><?php esc_html_e( 'Player poll interval (seconds)', 'digital-signage' ); ?></label></th>
				<td><input type="number" min="10" id="ds_poll_interval" name="ds_settings[poll_interval]" value="<?php echo esc_attr( $s['poll_interval'] ); ?>" class="small-text" />
				<p class="description"><?php esc_html_e( 'How often each player checks for schedule/content changes.', 'digital-signage' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="ds_heartbeat_interval"><?php esc_html_e( 'Heartbeat interval (seconds)', 'digital-signage' ); ?></label></th>
				<td><input type="number" min="10" id="ds_heartbeat_interval" name="ds_settings[heartbeat_interval]" value="<?php echo esc_attr( $s['heartbeat_interval'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label for="ds_offline_status_sec"><?php esc_html_e( 'Mark offline after (seconds without heartbeat)', 'digital-signage' ); ?></label></th>
				<td><input type="number" min="30" id="ds_offline_status_sec" name="ds_settings[offline_status_sec]" value="<?php echo esc_attr( $s['offline_status_sec'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label for="ds_timezone"><?php esc_html_e( 'Scheduling time zone', 'digital-signage' ); ?></label></th>
				<td><input type="text" id="ds_timezone" name="ds_settings[timezone]" value="<?php echo esc_attr( $s['timezone'] ); ?>" class="regular-text" placeholder="e.g. America/New_York" /></td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
