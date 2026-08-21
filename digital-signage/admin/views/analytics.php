<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ds-wrap">
	<h1><?php esc_html_e( 'Proof of Play', 'digital-signage' ); ?></h1>

	<form method="get">
		<input type="hidden" name="page" value="ds-analytics" />
		<select name="screen_id" onchange="this.form.submit()">
			<option value="0"><?php esc_html_e( 'All screens', 'digital-signage' ); ?></option>
			<?php foreach ( $screens as $s ) : ?>
				<option value="<?php echo esc_attr( $s->ID ); ?>" <?php selected( $screen_id, $s->ID ); ?>><?php echo esc_html( $s->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
	</form>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ds_export_proof_of_play' ), 'ds_export_proof_of_play' ) ); ?>"><?php esc_html_e( 'Export CSV', 'digital-signage' ); ?></a>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Played At (UTC)', 'digital-signage' ); ?></th>
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
					<td><?php echo esc_html( $row->played_at ); ?></td>
					<td><?php echo esc_html( get_the_title( $row->screen_id ) ); ?></td>
					<td><?php echo esc_html( get_the_title( $row->channel_id ) ); ?></td>
					<td><?php echo esc_html( get_the_title( $row->slide_id ) ); ?></td>
					<td><?php echo esc_html( $row->zone ); ?></td>
					<td><?php echo esc_html( $row->duration_seconds ); ?>s</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No plays logged yet.', 'digital-signage' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>
