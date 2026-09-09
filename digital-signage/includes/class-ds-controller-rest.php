<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticated REST transport for multi-display controller computers.
 */
class DS_Controller_REST {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ds/v1',
			'/controller/request',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'request_controller' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'ds/v1',
			'/controller/public/(?P<public_id>[a-f0-9-]{36})/(?P<output_key>[a-zA-Z0-9_-]{1,100})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'public_status' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'ds/v1',
			'/controller/(?P<public_id>[a-f0-9-]{36})/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'controller_status' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'ds/v1',
			'/controller/(?P<public_id>[a-f0-9-]{36})/heartbeat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'heartbeat' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'ds/v1',
			'/controller/(?P<public_id>[a-f0-9-]{36})/command/(?P<command_id>\d+)/ack',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'acknowledge_command' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function request_controller( WP_REST_Request $request ) {
		$ip  = self::client_ip();
		$key = 'ds_controller_request_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 10 ) {
			return new WP_Error( 'ds_controller_rate_limit', __( 'Too many controller requests. Try again later.', 'digital-signage' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		return rest_ensure_response(
			DS_Controllers::create_request(
				sanitize_key( $payload['platform'] ?? 'linux' ),
				sanitize_text_field( $payload['hostname'] ?? '' ),
				(array) ( $payload['outputs'] ?? array() ),
				sanitize_text_field( $payload['legacy_screen_token'] ?? '' )
			)
		);
	}

	public function public_status( WP_REST_Request $request ) {
		$status = DS_Controllers::public_output_status( $request['public_id'], $request['output_key'] );
		if ( ! $status ) {
			return new WP_Error( 'ds_controller_not_found', __( 'Controller not found.', 'digital-signage' ), array( 'status' => 404 ) );
		}
		$response = rest_ensure_response( $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	public function controller_status( WP_REST_Request $request ) {
		$controller = $this->authenticate_request( $request );
		if ( is_wp_error( $controller ) ) {
			return $controller;
		}
		$response = rest_ensure_response( DS_Controllers::build_agent_state( (int) $controller->id ) );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	public function heartbeat( WP_REST_Request $request ) {
		$controller = $this->authenticate_request( $request );
		if ( is_wp_error( $controller ) ) {
			return $controller;
		}
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$response = rest_ensure_response( DS_Controllers::update_heartbeat( $controller, $payload, self::client_ip() ) );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	public function acknowledge_command( WP_REST_Request $request ) {
		$controller = $this->authenticate_request( $request );
		if ( is_wp_error( $controller ) ) {
			return $controller;
		}
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$ok = DS_Controllers::acknowledge_command(
			(int) $controller->id,
			absint( $request['command_id'] ),
			sanitize_key( $payload['status'] ?? '' ),
			sanitize_textarea_field( $payload['result'] ?? '' )
		);
		return $ok ? rest_ensure_response( array( 'ok' => true ) ) : new WP_Error( 'ds_command_ack', __( 'Command acknowledgement was rejected.', 'digital-signage' ), array( 'status' => 400 ) );
	}

	private function authenticate_request( WP_REST_Request $request ) {
		$token = sanitize_text_field( $request->get_header( 'X-DS-Controller-Token' ) );
		$controller = DS_Controllers::authenticate( $request['public_id'], $token );
		if ( ! $controller ) {
			return new WP_Error( 'ds_controller_auth', __( 'Controller authentication failed.', 'digital-signage' ), array( 'status' => 401 ) );
		}
		return $controller;
	}

	private static function client_ip() {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return sanitize_text_field( mb_substr( (string) $raw, 0, 45 ) );
	}
}
