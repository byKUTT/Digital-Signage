<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proof-of-play analytics: lists what played, where, and when, with CSV export
 * for advertising/compliance reporting.
 */
class DS_Analytics {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_ds_export_proof_of_play', array( __CLASS__, 'export_csv' ) );
	}

	public static function render_page() {
		global $wpdb;

		$screen_id  = absint( $_GET['screen_id'] ?? 0 );
		$where      = '';
		$params     = array();
		if ( $screen_id ) {
			$where    = 'WHERE screen_id = %d';
			$params[] = $screen_id;
		}

		$sql = "SELECT * FROM {$wpdb->prefix}ds_proof_of_play {$where} ORDER BY played_at DESC LIMIT 200";
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

		$screens = get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1 ) );

		include DS_PLUGIN_DIR . 'admin/views/analytics.php';
	}

	public static function export_csv() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_export_proof_of_play' );

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ds_proof_of_play ORDER BY played_at DESC LIMIT 50000" );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="proof-of-play-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Played At (UTC)', 'Screen', 'Channel', 'Slide', 'Zone', 'Duration (s)' ) );

		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row->played_at,
					get_the_title( $row->screen_id ),
					get_the_title( $row->channel_id ),
					get_the_title( $row->slide_id ),
					$row->zone,
					$row->duration_seconds,
				)
			);
		}

		fclose( $out );
		exit;
	}
}
