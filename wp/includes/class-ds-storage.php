<?php
/**
 * Group-owned media storage and quota enforcement.
 *
 * @package Digital_Signage
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Storage {
	const DEFAULT_LIMIT = 2147483648;
	const LIMIT_OPTION_PREFIX = 'ds_group_storage_limit_';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_ds_media_upload', array( $this, 'ajax_upload' ) );
	}

	public static function limit( $group_id ) {
		return max( 0, (int) get_option( self::LIMIT_OPTION_PREFIX . absint( $group_id ), self::DEFAULT_LIMIT ) );
	}

	public static function set_limit( $group_id, $bytes ) {
		if ( current_user_can( 'manage_options' ) ) { update_option( self::LIMIT_OPTION_PREFIX . absint( $group_id ), max( 0, (int) $bytes ), false ); }
	}

	public static function items( $group_id, $limit = 200 ) {
		$posts_per_page = -1 === (int) $limit ? -1 : max( 1, absint( $limit ) );
		return get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => $posts_per_page,
			'orderby' => 'date', 'order' => 'DESC', 'meta_key' => DS_Groups::POST_META,
			'meta_value' => absint( $group_id ),
		) );
	}

	public static function used_bytes( $group_id ) {
		$bytes = 0;
		foreach ( self::items( $group_id, -1 ) as $attachment ) {
			$bytes += self::attachment_bytes( $attachment->ID );
		}
		foreach ( get_posts( array( 'post_type' => 'ds_design', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => DS_Groups::POST_META, 'meta_value' => absint( $group_id ) ) ) as $design ) {
			$bytes += strlen( (string) get_post_meta( $design->ID, 'ds_vellum_document', true ) );
		}
		return $bytes;
	}

	public static function attachment_bytes( $attachment_id ) {
		$file = get_attached_file( absint( $attachment_id ) );
		if ( ! $file ) { return 0; }
		$paths = array( $file );
		$metadata = wp_get_attachment_metadata( absint( $attachment_id ) );
		foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) {
			if ( ! empty( $size['file'] ) ) { $paths[] = trailingslashit( dirname( $file ) ) . $size['file']; }
		}
		$bytes = 0;
		foreach ( array_unique( $paths ) as $path ) { if ( is_file( $path ) ) { $bytes += (int) filesize( $path ); } }
		return $bytes;
	}

	public static function can_store( $group_id, $incoming_bytes ) {
		return self::used_bytes( $group_id ) + max( 0, (int) $incoming_bytes ) <= self::limit( $group_id );
	}

	public static function usage( $group_id ) {
		$used = self::used_bytes( $group_id );
		$limit = self::limit( $group_id );
		return array( 'used' => $used, 'limit' => $limit, 'percent' => $limit ? min( 100, round( $used / $limit * 100, 1 ) ) : 100 );
	}

	public function ajax_upload() {
		check_ajax_referer( 'ds_media_library', 'nonce' );
		if ( ! current_user_can( DS_Roles::CAP ) ) { wp_send_json_error( array( 'message' => __( 'Media access denied.', 'digital-signage' ) ), 403 ); }
		$group_id = absint( $_POST['group_id'] ?? 0 );
		if ( ! $group_id || ( ! current_user_can( 'manage_options' ) && ! in_array( $group_id, DS_Groups::user_group_ids(), true ) ) ) { wp_send_json_error( array( 'message' => __( 'Group access denied.', 'digital-signage' ) ), 403 ); }
		if ( empty( $_FILES['media_file']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE ) ) { wp_send_json_error( array( 'message' => __( 'Choose a valid file to upload.', 'digital-signage' ) ), 400 ); }
		$file_size = (int) ( $_FILES['media_file']['size'] ?? 0 );
		if ( ! self::can_store( $group_id, $file_size ) ) { wp_send_json_error( array( 'message' => __( 'This group has reached its media storage limit. Delete files or ask the site administrator for more space.', 'digital-signage' ) ), 413 ); }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$uploaded = wp_handle_upload( $_FILES['media_file'], array( 'test_form' => false ) );
		if ( ! empty( $uploaded['error'] ) ) { wp_send_json_error( array( 'message' => sanitize_text_field( $uploaded['error'] ) ), 400 ); }
		$mime = sanitize_mime_type( $uploaded['type'] ?? '' );
		if ( 0 !== strpos( $mime, 'image/' ) && 0 !== strpos( $mime, 'video/' ) && 0 !== strpos( $mime, 'audio/' ) && 'application/pdf' !== $mime ) { wp_delete_file( $uploaded['file'] ); wp_send_json_error( array( 'message' => __( 'This file type is not supported.', 'digital-signage' ) ), 415 ); }
		$title = sanitize_text_field( pathinfo( sanitize_file_name( $_FILES['media_file']['name'] ), PATHINFO_FILENAME ) );
		$attachment_id = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => $title, 'post_status' => 'inherit', 'post_author' => get_current_user_id() ), $uploaded['file'] );
		if ( is_wp_error( $attachment_id ) ) { wp_delete_file( $uploaded['file'] ); wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ), 500 ); }
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] ) );
		DS_Groups::set_post_group( $attachment_id, $group_id );
		wp_send_json_success( array( 'id' => $attachment_id, 'title' => get_the_title( $attachment_id ), 'url' => wp_get_attachment_url( $attachment_id ), 'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'medium' ), 'type' => strtok( $mime, '/' ), 'usage' => self::usage( $group_id ) ) );
	}
}
