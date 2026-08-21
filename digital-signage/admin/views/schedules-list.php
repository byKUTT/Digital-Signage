<?php
/** @var WP_Post[] $schedules */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<h1><?php esc_html_e( 'Schedules', 'digital-signage' ); ?></h1>
			<p class="ds-app-subtitle"><?php esc_html_e( 'Control which channel plays on which screens, and when.', 'digital-signage' ); ?></p>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-schedule-edit' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'New Schedule', 'digital-signage' ); ?></a>
	</div>

	<?php if ( isset( $_GET['ds_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Saved.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_deleted'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Schedule deleted.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<?php if ( $schedules ) : ?>
		<div class="ds-table-wrap">
			<table class="ds-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'digital-signage' ); ?></th>
						<th><?php esc_html_e( 'Channel', 'digital-signage' ); ?></th>
						<th><?php esc_html_e( 'Screens', 'digital-signage' ); ?></th>
						<th><?php esc_html_e( 'When', 'digital-signage' ); ?></th>
						<th><?php esc_html_e( 'Type', 'digital-signage' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $schedules as $schedule ) : ?>
						<?php
						$channel_id = get_post_meta( $schedule->ID, 'ds_channel_id', true );
						$screen_ids = (array) get_post_meta( $schedule->ID, 'ds_screen_ids', true );
						$is_oneoff  = get_post_meta( $schedule->ID, 'ds_is_one_off', true );
						$days       = (array) get_post_meta( $schedule->ID, 'ds_days', true );
						$start_time = get_post_meta( $schedule->ID, 'ds_start_time', true );
						$end_time   = get_post_meta( $schedule->ID, 'ds_end_time', true );
						?>
						<tr>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-schedule-edit&id=' . $schedule->ID ) ); ?>"><?php echo esc_html( $schedule->post_title ); ?></a></td>
							<td><?php echo $channel_id ? esc_html( get_the_title( $channel_id ) ) : '—'; ?></td>
							<td><?php echo esc_html( count( $screen_ids ) ); ?></td>
							<td>
								<?php if ( $is_oneoff ) : ?>
									<?php echo esc_html( get_post_meta( $schedule->ID, 'ds_start_date', true ) . ' → ' . get_post_meta( $schedule->ID, 'ds_end_date', true ) ); ?>
								<?php else : ?>
									<?php echo esc_html( $days ? implode( ', ', array_map( 'ucfirst', $days ) ) : __( 'Every day', 'digital-signage' ) ); ?>
									<?php echo $start_time ? esc_html( ' ' . $start_time . '–' . $end_time ) : ''; ?>
								<?php endif; ?>
							</td>
							<td><?php echo $is_oneoff ? esc_html__( 'One-off', 'digital-signage' ) : esc_html__( 'Recurring', 'digital-signage' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<div class="ds-empty-state">
			<p><?php esc_html_e( 'No schedules yet — screens just play their assigned channel all the time.', 'digital-signage' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-schedule-edit' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'Create a Schedule', 'digital-signage' ); ?></a>
		</div>
	<?php endif; ?>
</div>
