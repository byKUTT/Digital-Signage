<?php
/**
 * @var WP_Post $post
 * @var string  $type
 * @var int     $media
 * @var string  $url
 * @var string  $html
 * @var string  $feed
 * @var string  $weather
 * @var string  $api_key
 * @var string  $playmode
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
?>
<p>
	<label for="ds_slide_type"><strong><?php esc_html_e( 'Slide type', 'digital-signage' ); ?></strong></label><br />
	<select id="ds_slide_type" name="ds_slide_type" class="widefat">
		<?php foreach ( $types as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
</p>

<div class="ds-type-field" data-type="image,video,pdf">
	<p>
		<label><strong><?php esc_html_e( 'Media (upload or choose from Media Library)', 'digital-signage' ); ?></strong></label><br />
		<input type="hidden" id="ds_media_id" name="ds_media_id" value="<?php echo esc_attr( $media ); ?>" />
		<button type="button" class="button" id="ds-select-media"><?php esc_html_e( 'Select Media', 'digital-signage' ); ?></button>
		<button type="button" class="button" id="ds-clear-media"><?php esc_html_e( 'Clear', 'digital-signage' ); ?></button>
		<div id="ds-media-preview">
			<?php if ( $media ) {
				echo wp_get_attachment_image( $media, 'medium' );
			} ?>
		</div>
	</p>
</div>

<div class="ds-type-field" data-type="video">
	<p>
		<label><strong><?php esc_html_e( 'Video playback mode', 'digital-signage' ); ?></strong></label><br />
		<label><input type="radio" name="ds_video_play_mode" value="full_length" <?php checked( $playmode, 'full_length' ); ?> /> <?php esc_html_e( 'Play full length (advance on video end)', 'digital-signage' ); ?></label><br />
		<label><input type="radio" name="ds_video_play_mode" value="fixed_duration" <?php checked( $playmode, 'fixed_duration' ); ?> /> <?php esc_html_e( 'Use fixed duration (loop/cut to duration set below)', 'digital-signage' ); ?></label>
	</p>
</div>

<div class="ds-type-field" data-type="webpage,social">
	<p>
		<label><strong><?php esc_html_e( 'URL', 'digital-signage' ); ?></strong></label><br />
		<input type="url" name="ds_content_url" class="widefat" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com" />
	</p>
</div>

<div class="ds-type-field" data-type="html">
	<p>
		<label><strong><?php esc_html_e( 'HTML / CSS', 'digital-signage' ); ?></strong></label><br />
		<textarea name="ds_content_html" rows="8" class="widefat code"><?php echo esc_textarea( $html ); ?></textarea>
		<span class="description"><?php esc_html_e( 'Sanitized with wp_kses_post on save; scripts are stripped.', 'digital-signage' ); ?></span>
	</p>
</div>

<div class="ds-type-field" data-type="rss">
	<p>
		<label><strong><?php esc_html_e( 'Feed URL', 'digital-signage' ); ?></strong></label><br />
		<input type="url" name="ds_feed_url" class="widefat" value="<?php echo esc_attr( $feed ); ?>" placeholder="https://example.com/feed" />
	</p>
</div>

<div class="ds-type-field" data-type="weather">
	<p>
		<label><strong><?php esc_html_e( 'Location', 'digital-signage' ); ?></strong></label><br />
		<input type="text" name="ds_weather_location" class="widefat" value="<?php echo esc_attr( $weather ); ?>" placeholder="<?php esc_attr_e( 'City name or lat,lon', 'digital-signage' ); ?>" />
	</p>
	<p>
		<label><strong><?php esc_html_e( 'Weather API key', 'digital-signage' ); ?></strong></label><br />
		<input type="text" name="ds_weather_api_key" class="widefat" value="<?php echo esc_attr( $api_key ); ?>" />
		<span class="description"><?php esc_html_e( 'Uses the OpenWeatherMap-compatible API; the player fetches this client-side.', 'digital-signage' ); ?></span>
	</p>
</div>
