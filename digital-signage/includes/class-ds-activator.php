<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation/deactivation: creates custom tables used for high write-volume,
 * append-only data (heartbeats, proof-of-play log, pairing codes) that don't need to be
 * post types, and provisions the "Signage Manager" role.
 */
class DS_Activator {

	public static function activate() {
		self::create_tables();
		DS_Roles::add_role();
		update_option( 'ds_db_version', DS_DB_VERSION );
		update_option( 'ds_flush_rewrite_rules', 1 );

		if ( ! get_option( 'ds_settings' ) ) {
			update_option( 'ds_settings', self::default_settings() );
		}
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'ds_db_version' ) !== DS_DB_VERSION ) {
			self::create_tables();
			update_option( 'ds_db_version', DS_DB_VERSION );
		}

		// Re-flush rewrite rules on every version bump — new routes (e.g. the
		// /signage/preview/ URL) need this even for an in-place plugin upgrade.
		if ( get_option( 'ds_rewrite_version' ) !== DS_VERSION ) {
			update_option( 'ds_flush_rewrite_rules', 1 );
			update_option( 'ds_rewrite_version', DS_VERSION );
		}
	}

	public static function default_settings() {
		return array(
			'duration_image'     => 10,
			'duration_video'     => 15,
			'duration_webpage'   => 20,
			'duration_html'      => 15,
			'duration_rss'       => 20,
			'duration_weather'   => 10,
			'duration_clock'     => 10,
			'duration_pdf'       => 15,
			'duration_social'    => 15,
			'duration_infinite_scroll' => 30,
			'transition'         => 'fade',
			'poll_interval'      => 60,
			'heartbeat_interval' => 30,
			'timezone'           => get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : 'UTC',
			'offline_status_sec' => 120,
		);
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$heartbeats = $wpdb->prefix . 'ds_heartbeats';
		$pop        = $wpdb->prefix . 'ds_proof_of_play';
		$pairing    = $wpdb->prefix . 'ds_pairing_codes';
		$controllers = $wpdb->prefix . 'ds_controllers';
		$displays    = $wpdb->prefix . 'ds_controller_displays';
		$commands    = $wpdb->prefix . 'ds_controller_commands';

		$sql = "CREATE TABLE {$heartbeats} (
			screen_id BIGINT UNSIGNED NOT NULL,
			last_seen DATETIME NOT NULL,
			ip_address VARCHAR(45) DEFAULT '',
			resolution VARCHAR(20) DEFAULT '',
			orientation VARCHAR(10) DEFAULT '',
			user_agent VARCHAR(255) DEFAULT '',
			current_channel_id BIGINT UNSIGNED DEFAULT 0,
			app_version VARCHAR(20) DEFAULT '',
			device_info LONGTEXT NULL,
			PRIMARY KEY  (screen_id)
		) {$charset_collate};

		CREATE TABLE {$pop} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			screen_id BIGINT UNSIGNED NOT NULL,
			channel_id BIGINT UNSIGNED NOT NULL,
			slide_id BIGINT UNSIGNED NOT NULL,
			zone VARCHAR(40) DEFAULT 'main',
			played_at DATETIME NOT NULL,
			duration_seconds SMALLINT UNSIGNED DEFAULT 0,
			PRIMARY KEY  (id),
			KEY screen_id (screen_id),
			KEY played_at (played_at)
		) {$charset_collate};

		CREATE TABLE {$pairing} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(10) NOT NULL,
			token VARCHAR(64) NOT NULL,
			screen_id BIGINT UNSIGNED DEFAULT 0,
			attempts SMALLINT UNSIGNED DEFAULT 0,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			paired_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY token (token)
		) {$charset_collate};

		CREATE TABLE {$controllers} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id VARCHAR(40) NOT NULL,
			token_hash CHAR(64) NOT NULL,
			pairing_code VARCHAR(10) NOT NULL,
			pairing_code_expires DATETIME DEFAULT NULL,
			name VARCHAR(190) DEFAULT '',
			hostname VARCHAR(190) DEFAULT '',
			platform VARCHAR(20) DEFAULT '',
			os_version VARCHAR(190) DEFAULT '',
			agent_version VARCHAR(30) DEFAULT '',
			software_version VARCHAR(30) DEFAULT '',
			ip_address VARCHAR(45) DEFAULT '',
			telemetry LONGTEXT NULL,
			power_schedule LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			paired_at DATETIME DEFAULT NULL,
			last_seen DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY token_hash (token_hash),
			UNIQUE KEY pairing_code (pairing_code),
			KEY last_seen (last_seen)
		) {$charset_collate};

		CREATE TABLE {$displays} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			controller_id BIGINT UNSIGNED NOT NULL,
			output_key VARCHAR(100) NOT NULL,
			connector VARCHAR(100) DEFAULT '',
			label VARCHAR(190) DEFAULT '',
			geometry VARCHAR(80) DEFAULT '',
			resolution VARCHAR(30) DEFAULT '',
			is_primary TINYINT(1) DEFAULT 0,
			is_connected TINYINT(1) DEFAULT 1,
			screen_id BIGINT UNSIGNED DEFAULT 0,
			last_seen DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY controller_output (controller_id,output_key),
			KEY screen_id (screen_id)
		) {$charset_collate};

		CREATE TABLE {$commands} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			controller_id BIGINT UNSIGNED NOT NULL,
			command_type VARCHAR(50) NOT NULL,
			payload LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			attempts SMALLINT UNSIGNED DEFAULT 0,
			result LONGTEXT NULL,
			created_by BIGINT UNSIGNED DEFAULT 0,
			created_at DATETIME NOT NULL,
			delivered_at DATETIME DEFAULT NULL,
			completed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY controller_status (controller_id,status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
