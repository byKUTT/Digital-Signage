<?php
/**
 * @var int          $id
 * @var WP_Post|null $slide
 * @var int          $channel_id
 * @var WP_Post|null $channel
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $channel_id ) {
	wp_die( esc_html__( 'A slide must belong to a channel. Go to a channel and click "Add Slide".', 'digital-signage' ) );
}

$type     = $id ? ( get_post_meta( $id, 'ds_slide_type', true ) ?: 'image' ) : 'image';
$media    = $id ? absint( get_post_meta( $id, 'ds_media_id', true ) ) : 0;
$url      = $id ? get_post_meta( $id, 'ds_content_url', true ) : '';
$html     = $id ? get_post_meta( $id, 'ds_content_html', true ) : '';
$feed     = $id ? get_post_meta( $id, 'ds_feed_url', true ) : '';
$weather  = $id ? get_post_meta( $id, 'ds_weather_location', true ) : '';
$api_key  = $id ? get_post_meta( $id, 'ds_weather_api_key', true ) : '';
$playmode = $id ? ( get_post_meta( $id, 'ds_video_play_mode', true ) ?: 'fixed_duration' ) : 'fixed_duration';
$duration = $id ? get_post_meta( $id, 'ds_duration_override', true ) : '';
$transition = $id ? ( get_post_meta( $id, 'ds_transition_override', true ) ?: 'default' ) : 'default';
$zone     = $id ? ( get_post_meta( $id, 'ds_zone', true ) ?: 'main' ) : 'main';
$order    = $id ? get_post_meta( $id, 'ds_order', true ) : '';
if ( ! $id && '' === $order ) {
	$last  = get_posts( array( 'post_type' => 'ds_slide', 'posts_per_page' => 1, 'meta_key' => 'ds_channel_id', 'meta_value' => $channel_id, 'orderby' => 'meta_value_num', 'meta_key2' => 'ds_order', 'order' => 'DESC', 'fields' => 'ids' ) );
	$order = $last ? absint( get_post_meta( $last[0], 'ds_order', true ) ) + 10 : 10;
}
$start_date = $id ? get_post_meta( $id, 'ds_sched_start_date', true ) : '';
$end_date   = $id ? get_post_meta( $id, 'ds_sched_end_date', true ) : '';
$start_time = $id ? get_post_meta( $id, 'ds_sched_start_time', true ) : '';
$end_time   = $id ? get_post_meta( $id, 'ds_sched_end_time', true ) : '';
$days       = $id ? (array) get_post_meta( $id, 'ds_sched_days', true ) : array();

$types = array(
	'image'   => __( 'Image', 'digital-signage' ),
	'video'   => __( 'Video', 'digital-signage' ),
	'webpage' => __( 'Webpage (iframe URL)', 'digital-signage' ),
	'html'    => __( 'Custom HTML / CSS block', 'digital-signage' ),
	'rss'     => __( 'RSS / Atom feed ticker', 'digital-signage' ),
	'weather' => __( 'Weather widget', 'digital-signage' ),
	'clock'   => __( 'Live clock / date', 'digital-signage' ),
	'pdf'     => __( 'PDF page / Google Slides embed', 'digital-signage' ),
	'social'  => __( 'Social media embed', 'digital-signage' ),
);
$day_labels = array( 'mon' => __( 'Mon', 'digital-signage' ), 'tue' => __( 'Tue', 'digital-signage' ), 'wed' => __( 'Wed', 'digital-signage' ), 'thu' => __( 'Thu', 'digital-signage' ), 'fri' => __( 'Fri', 'digital-signage' ), 'sat' => __( 'Sat', 'digital-signage' ), 'sun' => __( 'Sun', 'digital-signage' ) );
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<p class="ds-breadcrumb"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel_id . '#ds-playlist' ) ); ?>">&larr; <?php echo esc_html( $channel ? $channel->post_title : __( 'Channel', 'digital-signage' ) ); ?></a></p>
			<h1><?php echo $id ? esc_html( $slide->post_title ) : esc_html__( 'New Slide', 'digital-signage' ); ?></h1>
		</div>
		<?php if ( $id ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this slide?', 'digital-signage' ) ); ?>');">
				<input type="hidden" name="action" value="ds_delete_slide" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
				<input type="hidden" name="channel_id" value="<?php echo esc_attr( $channel_id ); ?>" />
				<?php wp_nonce_field( 'ds_delete_slide' ); ?>
				<button type="submit" class="ds-btn ds-btn-danger"><?php esc_html_e( 'Delete', 'digital-signage' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ds_save_slide" />
		<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
		<input type="hidden" name="channel_id" value="<?php echo esc_attr( $channel_id ); ?>" />
		<?php wp_nonce_field( 'ds_save_slide' ); ?>

		<div class="ds-two-col">
			<div class="ds-panel">
				<div class="ds-field">
					<label for="title"><?php esc_html_e( 'Slide name (for your reference, not shown on screen)', 'digital-signage' ); ?></label>
					<input type="text" id="title" name="title" class="ds-input" value="<?php echo $id ? esc_attr( $slide->post_title ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Lunch Special', 'digital-signage' ); ?>" required />
				</div>

				<div class="ds-field">
					<label for="ds_slide_type"><?php esc_html_e( 'Slide type', 'digital-signage' ); ?></label>
					<select id="ds_slide_type" name="slide_type" class="ds-input">
						<?php foreach ( $types as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="ds-type-field" data-type="image,video,pdf">
					<div class="ds-field">
						<label><?php esc_html_e( 'Media', 'digital-signage' ); ?></label>
						<input type="hidden" id="ds_media_id" name="media_id" value="<?php echo esc_attr( $media ); ?>" />
						<div class="ds-media-row">
							<button type="button" class="ds-btn" id="ds-select-media"><?php esc_html_e( 'Select Media', 'digital-signage' ); ?></button>
							<button type="button" class="ds-btn" id="ds-clear-media"><?php esc_html_e( 'Clear', 'digital-signage' ); ?></button>
						</div>
						<div id="ds-media-preview" class="ds-media-preview">
							<?php if ( $media ) {
								echo wp_get_attachment_image( $media, 'medium' );
							} ?>
						</div>
					</div>
				</div>

				<div class="ds-type-field" data-type="video">
					<div class="ds-field">
						<label><?php esc_html_e( 'Video playback', 'digital-signage' ); ?></label>
						<label class="ds-radio-label"><input type="radio" name="video_play_mode" value="full_length" <?php checked( $playmode, 'full_length' ); ?> /> <?php esc_html_e( 'Play full length (advance on video end)', 'digital-signage' ); ?></label>
						<label class="ds-radio-label"><input type="radio" name="video_play_mode" value="fixed_duration" <?php checked( $playmode, 'fixed_duration' ); ?> /> <?php esc_html_e( 'Use fixed duration below', 'digital-signage' ); ?></label>
					</div>
				</div>

				<div class="ds-type-field" data-type="webpage,social">
					<div class="ds-field">
						<label><?php esc_html_e( 'URL', 'digital-signage' ); ?></label>
						<input type="url" name="content_url" class="ds-input" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com" />
					</div>
				</div>

				<div class="ds-type-field" data-type="html">
					<div class="ds-field">
						<label><?php esc_html_e( 'HTML / CSS', 'digital-signage' ); ?></label>
						<textarea name="content_html" rows="8" class="ds-input ds-input-code"><?php echo esc_textarea( $html ); ?></textarea>
						<span class="ds-hint"><?php esc_html_e( 'Scripts are stripped on save.', 'digital-signage' ); ?></span>
					</div>
				</div>

				<div class="ds-type-field" data-type="rss">
					<div class="ds-field">
						<label><?php esc_html_e( 'Feed URL', 'digital-signage' ); ?></label>
						<input type="url" name="feed_url" class="ds-input" value="<?php echo esc_attr( $feed ); ?>" placeholder="https://example.com/feed" />
					</div>
				</div>

				<div class="ds-type-field" data-type="weather">
					<div class="ds-field">
						<label><?php esc_html_e( 'Location', 'digital-signage' ); ?></label>
						<input type="text" name="weather_location" class="ds-input" value="<?php echo esc_attr( $weather ); ?>" placeholder="<?php esc_attr_e( 'City name or lat,lon', 'digital-signage' ); ?>" />
					</div>
					<div class="ds-field">
						<label><?php esc_html_e( 'Weather API key', 'digital-signage' ); ?></label>
						<input type="text" name="weather_api_key" class="ds-input" value="<?php echo esc_attr( $api_key ); ?>" />
					</div>
				</div>
			</div>

			<div class="ds-panel">
				<h3><?php esc_html_e( 'Timing & placement', 'digital-signage' ); ?></h3>

				<div class="ds-field">
					<label for="zone"><?php esc_html_e( 'Zone', 'digital-signage' ); ?></label>
					<select id="zone" name="zone" class="ds-input">
						<option value="main" <?php selected( $zone, 'main' ); ?>><?php esc_html_e( 'Main', 'digital-signage' ); ?></option>
						<option value="ticker" <?php selected( $zone, 'ticker' ); ?>><?php esc_html_e( 'Ticker (bottom strip)', 'digital-signage' ); ?></option>
						<option value="corner" <?php selected( $zone, 'corner' ); ?>><?php esc_html_e( 'Corner widget', 'digital-signage' ); ?></option>
						<option value="secondary" <?php selected( $zone, 'secondary' ); ?>><?php esc_html_e( 'Secondary panel', 'digital-signage' ); ?></option>
					</select>
				</div>

				<div class="ds-field">
					<label for="duration_override"><?php esc_html_e( 'Duration override (seconds)', 'digital-signage' ); ?></label>
					<input type="number" min="0" id="duration_override" name="duration_override" value="<?php echo esc_attr( $duration ); ?>" class="ds-input ds-input-small" />
					<span class="ds-hint"><?php esc_html_e( 'Blank/0 uses the type default from Settings.', 'digital-signage' ); ?></span>
				</div>

				<div class="ds-field">
					<label for="transition_override"><?php esc_html_e( 'Transition', 'digital-signage' ); ?></label>
					<select id="transition_override" name="transition_override" class="ds-input">
						<option value="default" <?php selected( $transition, 'default' ); ?>><?php esc_html_e( 'Use global default', 'digital-signage' ); ?></option>
						<?php foreach ( array( 'none', 'fade', 'slide', 'zoom' ) as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $transition, $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="ds-field">
					<label for="order"><?php esc_html_e( 'Order', 'digital-signage' ); ?></label>
					<input type="number" id="order" name="order" value="<?php echo esc_attr( $order ); ?>" class="ds-input ds-input-small" />
				</div>

				<h3><?php esc_html_e( 'Show only during (optional)', 'digital-signage' ); ?></h3>
				<div class="ds-field">
					<label><?php esc_html_e( 'Date range', 'digital-signage' ); ?></label>
					<div class="ds-input-row">
						<input type="date" name="sched_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
						<span>&mdash;</span>
						<input type="date" name="sched_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
					</div>
				</div>
				<div class="ds-field">
					<label><?php esc_html_e( 'Time of day', 'digital-signage' ); ?></label>
					<div class="ds-input-row">
						<input type="time" name="sched_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
						<span>&mdash;</span>
						<input type="time" name="sched_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
					</div>
				</div>
				<div class="ds-field">
					<label><?php esc_html_e( 'Days of week', 'digital-signage' ); ?></label>
					<div class="ds-checkbox-row">
						<?php foreach ( $day_labels as $key => $label ) : ?>
							<label class="ds-checkbox-label"><input type="checkbox" name="sched_days[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $days, true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</div>
					<span class="ds-hint"><?php esc_html_e( 'Leave all unchecked to allow every day.', 'digital-signage' ); ?></span>
				</div>
			</div>
		</div>

		<button type="submit" class="ds-btn ds-btn-primary ds-btn-large"><?php echo $id ? esc_html__( 'Save Slide', 'digital-signage' ) : esc_html__( 'Add Slide', 'digital-signage' ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel_id . '#ds-playlist' ) ); ?>" class="ds-btn"><?php esc_html_e( 'Cancel', 'digital-signage' ); ?></a>
	</form>
</div>
