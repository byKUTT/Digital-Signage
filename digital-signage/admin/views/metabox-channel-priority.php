<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p>
	<label>
		<input type="checkbox" name="ds_is_priority" value="1" <?php checked( $is_priority ); ?> />
		<strong><?php esc_html_e( 'Emergency / priority channel', 'digital-signage' ); ?></strong>
	</label>
</p>
<p class="description"><?php esc_html_e( 'When enabled, this channel immediately overrides normal rotation on ALL screens, ignoring schedules, until you uncheck this box. Use for alerts.', 'digital-signage' ); ?></p>
