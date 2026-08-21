<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-Cron housekeeping: purge expired pairing codes and trim the proof-of-play
 * log so it doesn't grow unbounded. Uses WP Cron rather than a custom scheduler.
 */
class DS_Cron {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'ds_hourly_maintenance', array( $this, 'run_maintenance' ) );

		if ( ! wp_next_scheduled( 'ds_hourly_maintenance' ) ) {
			wp_schedule_event( time(), 'hourly', 'ds_hourly_maintenance' );
		}
	}

	public function run_maintenance() {
		global $wpdb;

		// Expired, never-claimed pairing codes.
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}ds_pairing_codes
			 WHERE paired_at IS NULL AND expires_at < UTC_TIMESTAMP()"
		);

		// Keep 180 days of proof-of-play by default.
		$retention_days = (int) apply_filters( 'ds_proof_of_play_retention_days', 180 );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ds_proof_of_play WHERE played_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS )
			)
		);

		// Clear stale manual "priority" overrides on screens after 24h so nothing gets stuck.
		$screens = get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		foreach ( $screens as $screen_id ) {
			$expires = (int) get_post_meta( $screen_id, 'ds_manual_override_expires', true );
			if ( $expires && $expires < time() ) {
				delete_post_meta( $screen_id, 'ds_manual_override_channel' );
				delete_post_meta( $screen_id, 'ds_manual_override_expires' );
			}
		}
	}
}
