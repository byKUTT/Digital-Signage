<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces a screen's online/offline status in wp-admin. Actual heartbeat writes
 * happen via the REST endpoint (DS_REST::post_heartbeat); this class just adds
 * a small admin-ajax helper the Dashboard uses to refresh status without a full
 * page reload, and taps WP's own Heartbeat API to keep the admin dashboard live.
 */
class DS_Heartbeat {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_ds_get_screen_statuses', array( $this, 'ajax_get_statuses' ) );
		add_filter( 'heartbeat_received', array( $this, 'on_admin_heartbeat' ), 10, 2 );
	}

	public function ajax_get_statuses() {
		check_ajax_referer( 'ds_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		global $wpdb;
		$rows          = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ds_heartbeats", ARRAY_A );
		$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );
		$out           = array();

		foreach ( $rows as $row ) {
			$row['status'] = ( time() - strtotime( $row['last_seen'] . ' UTC' ) ) <= $offline_after ? 'online' : 'offline';
			$out[ $row['screen_id'] ] = $row;
		}

		wp_send_json_success( $out );
	}

	/**
	 * Piggy-backs on WP core's Heartbeat API so the Screens dashboard in wp-admin
	 * live-refreshes status badges while an admin has it open, without a bespoke poller.
	 */
	public function on_admin_heartbeat( $response, $data ) {
		if ( empty( $data['ds_dashboard'] ) ) {
			return $response;
		}

		global $wpdb;
		$rows          = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ds_heartbeats", ARRAY_A );
		$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );
		$out           = array();

		foreach ( $rows as $row ) {
			$row['status'] = ( time() - strtotime( $row['last_seen'] . ' UTC' ) ) <= $offline_after ? 'online' : 'offline';
			$out[ $row['screen_id'] ] = $row;
		}

		$response['ds_screen_statuses'] = $out;
		return $response;
	}
}
