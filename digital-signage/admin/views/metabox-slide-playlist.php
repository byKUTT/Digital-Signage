<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p>
	<label for="ds_channel_id"><strong><?php esc_html_e( 'Channel (playlist)', 'digital-signage' ); ?></strong></label><br />
	<select id="ds_channel_id" name="ds_channel_id" class="widefat">
		<option value="0"><?php esc_html_e( '— Unassigned —', 'digital-signage' ); ?></option>
		<?php foreach ( $channels as $c ) : ?>
			<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $channel_id, $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
		<?php endforeach; ?>
	</select>
</p>
<p>
	<label for="ds_zone"><strong><?php esc_html_e( 'Zone', 'digital-signage' ); ?></strong></label><br />
	<select id="ds_zone" name="ds_zone" class="widefat">
		<option value="main" <?php selected( $zone, 'main' ); ?>><?php esc_html_e( 'Main', 'digital-signage' ); ?></option>
		<option value="ticker" <?php selected( $zone, 'ticker' ); ?>><?php esc_html_e( 'Ticker (bottom strip)', 'digital-signage' ); ?></option>
		<option value="corner" <?php selected( $zone, 'corner' ); ?>><?php esc_html_e( 'Corner widget', 'digital-signage' ); ?></option>
		<option value="secondary" <?php selected( $zone, 'secondary' ); ?>><?php esc_html_e( 'Secondary panel', 'digital-signage' ); ?></option>
	</select>
</p>
<p>
	<label for="ds_order"><strong><?php esc_html_e( 'Order', 'digital-signage' ); ?></strong></label><br />
	<input type="number" id="ds_order" name="ds_order" value="<?php echo esc_attr( $order ); ?>" class="small-text" />
	<span class="description"><?php esc_html_e( 'Or drag-reorder from the channel\'s edit screen.', 'digital-signage' ); ?></span>
</p>
<?php if ( $channel_id ) : ?>
	<p>
		<a href="<?php echo esc_url( get_edit_post_link( $channel_id ) ); ?>">&larr; <?php echo esc_html( sprintf( __( 'Back to "%s"', 'digital-signage' ), get_the_title( $channel_id ) ) ); ?></a>
	</p>
<?php endif; ?>
