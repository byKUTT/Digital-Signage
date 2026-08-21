<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global plugin settings: default per-type slide durations, default transition,
 * player poll interval, heartbeat interval and timezone. Stored as a single
 * option array ('ds_settings') and edited via the Settings API.
 */
class DS_Settings {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function get_all() {
		return wp_parse_args( get_option( 'ds_settings', array() ), DS_Activator::default_settings() );
	}

	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	public function register_settings() {
		register_setting(
			'ds_settings_group',
			'ds_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public function sanitize( $input ) {
		$defaults = DS_Activator::default_settings();
		$out      = array();

		foreach ( $defaults as $key => $default ) {
			if ( strpos( $key, 'duration_' ) === 0 || in_array( $key, array( 'poll_interval', 'heartbeat_interval', 'offline_status_sec' ), true ) ) {
				$out[ $key ] = isset( $input[ $key ] ) ? max( 1, absint( $input[ $key ] ) ) : $default;
			} elseif ( 'transition' === $key ) {
				$allowed     = array( 'none', 'fade', 'slide', 'zoom' );
				$out[ $key ] = isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ? $input[ $key ] : $default;
			} elseif ( 'timezone' === $key ) {
				$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $default;
			} else {
				$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $default;
			}
		}

		return $out;
	}
}
