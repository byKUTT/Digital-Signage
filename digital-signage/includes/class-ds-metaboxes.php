<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta boxes for the CPT edit screens: Slide content/type/timing/schedule,
 * Channel zones & layout template, Screen pairing/orientation info,
 * Schedule recurrence rules + one-off overrides + priority/emergency flag.
 */
class DS_Metaboxes {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post_ds_slide', array( $this, 'save_slide' ) );
		add_action( 'save_post_ds_channel', array( $this, 'save_channel' ) );
		add_action( 'save_post_ds_screen', array( $this, 'save_screen' ) );
		add_action( 'save_post_ds_schedule', array( $this, 'save_schedule' ) );
	}

	public function register() {
		add_meta_box( 'ds_slide_content', __( 'Slide Content', 'digital-signage' ), array( $this, 'render_slide_content' ), 'ds_slide', 'normal', 'high' );
		add_meta_box( 'ds_slide_timing', __( 'Timing & Transition', 'digital-signage' ), array( $this, 'render_slide_timing' ), 'ds_slide', 'side', 'default' );
		add_meta_box( 'ds_slide_schedule', __( 'Slide Schedule (optional)', 'digital-signage' ), array( $this, 'render_slide_schedule' ), 'ds_slide', 'normal', 'default' );
		add_meta_box( 'ds_slide_playlist', __( 'Playlist Assignment', 'digital-signage' ), array( $this, 'render_slide_playlist' ), 'ds_slide', 'side', 'high' );

		add_meta_box( 'ds_channel_zones', __( 'Zones & Layout', 'digital-signage' ), array( $this, 'render_channel_zones' ), 'ds_channel', 'normal', 'high' );
		add_meta_box( 'ds_channel_playlist', __( 'Playlist (drag to reorder)', 'digital-signage' ), array( $this, 'render_channel_playlist' ), 'ds_channel', 'normal', 'high' );
		add_meta_box( 'ds_channel_priority', __( 'Priority / Emergency Channel', 'digital-signage' ), array( $this, 'render_channel_priority' ), 'ds_channel', 'side', 'default' );

		add_meta_box( 'ds_screen_info', __( 'Screen Info', 'digital-signage' ), array( $this, 'render_screen_info' ), 'ds_screen', 'normal', 'high' );
		add_meta_box( 'ds_screen_status', __( 'Live Status', 'digital-signage' ), array( $this, 'render_screen_status' ), 'ds_screen', 'side', 'default' );
		add_meta_box( 'ds_screen_remote', __( 'Remote Control', 'digital-signage' ), array( $this, 'render_screen_remote' ), 'ds_screen', 'side', 'default' );

		add_meta_box( 'ds_schedule_rules', __( 'Recurrence & Overrides', 'digital-signage' ), array( $this, 'render_schedule_rules' ), 'ds_schedule', 'normal', 'high' );
	}

	/* ---------------- Slide ---------------- */

	public function render_slide_content( $post ) {
		wp_nonce_field( 'ds_save_slide', 'ds_slide_nonce' );
		$type    = get_post_meta( $post->ID, 'ds_slide_type', true ) ?: 'image';
		$media   = get_post_meta( $post->ID, 'ds_media_id', true );
		$url     = get_post_meta( $post->ID, 'ds_content_url', true );
		$html    = get_post_meta( $post->ID, 'ds_content_html', true );
		$feed    = get_post_meta( $post->ID, 'ds_feed_url', true );
		$weather = get_post_meta( $post->ID, 'ds_weather_location', true );
		$api_key = get_post_meta( $post->ID, 'ds_weather_api_key', true );
		$playmode = get_post_meta( $post->ID, 'ds_video_play_mode', true ) ?: 'fixed_duration';
		include DS_PLUGIN_DIR . 'admin/views/metabox-slide-content.php';
	}

	public function render_slide_timing( $post ) {
		$duration   = get_post_meta( $post->ID, 'ds_duration_override', true );
		$transition = get_post_meta( $post->ID, 'ds_transition_override', true ) ?: 'default';
		include DS_PLUGIN_DIR . 'admin/views/metabox-slide-timing.php';
	}

	public function render_slide_schedule( $post ) {
		$start_date = get_post_meta( $post->ID, 'ds_sched_start_date', true );
		$end_date   = get_post_meta( $post->ID, 'ds_sched_end_date', true );
		$start_time = get_post_meta( $post->ID, 'ds_sched_start_time', true );
		$end_time   = get_post_meta( $post->ID, 'ds_sched_end_time', true );
		$days       = (array) get_post_meta( $post->ID, 'ds_sched_days', true );
		include DS_PLUGIN_DIR . 'admin/views/metabox-slide-schedule.php';
	}

	public function render_slide_playlist( $post ) {
		$channel_id = get_post_meta( $post->ID, 'ds_channel_id', true );
		$zone       = get_post_meta( $post->ID, 'ds_zone', true ) ?: 'main';
		$order      = get_post_meta( $post->ID, 'ds_order', true );

		// Coming from a channel's "Add Slide to this Channel" button: prefill the
		// channel and append this slide to the end of its current playlist so staff
		// don't have to look up the right order number by hand.
		if ( 'auto-draft' === $post->post_status && ! $channel_id && ! empty( $_GET['ds_channel'] ) ) {
			$channel_id = absint( $_GET['ds_channel'] );
		}
		if ( '' === $order ) {
			$order = $channel_id ? self::next_order_for_channel( $channel_id ) : 10;
		}

		$channels = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) );
		include DS_PLUGIN_DIR . 'admin/views/metabox-slide-playlist.php';
	}

	private static function next_order_for_channel( $channel_id ) {
		$last = get_posts(
			array(
				'post_type'      => 'ds_slide',
				'posts_per_page' => 1,
				'meta_key'       => 'ds_channel_id',
				'meta_value'     => $channel_id,
				'orderby'        => 'meta_value_num',
				'meta_key2'      => 'ds_order',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		return $last ? absint( get_post_meta( $last[0], 'ds_order', true ) ) + 10 : 10;
	}

	public function save_slide( $post_id ) {
		if ( ! isset( $_POST['ds_slide_nonce'] ) || ! wp_verify_nonce( $_POST['ds_slide_nonce'], 'ds_save_slide' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$text_fields = array(
			'ds_slide_type'        => 'sanitize_key',
			'ds_media_id'          => 'absint',
			'ds_content_url'       => 'esc_url_raw',
			'ds_feed_url'          => 'esc_url_raw',
			'ds_weather_location'  => 'sanitize_text_field',
			'ds_weather_api_key'   => 'sanitize_text_field',
			'ds_video_play_mode'   => 'sanitize_key',
			'ds_duration_override' => 'absint',
			'ds_transition_override' => 'sanitize_key',
			'ds_sched_start_date'  => 'sanitize_text_field',
			'ds_sched_end_date'    => 'sanitize_text_field',
			'ds_sched_start_time'  => 'sanitize_text_field',
			'ds_sched_end_time'    => 'sanitize_text_field',
			'ds_channel_id'        => 'absint',
			'ds_zone'              => 'sanitize_key',
			'ds_order'             => 'absint',
		);

		foreach ( $text_fields as $field => $sanitizer ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		// HTML content block: allow safe HTML/CSS only (post content filtering applies on output too).
		if ( isset( $_POST['ds_content_html'] ) ) {
			update_post_meta( $post_id, 'ds_content_html', wp_kses_post( wp_unslash( $_POST['ds_content_html'] ) ) );
		}

		$days = isset( $_POST['ds_sched_days'] ) ? array_map( 'sanitize_key', (array) $_POST['ds_sched_days'] ) : array();
		update_post_meta( $post_id, 'ds_sched_days', $days );
	}

	/* ---------------- Channel ---------------- */

	public function render_channel_zones( $post ) {
		wp_nonce_field( 'ds_save_channel', 'ds_channel_nonce' );
		$layout = get_post_meta( $post->ID, 'ds_layout_template', true ) ?: 'fullscreen';
		include DS_PLUGIN_DIR . 'admin/views/metabox-channel-zones.php';
	}

	public function render_channel_playlist( $post ) {
		$slides = get_posts(
			array(
				'post_type'      => 'ds_slide',
				'posts_per_page' => -1,
				'meta_key'       => 'ds_channel_id',
				'meta_value'     => $post->ID,
				'meta_key2'      => 'ds_order',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);
		include DS_PLUGIN_DIR . 'admin/views/metabox-channel-playlist.php';
	}

	public function render_channel_priority( $post ) {
		$is_priority = get_post_meta( $post->ID, 'ds_is_priority', true );
		include DS_PLUGIN_DIR . 'admin/views/metabox-channel-priority.php';
	}

	public function save_channel( $post_id ) {
		if ( ! isset( $_POST['ds_channel_nonce'] ) || ! wp_verify_nonce( $_POST['ds_channel_nonce'], 'ds_save_channel' ) ) {
			return;
		}
		if ( isset( $_POST['ds_layout_template'] ) ) {
			update_post_meta( $post_id, 'ds_layout_template', sanitize_key( wp_unslash( $_POST['ds_layout_template'] ) ) );
		}
		update_post_meta( $post_id, 'ds_is_priority', isset( $_POST['ds_is_priority'] ) ? 1 : 0 );

		// Persist drag-and-drop ordering posted as slide_id => order.
		if ( ! empty( $_POST['ds_slide_order'] ) && is_array( $_POST['ds_slide_order'] ) ) {
			foreach ( $_POST['ds_slide_order'] as $slide_id => $order ) {
				update_post_meta( absint( $slide_id ), 'ds_order', absint( $order ) );
			}
		}
	}

	/* ---------------- Screen ---------------- */

	public function render_screen_info( $post ) {
		wp_nonce_field( 'ds_save_screen', 'ds_screen_nonce' );
		$channel_id  = get_post_meta( $post->ID, 'ds_channel_id', true );
		$orientation = get_post_meta( $post->ID, 'ds_orientation', true ) ?: 'landscape';
		$token       = get_post_meta( $post->ID, 'ds_pairing_token', true );
		$channels    = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) );
		$locations   = get_terms( array( 'taxonomy' => 'ds_location', 'hide_empty' => false ) );
		include DS_PLUGIN_DIR . 'admin/views/metabox-screen-info.php';
	}

	public function render_screen_status( $post ) {
		global $wpdb;
		$hb = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ds_heartbeats WHERE screen_id = %d", $post->ID ) );
		include DS_PLUGIN_DIR . 'admin/views/metabox-screen-status.php';
	}

	public function render_screen_remote( $post ) {
		include DS_PLUGIN_DIR . 'admin/views/metabox-screen-remote.php';
	}

	public function save_screen( $post_id ) {
		if ( ! isset( $_POST['ds_screen_nonce'] ) || ! wp_verify_nonce( $_POST['ds_screen_nonce'], 'ds_save_screen' ) ) {
			return;
		}
		if ( isset( $_POST['ds_channel_id'] ) ) {
			update_post_meta( $post_id, 'ds_channel_id', absint( $_POST['ds_channel_id'] ) );
		}
		if ( isset( $_POST['ds_orientation'] ) ) {
			update_post_meta( $post_id, 'ds_orientation', sanitize_key( $_POST['ds_orientation'] ) );
		}
		if ( isset( $_POST['ds_location'] ) ) {
			wp_set_post_terms( $post_id, array( absint( $_POST['ds_location'] ) ), 'ds_location' );
		}
	}

	/* ---------------- Schedule ---------------- */

	public function render_schedule_rules( $post ) {
		wp_nonce_field( 'ds_save_schedule', 'ds_schedule_nonce' );
		$channel_id = get_post_meta( $post->ID, 'ds_channel_id', true );
		$screen_ids = (array) get_post_meta( $post->ID, 'ds_screen_ids', true );
		$days       = (array) get_post_meta( $post->ID, 'ds_days', true );
		$start_time = get_post_meta( $post->ID, 'ds_start_time', true );
		$end_time   = get_post_meta( $post->ID, 'ds_end_time', true );
		$start_date = get_post_meta( $post->ID, 'ds_start_date', true );
		$end_date   = get_post_meta( $post->ID, 'ds_end_date', true );
		$is_oneoff  = get_post_meta( $post->ID, 'ds_is_one_off', true );
		$priority   = get_post_meta( $post->ID, 'ds_priority', true ) ?: 10;
		$channels   = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) );
		$screens    = get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1 ) );
		include DS_PLUGIN_DIR . 'admin/views/metabox-schedule-rules.php';
	}

	public function save_schedule( $post_id ) {
		if ( ! isset( $_POST['ds_schedule_nonce'] ) || ! wp_verify_nonce( $_POST['ds_schedule_nonce'], 'ds_save_schedule' ) ) {
			return;
		}

		update_post_meta( $post_id, 'ds_channel_id', absint( $_POST['ds_channel_id'] ?? 0 ) );
		update_post_meta( $post_id, 'ds_screen_ids', array_map( 'absint', (array) ( $_POST['ds_screen_ids'] ?? array() ) ) );
		update_post_meta( $post_id, 'ds_days', array_map( 'sanitize_key', (array) ( $_POST['ds_days'] ?? array() ) ) );
		update_post_meta( $post_id, 'ds_start_time', sanitize_text_field( $_POST['ds_start_time'] ?? '' ) );
		update_post_meta( $post_id, 'ds_end_time', sanitize_text_field( $_POST['ds_end_time'] ?? '' ) );
		update_post_meta( $post_id, 'ds_start_date', sanitize_text_field( $_POST['ds_start_date'] ?? '' ) );
		update_post_meta( $post_id, 'ds_end_date', sanitize_text_field( $_POST['ds_end_date'] ?? '' ) );
		update_post_meta( $post_id, 'ds_is_one_off', isset( $_POST['ds_is_one_off'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'ds_priority', absint( $_POST['ds_priority'] ?? 10 ) );
	}
}
