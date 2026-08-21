<?php
/**
 * @var int          $id
 * @var WP_Post|null $schedule
 * @var int          $channel_id
 * @var int[]        $screen_ids
 * @var string[]     $days
 * @var string       $start_time
 * @var string       $end_time
 * @var string       $start_date
 * @var string       $end_date
 * @var bool         $is_oneoff
 * @var int          $priority
 * @var WP_Post[]    $channels
 * @var WP_Post[]    $screens
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$day_labels = array( 'mon' => __( 'Mon', 'digital-signage' ), 'tue' => __( 'Tue', 'digital-signage' ), 'wed' => __( 'Wed', 'digital-signage' ), 'thu' => __( 'Thu', 'digital-signage' ), 'fri' => __( 'Fri', 'digital-signage' ), 'sat' => __( 'Sat', 'digital-signage' ), 'sun' => __( 'Sun', 'digital-signage' ) );
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-schedules' ) ); ?>">&larr; <?php esc_html_e( 'Schedules', 'digital-signage' ); ?></a></p>
			<h1><?php echo $id ? esc_html( $schedule->post_title ) : esc_html__( 'New Schedule', 'digital-signage' ); ?></h1>
		</div>
		<?php if ( $id ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this schedule?', 'digital-signage' ) ); ?>');">
				<input type="hidden" name="action" value="ds_delete_schedule" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
				<?php wp_nonce_field( 'ds_delete_schedule' ); ?>
				<button type="submit" class="ds-btn ds-btn-danger"><?php esc_html_e( 'Delete', 'digital-signage' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<?php if ( isset( $_GET['ds_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Schedule saved.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<div class="ds-panel">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ds_save_schedule" />
			<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( 'ds_save_schedule' ); ?>

			<div class="ds-field">
				<label for="title"><?php esc_html_e( 'Schedule name', 'digital-signage' ); ?></label>
				<input type="text" id="title" name="title" class="ds-input" value="<?php echo $id ? esc_attr( $schedule->post_title ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Holiday Takeover', 'digital-signage' ); ?>" required />
			</div>

			<div class="ds-field">
				<label for="channel_id"><?php esc_html_e( 'Channel to play', 'digital-signage' ); ?></label>
				<select id="channel_id" name="channel_id" class="ds-input">
					<option value="0"><?php esc_html_e( '— Select —', 'digital-signage' ); ?></option>
					<?php foreach ( $channels as $c ) : ?>
						<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $channel_id, $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="ds-field">
				<label><?php esc_html_e( 'Target screens', 'digital-signage' ); ?></label>
				<div class="ds-checkbox-list">
					<?php foreach ( $screens as $s ) : ?>
						<label class="ds-checkbox-label"><input type="checkbox" name="screen_ids[]" value="<?php echo esc_attr( $s->ID ); ?>" <?php checked( in_array( $s->ID, $screen_ids, true ) ); ?> /> <?php echo esc_html( $s->post_title ); ?></label>
					<?php endforeach; ?>
					<?php if ( ! $screens ) : ?><p class="ds-hint"><?php esc_html_e( 'No screens paired yet.', 'digital-signage' ); ?></p><?php endif; ?>
				</div>
			</div>

			<div class="ds-field">
				<label><?php esc_html_e( 'Schedule type', 'digital-signage' ); ?></label>
				<label class="ds-radio-label"><input type="radio" name="is_one_off_radio" class="ds-schedule-type" value="0" <?php checked( ! $is_oneoff ); ?> /> <?php esc_html_e( 'Recurring (day-of-week + time)', 'digital-signage' ); ?></label>
				<label class="ds-radio-label"><input type="radio" name="is_one_off_radio" class="ds-schedule-type" value="1" <?php checked( (bool) $is_oneoff ); ?> /> <?php esc_html_e( 'One-off date override (e.g. holiday takeover)', 'digital-signage' ); ?></label>
				<input type="hidden" name="is_one_off" id="ds_is_one_off" value="<?php echo $is_oneoff ? '1' : ''; ?>" />
			</div>

			<div class="ds-field ds-recurring-only">
				<label><?php esc_html_e( 'Days of week', 'digital-signage' ); ?></label>
				<div class="ds-checkbox-row">
					<?php foreach ( $day_labels as $key => $label ) : ?>
						<label class="ds-checkbox-label"><input type="checkbox" name="days[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $days, true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ds-field">
				<label><?php esc_html_e( 'Date range', 'digital-signage' ); ?></label>
				<div class="ds-input-row">
					<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
					<span>&mdash;</span>
					<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
				</div>
				<span class="ds-hint"><?php esc_html_e( 'For a one-off, this is the takeover window. For recurring, optional bounds on when the rule is active.', 'digital-signage' ); ?></span>
			</div>

			<div class="ds-field">
				<label><?php esc_html_e( 'Time of day', 'digital-signage' ); ?></label>
				<div class="ds-input-row">
					<input type="time" name="start_time" value="<?php echo esc_attr( $start_time ); ?>" />
					<span>&mdash;</span>
					<input type="time" name="end_time" value="<?php echo esc_attr( $end_time ); ?>" />
				</div>
			</div>

			<div class="ds-field">
				<label for="priority"><?php esc_html_e( 'Priority', 'digital-signage' ); ?></label>
				<input type="number" id="priority" name="priority" value="<?php echo esc_attr( $priority ); ?>" class="ds-input ds-input-small" min="0" max="100" />
				<span class="ds-hint"><?php esc_html_e( 'Higher priority wins when two schedules overlap for the same screen.', 'digital-signage' ); ?></span>
			</div>

			<button type="submit" class="ds-btn ds-btn-primary"><?php echo $id ? esc_html__( 'Save Changes', 'digital-signage' ) : esc_html__( 'Create Schedule', 'digital-signage' ); ?></button>
		</form>
	</div>

	<?php if ( $id && count( $screen_ids ) > 1 ) : ?>
		<div class="ds-panel">
			<h3><?php esc_html_e( 'Clone as separate schedules', 'digital-signage' ); ?></h3>
			<p class="ds-hint"><?php esc_html_e( 'Split this schedule into one independent copy per screen, so each can be edited on its own later.', 'digital-signage' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_clone_schedule" />
				<input type="hidden" name="schedule_id" value="<?php echo esc_attr( $id ); ?>" />
				<?php wp_nonce_field( 'ds_clone_schedule' ); ?>
				<?php foreach ( $screen_ids as $sid ) : ?>
					<input type="hidden" name="screen_ids[]" value="<?php echo esc_attr( $sid ); ?>" />
				<?php endforeach; ?>
				<button type="submit" class="ds-btn"><?php esc_html_e( 'Clone to Separate Schedules', 'digital-signage' ); ?></button>
			</form>
		</div>
	<?php endif; ?>
</div>
