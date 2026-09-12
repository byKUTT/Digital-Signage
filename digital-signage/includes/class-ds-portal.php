<?php
/** Authenticated, group-scoped frontend signage manager. */
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
		add_action( 'admin_menu', array( $this, 'remove_legacy_menu' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_link' ), 90 );
		add_filter( 'plugin_action_links_' . plugin_basename( DS_PLUGIN_FILE ), array( $this, 'plugin_links' ) );
	}
	public static function url( $section = 'overview', array $args = array() ) { return add_query_arg( array_merge( array( 'section' => sanitize_key( $section ) ), $args ), home_url( self::PATH ) ); }
	private function is_portal() { $path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ); return untrailingslashit( (string) $path ) === untrailingslashit( self::PATH ); }
	public function admin_bar( $show ) { return $this->is_portal() ? false : $show; }
	public function admin_bar_link( $bar ) { if ( is_user_logged_in() && current_user_can( DS_Roles::CAP ) ) { $bar->add_node( array( 'id' => 'ds-screen-manager', 'title' => __( 'Screen Manager', 'digital-signage' ), 'href' => self::url() ) ); } }
	public function login_redirect( $redirect_to, $requested, $user ) { return ( $user instanceof WP_User && $user->has_cap( DS_Roles::CAP ) ) ? self::url() : $redirect_to; }
	public function redirect_legacy_admin() { if ( ! current_user_can( DS_Roles::CAP ) || wp_doing_ajax() ) { return; } $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); if ( 'digital-signage' === $page || 0 === strpos( $page, 'ds-' ) ) { wp_safe_redirect( self::url() ); exit; } }
	public function remove_legacy_menu() { remove_menu_page( 'digital-signage' ); }
	public function plugin_links( $links ) { array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open Signage Manager', 'digital-signage' ) . '</a>' ); return $links; }
	public function assets() {
		if ( ! $this->is_portal() ) { return; }
		wp_enqueue_style( 'ds-portal', DS_PLUGIN_URL . 'public/css/portal.css', array(), DS_VERSION );
		wp_enqueue_style( 'ds-portal-time', DS_PLUGIN_URL . 'public/css/portal-time.css', array( 'ds-portal' ), DS_VERSION );
		wp_enqueue_style( 'ds-designer-ui', DS_PLUGIN_URL . 'public/css/designer.css', array( 'ds-portal' ), DS_VERSION );
		wp_enqueue_style( 'ds-portal-extras', DS_PLUGIN_URL . 'public/css/portal-extras.css', array( 'ds-portal' ), DS_VERSION );
		wp_enqueue_media();
		wp_enqueue_script( 'ds-portal', DS_PLUGIN_URL . 'public/js/portal.js', array(), DS_VERSION, true );
		wp_localize_script( 'ds-portal', 'DSPortal', array( 'groupId' => DS_Groups::current_group_id() ) );
		if ( 'designer' === sanitize_key( wp_unslash( $_GET['section'] ?? '' ) ) ) {
			$design_id = absint( $_GET['id'] ?? 0 );
			wp_enqueue_script( 'ds-designer', DS_PLUGIN_URL . 'public/js/designer.js', array(), DS_VERSION, true );
			wp_localize_script( 'ds-designer', 'DSDesigner', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ds_vellum_save' ),
				'groupId' => DS_Groups::current_group_id(), 'designId' => $design_id,
				'document' => $design_id ? json_decode( DS_Designer::document( $design_id ), true ) : null,
				'editUrl' => self::url( 'designer', array( 'group' => DS_Groups::current_group_id(), 'id' => '__ID__' ) ),
			) );
		}
	}
	public static function can_access_post( $post ) { return DS_Groups::can_access_post( $post ); }
	public static function posts( $type ) {
		$group_id = DS_Groups::current_group_id();
		return array_values( array_filter( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ), function ( $post ) use ( $group_id ) { return DS_Groups::can_access_post( $post ) && ( ! $group_id || $group_id === DS_Groups::post_group_id( $post ) ); } ) );
	}
	public static function can_access_controller( $id ) { return DS_Groups::can_access_controller( $id ); }
	private static function require_group( $group_id ) { $group_id = absint( $group_id ?: DS_Groups::ensure_user_group() ); if ( ! $group_id || ( ! current_user_can( 'manage_options' ) && ! in_array( $group_id, DS_Groups::user_group_ids(), true ) ) ) { wp_die( esc_html__( 'This workspace is not assigned to your account.', 'digital-signage' ), '', array( 'response' => 403 ) ); } return $group_id; }
	private function redirect( $section, array $args = array() ) { wp_safe_redirect( self::url( $section, array_merge( array( 'group' => DS_Groups::current_group_id() ), $args ) ) ); exit; }

	public function render() {
		if ( ! $this->is_portal() ) { return; }
		if ( ! is_user_logged_in() ) { wp_safe_redirect( DS_Auth::login_url( self::url() ) ); exit; }
		if ( ! current_user_can( DS_Roles::CAP ) ) { wp_die( esc_html__( 'You do not have access to Digital Signage.', 'digital-signage' ), '', array( 'response' => 403 ) ); }
		status_header( 200 ); nocache_headers();
		$allowed = array( 'overview', 'channels', 'screens', 'controllers', 'designer', 'calendar', 'media', 'people', 'settings', 'pair' );
		$section = sanitize_key( wp_unslash( $_GET['section'] ?? 'overview' ) ); if ( ! in_array( $section, $allowed, true ) ) { $section = 'overview'; }
		DS_Groups::ensure_user_group();
		$id = absint( $_GET['id'] ?? 0 ); $group_id = DS_Groups::current_group_id(); $groups = DS_Groups::all();
		$channels = self::posts( 'ds_channel' ); $screens = self::posts( 'ds_screen' ); $schedules = self::posts( 'ds_schedule' ); $designs = self::posts( 'ds_design' );
		$controllers = array_values( array_filter( DS_Controllers::get_all(), function ( $item ) use ( $group_id ) { return self::can_access_controller( $item->id ) && ( ! $group_id || $group_id === DS_Groups::controller_group_id( $item->id ) ); } ) );
		include DS_PLUGIN_DIR . 'public/templates/portal.php'; exit;
	}

	public function handle_action() {
		if ( ! current_user_can( DS_Roles::CAP ) ) { wp_die( esc_html__( 'Access denied.', 'digital-signage' ) ); }
		check_admin_referer( 'ds_portal_action' );
		DS_Groups::ensure_user_group();
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) ); $group_id = absint( $_POST['group_id'] ?? DS_Groups::current_group_id() );
		if ( 'create_group' === $operation ) { if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Only administrators can create groups.', 'digital-signage' ) ); } $group_id = DS_Groups::create( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) ); DS_Groups::add_user( $group_id, get_current_user_id() ); $this->redirect( 'settings', array( 'group' => $group_id, 'saved' => 1 ) ); }
		$group_id = self::require_group( $group_id );
		if ( 'save_channel' === $operation ) { $id = absint( $_POST['id'] ?? 0 ); if ( $id && ( ! self::can_access_post( $id ) || $group_id !== DS_Groups::post_group_id( $id ) ) ) { wp_die( esc_html__( 'Channel access denied.', 'digital-signage' ) ); } $id = DS_CRUD::save_channel( $id, wp_unslash( $_POST ) ); DS_Groups::set_post_group( $id, $group_id ); $this->redirect( 'channels', array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'save_slide' === $operation ) { $channel_id = absint( $_POST['channel_id'] ?? 0 ); if ( ! self::can_access_post( $channel_id ) || $group_id !== DS_Groups::post_group_id( $channel_id ) ) { wp_die( esc_html__( 'Channel access denied.', 'digital-signage' ) ); } $id = absint( $_POST['id'] ?? 0 ); if ( $id && ( ! self::can_access_post( $id ) || $group_id !== DS_Groups::post_group_id( $id ) ) ) { wp_die( esc_html__( 'Slide access denied.', 'digital-signage' ) ); } $media_id = absint( $_POST['media_id'] ?? 0 ); if ( $media_id && ( 'attachment' !== get_post_type( $media_id ) || ! self::can_access_post( $media_id ) || $group_id !== DS_Groups::post_group_id( $media_id ) ) ) { wp_die( esc_html__( 'Media access denied.', 'digital-signage' ) ); } $id = DS_CRUD::save_slide( $id, wp_unslash( $_POST ) ); DS_Groups::set_post_group( $id, $group_id ); $this->redirect( 'channels', array( 'id' => $channel_id, 'saved' => 1 ) ); }
		if ( 'save_screen' === $operation ) { $id = absint( $_POST['id'] ?? 0 ); if ( $id && ( ! self::can_access_post( $id ) || $group_id !== DS_Groups::post_group_id( $id ) ) ) { wp_die( esc_html__( 'Screen access denied.', 'digital-signage' ) ); } $channel_id = absint( $_POST['channel_id'] ?? 0 ); if ( $channel_id && ( ! self::can_access_post( $channel_id ) || $group_id !== DS_Groups::post_group_id( $channel_id ) ) ) { wp_die( esc_html__( 'Channel access denied.', 'digital-signage' ) ); } $id = DS_CRUD::save_screen( $id, wp_unslash( $_POST ) ); DS_Groups::set_post_group( $id, $group_id ); $this->redirect( 'screens', array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'save_schedule' === $operation ) { $id = absint( $_POST['id'] ?? 0 ); if ( $id && ( ! self::can_access_post( $id ) || $group_id !== DS_Groups::post_group_id( $id ) ) ) { wp_die( esc_html__( 'Schedule access denied.', 'digital-signage' ) ); } $channel_id = absint( $_POST['channel_id'] ?? 0 ); if ( ! $channel_id || ! self::can_access_post( $channel_id ) || $group_id !== DS_Groups::post_group_id( $channel_id ) ) { wp_die( esc_html__( 'Channel access denied.', 'digital-signage' ) ); } foreach ( array_map( 'absint', (array) ( $_POST['screen_ids'] ?? array() ) ) as $screen_id ) { if ( ! self::can_access_post( $screen_id ) || $group_id !== DS_Groups::post_group_id( $screen_id ) ) { wp_die( esc_html__( 'Screen access denied.', 'digital-signage' ) ); } } $id = DS_CRUD::save_schedule( $id, wp_unslash( $_POST ) ); DS_Groups::set_post_group( $id, $group_id ); $this->redirect( 'calendar', array( 'saved' => 1 ) ); }
		if ( 'controller_command' === $operation ) { $id = absint( $_POST['controller_id'] ?? 0 ); if ( ! self::can_access_controller( $id ) || $group_id !== DS_Groups::controller_group_id( $id ) ) { wp_die( esc_html__( 'Controller access denied.', 'digital-signage' ) ); } $type = sanitize_key( wp_unslash( $_POST['command_type'] ?? '' ) ); $payload = 'software_update' === $type ? array( 'version' => DS_DEVICE_VERSION, 'source' => 'configured_git_remote' ) : array(); $result = DS_Controllers::queue_command( $id, $type, $payload ); $this->redirect( 'controllers', is_wp_error( $result ) ? array( 'id' => $id, 'error' => $result->get_error_code() ) : array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'save_controller' === $operation ) { $id = absint( $_POST['controller_id'] ?? 0 ); if ( ! self::can_access_controller( $id ) || $group_id !== DS_Groups::controller_group_id( $id ) ) { wp_die( esc_html__( 'Controller access denied.', 'digital-signage' ) ); } foreach ( array_filter( array_map( 'absint', (array) ( $_POST['display_screen_ids'] ?? array() ) ) ) as $screen_id ) { if ( ! self::can_access_post( $screen_id ) || $group_id !== DS_Groups::post_group_id( $screen_id ) ) { wp_die( esc_html__( 'Screen access denied.', 'digital-signage' ) ); } } DS_Controllers::update_controller( $id, sanitize_text_field( wp_unslash( $_POST['controller_name'] ?? '' ) ), (array) ( $_POST['display_screen_ids'] ?? array() ) ); $this->redirect( 'controllers', array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'save_sleep_schedule' === $operation ) { $id = absint( $_POST['controller_id'] ?? 0 ); if ( ! self::can_access_controller( $id ) || $group_id !== DS_Groups::controller_group_id( $id ) ) { wp_die( esc_html__( 'Controller access denied.', 'digital-signage' ) ); } DS_Controllers::save_schedule( $id, wp_unslash( $_POST ) ); $this->redirect( 'controllers', array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'switch_url' === $operation ) { $id = absint( $_POST['controller_id'] ?? 0 ); if ( ! self::can_access_controller( $id ) || $group_id !== DS_Groups::controller_group_id( $id ) ) { wp_die( esc_html__( 'Controller access denied.', 'digital-signage' ) ); } $new_url = esc_url_raw( wp_unslash( $_POST['new_site_url'] ?? '' ) ); $current = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); $verify = strtolower( sanitize_text_field( wp_unslash( $_POST['verify_host'] ?? '' ) ) ); if ( empty( $_POST['confirm_switch'] ) || ! hash_equals( $current, $verify ) || ! wp_http_validate_url( $new_url ) ) { $this->redirect( 'controllers', array( 'id' => $id, 'error' => 'verify' ) ); } DS_Controllers::queue_command( $id, 'switch_url', array( 'site' => untrailingslashit( $new_url ) ) ); $this->redirect( 'controllers', array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'pair' === $operation ) { $id = DS_Controllers::pair_by_code( sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), get_current_user_id(), $group_id ); if ( is_wp_error( $id ) ) { $this->redirect( 'pair', array( 'error' => 'code' ) ); } $this->redirect( 'controllers', array( 'id' => $id, 'saved' => 1 ) ); }
		if ( 'member' === $operation ) { $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); $user = get_user_by( 'email', $email ); if ( 'remove' === sanitize_key( wp_unslash( $_POST['member_action'] ?? 'add' ) ) ) { if ( $user ) { DS_Groups::remove_user( $group_id, $user->ID ); } } else { if ( ! $user && is_email( $email ) ) { $login = sanitize_user( strstr( $email, '@', true ), true ); $login = username_exists( $login ) ? $login . wp_rand( 100, 999 ) : $login; $user_id = wp_create_user( $login, wp_generate_password( 24 ), $email ); if ( ! is_wp_error( $user_id ) ) { $user = get_user_by( 'id', $user_id ); wp_new_user_notification( $user_id, null, 'user' ); } } if ( $user ) { $user->add_role( DS_Roles::SCREEN_MANAGER_ROLE ); $user->add_cap( DS_Roles::CAP ); DS_Groups::ensure_user_group( $user->ID ); DS_Groups::add_user( $group_id, $user->ID ); } } $this->redirect( 'people', array( 'saved' => 1 ) ); }
		if ( 'save_settings' === $operation ) { DS_Groups::save_settings( $group_id, (array) wp_unslash( $_POST['ds_settings'] ?? array() ) ); $this->redirect( 'settings', array( 'saved' => 1 ) ); }
		if ( 'upload_media' === $operation ) { require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; $attachment_id = media_handle_upload( 'media_file', 0 ); if ( ! is_wp_error( $attachment_id ) ) { DS_Groups::set_post_group( $attachment_id, $group_id ); } $this->redirect( 'media', is_wp_error( $attachment_id ) ? array( 'error' => 'upload' ) : array( 'saved' => 1 ) ); }
		if ( 'delete' === $operation ) { $type = sanitize_key( wp_unslash( $_POST['entity_type'] ?? '' ) ); $id = absint( $_POST['entity_id'] ?? 0 ); $phrases = array( 'channel' => 'DELETE CHANNEL', 'screen' => 'DELETE SCREEN', 'schedule' => 'DELETE SCHEDULE', 'controller' => 'DELETE CONTROLLER' ); if ( 'slide' !== $type && ( ! isset( $phrases[ $type ] ) || ! hash_equals( $phrases[ $type ], strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['verification'] ?? '' ) ) ) ) ) ) ) { $this->redirect( 'overview', array( 'error' => 'delete_verify' ) ); } if ( 'controller' === $type ) { if ( ! self::can_access_controller( $id ) || $group_id !== DS_Groups::controller_group_id( $id ) ) { wp_die( esc_html__( 'Controller access denied.', 'digital-signage' ) ); } DS_Controllers::delete( $id ); } else { if ( ! self::can_access_post( $id ) || $group_id !== DS_Groups::post_group_id( $id ) ) { wp_die( esc_html__( 'Item access denied.', 'digital-signage' ) ); } $channel_id = 'slide' === $type ? absint( get_post_meta( $id, 'ds_channel_id', true ) ) : 0; $method = 'delete_' . $type; if ( is_callable( array( 'DS_CRUD', $method ) ) ) { call_user_func( array( 'DS_CRUD', $method ), $id ); } if ( 'slide' === $type ) { $this->redirect( 'channels', array( 'id' => $channel_id, 'deleted' => 1 ) ); } } $this->redirect( 'overview', array( 'deleted' => 1 ) ); }
		$this->redirect( 'overview', array( 'error' => 'action' ) );
	}
}
