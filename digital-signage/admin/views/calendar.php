<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$schedules = get_posts( array( 'post_type' => 'ds_schedule', 'posts_per_page' => -1 ) );
$days      = array( 'mon' => __( 'Mon', 'digital-signage' ), 'tue' => __( 'Tue', 'digital-signage' ), 'wed' => __( 'Wed', 'digital-signage' ), 'thu' => __( 'Thu', 'digital-signage' ), 'fri' => __( 'Fri', 'digital-signage' ), 'sat' => __( 'Sat', 'digital-signage' ), 'sun' => __( 'Sun', 'digital-signage' ) );
?>
<div class="wrap ds-wrap">
	<h1><?php esc_html_e( 'Schedule Calendar', 'digital-signage' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Weekly view of recurring schedules. One-off date overrides are listed below the grid.', 'digital-signage' ); ?></p>

	<div class="ds-calendar-grid">
		<div class="ds-calendar-header">
			<div class="ds-calendar-time-col"></div>
			<?php foreach ( $days as $key => $label ) : ?>
				<div class="ds-calendar-day-col"><?php echo esc_html( $label ); ?></div>
			<?php endforeach; ?>
		</div>

		<div class="ds-calendar-body">
			<?php foreach ( $days as $day_key => $day_label ) : ?>
				<div class="ds-calendar-day" data-day="<?php echo esc_attr( $day_key ); ?>">
					<?php
					foreach ( $schedules as $schedule ) {
						if ( get_post_meta( $schedule->ID, 'ds_is_one_off', true ) ) {
							continue;
						}
						$sched_days = (array) get_post_meta( $schedule->ID, 'ds_days', true );
						if ( ! in_array( $day_key, $sched_days, true ) ) {
							continue;
						}
						$channel_id = get_post_meta( $schedule->ID, 'ds_channel_id', true );
						$start      = get_post_meta( $schedule->ID, 'ds_start_time', true ) ?: '00:00';
						$end        = get_post_meta( $schedule->ID, 'ds_end_time', true ) ?: '23:59';
						$screens    = (array) get_post_meta( $schedule->ID, 'ds_screen_ids', true );
						$priority   = get_post_meta( $schedule->ID, 'ds_priority', true );
						?>
						<a class="ds-calendar-event <?php echo $priority > 10 ? 'ds-calendar-event-priority' : ''; ?>" href="<?php echo esc_url( get_edit_post_link( $schedule->ID ) ); ?>">
							<strong><?php echo esc_html( $start . '–' . $end ); ?></strong><br />
							<?php echo esc_html( $channel_id ? get_the_title( $channel_id ) : '—' ); ?><br />
							<span class="ds-calendar-event-screens"><?php echo esc_html( count( $screens ) . ' ' . _n( 'screen', 'screens', count( $screens ), 'digital-signage' ) ); ?></span>
						</a>
						<?php
					}
					?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<h2><?php esc_html_e( 'One-off date overrides', 'digital-signage' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'digital-signage' ); ?></th>
				<th><?php esc_html_e( 'Dates', 'digital-signage' ); ?></th>
				<th><?php esc_html_e( 'Time', 'digital-signage' ); ?></th>
				<th><?php esc_html_e( 'Channel', 'digital-signage' ); ?></th>
				<th><?php esc_html_e( 'Screens', 'digital-signage' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $schedules as $schedule ) : ?>
				<?php if ( ! get_post_meta( $schedule->ID, 'ds_is_one_off', true ) ) { continue; } ?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $schedule->ID ) ); ?>"><?php echo esc_html( $schedule->post_title ); ?></a></td>
					<td><?php echo esc_html( get_post_meta( $schedule->ID, 'ds_start_date', true ) . ' → ' . get_post_meta( $schedule->ID, 'ds_end_date', true ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $schedule->ID, 'ds_start_time', true ) . '–' . get_post_meta( $schedule->ID, 'ds_end_time', true ) ); ?></td>
					<td><?php $c = get_post_meta( $schedule->ID, 'ds_channel_id', true ); echo $c ? esc_html( get_the_title( $c ) ) : '—'; ?></td>
					<td><?php echo esc_html( count( (array) get_post_meta( $schedule->ID, 'ds_screen_ids', true ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
