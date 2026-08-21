<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$day_labels = array( 'mon' => __( 'Mon', 'digital-signage' ), 'tue' => __( 'Tue', 'digital-signage' ), 'wed' => __( 'Wed', 'digital-signage' ), 'thu' => __( 'Thu', 'digital-signage' ), 'fri' => __( 'Fri', 'digital-signage' ), 'sat' => __( 'Sat', 'digital-signage' ), 'sun' => __( 'Sun', 'digital-signage' ) );
?>
<table class="form-table">
	<tr>
		<th><label for="ds_channel_id"><?php esc_html_e( 'Channel to play', 'digital-signage' ); ?></label></th>
		<td>
			<select id="ds_channel_id" name="ds_channel_id" class="widefat">
				<option value="0"><?php esc_html_e( '— Select —', 'digital-signage' ); ?></option>
				<?php foreach ( $channels as $c ) : ?>
					<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $channel_id, $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Target screens', 'digital-signage' ); ?></th>
		<td>
			<div class="ds-checkbox-list">
				<?php foreach ( $screens as $s ) : ?>
					<label><input type="checkbox" name="ds_screen_ids[]" value="<?php echo esc_attr( $s->ID ); ?>" <?php checked( in_array( $s->ID, $screen_ids, true ) ); ?> /> <?php echo esc_html( $s->post_title ); ?></label><br />
				<?php endforeach; ?>
			</div>
		</td>
	</tr>
	<tr>
		<th><label><?php esc_html_e( 'Schedule type', 'digital-signage' ); ?></label></th>
		<td>
			<label><input type="radio" name="ds_is_one_off_radio" class="ds-schedule-type" value="0" <?php checked( ! $is_oneoff ); ?> /> <?php esc_html_e( 'Recurring (day-of-week + time)', 'digital-signage' ); ?></label><br />
			<label><input type="radio" name="ds_is_one_off_radio" class="ds-schedule-type" value="1" <?php checked( (bool) $is_oneoff ); ?> /> <?php esc_html_e( 'One-off date override (e.g. holiday takeover)', 'digital-signage' ); ?></label>
			<input type="hidden" name="ds_is_one_off" id="ds_is_one_off" value="<?php echo $is_oneoff ? '1' : ''; ?>" />
		</td>
	</tr>
	<tr class="ds-recurring-only">
		<th><?php esc_html_e( 'Days of week', 'digital-signage' ); ?></th>
		<td>
			<?php foreach ( $day_labels as $key => $label ) : ?>
				<label style="margin-right:10px"><input type="checkbox" name="ds_days[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $days, true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Date range', 'digital-signage' ); ?></th>
		<td>
			<input type="date" name="ds_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
			&mdash;
			<input type="date" name="ds_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
			<p class="description"><?php esc_html_e( 'For a one-off, this is the takeover window. For recurring, optional bounds on when the rule is active.', 'digital-signage' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Time of day', 'digital-signage' ); ?></th>
		<td>
			<input type="time" name="ds_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
			&mdash;
			<input type="time" name="ds_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
		</td>
	</tr>
	<tr>
		<th><label for="ds_priority"><?php esc_html_e( 'Priority', 'digital-signage' ); ?></label></th>
		<td>
			<input type="number" id="ds_priority" name="ds_priority" value="<?php echo esc_attr( $priority ); ?>" class="small-text" min="0" max="100" />
			<p class="description"><?php esc_html_e( 'Higher priority wins when two schedules overlap for the same screen.', 'digital-signage' ); ?></p>
		</td>
	</tr>
</table>
