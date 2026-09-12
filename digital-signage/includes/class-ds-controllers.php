<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller/domain model for one Linux or Windows PC driving many displays.
 */
class DS_Controllers {

	private static $instance = null;

	const MAX_OUTPUTS = 16;
	const ONLINE_SECONDS = 45;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function table( $name ) {
		global $wpdb;
		$allowed = array( 'displays', 'commands' );
		if ( ! in_array( $name, $allowed, true ) ) {
			return '';
		}
		return $wpdb->prefix . 'ds_controller_' . $name;
	}

	public static function controllers_table() {
		global $wpdb;
		return $wpdb->prefix . 'ds_controllers';
	}

	public static function token_hash( $token ) {
		return hash( 'sha256', (string) $token );
	}

	public static function create_request( $platform, $hostname, array $outputs, $legacy_screen_token = '' ) {
		global $wpdb;
		$platform = in_array( $platform, array( 'linux', 'windows' ), true ) ? $platform : 'linux';
		$token    = wp_generate_password( 64, false, false );
		$public_id = wp_generate_uuid4();
		$code      = self::generate_pairing_code();
		if ( ! $code ) {
			return new WP_Error( 'ds_controller_code', __( 'Could not generate a controller pairing code.', 'digital-signage' ), array( 'status' => 500 ) );
		}

		$inserted = $wpdb->insert(
			self::controllers_table(),
			array(
				'public_id'        => $public_id,
				'token_hash'       => self::token_hash( $token ),
				'pairing_code'     => $code,
				'pairing_code_expires' => gmdate( 'Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS ),
				'hostname'         => sanitize_text_field( mb_substr( $hostname, 0, 190 ) ),
				'platform'         => $platform,
				'agent_version'    => DS_DEVICE_VERSION,
				'software_version' => DS_DEVICE_VERSION,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'ds_controller_create', __( 'Could not register the controller.', 'digital-signage' ), array( 'status' => 500 ) );
		}

		$controller_id = (int) $wpdb->insert_id;
		self::reconcile_displays( $controller_id, $outputs, false );

		return array(
			'public_id'   => $public_id,
			'token'       => $token,
			'code'        => $code,
			'pairing_url' => home_url( '/signage/controller/' . $public_id . '/main/' ),
		);
	}

	private static function generate_pairing_code() {
		global $wpdb;
		$table    = self::controllers_table();
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$code = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
			}
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE pairing_code = %s", $code ) );
			if ( ! $exists ) {
				return $code;
			}
		}
		return '';
	}

	public static function get_by_public_id( $public_id ) {
		global $wpdb;
		$table = self::controllers_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id = %s", sanitize_text_field( $public_id ) ) );
	}

	public static function authenticate( $public_id, $token ) {
		$controller = self::get_by_public_id( $public_id );
		if ( ! $controller || ! $token ) {
			return null;
		}
		return hash_equals( (string) $controller->token_hash, self::token_hash( $token ) ) ? $controller : null;
	}

	public static function pair_by_code( $code, $name, $owner_user_id = 0, $group_id = 0 ) {
		global $wpdb;
		$table = self::controllers_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE pairing_code = %s AND paired_at IS NULL AND pairing_code_expires >= %s", strtoupper( sanitize_text_field( $code ) ), current_time( 'mysql', true ) ) );
		if ( ! $row ) {
			return new WP_Error( 'ds_invalid_controller_code', __( 'That controller pairing code is invalid or already used.', 'digital-signage' ) );
		}

		$name = sanitize_text_field( $name );
		if ( ! $name ) {
			$name = $row->hostname ? $row->hostname : __( 'Linux Controller', 'digital-signage' );
		}
		$wpdb->update(
			$table,
			array( 'name' => $name, 'paired_at' => current_time( 'mysql', true ) ),
			array( 'id' => $row->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$owner_user_id = absint( $owner_user_id ?: get_current_user_id() );
		$group_id      = absint( $group_id ?: DS_Groups::ensure_user_group( $owner_user_id ) );
		DS_Groups::set_controller_owner( (int) $row->id, $owner_user_id );
		DS_Groups::set_controller_group( (int) $row->id, $group_id );
		self::ensure_display_screens( (int) $row->id, $name );
		return (int) $row->id;
	}

	public static function reconcile_displays( $controller_id, array $outputs, $create_screens = true ) {
		global $wpdb;
		$table         = self::table( 'displays' );
		$controller_id = absint( $controller_id );
		$seen          = array();
		$outputs       = array_slice( $outputs, 0, self::MAX_OUTPUTS );
		foreach ( $outputs as $index => $output ) {
			if ( ! is_array( $output ) ) {
				continue;
			}
			$output = self::sanitize_output( $output, $index );
			$key    = $output['output_key'];
			$seen[] = $key;
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE controller_id = %d AND output_key = %s", $controller_id, $key ) );
			$data = array(
				'connector'    => $output['connector'],
				'label'        => $output['label'],
				'geometry'     => $output['geometry'],
				'resolution'   => $output['resolution'],
				'is_primary'   => $output['is_primary'],
				'is_connected' => 1,
				'last_seen'    => current_time( 'mysql', true ),
			);
			if ( $existing ) {
				$wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			} else {
				$data['controller_id'] = $controller_id;
				$data['output_key']     = $key;
				$data['screen_id']      = 0;
				$wpdb->insert( $table, $data );
			}
		}

		$existing_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, output_key FROM {$table} WHERE controller_id = %d", $controller_id ) );
		foreach ( $existing_rows as $existing ) {
			if ( ! in_array( $existing->output_key, $seen, true ) ) {
				$wpdb->update( $table, array( 'is_connected' => 0 ), array( 'id' => $existing->id ), array( '%d' ), array( '%d' ) );
			}
		}

		$controller = self::get( $controller_id );
		if ( $create_screens && $controller && $controller->paired_at ) {
			self::ensure_display_screens( $controller_id, $controller->name );
		}
	}

	private static function sanitize_output( array $output, $index ) {
		$connector = sanitize_text_field( mb_substr( (string) ( $output['connector'] ?? '' ), 0, 100 ) );
		$key       = sanitize_key( (string) ( $output['output_key'] ?? '' ) );
		if ( ! $key ) {
			$key = sanitize_key( $connector ? $connector : 'display-' . ( (int) $index + 1 ) );
		}
		return array(
			'output_key'  => mb_substr( $key, 0, 100 ),
			'connector'   => $connector,
			'label'       => sanitize_text_field( mb_substr( (string) ( $output['label'] ?? $connector ), 0, 190 ) ),
			'geometry'    => preg_match( '/^-?\d{1,5},-?\d{1,5},\d{2,5},\d{2,5}$/', (string) ( $output['geometry'] ?? '' ) ) ? (string) $output['geometry'] : '',
			'resolution'  => preg_match( '/^\d{2,5}x\d{2,5}$/', (string) ( $output['resolution'] ?? '' ) ) ? (string) $output['resolution'] : '',
			'is_primary'  => empty( $output['is_primary'] ) ? 0 : 1,
		);
	}

	private static function ensure_display_screens( $controller_id, $controller_name ) {
		global $wpdb;
		$table = self::table( 'displays' );
		$owner_id = DS_Groups::controller_owner_id( $controller_id );
		$group_id = DS_Groups::controller_group_id( $controller_id );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE controller_id = %d AND is_connected = 1 ORDER BY id ASC", $controller_id ) );
		foreach ( $rows as $index => $row ) {
			if ( $row->screen_id && 'ds_screen' === get_post_type( $row->screen_id ) ) {
				continue;
			}
			$label     = $row->label ? $row->label : ( $row->connector ? $row->connector : sprintf( __( 'Display %d', 'digital-signage' ), $index + 1 ) );
			$screen_id = DS_CRUD::save_screen(
				0,
				array(
					'title'       => $controller_name . ' — ' . $label,
					'orientation' => 'auto',
				)
			);
			if ( $owner_id ) { wp_update_post( array( 'ID' => $screen_id, 'post_author' => $owner_id ) ); }
			if ( $group_id ) { DS_Groups::set_post_group( $screen_id, $group_id ); }
			update_post_meta( $screen_id, 'ds_pairing_token', wp_generate_password( 40, false, false ) );
			update_post_meta( $screen_id, 'ds_controller_id', absint( $controller_id ) );
			update_post_meta( $screen_id, 'ds_controller_output', $row->output_key );
			$wpdb->update( $table, array( 'screen_id' => $screen_id ), array( 'id' => $row->id ), array( '%d' ), array( '%d' ) );
		}
	}

	private static function attach_legacy_screen( $controller_id, $token ) {
		global $wpdb;
		$token = sanitize_text_field( $token );
		if ( ! $token ) {
			return;
		}
		$screens = get_posts(
			array(
				'post_type' => 'ds_screen', 'post_status' => 'any', 'posts_per_page' => 1,
				'meta_key' => 'ds_pairing_token', 'meta_value' => $token,
			)
		);
		if ( ! $screens ) {
			return;
		}
		$table = self::table( 'displays' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE controller_id = %d ORDER BY is_primary DESC, id ASC LIMIT 1", $controller_id ) );
		if ( $row && ! $row->screen_id ) {
			$wpdb->update( $table, array( 'screen_id' => $screens[0]->ID ), array( 'id' => $row->id ), array( '%d' ), array( '%d' ) );
			update_post_meta( $screens[0]->ID, 'ds_controller_id', absint( $controller_id ) );
			update_post_meta( $screens[0]->ID, 'ds_controller_output', $row->output_key );
		}
	}

	public static function update_heartbeat( $controller, array $payload, $ip_address ) {
		global $wpdb;
		$telemetry = self::sanitize_telemetry( (array) ( $payload['telemetry'] ?? array() ) );
		$outputs   = (array) ( $payload['outputs'] ?? array() );
		$data      = array(
			'hostname'         => sanitize_text_field( mb_substr( (string) ( $payload['hostname'] ?? $controller->hostname ), 0, 190 ) ),
			'os_version'       => sanitize_text_field( mb_substr( (string) ( $payload['os_version'] ?? '' ), 0, 190 ) ),
			'agent_version'    => sanitize_text_field( mb_substr( (string) ( $payload['agent_version'] ?? '' ), 0, 30 ) ),
			'software_version' => sanitize_text_field( mb_substr( (string) ( $payload['software_version'] ?? '' ), 0, 30 ) ),
			'ip_address'       => sanitize_text_field( mb_substr( $ip_address, 0, 45 ) ),
			'telemetry'        => wp_json_encode( $telemetry ),
			'last_seen'        => current_time( 'mysql', true ),
		);
		$wpdb->update( self::controllers_table(), $data, array( 'id' => $controller->id ) );
		self::reconcile_displays( (int) $controller->id, $outputs, true );
		return self::build_agent_state( (int) $controller->id );
	}

	private static function sanitize_telemetry( array $telemetry ) {
		$allowed_strings = array( 'architecture', 'cpu_model', 'gpu', 'browser', 'network', 'kernel', 'power_state', 'update_status', 'update_result', 'cursor_error', 'last_error' );
		$allowed_numbers = array( 'cpu_cores', 'cpu_load_percent', 'cpu_temp_c', 'memory_total_mb', 'memory_free_mb', 'disk_total_mb', 'disk_free_mb', 'uptime_seconds', 'connected_outputs', 'player_processes' );
		$allowed_bools   = array( 'browser_running', 'screens_paused', 'cursor_hidden', 'display_sleeping', 'rtc_wake_supported', 'suspend_supported', 'os_update_supported', 'automatic_reboot_enabled' );
		$out = array();
		foreach ( $allowed_strings as $key ) {
			if ( isset( $telemetry[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( mb_substr( (string) $telemetry[ $key ], 0, 300 ) );
			}
		}
		foreach ( $allowed_numbers as $key ) {
			if ( isset( $telemetry[ $key ] ) && is_numeric( $telemetry[ $key ] ) ) {
				$out[ $key ] = 0 + $telemetry[ $key ];
			}
		}
		foreach ( $allowed_bools as $key ) {
			if ( isset( $telemetry[ $key ] ) ) {
				$out[ $key ] = (bool) $telemetry[ $key ];
			}
		}
		if ( isset( $telemetry['recent_log'] ) && is_array( $telemetry['recent_log'] ) ) {
			$out['recent_log'] = array();
			foreach ( array_slice( $telemetry['recent_log'], -30 ) as $line ) {
				$out['recent_log'][] = sanitize_text_field( mb_substr( (string) $line, 0, 300 ) );
			}
		}
		return $out;
	}

	public static function build_agent_state( $controller_id ) {
		$controller = self::get( $controller_id );
		return array(
			'paired'      => $controller && ! empty( $controller->paired_at ),
			'assignments' => self::get_assignments( $controller_id ),
			'schedule'    => $controller ? json_decode( (string) $controller->power_schedule, true ) : null,
			'command'     => self::next_command( $controller_id ),
			'server_time' => time(),
			'server_timezone' => wp_timezone_string() ?: 'UTC',
		);
	}

	public static function get_assignments( $controller_id ) {
		global $wpdb;
		$table = self::table( 'displays' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE controller_id = %d ORDER BY id ASC", $controller_id ) );
		$out   = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'output_key'   => $row->output_key,
				'connector'    => $row->connector,
				'screen_id'    => (int) $row->screen_id,
				'player_url'   => $row->screen_id ? DS_Player::get_player_url( $row->screen_id ) : '',
				'pairing_url'  => home_url( '/signage/controller/' . self::get_public_id( $controller_id ) . '/' . rawurlencode( $row->output_key ) . '/' ),
			);
		}
		return $out;
	}

	private static function get_public_id( $controller_id ) {
		$controller = self::get( $controller_id );
		return $controller ? $controller->public_id : '';
	}

	public static function get( $controller_id ) {
		global $wpdb;
		$table = self::controllers_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $controller_id ) ) );
	}

	public static function get_all() {
		global $wpdb;
		$table = self::controllers_table();
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE paired_at IS NOT NULL ORDER BY name ASC, hostname ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_displays( $controller_id ) {
		global $wpdb;
		$table = self::table( 'displays' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE controller_id = %d ORDER BY is_primary DESC, id ASC", absint( $controller_id ) ) );
	}

	public static function get_commands( $controller_id, $limit = 30 ) {
		global $wpdb;
		$table = self::table( 'commands' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE controller_id = %d ORDER BY id DESC LIMIT %d", absint( $controller_id ), min( 100, max( 1, absint( $limit ) ) ) ) );
	}

	/**
	 * Return the newest software-update command for every controller.
	 *
	 * @return array<int,object>
	 */
	public static function get_latest_update_commands() {
		global $wpdb;
		$table = self::table( 'commands' );
		$rows  = $wpdb->get_results(
			"SELECT cmd.* FROM {$table} cmd INNER JOIN (SELECT controller_id, MAX(id) AS newest_id FROM {$table} WHERE command_type = 'software_update' GROUP BY controller_id) latest ON cmd.id = latest.newest_id" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$out = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->controller_id ] = $row;
		}
		return $out;
	}

	/**
	 * Check for an update to the same version that has not finished.
	 */
	public static function has_active_software_update( $controller_id, $version ) {
		global $wpdb;
		$table = self::table( 'commands' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT payload FROM {$table} WHERE controller_id = %d AND command_type = 'software_update' AND (status IN ('queued','delivered','running') OR (status = 'succeeded' AND completed_at >= %s)) ORDER BY id DESC LIMIT 20",
				absint( $controller_id ),
				gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS )
			)
		);
		foreach ( $rows as $row ) {
			$payload = json_decode( (string) $row->payload, true );
			if ( is_array( $payload ) && isset( $payload['version'] ) && hash_equals( (string) $version, (string) $payload['version'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function queue_command( $controller_id, $type, array $payload = array() ) {
		global $wpdb;
		$allowed = array( 'restart_players', 'stop_players', 'start_players', 'refresh_displays', 'reboot', 'software_update', 'system_update', 'power_test', 'switch_url' );
		$type    = sanitize_key( $type );
		if ( ! in_array( $type, $allowed, true ) ) {
			return new WP_Error( 'ds_command_type', __( 'Unsupported controller command.', 'digital-signage' ) );
		}
		$inserted = $wpdb->insert(
			self::table( 'commands' ),
			array(
				'controller_id' => absint( $controller_id ),
				'command_type'  => $type,
				'payload'       => wp_json_encode( $payload ),
				'status'        => 'queued',
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( false === $inserted || ! $wpdb->insert_id ) {
			return new WP_Error( 'command_database', __( 'WordPress could not save the command. Check that the plugin database tables are up to date.', 'digital-signage' ) );
		}
		return (int) $wpdb->insert_id;
	}

	private static function next_command( $controller_id ) {
		global $wpdb;
		$table = self::table( 'commands' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE controller_id = %d AND (status = 'queued' OR (status IN ('delivered','running') AND delivered_at < %s)) ORDER BY id ASC LIMIT 1",
				absint( $controller_id ),
				gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS )
			)
		);
		if ( ! $row ) {
			return null;
		}
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'delivered', attempts = attempts + 1, delivered_at = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$row->id
			)
		);
		return array(
			'id'      => (int) $row->id,
			'type'    => $row->command_type,
			'payload' => json_decode( (string) $row->payload, true ),
		);
	}

	public static function acknowledge_command( $controller_id, $command_id, $status, $result = '' ) {
		global $wpdb;
		$allowed = array( 'running', 'succeeded', 'failed' );
		$status  = sanitize_key( $status );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		$data = array(
			'status' => $status,
			'result' => sanitize_textarea_field( mb_substr( (string) $result, 0, 4000 ) ),
		);
		if ( in_array( $status, array( 'succeeded', 'failed' ), true ) ) {
			$data['completed_at'] = current_time( 'mysql', true );
		}
		return false !== $wpdb->update(
			self::table( 'commands' ),
			$data,
			array( 'id' => absint( $command_id ), 'controller_id' => absint( $controller_id ) )
		);
	}

	public static function save_schedule( $controller_id, array $input ) {
		global $wpdb;
		$timezone = wp_timezone_string() ?: 'UTC';
		if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$timezone = 'UTC';
		}
		$days = array_values( array_intersect( array_map( 'absint', (array) ( $input['days'] ?? array() ) ), range( 0, 6 ) ) );
		$time_pattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
		$wake = sanitize_text_field( $input['wake_time'] ?? '07:00' );
		$sleep = sanitize_text_field( $input['sleep_time'] ?? '22:00' );
		$schedule = array(
			'enabled'    => ! empty( $input['enabled'] ),
			'timezone'   => $timezone,
			'days'       => $days,
			'wake_time'  => preg_match( $time_pattern, $wake ) ? $wake : '07:00',
			'sleep_time' => preg_match( $time_pattern, $sleep ) ? $sleep : '22:00',
		);
		$wpdb->update( self::controllers_table(), array( 'power_schedule' => wp_json_encode( $schedule ) ), array( 'id' => absint( $controller_id ) ) );
		return $schedule;
	}

	public static function update_controller( $controller_id, $name, array $display_screen_ids ) {
		global $wpdb;
		$controller_id = absint( $controller_id );
		$name          = sanitize_text_field( mb_substr( $name, 0, 190 ) );
		$wpdb->update( self::controllers_table(), array( 'name' => $name ), array( 'id' => $controller_id ), array( '%s' ), array( '%d' ) );
		$table = self::table( 'displays' );
		foreach ( $display_screen_ids as $display_id => $screen_id ) {
			$display_id = absint( $display_id );
			$screen_id  = absint( $screen_id );
			if ( ! $display_id || ( $screen_id && 'ds_screen' !== get_post_type( $screen_id ) ) ) {
				continue;
			}
			$display = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND controller_id = %d", $display_id, $controller_id ) );
			if ( ! $display ) {
				continue;
			}
			if ( $screen_id ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET screen_id = 0 WHERE screen_id = %d AND id <> %d", $screen_id, $display_id ) );
				update_post_meta( $screen_id, 'ds_controller_id', $controller_id );
				update_post_meta( $screen_id, 'ds_controller_output', $display->output_key );
			}
			if ( $display->screen_id && (int) $display->screen_id !== $screen_id ) {
				delete_post_meta( $display->screen_id, 'ds_controller_id' );
				delete_post_meta( $display->screen_id, 'ds_controller_output' );
			}
			$wpdb->update( $table, array( 'screen_id' => $screen_id ), array( 'id' => $display_id, 'controller_id' => $controller_id ), array( '%d' ), array( '%d', '%d' ) );
		}
	}

	public static function public_output_status( $public_id, $output_key ) {
		global $wpdb;
		$controller = self::get_by_public_id( $public_id );
		if ( ! $controller ) {
			return null;
		}
		if ( empty( $controller->paired_at ) && ( ! $controller->pairing_code_expires || strtotime( $controller->pairing_code_expires . ' UTC' ) <= time() ) ) {
			$fresh_code = self::generate_pairing_code();
			if ( $fresh_code ) {
				$wpdb->update(
					self::controllers_table(),
					array( 'pairing_code' => $fresh_code, 'pairing_code_expires' => gmdate( 'Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS ) ),
					array( 'id' => $controller->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				$controller->pairing_code = $fresh_code;
			}
		}
		$table = self::table( 'displays' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE controller_id = %d AND output_key = %s", $controller->id, sanitize_key( $output_key ) ) );
		return array(
			'paired'          => ! empty( $controller->paired_at ),
			'code'            => $controller->pairing_code,
			'controller_name' => $controller->name ? $controller->name : $controller->hostname,
			'output_label'    => $row ? ( $row->label ? $row->label : $row->connector ) : sanitize_text_field( $output_key ),
			'player_url'      => $row && $row->screen_id ? DS_Player::get_player_url( $row->screen_id ) : '',
		);
	}

	public static function unlink_screen( $screen_id ) {
		global $wpdb;
		$wpdb->update( self::table( 'displays' ), array( 'screen_id' => 0 ), array( 'screen_id' => absint( $screen_id ) ), array( '%d' ), array( '%d' ) );
	}

	public static function delete( $controller_id ) {
		global $wpdb; $controller_id = absint( $controller_id );
		foreach ( self::get_displays( $controller_id ) as $display ) { if ( $display->screen_id ) { delete_post_meta( $display->screen_id, 'ds_controller_id' ); delete_post_meta( $display->screen_id, 'ds_controller_output' ); } }
		$wpdb->delete( self::table( 'commands' ), array( 'controller_id' => $controller_id ), array( '%d' ) );
		$wpdb->delete( self::table( 'displays' ), array( 'controller_id' => $controller_id ), array( '%d' ) );
		$wpdb->delete( self::controllers_table(), array( 'id' => $controller_id ), array( '%d' ) );
		$map = (array) get_option( DS_Groups::CONTROLLER_OPTION, array() ); unset( $map[ $controller_id ] ); update_option( DS_Groups::CONTROLLER_OPTION, $map, false );
		$owners = (array) get_option( DS_Groups::CONTROLLER_OWNER_OPTION, array() ); unset( $owners[ $controller_id ] ); update_option( DS_Groups::CONTROLLER_OWNER_OPTION, $owners, false );
	}
}
