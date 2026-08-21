<?php
/** @var WP_Post[] $slides */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="description"><?php esc_html_e( 'Drag rows to reorder the playlist. Order is saved when you Update the channel.', 'digital-signage' ); ?></p>
<ul id="ds-sortable-playlist" class="ds-sortable-playlist">
	<?php foreach ( $slides as $i => $slide ) : ?>
		<?php
		$type = get_post_meta( $slide->ID, 'ds_slide_type', true ) ?: 'image';
		$zone = get_post_meta( $slide->ID, 'ds_zone', true ) ?: 'main';
		?>
		<li class="ds-sortable-item" data-slide-id="<?php echo esc_attr( $slide->ID ); ?>">
			<span class="dashicons dashicons-menu ds-drag-handle"></span>
			<span class="ds-slide-title"><a href="<?php echo esc_url( get_edit_post_link( $slide->ID ) ); ?>"><?php echo esc_html( $slide->post_title ); ?></a></span>
			<span class="ds-slide-meta">[<?php echo esc_html( $type ); ?>] &middot; <?php echo esc_html( $zone ); ?></span>
			<input type="hidden" name="ds_slide_order[<?php echo esc_attr( $slide->ID ); ?>]" value="<?php echo esc_attr( $i * 10 ); ?>" class="ds-order-input" />
		</li>
	<?php endforeach; ?>
	<?php if ( ! $slides ) : ?>
		<li class="ds-sortable-empty"><?php esc_html_e( 'No slides in this channel yet. Add a Slide and assign this channel from its Playlist Assignment box.', 'digital-signage' ); ?></li>
	<?php endif; ?>
</ul>
<p>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ds_slide&ds_channel=' . get_the_ID() ) ); ?>" class="button"><?php esc_html_e( 'Add Slide to this Channel', 'digital-signage' ); ?></a>
</p>
