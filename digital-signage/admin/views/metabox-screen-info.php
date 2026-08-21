<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table">
	<tr>
		<th><label for="ds_channel_id"><?php esc_html_e( 'Assigned channel', 'digital-signage' ); ?></label></th>
		<td>
			<select id="ds_channel_id" name="ds_channel_id" class="widefat">
				<option value="0"><?php esc_html_e( '— None —', 'digital-signage' ); ?></option>
				<?php foreach ( $channels as $c ) : ?>
					<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $channel_id, $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Default channel; schedules can override this at specific times.', 'digital-signage' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ds_orientation"><?php esc_html_e( 'Orientation', 'digital-signage' ); ?></label></th>
		<td>
			<select id="ds_orientation" name="ds_orientation">
				<option value="landscape" <?php selected( $orientation, 'landscape' ); ?>><?php esc_html_e( 'Landscape', 'digital-signage' ); ?></option>
				<option value="portrait" <?php selected( $orientation, 'portrait' ); ?>><?php esc_html_e( 'Portrait', 'digital-signage' ); ?></option>
				<option value="auto" <?php selected( $orientation, 'auto' ); ?>><?php esc_html_e( 'Auto-detect (CSS orientation media query)', 'digital-signage' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="ds_location"><?php esc_html_e( 'Location', 'digital-signage' ); ?></label></th>
		<td>
			<select id="ds_location" name="ds_location">
				<option value="0"><?php esc_html_e( '— None —', 'digital-signage' ); ?></option>
				<?php
				$current = wp_get_post_terms( $post->ID, 'ds_location', array( 'fields' => 'ids' ) );
				foreach ( $locations as $loc ) :
					?>
					<option value="<?php echo esc_attr( $loc->term_id ); ?>" <?php selected( in_array( $loc->term_id, $current, true ) ); ?>><?php echo esc_html( $loc->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=ds_location&post_type=ds_screen' ) ); ?>"><?php esc_html_e( 'Manage locations', 'digital-signage' ); ?></a>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Player URL', 'digital-signage' ); ?></th>
		<td>
			<?php if ( $token ) : ?>
				<code><?php echo esc_html( home_url( '/signage/play/' . $token . '/' ) ); ?></code>
				<a href="<?php echo esc_url( home_url( '/signage/play/' . $token . '/' ) ); ?>" target="_blank" class="button"><?php esc_html_e( 'Open', 'digital-signage' ); ?></a>
			<?php else : ?>
				<em><?php esc_html_e( 'Not paired yet — this screen was created manually. Use the Pair a Screen flow instead so a secure token is generated.', 'digital-signage' ); ?></em>
			<?php endif; ?>
		</td>
	</tr>
</table>
