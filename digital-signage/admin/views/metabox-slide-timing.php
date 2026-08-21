<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p>
	<label for="ds_duration_override"><strong><?php esc_html_e( 'Duration override (seconds)', 'digital-signage' ); ?></strong></label><br />
	<input type="number" min="0" id="ds_duration_override" name="ds_duration_override" value="<?php echo esc_attr( $duration ); ?>" class="small-text" />
	<p class="description"><?php esc_html_e( 'Leave 0/blank to use the type default from Settings.', 'digital-signage' ); ?></p>
</p>
<p>
	<label for="ds_transition_override"><strong><?php esc_html_e( 'Transition override', 'digital-signage' ); ?></strong></label><br />
	<select id="ds_transition_override" name="ds_transition_override">
		<option value="default" <?php selected( $transition, 'default' ); ?>><?php esc_html_e( 'Use global default', 'digital-signage' ); ?></option>
		<?php foreach ( array( 'none', 'fade', 'slide', 'zoom' ) as $t ) : ?>
			<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $transition, $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
		<?php endforeach; ?>
	</select>
</p>
