<?php
/**
 * Controller-scoped Spotify Connect controls.
 *
 * @package Digital_Signage
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Spotify {
	const SETTINGS_OPTION = 'ds_spotify_settings';
	const USER_CONTROLLERS = 'ds_spotify_controller_ids';
	const CONNECTION_PREFIX = 'ds_spotify_connection_';
	const SCOPES = 'streaming user-read-email user-read-private user-read-playback-state user-modify-playback-state';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_ds_spotify_oauth_start', array( $this, 'oauth_start' ) );
		add_action( 'admin_post_ds_spotify_oauth_callback', array( $this, 'oauth_callback' ) );
		add_action( 'admin_post_ds_spotify_disconnect', array( $this, 'disconnect' ) );
		add_action( 'wp_ajax_ds_spotify_control', array( $this, 'ajax_control' ) );
	}

	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=ds_spotify_oauth_callback' );
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::SETTINGS_OPTION, array() ), array( 'client_id' => '', 'client_secret' => '' ) );
	}

	public static function save_settings( $client_id, $client_secret ) {
		if ( ! current_user_can( 'manage_options' ) ) { return false; }
		$current = self::settings();
		$current['client_id'] = sanitize_text_field( $client_id );
		if ( '' !== trim( (string) $client_secret ) ) { $current['client_secret'] = sanitize_text_field( $client_secret ); }
		return update_option( self::SETTINGS_OPTION, $current, false );
	}

	private static function connection_key( $controller_id ) {
		return self::CONNECTION_PREFIX . absint( $controller_id );
	}

	public static function connection( $controller_id, $user_id = 0 ) {
		$user_id = absint( $user_id ?: get_current_user_id() );
		return $user_id ? (array) get_user_meta( $user_id, self::connection_key( $controller_id ), true ) : array();
	}

	private static function save_connection( $controller_id, array $connection, $user_id = 0 ) {
		$user_id = absint( $user_id ?: get_current_user_id() );
		if ( $user_id ) { update_user_meta( $user_id, self::connection_key( $controller_id ), $connection ); }
	}

	public static function user_controller_ids( $user_id = 0 ) {
		$user_id = $user_id ?: get_current_user_id();
		return array_values( array_unique( array_filter( array_map( 'absint', (array) get_user_meta( $user_id, self::USER_CONTROLLERS, true ) ) ) ) );
	}

	public static function set_user_controller_ids( $user_id, array $controller_ids ) {
		update_user_meta( absint( $user_id ), self::USER_CONTROLLERS, array_values( array_unique( array_filter( array_map( 'absint', $controller_ids ) ) ) ) );
	}

	public static function can_access_controller( $controller_id ) {
		$controller_id = absint( $controller_id );
		if ( current_user_can( 'manage_options' ) ) { return true; }
		if ( current_user_can( DS_Roles::CAP ) && DS_Groups::can_access_controller( $controller_id ) ) { return true; }
		return current_user_can( DS_Roles::SPOTIFY_CAP ) && in_array( $controller_id, self::user_controller_ids(), true );
	}

	public static function accessible_controllers() {
		return array_values( array_filter( DS_Controllers::get_all(), function ( $controller ) { return self::can_access_controller( $controller->id ); } ) );
	}

	public static function account_label( $controller_id, $user_id = 0 ) {
		$connection = self::connection( $controller_id, $user_id );
		return sanitize_text_field( $connection['display_name'] ?? $connection['email'] ?? '' );
	}

	public function oauth_start() {
		check_admin_referer( 'ds_spotify_connect' );
		$controller_id = absint( $_POST['controller_id'] ?? 0 );
		if ( ! self::can_access_controller( $controller_id ) ) { wp_die( esc_html__( 'Spotify controller access denied.', 'digital-signage' ), '', array( 'response' => 403 ) ); }
		$settings = self::settings();
		if ( ! $settings['client_id'] || ! $settings['client_secret'] ) { wp_safe_redirect( DS_Portal::url( 'spotify', array( 'id' => $controller_id, 'spotify_error' => 'credentials' ) ) ); exit; }
		$state = wp_generate_password( 48, false, false );
		set_transient( 'ds_spotify_oauth_' . hash( 'sha256', $state ), array( 'controller_id' => $controller_id, 'user_id' => get_current_user_id() ), 10 * MINUTE_IN_SECONDS );
		$url = add_query_arg( array( 'client_id' => $settings['client_id'], 'response_type' => 'code', 'redirect_uri' => self::redirect_uri(), 'scope' => self::SCOPES, 'state' => $state, 'show_dialog' => 'true' ), 'https://accounts.spotify.com/authorize' );
		wp_redirect( esc_url_raw( $url ) ); exit;
	}

	public function oauth_callback() {
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
		$key = 'ds_spotify_oauth_' . hash( 'sha256', $state );
		$pending = (array) get_transient( $key );
		delete_transient( $key );
		$controller_id = absint( $pending['controller_id'] ?? 0 );
		$user_id = absint( $pending['user_id'] ?? 0 );
		if ( ! $state || ! $code || get_current_user_id() !== $user_id || ! self::can_access_controller( $controller_id ) ) { wp_die( esc_html__( 'Spotify authorization expired or was invalid.', 'digital-signage' ), '', array( 'response' => 403 ) ); }
		$settings = self::settings();
		$response = wp_remote_post( 'https://accounts.spotify.com/api/token', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $settings['client_id'] . ':' . $settings['client_secret'] ) ), 'body' => array( 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::redirect_uri() ) ) );
		$tokens = $this->response_json( $response );
		if ( is_wp_error( $tokens ) || empty( $tokens['access_token'] ) ) { wp_safe_redirect( DS_Portal::url( 'spotify', array( 'id' => $controller_id, 'spotify_error' => 'oauth' ) ) ); exit; }
		$connection = array( 'access_token' => sanitize_text_field( $tokens['access_token'] ), 'refresh_token' => sanitize_text_field( $tokens['refresh_token'] ?? '' ), 'expires_at' => time() + absint( $tokens['expires_in'] ?? 3600 ) - 60, 'device_id' => '' );
		self::save_connection( $controller_id, $connection, $user_id );
		$profile = $this->api_request( $controller_id, 'GET', '/me', null, $user_id );
		if ( ! is_wp_error( $profile ) ) { $connection['display_name'] = sanitize_text_field( $profile['display_name'] ?? '' ); $connection['email'] = sanitize_email( $profile['email'] ?? '' ); self::save_connection( $controller_id, $connection, $user_id ); }
		wp_safe_redirect( DS_Portal::url( 'spotify', array( 'id' => $controller_id, 'spotify_connected' => 1 ) ) ); exit;
	}

	public function disconnect() {
		check_admin_referer( 'ds_spotify_disconnect' );
		$controller_id = absint( $_POST['controller_id'] ?? 0 );
		if ( ! self::can_access_controller( $controller_id ) ) { wp_die( esc_html__( 'Spotify controller access denied.', 'digital-signage' ), '', array( 'response' => 403 ) ); }
		delete_user_meta( get_current_user_id(), self::connection_key( $controller_id ) );
		wp_safe_redirect( DS_Portal::url( 'spotify', array( 'id' => $controller_id, 'spotify_disconnected' => 1 ) ) ); exit;
	}

	private function access_token( $controller_id, $user_id = 0 ) {
		$user_id = absint( $user_id ?: get_current_user_id() );
		$connection = self::connection( $controller_id, $user_id );
		if ( empty( $connection['refresh_token'] ) ) { return new WP_Error( 'ds_spotify_disconnected', __( 'Connect your Spotify account first.', 'digital-signage' ) ); }
		if ( ! empty( $connection['access_token'] ) && absint( $connection['expires_at'] ?? 0 ) > time() ) { return $connection['access_token']; }
		$settings = self::settings();
		$response = wp_remote_post( 'https://accounts.spotify.com/api/token', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $settings['client_id'] . ':' . $settings['client_secret'] ) ), 'body' => array( 'grant_type' => 'refresh_token', 'refresh_token' => $connection['refresh_token'] ) ) );
		$tokens = $this->response_json( $response );
		if ( is_wp_error( $tokens ) || empty( $tokens['access_token'] ) ) { return new WP_Error( 'ds_spotify_refresh', __( 'Spotify authorization must be renewed.', 'digital-signage' ) ); }
		$connection['access_token'] = sanitize_text_field( $tokens['access_token'] );
		$connection['expires_at'] = time() + absint( $tokens['expires_in'] ?? 3600 ) - 60;
		if ( ! empty( $tokens['refresh_token'] ) ) { $connection['refresh_token'] = sanitize_text_field( $tokens['refresh_token'] ); }
		self::save_connection( $controller_id, $connection, $user_id );
		return $connection['access_token'];
	}

	private function response_json( $response ) {
		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'Spotify request failed.', 'digital-signage' );
			if ( is_array( $body ) && is_array( $body['error'] ?? null ) ) {
				$message = $body['error']['message'] ?? $message;
			} elseif ( is_array( $body ) && is_string( $body['error'] ?? null ) ) {
				$message = $body['error'];
			} elseif ( is_array( $body ) && ! empty( $body['error_description'] ) ) {
				$message = $body['error_description'];
			}
			return new WP_Error( 'ds_spotify_api', sanitize_text_field( $message ) );
		}
		return is_array( $body ) ? $body : array();
	}

	private function api_request( $controller_id, $method, $path, $body = null, $user_id = 0 ) {
		$token = $this->access_token( $controller_id, $user_id );
		if ( is_wp_error( $token ) ) { return $token; }
		$args = array( 'method' => $method, 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ) );
		if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
		$response = wp_remote_request( 'https://api.spotify.com/v1' . $path, $args );
		if ( 204 === wp_remote_retrieve_response_code( $response ) ) { return array( 'ok' => true ); }
		return $this->response_json( $response );
	}

	public function ajax_control() {
		check_ajax_referer( 'ds_spotify_control', 'nonce' );
		$controller_id = absint( $_POST['controller_id'] ?? 0 );
		if ( ! self::can_access_controller( $controller_id ) ) { wp_send_json_error( array( 'message' => __( 'Spotify controller access denied.', 'digital-signage' ) ), 403 ); }
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? 'status' ) );
		$device_id = sanitize_text_field( wp_unslash( $_POST['device_id'] ?? '' ) );
		$query_device = $device_id ? '?device_id=' . rawurlencode( $device_id ) : '';
		$result = null;
		if ( 'token' === $operation ) { $token = $this->access_token( $controller_id ); $result = is_wp_error( $token ) ? $token : array( 'access_token' => $token ); }
		elseif ( 'devices' === $operation ) { $result = $this->api_request( $controller_id, 'GET', '/me/player/devices' ); }
		elseif ( 'status' === $operation ) { $result = $this->api_request( $controller_id, 'GET', '/me/player' ); }
		elseif ( 'queue' === $operation ) { $result = $this->api_request( $controller_id, 'GET', '/me/player/queue' ); }
		elseif ( 'search' === $operation ) { $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) ); $result = $query ? $this->api_request( $controller_id, 'GET', '/search?' . http_build_query( array( 'q' => $query, 'type' => 'track,album,playlist', 'limit' => 10 ) ) ) : new WP_Error( 'ds_spotify_query', __( 'Enter a search term.', 'digital-signage' ) ); }
		elseif ( 'transfer' === $operation && $device_id ) { $result = $this->api_request( $controller_id, 'PUT', '/me/player', array( 'device_ids' => array( $device_id ), 'play' => false ) ); $connection = self::connection( $controller_id ); $connection['device_id'] = $device_id; self::save_connection( $controller_id, $connection ); }
		elseif ( 'pause' === $operation ) { $result = $this->api_request( $controller_id, 'PUT', '/me/player/pause' . $query_device ); }
		elseif ( in_array( $operation, array( 'next', 'previous' ), true ) ) { $paths = array( 'next' => '/me/player/next', 'previous' => '/me/player/previous' ); $result = $this->api_request( $controller_id, 'POST', $paths[ $operation ] . $query_device ); }
		elseif ( 'play' === $operation ) { $uri = sanitize_text_field( wp_unslash( $_POST['uri'] ?? '' ) ); $item_type = sanitize_key( wp_unslash( $_POST['item_type'] ?? 'track' ) ); $payload = $uri ? ( 'track' === $item_type ? array( 'uris' => array( $uri ) ) : array( 'context_uri' => $uri ) ) : array(); $result = $this->api_request( $controller_id, 'PUT', '/me/player/play' . $query_device, $payload ); }
		elseif ( 'seek' === $operation ) { $position = max( 0, absint( $_POST['position_ms'] ?? 0 ) ); $result = $this->api_request( $controller_id, 'PUT', '/me/player/seek?position_ms=' . $position . ( $device_id ? '&device_id=' . rawurlencode( $device_id ) : '' ) ); }
		elseif ( 'volume' === $operation ) { $volume = min( 100, max( 0, absint( $_POST['volume_percent'] ?? 50 ) ) ); $result = $this->api_request( $controller_id, 'PUT', '/me/player/volume?volume_percent=' . $volume . ( $device_id ? '&device_id=' . rawurlencode( $device_id ) : '' ) ); }
		elseif ( 'shuffle' === $operation ) { $state = ! empty( $_POST['state'] ) && 'false' !== $_POST['state'] ? 'true' : 'false'; $result = $this->api_request( $controller_id, 'PUT', '/me/player/shuffle?state=' . $state . ( $device_id ? '&device_id=' . rawurlencode( $device_id ) : '' ) ); }
		elseif ( 'repeat' === $operation ) { $state = sanitize_key( wp_unslash( $_POST['state'] ?? 'off' ) ); if ( ! in_array( $state, array( 'off', 'context', 'track' ), true ) ) { $state = 'off'; } $result = $this->api_request( $controller_id, 'PUT', '/me/player/repeat?state=' . $state . ( $device_id ? '&device_id=' . rawurlencode( $device_id ) : '' ) ); }
		else { $result = new WP_Error( 'ds_spotify_operation', __( 'Unsupported Spotify action.', 'digital-signage' ) ); }
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ); }
		wp_send_json_success( $result );
	}
}
