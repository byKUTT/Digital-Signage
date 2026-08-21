<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data layer behind the custom admin UI: creates/updates/deletes Channels,
 * Slides, Screens and Schedules from plain $_POST arrays submitted by our
 * own forms (no WP post-editor involved). Every public entry point assumes
 * the caller already checked the nonce and the 'manage_digital_signage'
 * capability (DS_Admin does this before calling in).
 */
class DS_CRUD {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/* ---------------- Channel ---------------- */

	public static function save_channel( $id, array $data ) {
		$post_id = self::upsert_post( $id, 'ds_channel', $data['title'] ?? '' );

		update_post_meta( $post_id, 'ds_layout_template', sanitize_key( $data['layout_template'] ?? 'fullscreen' ) );
		update_post_meta( $post_id, 'ds_is_priority', empty( $data['is_priority'] ) ? 0 : 1 );
		$transition = sanitize_key( $data['transition'] ?? 'default' );
		if ( ! in_array( $transition, array( 'default', 'none', 'fade', 'slide', 'zoom', 'infinite_slider' ), true ) ) {
			$transition = 'default';
		}
		update_post_meta( $post_id, 'ds_transition', $transition );
		update_post_meta( $post_id, 'ds_infinite_slider_vertical_spacing', absint( $data['infinite_slider_vertical_spacing'] ?? 20 ) );
		update_post_meta( $post_id, 'ds_infinite_slider_horizontal_spacing', absint( $data['infinite_slider_horizontal_spacing'] ?? 20 ) );
		update_post_meta( $post_id, 'ds_infinite_slider_speed', max( 5, absint( $data['infinite_slider_speed'] ?? 60 ) ) );
		update_post_meta( $post_id, 'ds_infinite_slider_border_radius', absint( $data['infinite_slider_border_radius'] ?? 0 ) );

		$zone_bg = sanitize_hex_color( $data['zone_bg_color'] ?? '' );
		update_post_meta( $post_id, 'ds_zone_bg_color', $zone_bg ? $zone_bg : '' );

		// Infinite-scroll gallery defaults apply to every such slide in this
		// channel — one place to tune the look/feel rather than per slide.
		$scroll_bg = sanitize_hex_color( $data['scroll_bg_color'] ?? '' );
		update_post_meta( $post_id, 'ds_scroll_bg_color', $scroll_bg ? $scroll_bg : '#000000' );
		update_post_meta( $post_id, 'ds_scroll_spacing', absint( $data['scroll_spacing'] ?? 20 ) );
		update_post_meta( $post_id, 'ds_scroll_speed', max( 5, absint( $data['scroll_speed'] ?? 60 ) ) );

		return $post_id;
	}

	public static function delete_channel( $id ) {
		$slides = get_posts(
			array(
				'post_type'      => 'ds_slide',
				'posts_per_page' => -1,
				'meta_key'       => 'ds_channel_id',
				'meta_value'     => $id,
				'fields'         => 'ids',
			)
		);
		foreach ( $slides as $slide_id ) {
			wp_delete_post( $slide_id, true );
		}
		wp_delete_post( $id, true );
	}

	public static function duplicate_channel( $id ) {
		$source = get_post( $id );
		if ( ! $source || 'ds_channel' !== $source->post_type ) {
			return 0;
		}

		$new_id = self::upsert_post( 0, 'ds_channel', $source->post_title . ' ' . __( '(Copy)', 'digital-signage' ) );
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( 0 === strpos( $key, 'ds_' ) ) {
				update_post_meta( $new_id, $key, maybe_unserialize( $values[0] ) );
			}
		}

		$slides = get_posts(
			array(
				'post_type'      => 'ds_slide',
				'posts_per_page' => -1,
				'meta_key'       => 'ds_channel_id',
				'meta_value'     => $id,
			)
		);
		foreach ( $slides as $slide ) {
			$new_slide = self::upsert_post( 0, 'ds_slide', $slide->post_title );
			foreach ( get_post_meta( $slide->ID ) as $key => $values ) {
				if ( 0 === strpos( $key, 'ds_' ) ) {
					update_post_meta( $new_slide, $key, maybe_unserialize( $values[0] ) );
				}
			}
			update_post_meta( $new_slide, 'ds_channel_id', $new_id );
		}

		return $new_id;
	}

	/* ---------------- Slide ---------------- */

	public static function save_slide( $id, array $data ) {
		$channel_id = absint( $data['channel_id'] ?? 0 );
		$title      = trim( sanitize_text_field( $data['title'] ?? '' ) );
		if ( '' === $title ) {
			$title = self::next_slide_title( $channel_id, $id );
		}
		$post_id = self::upsert_post( $id, 'ds_slide', $title );

		$fields = array(
			'slide_type'        => array( 'ds_slide_type', 'sanitize_key' ),
			'media_id'          => array( 'ds_media_id', 'absint' ),
			'content_url'       => array( 'ds_content_url', 'esc_url_raw' ),
			'feed_url'          => array( 'ds_feed_url', 'esc_url_raw' ),
			'weather_location'  => array( 'ds_weather_location', 'sanitize_text_field' ),
			'weather_api_key'   => array( 'ds_weather_api_key', 'sanitize_text_field' ),
			'video_play_mode'   => array( 'ds_video_play_mode', 'sanitize_key' ),
			'duration_override' => array( 'ds_duration_override', 'absint' ),
			'sched_start_date'  => array( 'ds_sched_start_date', 'sanitize_text_field' ),
			'sched_end_date'    => array( 'ds_sched_end_date', 'sanitize_text_field' ),
			'sched_start_time'  => array( 'ds_sched_start_time', 'sanitize_text_field' ),
			'sched_end_time'    => array( 'ds_sched_end_time', 'sanitize_text_field' ),
			'channel_id'        => array( 'ds_channel_id', 'absint' ),
			'zone'              => array( 'ds_zone', 'sanitize_key' ),
			'order'             => array( 'ds_order', 'absint' ),
			'fit'               => array( 'ds_fit', 'sanitize_key' ),
		);

		foreach ( $fields as $input_key => $meta ) {
			list( $meta_key, $sanitizer ) = $meta;
			if ( isset( $data[ $input_key ] ) ) {
				update_post_meta( $post_id, $meta_key, call_user_func( $sanitizer, $data[ $input_key ] ) );
			}
		}

		update_post_meta( $post_id, 'ds_content_html', wp_kses_post( $data['content_html'] ?? '' ) );
		update_post_meta( $post_id, 'ds_sched_days', array_map( 'sanitize_key', (array) ( $data['sched_days'] ?? array() ) ) );
		delete_post_meta( $post_id, 'ds_transition_override' );

		// Infinite-scroll gallery: an ordered list of attachment IDs.
		$scroll_images = array_filter( array_map( 'absint', (array) ( $data['scroll_images'] ?? array() ) ) );
		update_post_meta( $post_id, 'ds_scroll_images', array_values( $scroll_images ) );

		return $post_id;
	}

	public static function delete_slide( $id ) {
		wp_delete_post( $id, true );
	}

	public static function duplicate_slide( $id ) {
		$source = get_post( $id );
		if ( ! $source || 'ds_slide' !== $source->post_type ) {
			return 0;
		}

		$channel_id = absint( get_post_meta( $id, 'ds_channel_id', true ) );
		$new_id     = self::upsert_post( 0, 'ds_slide', $source->post_title . ' ' . __( '(Copy)', 'digital-signage' ) );

		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( 0 === strpos( $key, 'ds_' ) ) {
				update_post_meta( $new_id, $key, maybe_unserialize( $values[0] ) );
			}
		}

		// Append to the end of the playlist rather than duplicating the order number.
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
		update_post_meta( $new_id, 'ds_order', $last ? absint( get_post_meta( $last[0], 'ds_order', true ) ) + 10 : 10 );

		return $new_id;
	}

	public static function save_slide_order( array $order_map ) {
		foreach ( $order_map as $slide_id => $order ) {
			update_post_meta( absint( $slide_id ), 'ds_order', absint( $order ) );
		}
	}

	/* ---------------- Screen ---------------- */

	public static function save_screen( $id, array $data ) {
		$post_id = self::upsert_post( $id, 'ds_screen', $data['title'] ?? '' );

		update_post_meta( $post_id, 'ds_channel_id', absint( $data['channel_id'] ?? 0 ) );
		update_post_meta( $post_id, 'ds_orientation', sanitize_key( $data['orientation'] ?? 'landscape' ) );

		if ( ! empty( $data['location'] ) ) {
			wp_set_post_terms( $post_id, array( absint( $data['location'] ) ), 'ds_location' );
		} else {
			wp_set_post_terms( $post_id, array(), 'ds_location' );
		}

		return $post_id;
	}

	public static function delete_screen( $id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'ds_heartbeats', array( 'screen_id' => $id ), array( '%d' ) );
		// Also free up the pairing code/token this screen used. Without this, the
		// ds_pairing_codes row stays behind with paired_at already set, so if the
		// same physical device (same persistent token) ever revisits its player
		// URL it's shown that same already-used code forever — which wp-admin's
		// "Pair a Screen" form rejects as invalid, permanently locking the device
		// out. Deleting the row here means the device's next visit mints a fresh,
		// reusable code, same as a brand-new device.
		//
		// Matched on both screen_id AND token (belt-and-suspenders): screen_id is
		// the normal path, but token is the actual link render_unpaired_screen()
		// looks up by, so matching on it too covers any row that ended up without
		// screen_id set for some reason (e.g. a pre-2.6.0 pairing).
		$token = get_post_meta( $id, 'ds_pairing_token', true );
		$wpdb->delete( $wpdb->prefix . 'ds_pairing_codes', array( 'screen_id' => $id ), array( '%d' ) );
		if ( $token ) {
			$wpdb->delete( $wpdb->prefix . 'ds_pairing_codes', array( 'token' => $token ), array( '%s' ) );
		}
		wp_delete_post( $id, true );
	}

	/**
	 * Queues a command for the on-device agent (ds-agent, Raspberry Pi only) —
	 * delivered the next time that device's heartbeat is answered, then cleared.
	 * Overwrites any still-pending command rather than stacking them.
	 */
	public static function queue_device_command( $screen_id, array $command ) {
		update_post_meta( $screen_id, 'ds_device_command', wp_json_encode( $command ) );
	}

	/* ---------------- Schedule ---------------- */

	public static function save_schedule( $id, array $data ) {
		$post_id = self::upsert_post( $id, 'ds_schedule', $data['title'] ?? '' );

		update_post_meta( $post_id, 'ds_channel_id', absint( $data['channel_id'] ?? 0 ) );
		update_post_meta( $post_id, 'ds_screen_ids', array_map( 'absint', (array) ( $data['screen_ids'] ?? array() ) ) );
		update_post_meta( $post_id, 'ds_days', array_map( 'sanitize_key', (array) ( $data['days'] ?? array() ) ) );
		update_post_meta( $post_id, 'ds_start_time', sanitize_text_field( $data['start_time'] ?? '' ) );
		update_post_meta( $post_id, 'ds_end_time', sanitize_text_field( $data['end_time'] ?? '' ) );
		update_post_meta( $post_id, 'ds_start_date', sanitize_text_field( $data['start_date'] ?? '' ) );
		update_post_meta( $post_id, 'ds_end_date', sanitize_text_field( $data['end_date'] ?? '' ) );
		update_post_meta( $post_id, 'ds_is_one_off', empty( $data['is_one_off'] ) ? 0 : 1 );
		update_post_meta( $post_id, 'ds_priority', absint( $data['priority'] ?? 10 ) );

		return $post_id;
	}

	public static function delete_schedule( $id ) {
		wp_delete_post( $id, true );
	}

	public static function clone_schedule_to_screens( $id, array $screen_ids ) {
		$source = get_post( $id );
		if ( ! $source || 'ds_schedule' !== $source->post_type ) {
			return array();
		}

		$new_ids = array();
		foreach ( $screen_ids as $screen_id ) {
			$new_id = self::upsert_post( 0, 'ds_schedule', $source->post_title . ' — ' . get_the_title( $screen_id ) );
			foreach ( get_post_meta( $id ) as $key => $values ) {
				if ( 0 === strpos( $key, 'ds_' ) ) {
					update_post_meta( $new_id, $key, maybe_unserialize( $values[0] ) );
				}
			}
			update_post_meta( $new_id, 'ds_screen_ids', array( absint( $screen_id ) ) );
			$new_ids[] = $new_id;
		}

		return $new_ids;
	}

	/* ---------------- Shared ---------------- */

	private static function next_slide_title( $channel_id, $exclude_id = 0 ) {
		$args = array(
			'post_type'      => 'ds_slide',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => 'ds_channel_id',
			'meta_value'     => absint( $channel_id ),
			'fields'         => 'ids',
		);
		if ( $exclude_id ) {
			$args['post__not_in'] = array( absint( $exclude_id ) );
		}

		$used_numbers = array();
		foreach ( get_posts( $args ) as $slide_id ) {
			if ( preg_match( '/^Slide ([1-9][0-9]*)$/i', get_the_title( $slide_id ), $matches ) ) {
				$used_numbers[ absint( $matches[1] ) ] = true;
			}
		}

		$number = 1;
		while ( isset( $used_numbers[ $number ] ) ) {
			$number++;
		}

		return sprintf( __( 'Slide %d', 'digital-signage' ), $number );
	}

	private static function upsert_post( $id, $post_type, $title ) {
		$title = $title ? sanitize_text_field( $title ) : __( '(untitled)', 'digital-signage' );

		if ( $id ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
			return $id;
		}

		return wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
	}
}
