<?php
/**
 * @var int          $id
 * @var WP_Post|null $channel
 * @var string       $layout_template
 * @var bool         $is_priority
 * @var WP_Post[]    $slides
 * @var int[]        $screens_using
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$layouts = array(
	'fullscreen'   => __( 'Full Screen', 'digital-signage' ),
	'main_ticker'  => __( 'Main + Ticker', 'digital-signage' ),
	'split_screen' => __( 'Split Screen', 'digital-signage' ),
	'grid'         => __( 'Grid', 'digital-signage' ),
);
$zone_bg        = $id ? ( get_post_meta( $id, 'ds_zone_bg_color', true ) ?: '#000000' ) : '#000000';
$scroll_bg      = $id ? ( get_post_meta( $id, 'ds_scroll_bg_color', true ) ?: '#000000' ) : '#000000';
$transition     = $id ? ( get_post_meta( $id, 'ds_transition', true ) ?: 'default' ) : 'default';
$scroll_spacing_meta = $id ? get_post_meta( $id, 'ds_scroll_spacing', true ) : '';
$scroll_spacing_fallback = $id ? get_post_meta( $id, 'ds_scroll_vertical_spacing', true ) : '';
$scroll_spacing = '' !== $scroll_spacing_meta ? absint( $scroll_spacing_meta ) : ( '' !== $scroll_spacing_fallback ? absint( $scroll_spacing_fallback ) : 20 );
$scroll_speed   = $id ? ( get_post_meta( $id, 'ds_scroll_speed', true ) ?: 60 ) : 60;
$slider_vertical_meta = $id ? get_post_meta( $id, 'ds_infinite_slider_vertical_spacing', true ) : '';
$slider_horizontal_meta = $id ? get_post_meta( $id, 'ds_infinite_slider_horizontal_spacing', true ) : '';
$slider_speed_meta = $id ? get_post_meta( $id, 'ds_infinite_slider_speed', true ) : '';
$slider_radius_meta = $id ? get_post_meta( $id, 'ds_infinite_slider_border_radius', true ) : '';
$slider_direction_meta = $id ? sanitize_key( get_post_meta( $id, 'ds_infinite_slider_direction', true ) ) : '';
$slider_orientation_meta = $id ? sanitize_key( get_post_meta( $id, 'ds_infinite_slider_orientation', true ) ) : 'auto';
$slider_width_mode_meta = $id ? sanitize_key( get_post_meta( $id, 'ds_infinite_slider_width_mode', true ) ) : 'full';
$slider_width_percent_meta = $id ? get_post_meta( $id, 'ds_infinite_slider_width_percent', true ) : '';
$slider_vertical_fallback = $id ? get_post_meta( $id, 'ds_scroll_vertical_spacing', true ) : '';
$slider_horizontal_fallback = $id ? get_post_meta( $id, 'ds_scroll_horizontal_spacing', true ) : '';
$slider_vertical_spacing = '' !== $slider_vertical_meta ? absint( $slider_vertical_meta ) : ( '' !== $slider_vertical_fallback ? absint( $slider_vertical_fallback ) : 20 );
$slider_horizontal_spacing = '' !== $slider_horizontal_meta ? absint( $slider_horizontal_meta ) : ( '' !== $slider_horizontal_fallback ? absint( $slider_horizontal_fallback ) : 20 );
$slider_speed = '' !== $slider_speed_meta ? max( 5, absint( $slider_speed_meta ) ) : $scroll_speed;
$slider_border_radius = '' !== $slider_radius_meta ? absint( $slider_radius_meta ) : 0;
$legacy_direction = 'vertical' === $slider_orientation_meta ? 'up' : ( 'horizontal' === $slider_orientation_meta ? 'left' : 'auto' );
$slider_direction = in_array( $slider_direction_meta, array( 'auto', 'up', 'down', 'left', 'right' ), true ) ? $slider_direction_meta : $legacy_direction;
$slider_width_mode = in_array( $slider_width_mode_meta, array( 'full', 'custom' ), true ) ? $slider_width_mode_meta : 'full';
$slider_width_percent = '' !== $slider_width_percent_meta ? min( 100, max( 10, absint( $slider_width_percent_meta ) ) ) : 100;
$transition_options = array(
	'none'            => __( 'None', 'digital-signage' ),
	'fade'            => __( 'Fade', 'digital-signage' ),
	'slide'           => __( 'Slide', 'digital-signage' ),
	'zoom'            => __( 'Zoom', 'digital-signage' ),
	'infinite_slider' => __( 'Infinite Slider', 'digital-signage' ),
);
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channels' ) ); ?>">&larr; <?php esc_html_e( 'Channels', 'digital-signage' ); ?></a></p>
			<h1><?php echo $id ? esc_html( $channel->post_title ) : esc_html__( 'New Channel', 'digital-signage' ); ?></h1>
		</div>
		<?php if ( $id ) : ?>
			<div class="ds-app-header-actions">
				<a class="ds-btn ds-btn-primary" href="<?php echo esc_url( home_url( '/signage/preview/' . $id . '/' ) ); ?>" target="_blank" rel="noopener">▶ <?php esc_html_e( 'Preview', 'digital-signage' ); ?></a>
				<a class="ds-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ds_duplicate_channel&id=' . $id ), 'ds_duplicate_channel' ) ); ?>"><?php esc_html_e( 'Duplicate', 'digital-signage' ); ?></a>
				<a class="ds-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ds_export_channel&channel_id=' . $id ), 'ds_export_channel' ) ); ?>"><?php esc_html_e( 'Export JSON', 'digital-signage' ); ?></a>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ds-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this channel and all its slides?', 'digital-signage' ) ); ?>');">
					<input type="hidden" name="action" value="ds_delete_channel" />
					<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
					<?php wp_nonce_field( 'ds_delete_channel' ); ?>
					<button type="submit" class="ds-btn ds-btn-danger"><?php esc_html_e( 'Delete', 'digital-signage' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( isset( $_GET['ds_saved'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Channel saved.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_deleted'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Slide deleted.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<div class="ds-panel">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ds_save_channel" />
			<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( 'ds_save_channel' ); ?>

			<div class="ds-field">
				<label for="title"><?php esc_html_e( 'Channel name', 'digital-signage' ); ?></label>
				<input type="text" id="title" name="title" class="ds-input ds-input-large" value="<?php echo $id ? esc_attr( $channel->post_title ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Lobby Menu', 'digital-signage' ); ?>" required />
			</div>

			<div class="ds-field">
				<label><?php esc_html_e( 'Zone layout', 'digital-signage' ); ?></label>
				<div class="ds-layout-picker">
					<?php foreach ( $layouts as $key => $label ) : ?>
						<label class="ds-layout-option">
							<input type="radio" name="layout_template" value="<?php echo esc_attr( $key ); ?>" <?php checked( $layout_template, $key ); ?> />
							<span class="ds-layout-thumb ds-layout-thumb-<?php echo esc_attr( $key ); ?>"></span>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ds-field">
				<label for="zone_bg_color"><?php esc_html_e( 'Letterbox / background color', 'digital-signage' ); ?></label>
				<input type="color" id="zone_bg_color" name="zone_bg_color" value="<?php echo esc_attr( $zone_bg ); ?>" class="ds-color-input" />
				<span class="ds-hint"><?php esc_html_e( 'Shown behind slides and in any empty space around them (e.g. a slide set to "Fit: Contain").', 'digital-signage' ); ?></span>
			</div>

			<div class="ds-field">
				<label for="transition"><?php esc_html_e( 'Slide transition', 'digital-signage' ); ?></label>
				<select id="transition" name="transition" class="ds-input">
					<option value="default" <?php selected( $transition, 'default' ); ?>><?php esc_html_e( 'Use global default', 'digital-signage' ); ?></option>
					<?php foreach ( $transition_options as $transition_key => $transition_label ) : ?>
						<option value="<?php echo esc_attr( $transition_key ); ?>" <?php selected( $transition, $transition_key ); ?>><?php echo esc_html( $transition_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="ds-hint"><?php esc_html_e( 'Applied to every slide in this channel. Infinite Slider continuously loops image-only zones; mixed-content zones safely use Fade.', 'digital-signage' ); ?></span>
			</div>

			<div class="ds-field">
				<label class="ds-checkbox-label">
					<input type="checkbox" name="is_priority" value="1" <?php checked( $is_priority ); ?> />
					<?php esc_html_e( 'Emergency / priority channel — takes over ALL screens immediately, ignoring schedules', 'digital-signage' ); ?>
				</label>
			</div>

			<div class="ds-feature-settings ds-infinite-slider-settings">
				<div class="ds-feature-settings-header">
					<div>
						<h3><?php esc_html_e( 'Infinite Slider', 'digital-signage' ); ?></h3>
						<p class="ds-hint"><?php esc_html_e( 'A continuous, content-first image stream with no controls shown on the signage screen.', 'digital-signage' ); ?></p>
					</div>
					<span class="ds-feature-badge"><?php esc_html_e( 'Channel transition', 'digital-signage' ); ?></span>
				</div>

				<div class="ds-field ds-orientation-field">
					<span class="ds-control-label" id="ds-slider-direction-label"><?php esc_html_e( 'Movement direction', 'digital-signage' ); ?></span>
					<div class="ds-segmented-control" role="radiogroup" aria-labelledby="ds-slider-direction-label">
						<?php foreach ( array( 'auto' => __( 'Auto', 'digital-signage' ), 'up' => __( '↑ Up', 'digital-signage' ), 'down' => __( '↓ Down', 'digital-signage' ), 'left' => __( '← Left', 'digital-signage' ), 'right' => __( '→ Right', 'digital-signage' ) ) as $direction_key => $direction_label ) : ?>
							<label class="ds-segmented-option">
								<input type="radio" name="infinite_slider_direction" value="<?php echo esc_attr( $direction_key ); ?>" <?php checked( $slider_direction, $direction_key ); ?> />
								<span><?php echo esc_html( $direction_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<span class="ds-hint"><?php esc_html_e( 'Up and Down use a vertical column. Left and Right use a horizontal row. Auto follows the zone proportions.', 'digital-signage' ); ?></span>
				</div>

				<div class="ds-settings-grid ds-slider-settings-grid">
				<div class="ds-field">
					<label for="infinite_slider_width_mode"><?php esc_html_e( 'Image width', 'digital-signage' ); ?></label>
					<select id="infinite_slider_width_mode" name="infinite_slider_width_mode" class="ds-input">
						<option value="full" <?php selected( $slider_width_mode, 'full' ); ?>><?php esc_html_e( 'Full screen width (100%)', 'digital-signage' ); ?></option>
						<option value="custom" <?php selected( $slider_width_mode, 'custom' ); ?>><?php esc_html_e( 'Custom screen percentage', 'digital-signage' ); ?></option>
					</select>
				</div>
				<div class="ds-field">
					<label for="infinite_slider_width_percent"><?php esc_html_e( 'Custom width (% of screen)', 'digital-signage' ); ?></label>
					<input type="number" min="10" max="100" id="infinite_slider_width_percent" name="infinite_slider_width_percent" value="<?php echo esc_attr( $slider_width_percent ); ?>" class="ds-input ds-input-small" />
				</div>
				<div class="ds-field">
					<label for="infinite_slider_vertical_spacing"><?php esc_html_e( 'Portrait vertical spacing (px)', 'digital-signage' ); ?></label>
					<input type="number" min="0" id="infinite_slider_vertical_spacing" name="infinite_slider_vertical_spacing" value="<?php echo esc_attr( $slider_vertical_spacing ); ?>" class="ds-input ds-input-small" />
				</div>
				<div class="ds-field">
					<label for="infinite_slider_horizontal_spacing"><?php esc_html_e( 'Landscape horizontal spacing (px)', 'digital-signage' ); ?></label>
					<input type="number" min="0" id="infinite_slider_horizontal_spacing" name="infinite_slider_horizontal_spacing" value="<?php echo esc_attr( $slider_horizontal_spacing ); ?>" class="ds-input ds-input-small" />
				</div>
				<div class="ds-field">
					<label for="infinite_slider_speed"><?php esc_html_e( 'Slider speed (px/second)', 'digital-signage' ); ?></label>
					<input type="number" min="5" id="infinite_slider_speed" name="infinite_slider_speed" value="<?php echo esc_attr( $slider_speed ); ?>" class="ds-input ds-input-small" />
				</div>
				<div class="ds-field">
					<label for="infinite_slider_border_radius"><?php esc_html_e( 'Image border radius (px)', 'digital-signage' ); ?></label>
					<input type="number" min="0" id="infinite_slider_border_radius" name="infinite_slider_border_radius" value="<?php echo esc_attr( $slider_border_radius ); ?>" class="ds-input ds-input-small" />
				</div>
				</div>
			</div>

			<h3><?php esc_html_e( 'Infinite Scroll Gallery defaults', 'digital-signage' ); ?></h3>
			<p class="ds-hint"><?php esc_html_e( 'Applies only to the separate Infinite Scroll Gallery slide type.', 'digital-signage' ); ?></p>
			<div class="ds-settings-grid">
				<div class="ds-field">
					<label for="scroll_bg_color"><?php esc_html_e( 'Background color', 'digital-signage' ); ?></label>
					<input type="color" id="scroll_bg_color" name="scroll_bg_color" value="<?php echo esc_attr( $scroll_bg ); ?>" class="ds-color-input" />
				</div>
				<div class="ds-field">
					<label for="scroll_spacing"><?php esc_html_e( 'Spacing between images (px)', 'digital-signage' ); ?></label>
					<input type="number" min="0" id="scroll_spacing" name="scroll_spacing" value="<?php echo esc_attr( $scroll_spacing ); ?>" class="ds-input ds-input-small" />
				</div>
				<div class="ds-field">
					<label for="scroll_speed"><?php esc_html_e( 'Scroll speed (px/second)', 'digital-signage' ); ?></label>
					<input type="number" min="5" id="scroll_speed" name="scroll_speed" value="<?php echo esc_attr( $scroll_speed ); ?>" class="ds-input ds-input-small" />
				</div>
			</div>

			<button type="submit" class="ds-btn ds-btn-primary"><?php echo $id ? esc_html__( 'Save Changes', 'digital-signage' ) : esc_html__( 'Create Channel', 'digital-signage' ); ?></button>
		</form>
	</div>

	<?php if ( $id ) : ?>
		<div class="ds-panel" id="ds-playlist">
			<div class="ds-panel-header">
				<h2><?php esc_html_e( 'Playlist', 'digital-signage' ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-slide-edit&channel_id=' . $id ) ); ?>" class="ds-btn ds-btn-primary ds-btn-small">+ <?php esc_html_e( 'Add Slide', 'digital-signage' ); ?></a>
			</div>
			<p class="ds-app-subtitle"><?php esc_html_e( 'Drag to reorder, then Save Order. Each slide can be assigned to a zone (Main, Ticker, Corner, Secondary) if this channel uses a multi-zone layout.', 'digital-signage' ); ?></p>

			<?php if ( $slides ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ds_save_slide_order" />
					<input type="hidden" name="channel_id" value="<?php echo esc_attr( $id ); ?>" />
					<?php wp_nonce_field( 'ds_save_slide_order' ); ?>

					<ul id="ds-sortable-playlist" class="ds-sortable-playlist">
						<?php foreach ( $slides as $i => $slide ) : ?>
							<?php
							$type  = get_post_meta( $slide->ID, 'ds_slide_type', true ) ?: 'image';
							$zone  = get_post_meta( $slide->ID, 'ds_zone', true ) ?: 'main';
							$dur   = get_post_meta( $slide->ID, 'ds_duration_override', true );
							$media = absint( get_post_meta( $slide->ID, 'ds_media_id', true ) );
							$edit_url = admin_url( 'admin.php?page=ds-slide-edit&id=' . $slide->ID . '&channel_id=' . $id );
							?>
							<li class="ds-sortable-item" data-slide-id="<?php echo esc_attr( $slide->ID ); ?>">
								<span class="dashicons dashicons-menu ds-drag-handle"></span>
								<a class="ds-slide-thumb ds-slide-thumb-<?php echo esc_attr( $type ); ?>" href="<?php echo esc_url( $edit_url ); ?>">
									<?php if ( $media && in_array( $type, array( 'image', 'video', 'pdf' ), true ) && wp_get_attachment_image_url( $media, 'thumbnail' ) ) : ?>
										<img src="<?php echo esc_url( wp_get_attachment_image_url( $media, 'thumbnail' ) ); ?>" alt="" />
									<?php else : ?>
										<?php echo DS_Icons::icon( $type ); // phpcs:ignore -- trusted static SVG. ?>
									<?php endif; ?>
								</a>
								<div class="ds-slide-info">
									<a class="ds-slide-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $slide->post_title ); ?></a>
									<span class="ds-slide-meta"><?php echo esc_html( DS_Icons::type_label( $type ) ); ?> &middot; <?php echo esc_html( ucfirst( $zone ) ); ?><?php echo $dur ? ' · ' . esc_html( $dur . 's' ) : ''; ?></span>
								</div>
								<input type="hidden" name="order[<?php echo esc_attr( $slide->ID ); ?>]" value="<?php echo esc_attr( $i * 10 ); ?>" class="ds-order-input" />
							</li>
						<?php endforeach; ?>
					</ul>

					<button type="submit" class="ds-btn"><?php esc_html_e( 'Save Order', 'digital-signage' ); ?></button>
				</form>
			<?php else : ?>
				<div class="ds-empty-state ds-empty-state-small">
					<p><?php esc_html_e( 'No slides yet.', 'digital-signage' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-slide-edit&channel_id=' . $id ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'Add your first Slide', 'digital-signage' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $screens_using ) : ?>
			<div class="ds-panel">
				<h2><?php esc_html_e( 'Playing on', 'digital-signage' ); ?></h2>
				<div class="ds-chip-list">
					<?php foreach ( $screens_using as $screen_id ) : ?>
						<a class="ds-chip" href="<?php echo esc_url( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen_id ) ); ?>"><?php echo esc_html( get_the_title( $screen_id ) ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="ds-panel">
			<p class="ds-hint"><?php esc_html_e( 'Save this channel first, then add slides to its playlist.', 'digital-signage' ); ?></p>
		</div>
	<?php endif; ?>
</div>
