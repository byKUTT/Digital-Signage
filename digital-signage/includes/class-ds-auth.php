<?php
/**
 * Frontend authentication for Screen Manager users.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Auth {
	const LOGIN_PATH    = '/signage-login/';
	const REGISTER_PATH = '/signage-register/';
	private static $instance = null;

	public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }
	private function __construct() {
		add_action( 'template_redirect', array( $this, 'render' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_nopriv_ds_front_login', array( $this, 'handle_login' ) );
		add_action( 'admin_post_nopriv_ds_front_register', array( $this, 'handle_register' ) );
	}

	public static function login_url( $redirect_to = '' ) { return add_query_arg( $redirect_to ? array( 'redirect_to' => $redirect_to ) : array(), home_url( self::LOGIN_PATH ) ); }
	public static function register_url() { return home_url( self::REGISTER_PATH ); }
	private function route() { $path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ); if ( untrailingslashit( (string) $path ) === untrailingslashit( self::LOGIN_PATH ) ) { return 'login'; } if ( untrailingslashit( (string) $path ) === untrailingslashit( self::REGISTER_PATH ) ) { return 'register'; } return ''; }
	public function assets() { if ( $this->route() ) { wp_enqueue_style( 'ds-auth', DS_PLUGIN_URL . 'public/css/auth.css', array(), DS_VERSION ); } }
	public function render() {
		$mode = $this->route();
		if ( ! $mode ) { return; }
		if ( is_user_logged_in() ) { wp_safe_redirect( DS_Portal::url() ); exit; }
		$status = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
		$redirect_to = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ?? '' ) ), DS_Portal::url() );
		status_header( 200 ); nocache_headers(); include DS_PLUGIN_DIR . 'public/templates/auth.php'; exit;
	}

	public function handle_login() {
		check_admin_referer( 'ds_front_login' );
		$credentials = array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) ),
			'user_password' => (string) wp_unslash( $_POST['user_password'] ?? '' ),
			'remember'      => ! empty( $_POST['remember'] ),
		);
		$user = wp_signon( $credentials, is_ssl() );
		if ( is_wp_error( $user ) || ! user_can( $user, DS_Roles::CAP ) ) {
			if ( $user instanceof WP_User ) { wp_logout(); }
			wp_safe_redirect( add_query_arg( 'status', 'login_failed', self::login_url() ) ); exit;
		}
		DS_Groups::ensure_user_group( $user->ID );
		$redirect_to = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? '' ) ), DS_Portal::url() );
		wp_safe_redirect( $redirect_to ); exit;
	}

	public function handle_register() {
		check_admin_referer( 'ds_front_register' );
		if ( ! empty( $_POST['website'] ) ) { wp_safe_redirect( self::register_url() ); exit; }
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$key = 'ds_register_' . md5( $ip ); $attempts = (int) get_transient( $key );
		if ( $attempts >= 5 ) { wp_safe_redirect( add_query_arg( 'status', 'rate_limited', self::register_url() ) ); exit; }
		set_transient( $key, $attempts + 1, HOUR_IN_SECONDS );
		$name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
		$password = (string) wp_unslash( $_POST['user_password'] ?? '' );
		$confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );
		if ( ! $name || ! is_email( $email ) || strlen( $password ) < 10 || ! hash_equals( $password, $confirmation ) || email_exists( $email ) ) { wp_safe_redirect( add_query_arg( 'status', 'register_failed', self::register_url() ) ); exit; }
		$base = sanitize_user( strstr( $email, '@', true ), true ); $login = $base ?: 'screen-manager';
		while ( username_exists( $login ) ) { $login = $base . wp_rand( 100, 9999 ); }
		$user_id = wp_insert_user( array( 'user_login' => $login, 'user_email' => $email, 'user_pass' => $password, 'display_name' => $name, 'role' => DS_Roles::SCREEN_MANAGER_ROLE ) );
		if ( is_wp_error( $user_id ) ) { wp_safe_redirect( add_query_arg( 'status', 'register_failed', self::register_url() ) ); exit; }
		DS_Groups::ensure_user_group( $user_id );
		wp_new_user_notification( $user_id, null, 'admin' );
		wp_set_current_user( $user_id ); wp_set_auth_cookie( $user_id, true, is_ssl() );
		wp_safe_redirect( DS_Portal::url( 'pair' ) ); exit;
	}
}
