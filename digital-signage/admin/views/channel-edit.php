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
$scroll_spacing = $id ? ( get_post_meta( $id, 'ds_scroll_spacing', true ) ?: 20 ) : 20;
$scroll_speed   = $id ? ( get_post_meta( $id, 'ds_scroll_speed', true ) ?: 60 ) : 60;
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
				<label class="ds-checkbox-label">
					<input type="checkbox" name="is_priority" value="1" <?php checked( $is_priority ); ?> />
					<?php esc_html_e( 'Emergency / priority channel — takes over ALL screens immediately, ignoring schedules', 'digital-signage' ); ?>
				</label>
			</div>

			<h3><?php esc_html_e( 'Infinite Scroll Gallery defaults', 'digital-signage' ); ?></h3>
			<p class="ds-hint"><?php esc_html_e( 'Applies to every Infinite Scroll Gallery slide in this channel — set once here rather than per slide.', 'digital-signage' ); ?></p>
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
