<?php
/** Vellum design storage and one-click publishing. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Designer {
	private static $instance = null;
	const MAX_DOCUMENT_BYTES = 8388608;
	const MAX_IMAGE_BYTES = 20971520;

	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	private function __construct() { add_action( 'wp_ajax_ds_vellum_save', array( $this, 'save' ) ); }

	public static function document( $design_id ) {
		$post = get_post( absint( $design_id ) );
		if ( ! $post || 'ds_design' !== $post->post_type || ! DS_Groups::can_access_post( $post ) ) { return ''; }
		return (string) get_post_meta( $post->ID, 'ds_vellum_document', true );
	}

	public function save() {
		if ( ! current_user_can( DS_Roles::CAP ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'digital-signage' ) ), 403 ); }
		check_ajax_referer( 'ds_vellum_save', 'nonce' );
		$group_id = absint( $_POST['group_id'] ?? 0 );
		if ( ! $group_id || ( ! current_user_can( 'manage_options' ) && ! in_array( $group_id, DS_Groups::user_group_ids(), true ) ) ) { wp_send_json_error( array( 'message' => __( 'Group access denied.', 'digital-signage' ) ), 403 ); }

		$design_id = absint( $_POST['design_id'] ?? 0 );
		if ( $design_id && ( 'ds_design' !== get_post_type( $design_id ) || ! DS_Groups::can_access_post( $design_id ) || $group_id !== DS_Groups::post_group_id( $design_id ) ) ) { wp_send_json_error( array( 'message' => __( 'Design access denied.', 'digital-signage' ) ), 403 ); }
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$document = wp_unslash( $_POST['document'] ?? '' );
		if ( '' === $title ) { $title = __( 'Untitled design', 'digital-signage' ); }
		if ( strlen( $document ) > self::MAX_DOCUMENT_BYTES ) { wp_send_json_error( array( 'message' => __( 'The design file is too large.', 'digital-signage' ) ), 413 ); }
		$decoded = json_decode( $document, true );
		if ( ! is_array( $decoded ) || 'vellum' !== ( $decoded['format'] ?? '' ) || empty( $decoded['pages'] ) ) { wp_send_json_error( array( 'message' => __( 'Vellum returned an invalid document.', 'digital-signage' ) ), 400 ); }

		$post_data = array( 'post_title' => $title, 'post_type' => 'ds_design', 'post_status' => 'publish' );
		if ( $design_id ) { $post_data['ID'] = $design_id; }
		$design_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $design_id ) ) { wp_send_json_error( array( 'message' => $design_id->get_error_message() ), 500 ); }
		DS_Groups::set_post_group( $design_id, $group_id );
		update_post_meta( $design_id, 'ds_vellum_document', $document );

		$attachment_id = $this->save_preview( wp_unslash( $_POST['image'] ?? '' ), $title, $group_id );
		if ( is_wp_error( $attachment_id ) ) { wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ), 400 ); }
		update_post_meta( $design_id, 'ds_preview_attachment_id', $attachment_id );

		$slide_id = 0;
		if ( ! empty( $_POST['publish'] ) ) {
			$channel_id = absint( $_POST['channel_id'] ?? 0 );
			if ( ! $channel_id || 'ds_channel' !== get_post_type( $channel_id ) || ! DS_Groups::can_access_post( $channel_id ) || $group_id !== DS_Groups::post_group_id( $channel_id ) ) { wp_send_json_error( array( 'message' => __( 'Choose a channel you can access.', 'digital-signage' ) ), 400 ); }
			$slide_id = absint( get_post_meta( $design_id, 'ds_published_slide_id', true ) );
			if ( $slide_id && ( 'ds_slide' !== get_post_type( $slide_id ) || ! DS_Groups::can_access_post( $slide_id ) ) ) { $slide_id = 0; }
			$slide_id = DS_CRUD::save_slide( $slide_id, array( 'title' => $title, 'channel_id' => $channel_id, 'slide_type' => 'image', 'media_id' => $attachment_id, 'duration_override' => absint( $_POST['duration'] ?? 0 ), 'zone' => 'main', 'fit' => 'contain' ) );
			DS_Groups::set_post_group( $slide_id, $group_id );
			update_post_meta( $design_id, 'ds_published_slide_id', $slide_id );
		}

		wp_send_json_success( array( 'designId' => $design_id, 'slideId' => $slide_id, 'previewUrl' => wp_get_attachment_url( $attachment_id ), 'message' => $slide_id ? __( 'Design saved and published.', 'digital-signage' ) : __( 'Design saved.', 'digital-signage' ) ) );
	}

	private function save_preview( $data_url, $title, $group_id ) {
		if ( ! preg_match( '#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', (string) $data_url, $matches ) ) { return new WP_Error( 'ds_preview', __( 'The design preview could not be encoded.', 'digital-signage' ) ); }
		$binary = base64_decode( $matches[1], true );
		if ( false === $binary || ! strlen( $binary ) || strlen( $binary ) > self::MAX_IMAGE_BYTES || "\x89PNG" !== substr( $binary, 0, 4 ) ) { return new WP_Error( 'ds_preview', __( 'The design preview is invalid or too large.', 'digital-signage' ) ); }
		$upload = wp_upload_bits( sanitize_file_name( $title ) . '-' . time() . '.png', null, $binary );
		if ( ! empty( $upload['error'] ) ) { return new WP_Error( 'ds_upload', $upload['error'] ); }
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit' ), $upload['file'] );
		if ( is_wp_error( $attachment_id ) ) { return $attachment_id; }
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		DS_Groups::set_post_group( $attachment_id, $group_id );
		return $attachment_id;
	}
}
