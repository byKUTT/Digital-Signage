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

		$zone_bg = sanitize_hex_color( $data['zone_bg_color'] ?? '' );
		update_post_meta( $post_id, 'ds_zone_bg_color', $zone_bg ? $zone_bg : '' );

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
		$post_id = self::upsert_post( $id, 'ds_slide', $data['title'] ?? '' );

		$fields = array(
			'slide_type'        => array( 'ds_slide_type', 'sanitize_key' ),
			'media_id'          => array( 'ds_media_id', 'absint' ),
			'content_url'       => array( 'ds_content_url', 'esc_url_raw' ),
			'feed_url'          => array( 'ds_feed_url', 'esc_url_raw' ),
			'weather_location'  => array( 'ds_weather_location', 'sanitize_text_field' ),
			'weather_api_key'   => array( 'ds_weather_api_key', 'sanitize_text_field' ),
			'video_play_mode'   => array( 'ds_video_play_mode', 'sanitize_key' ),
			'duration_override' => array( 'ds_duration_override', 'absint' ),
			'transition_override' => array( 'ds_transition_override', 'sanitize_key' ),
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
		wp_delete_post( $id, true );
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
