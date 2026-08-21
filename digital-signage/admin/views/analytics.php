<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=digital-signage' ) ); ?>">&larr; <?php esc_html_e( 'Dashboard', 'digital-signage' ); ?></a></p>
			<h1><?php esc_html_e( 'Proof of Play', 'digital-signage' ); ?></h1>
		</div>
		<a class="ds-btn ds-btn-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ds_export_proof_of_play' ), 'ds_export_proof_of_play' ) ); ?>"><?php esc_html_e( 'Export CSV', 'digital-signage' ); ?></a>
	</div>

	<div class="ds-panel">
		<form method="get" class="ds-field">
			<input type="hidden" name="page" value="ds-analytics" />
			<label for="screen_id"><?php esc_html_e( 'Filter by screen', 'digital-signage' ); ?></label>
			<select id="screen_id" name="screen_id" class="ds-input" onchange="this.form.submit()">
				<option value="0"><?php esc_html_e( 'All screens', 'digital-signage' ); ?></option>
				<?php foreach ( $screens as $s ) : ?>
					<option value="<?php echo esc_attr( $s->ID ); ?>" <?php selected( $screen_id, $s->ID ); ?>><?php echo esc_html( $s->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>

		<?php if ( $rows ) : ?>
			<div class="ds-table-wrap">
				<table class="ds-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Played At', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Screen', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Channel', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Slide', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Zone', 'digital-signage' ); ?></th>
							<th><?php esc_html_e( 'Duration', 'digital-signage' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $row->played_at . ' UTC' ) ) ); ?></td>
								<td><?php echo esc_html( get_the_title( $row->screen_id ) ); ?></td>
								<td><?php echo esc_html( get_the_title( $row->channel_id ) ); ?></td>
								<td><?php echo esc_html( get_the_title( $row->slide_id ) ); ?></td>
								<td><?php echo esc_html( $row->zone ); ?></td>
								<td><?php echo esc_html( $row->duration_seconds ); ?>s</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="ds-hint"><?php esc_html_e( 'No plays logged yet.', 'digital-signage' ); ?></p>
		<?php endif; ?>
	</div>
</div>
