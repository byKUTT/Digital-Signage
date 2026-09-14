<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export a channel (and its slides) as JSON for backup or to replicate on
 * another WP install, and import that JSON back in.
 */
class DS_Import_Export {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_ds_export_channel', array( __CLASS__, 'export_channel' ) );
		add_action( 'admin_post_ds_import_channel', array( __CLASS__, 'import_channel' ) );
	}

	public static function render_page() {
		$channels = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) );
		include DS_PLUGIN_DIR . 'admin/views/import-export.php';
	}

	public static function export_channel() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_export_channel' );

		$channel_id = absint( $_GET['channel_id'] ?? 0 );
		$channel    = get_post( $channel_id );
		if ( ! $channel || 'ds_channel' !== $channel->post_type ) {
			wp_die( esc_html__( 'Channel not found.', 'digital-signage' ) );
		}

		$slides = get_posts(
			array(
				'post_type'      => 'ds_slide',
				'posts_per_page' => -1,
				'meta_key'       => 'ds_channel_id',
				'meta_value'     => $channel_id,
			)
		);

		$payload = array(
			'plugin'  => 'digital-signage',
			'version' => DS_VERSION,
			'exported_at' => gmdate( 'c' ),
			'channel' => array(
				'title' => $channel->post_title,
				'meta'  => self::flatten_meta( get_post_meta( $channel_id ) ),
			),
			'slides'  => array_map(
				function ( $slide ) {
					return array(
						'title' => $slide->post_title,
						'meta'  => self::flatten_meta( get_post_meta( $slide->ID ) ),
					);
				},
				$slides
			),
		);

		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_title( $channel->post_title ) . '-export.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	public static function import_channel() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_import_channel' );

		if ( empty( $_FILES['ds_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'ds_error', 'no_file', wp_get_referer() ) );
			exit;
		}

		$json = file_get_contents( $_FILES['ds_import_file']['tmp_name'] ); // phpcs:ignore
		$data = json_decode( $json, true );

		if ( empty( $data['channel'] ) ) {
			wp_safe_redirect( add_query_arg( 'ds_error', 'invalid_json', wp_get_referer() ) );
			exit;
		}

		$channel_id = wp_insert_post(
			array(
				'post_type'   => 'ds_channel',
				'post_title'  => sanitize_text_field( $data['channel']['title'] ?? __( 'Imported Channel', 'digital-signage' ) ),
				'post_status' => 'publish',
			)
		);

		foreach ( (array) ( $data['channel']['meta'] ?? array() ) as $key => $value ) {
			update_post_meta( $channel_id, sanitize_key( $key ), self::sanitize_meta_value( $value ) );
		}

		foreach ( (array) ( $data['slides'] ?? array() ) as $slide_data ) {
			$slide_id = wp_insert_post(
				array(
					'post_type'   => 'ds_slide',
					'post_title'  => sanitize_text_field( $slide_data['title'] ?? __( 'Imported Slide', 'digital-signage' ) ),
					'post_status' => 'publish',
				)
			);
			foreach ( (array) ( $slide_data['meta'] ?? array() ) as $key => $value ) {
				update_post_meta( $slide_id, sanitize_key( $key ), self::sanitize_meta_value( $value ) );
			}
			update_post_meta( $slide_id, 'ds_channel_id', $channel_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel_id . '&ds_saved=1' ) );
		exit;
	}

	private static function flatten_meta( $meta ) {
		$out = array();
		foreach ( $meta as $key => $values ) {
			if ( 0 === strpos( $key, '_' ) ) {
				continue;
			}
			$out[ $key ] = maybe_unserialize( $values[0] ?? '' );
		}
		return $out;
	}

	private static function sanitize_meta_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}
		return is_string( $value ) ? sanitize_text_field( $value ) : $value;
	}
}
