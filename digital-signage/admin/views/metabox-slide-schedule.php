<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$day_labels = array( 'mon' => __( 'Mon', 'digital-signage' ), 'tue' => __( 'Tue', 'digital-signage' ), 'wed' => __( 'Wed', 'digital-signage' ), 'thu' => __( 'Thu', 'digital-signage' ), 'fri' => __( 'Fri', 'digital-signage' ), 'sat' => __( 'Sat', 'digital-signage' ), 'sun' => __( 'Sun', 'digital-signage' ) );
?>
<p class="description"><?php esc_html_e( 'Restrict when this individual slide appears within its playlist rotation, e.g. a "Lunch Special" slide shown only 11am–2pm.', 'digital-signage' ); ?></p>
<table class="form-table">
	<tr>
		<th><?php esc_html_e( 'Date range', 'digital-signage' ); ?></th>
		<td>
			<input type="date" name="ds_sched_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
			&mdash;
			<input type="date" name="ds_sched_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
			<span class="description"><?php esc_html_e( '(optional; leave blank for no date limit)', 'digital-signage' ); ?></span>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Time of day', 'digital-signage' ); ?></th>
		<td>
			<input type="time" name="ds_sched_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
			&mdash;
			<input type="time" name="ds_sched_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Days of week', 'digital-signage' ); ?></th>
		<td>
			<?php foreach ( $day_labels as $key => $label ) : ?>
				<label style="margin-right:10px"><input type="checkbox" name="ds_sched_days[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $days, true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Leave all unchecked to allow every day.', 'digital-signage' ); ?></p>
		</td>
	</tr>
</table>
