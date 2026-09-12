<?php
/** Authenticated frontend management portal. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Portal {
	private static $instance = null;
	const PATH = '/signage-manager/';
	public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }
	private function __construct() {
		add_action( 'template_redirect', array( $this, 'render' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_ds_portal_action', array( $this, 'handle_action' ) );
		add_filter( 'show_admin_bar', array( $this, 'admin_bar' ) );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_admin' ) );
	}
	public function login_redirect( $redirect_to, $requested, $user ) { return ( $user instanceof WP_User && $user->has_cap( DS_Roles::CAP ) && ! $user->has_cap( 'manage_options' ) ) ? self::url() : $redirect_to; }
	public function redirect_legacy_admin() {
		if ( current_user_can( DS_Roles::CAP ) && ! current_user_can( 'manage_options' ) && isset( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'ds-' ) ) { wp_safe_redirect( self::url() ); exit; }
	}
	public static function url( $section = 'overview', array $args = array() ) { return add_query_arg( array_merge( array( 'section' => sanitize_key( $section ) ), $args ), home_url( self::PATH ) ); }
	private function is_portal() { $path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ); return untrailingslashit( (string) $path ) === untrailingslashit( self::PATH ); }
	public function admin_bar( $show ) { return $this->is_portal() ? false : $show; }
	public function assets() { if ( $this->is_portal() ) { wp_enqueue_style( 'ds-portal', DS_PLUGIN_URL . 'public/css/portal.css', array(), DS_VERSION ); } }
	public static function can_access_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || ! is_user_logged_in() || ! current_user_can( DS_Roles::CAP ) ) { return false; }
		if ( current_user_can( 'manage_options' ) || (int) $post->post_author === get_current_user_id() ) { return true; }
		return in_array( get_current_user_id(), array_map( 'absint', (array) get_post_meta( $post->ID, 'ds_shared_user_ids', true ) ), true );
	}
	public static function posts( $type ) { return array_values( array_filter( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ), array( __CLASS__, 'can_access_post' ) ) ); }
	public static function can_access_controller( $id ) {
		if ( current_user_can( 'manage_options' ) ) { return true; }
		foreach ( self::posts( 'ds_screen' ) as $screen ) { if ( absint( get_post_meta( $screen->ID, 'ds_controller_id', true ) ) === absint( $id ) ) { return true; } }
		return false;
	}
	public function render() {
		if ( ! $this->is_portal() ) { return; }
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		if ( ! current_user_can( DS_Roles::CAP ) ) { wp_die( esc_html__( 'You do not have access to Digital Signage.', 'digital-signage' ), '', array( 'response' => 403 ) ); }
		status_header( 200 ); nocache_headers();
		$section = sanitize_key( wp_unslash( $_GET['section'] ?? 'overview' ) );
		if ( ! in_array( $section, array( 'overview', 'channels', 'screens', 'controllers', 'sharing' ), true ) ) { $section = 'overview'; }
		$id = absint( $_GET['id'] ?? 0 );
		$channels = self::posts( 'ds_channel' ); $screens = self::posts( 'ds_screen' );
		$controllers = array_values( array_filter( DS_Controllers::get_all(), function ( $item ) { return self::can_access_controller( $item->id ); } ) );
		include DS_PLUGIN_DIR . 'public/templates/portal.php'; exit;
	}
	private function redirect( $section, array $args = array() ) { wp_safe_redirect( self::url( $section, $args ) ); exit; }
	public function handle_action() {
		if ( ! current_user_can( DS_Roles::CAP ) ) { wp_die( esc_html__( 'Access denied.', 'digital-signage' ) ); }
		check_admin_referer( 'ds_portal_action' );
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) ); $id = absint( $_POST['controller_id'] ?? 0 );
		if ( in_array( $operation, array( 'controller_command', 'save_sleep_schedule', 'switch_url' ), true ) && ! self::can_access_controller( $id ) ) { wp_die( esc_html__( 'Controller access denied.', 'digital-signage' ) ); }
		if ( 'controller_command' === $operation ) {
			$type = sanitize_key( wp_unslash( $_POST['command_type'] ?? '' ) ); $payload = 'software_update' === $type ? array( 'version' => DS_DEVICE_VERSION, 'source' => 'configured_git_remote' ) : array();
			$result = DS_Controllers::queue_command( $id, $type, $payload );
			$args = is_wp_error( $result ) ? array( 'id' => $id, 'error' => 'queue' ) : array( 'id' => $id, 'saved' => 1 );
			$this->redirect( 'controllers', $args );
		}
		if ( 'save_sleep_schedule' === $operation ) { DS_Controllers::save_schedule( $id, wp_unslash( $_POST ) ); $this->redirect( 'controllers', array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'switch_url' === $operation ) {
			$new_url = esc_url_raw( wp_unslash( $_POST['new_site_url'] ?? '' ) ); $current = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); $verify = strtolower( sanitize_text_field( wp_unslash( $_POST['verify_host'] ?? '' ) ) );
			if ( empty( $_POST['confirm_switch'] ) || ! hash_equals( $current, $verify ) || ! wp_http_validate_url( $new_url ) || ! in_array( wp_parse_url( $new_url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) { $this->redirect( 'controllers', array( 'id' => $id, 'error' => 'verify' ) ); }
			$result = DS_Controllers::queue_command( $id, 'switch_url', array( 'site' => untrailingslashit( $new_url ) ) );
			$args = is_wp_error( $result ) ? array( 'id' => $id, 'error' => 'queue' ) : array( 'id' => $id, 'saved' => 1 );
			$this->redirect( 'controllers', $args );
		}
		if ( 'share' === $operation ) {
			$post_id = absint( $_POST['post_id'] ?? 0 ); $post = get_post( $post_id );
			if ( ! $post || ( ! current_user_can( 'manage_options' ) && (int) $post->post_author !== get_current_user_id() ) ) { wp_die( esc_html__( 'Only the owner can change access.', 'digital-signage' ) ); }
			$user = get_user_by( 'email', sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) ); if ( ! $user ) { $this->redirect( 'sharing', array( 'error' => 'user' ) ); }
			$shared = array_map( 'absint', (array) get_post_meta( $post_id, 'ds_shared_user_ids', true ) );
			if ( 'remove' === sanitize_key( wp_unslash( $_POST['share_action'] ?? 'add' ) ) ) { $shared = array_diff( $shared, array( $user->ID ) ); } else { $shared[] = $user->ID; $user->add_cap( DS_Roles::CAP ); }
			update_post_meta( $post_id, 'ds_shared_user_ids', array_values( array_unique( array_map( 'absint', $shared ) ) ) ); $this->redirect( 'sharing', array( 'saved' => 1 ) );
		}
		$this->redirect( 'overview', array( 'error' => 'action' ) );
	}
}
