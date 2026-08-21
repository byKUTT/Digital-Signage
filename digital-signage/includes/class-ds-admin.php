<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The entire custom admin UI: a from-scratch app-style interface (not
 * WordPress's native post-editor screens) for Channels, Screens, Slides and
 * Schedules, plus the Dashboard, Calendar, Settings, Pairing, Analytics and
 * Import/Export pages. Every page and form action here is gated behind the
 * single 'manage_digital_signage' capability (see DS_Roles) and reads/writes
 * through DS_CRUD — nothing goes through post.php/post-new.php.
 */
class DS_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_body_class', array( $this, 'body_class' ) );

		add_action( 'admin_post_ds_save_channel', array( $this, 'handle_save_channel' ) );
		add_action( 'admin_post_ds_delete_channel', array( $this, 'handle_delete_channel' ) );
		add_action( 'admin_post_ds_duplicate_channel', array( $this, 'handle_duplicate_channel' ) );

		add_action( 'admin_post_ds_save_slide', array( $this, 'handle_save_slide' ) );
		add_action( 'admin_post_ds_delete_slide', array( $this, 'handle_delete_slide' ) );
		add_action( 'admin_post_ds_duplicate_slide', array( $this, 'handle_duplicate_slide' ) );
		add_action( 'admin_post_ds_save_slide_order', array( $this, 'handle_save_slide_order' ) );

		add_action( 'admin_post_ds_save_screen', array( $this, 'handle_save_screen' ) );
		add_action( 'admin_post_ds_delete_screen', array( $this, 'handle_delete_screen' ) );

		add_action( 'admin_post_ds_save_schedule', array( $this, 'handle_save_schedule' ) );
		add_action( 'admin_post_ds_delete_schedule', array( $this, 'handle_delete_schedule' ) );
		add_action( 'admin_post_ds_clone_schedule', array( $this, 'handle_clone_schedule' ) );

		add_action( 'admin_post_ds_pair_screen', array( $this, 'handle_pairing' ) );
		add_action( 'admin_post_ds_bulk_assign_channel', array( $this, 'handle_bulk_assign' ) );
		add_action( 'admin_post_ds_remote_action', array( $this, 'handle_remote_action' ) );
		add_action( 'admin_post_ds_device_command', array( $this, 'handle_device_command' ) );
	}

	/* =================================================================
	 * Menu
	 * ================================================================= */

	public function menu() {
		add_menu_page(
			__( 'Digital Signage', 'digital-signage' ),
			__( 'Digital Signage', 'digital-signage' ),
			DS_Roles::CAP,
			'digital-signage',
			array( $this, 'render_dashboard' ),
			'dashicons-desktop',
			26
		);

		add_submenu_page( 'digital-signage', __( 'Dashboard', 'digital-signage' ), __( 'Dashboard', 'digital-signage' ), DS_Roles::CAP, 'digital-signage', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'digital-signage', __( 'Channels', 'digital-signage' ), __( 'Channels', 'digital-signage' ), DS_Roles::CAP, 'ds-channels', array( $this, 'render_channels_list' ) );
		add_submenu_page( 'digital-signage', __( 'Screens', 'digital-signage' ), __( 'Screens', 'digital-signage' ), DS_Roles::CAP, 'ds-screens', array( $this, 'render_screens_list' ) );
		add_submenu_page( 'digital-signage', __( 'Schedules', 'digital-signage' ), __( 'Schedules', 'digital-signage' ), DS_Roles::CAP, 'ds-schedules', array( $this, 'render_schedules_list' ) );
		add_submenu_page( 'digital-signage', __( 'Calendar', 'digital-signage' ), __( 'Calendar', 'digital-signage' ), DS_Roles::CAP, 'ds-calendar', array( $this, 'render_calendar' ) );
		add_submenu_page( 'digital-signage', __( 'Settings', 'digital-signage' ), __( 'Settings', 'digital-signage' ), 'manage_options', 'ds-settings', array( $this, 'render_settings' ) );

		// Hidden pages: reached via buttons/links from the pages above, not the sidebar.
		add_submenu_page( null, __( 'Edit Channel', 'digital-signage' ), '', DS_Roles::CAP, 'ds-channel-edit', array( $this, 'render_channel_edit' ) );
		add_submenu_page( null, __( 'Edit Slide', 'digital-signage' ), '', DS_Roles::CAP, 'ds-slide-edit', array( $this, 'render_slide_edit' ) );
		add_submenu_page( null, __( 'Edit Screen', 'digital-signage' ), '', DS_Roles::CAP, 'ds-screen-edit', array( $this, 'render_screen_edit' ) );
		add_submenu_page( null, __( 'Edit Schedule', 'digital-signage' ), '', DS_Roles::CAP, 'ds-schedule-edit', array( $this, 'render_schedule_edit' ) );
		add_submenu_page( null, __( 'Pair a Screen', 'digital-signage' ), '', DS_Roles::CAP, 'ds-pairing', array( $this, 'render_pairing' ) );
		add_submenu_page( null, __( 'Proof of Play', 'digital-signage' ), '', DS_Roles::CAP, 'ds-analytics', array( 'DS_Analytics', 'render_page' ) );
		add_submenu_page( null, __( 'Import / Export', 'digital-signage' ), '', DS_Roles::CAP, 'ds-import-export', array( 'DS_Import_Export', 'render_page' ) );
	}

	public function body_class( $classes ) {
		$screen = get_current_screen();
		if ( $screen && ( false !== strpos( $screen->id, 'digital-signage' ) || false !== strpos( $screen->id, 'ds-' ) ) ) {
			$classes .= ' ds-app';
		}
		return $classes;
	}

	public function assets( $hook ) {
		$screen = get_current_screen();
		$is_ds  = $screen && ( false !== strpos( $screen->id, 'digital-signage' ) || false !== strpos( $screen->id, 'ds-' ) );

		if ( ! $is_ds ) {
			return;
		}

		wp_enqueue_style( 'ds-admin', DS_PLUGIN_URL . 'admin/css/admin.css', array(), DS_VERSION );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'ds-admin', DS_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-util' ), DS_VERSION, true );
		wp_enqueue_media();

		wp_localize_script(
			'ds-admin',
			'DS_Admin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'ds/v1' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/* =================================================================
	 * Page renders — thin wrappers that load a view partial.
	 * ================================================================= */

	public function render_dashboard() {
		$this->require_cap();
		global $wpdb;
		$screens       = get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1, 'post_status' => 'any' ) );
		$heartbeats    = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ds_heartbeats", OBJECT_K );
		$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );
		$channel_count = count( get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
		$this->view( 'dashboard', compact( 'screens', 'heartbeats', 'offline_after', 'channel_count' ) );
	}

	public function render_channels_list() {
		$this->require_cap();
		$channels = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$this->view( 'channels-list', compact( 'channels' ) );
	}

	public function render_channel_edit() {
		$this->require_cap();
		$id      = absint( $_GET['id'] ?? 0 );
		$channel = $id ? get_post( $id ) : null;
		if ( $id && ( ! $channel || 'ds_channel' !== $channel->post_type ) ) {
			wp_die( esc_html__( 'Channel not found.', 'digital-signage' ) );
		}

		$layout_template = $channel ? ( get_post_meta( $id, 'ds_layout_template', true ) ?: 'fullscreen' ) : 'fullscreen';
		$is_priority     = $channel ? get_post_meta( $id, 'ds_is_priority', true ) : false;
		$slides           = array();
		if ( $id ) {
			$slides = get_posts(
				array(
					'post_type'      => 'ds_slide',
					'posts_per_page' => -1,
					'meta_key'       => 'ds_channel_id',
					'meta_value'     => $id,
					'orderby'        => 'meta_value_num',
					'meta_key2'      => 'ds_order',
					'order'          => 'ASC',
				)
			);
		}
		$screens_using = $id ? get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1, 'meta_key' => 'ds_channel_id', 'meta_value' => $id, 'fields' => 'ids' ) ) : array();

		$this->view( 'channel-edit', compact( 'id', 'channel', 'layout_template', 'is_priority', 'slides', 'screens_using' ) );
	}

	public function render_slide_edit() {
		$this->require_cap();
		$id         = absint( $_GET['id'] ?? 0 );
		$channel_id = absint( $_GET['channel_id'] ?? 0 );
		$slide      = $id ? get_post( $id ) : null;
		if ( $id && ( ! $slide || 'ds_slide' !== $slide->post_type ) ) {
			wp_die( esc_html__( 'Slide not found.', 'digital-signage' ) );
		}
		if ( $slide && ! $channel_id ) {
			$channel_id = absint( get_post_meta( $id, 'ds_channel_id', true ) );
		}
		$channel = $channel_id ? get_post( $channel_id ) : null;

		$this->view( 'slide-edit', compact( 'id', 'slide', 'channel_id', 'channel' ) );
	}

	public function render_screens_list() {
		$this->require_cap();
		global $wpdb;
		$screens       = get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$heartbeats    = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ds_heartbeats", OBJECT_K );
		$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );
		$channels      = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) );
		$this->view( 'screens-list', compact( 'screens', 'heartbeats', 'offline_after', 'channels' ) );
	}

	public function render_screen_edit() {
		$this->require_cap();
		$id     = absint( $_GET['id'] ?? 0 );
		$screen = $id ? get_post( $id ) : null;
		if ( $id && ( ! $screen || 'ds_screen' !== $screen->post_type ) ) {
			wp_die( esc_html__( 'Screen not found.', 'digital-signage' ) );
		}

		global $wpdb;
		$channel_id  = $id ? get_post_meta( $id, 'ds_channel_id', true ) : 0;
		$orientation = $id ? ( get_post_meta( $id, 'ds_orientation', true ) ?: 'landscape' ) : 'landscape';
		$token       = $id ? get_post_meta( $id, 'ds_pairing_token', true ) : '';
		$channels    = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) );
		$locations   = get_terms( array( 'taxonomy' => 'ds_location', 'hide_empty' => false ) );
		$current_loc = $id ? wp_get_post_terms( $id, 'ds_location', array( 'fields' => 'ids' ) ) : array();
		$heartbeat   = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ds_heartbeats WHERE screen_id = %d", $id ) ) : null;
		$device      = ( $heartbeat && ! empty( $heartbeat->device_info ) ) ? json_decode( $heartbeat->device_info, true ) : null;

		$this->view( 'screen-edit', compact( 'id', 'screen', 'channel_id', 'orientation', 'token', 'channels', 'locations', 'current_loc', 'heartbeat', 'device' ) );
	}

	public function render_schedules_list() {
		$this->require_cap();
		$schedules = get_posts( array( 'post_type' => 'ds_schedule', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$this->view( 'schedules-list', compact( 'schedules' ) );
	}

	public function render_schedule_edit() {
		$this->require_cap();
		$id       = absint( $_GET['id'] ?? 0 );
		$schedule = $id ? get_post( $id ) : null;
		if ( $id && ( ! $schedule || 'ds_schedule' !== $schedule->post_type ) ) {
			wp_die( esc_html__( 'Schedule not found.', 'digital-signage' ) );
		}

		$channel_id = $id ? get_post_meta( $id, 'ds_channel_id', true ) : 0;
		$screen_ids = $id ? (array) get_post_meta( $id, 'ds_screen_ids', true ) : array();
		$days       = $id ? (array) get_post_meta( $id, 'ds_days', true ) : array();
		$start_time = $id ? get_post_meta( $id, 'ds_start_time', true ) : '';
		$end_time   = $id ? get_post_meta( $id, 'ds_end_time', true ) : '';
		$start_date = $id ? get_post_meta( $id, 'ds_start_date', true ) : '';
		$end_date   = $id ? get_post_meta( $id, 'ds_end_date', true ) : '';
		$is_oneoff  = $id ? get_post_meta( $id, 'ds_is_one_off', true ) : false;
		$priority   = $id ? ( get_post_meta( $id, 'ds_priority', true ) ?: 10 ) : 10;
		$channels   = get_posts( array( 'post_type' => 'ds_channel', 'posts_per_page' => -1 ) );
		$screens    = get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1 ) );

		$this->view( 'schedule-edit', compact( 'id', 'schedule', 'channel_id', 'screen_ids', 'days', 'start_time', 'end_time', 'start_date', 'end_date', 'is_oneoff', 'priority', 'channels', 'screens' ) );
	}

	public function render_calendar() {
		$this->require_cap();
		$schedules = get_posts( array( 'post_type' => 'ds_schedule', 'posts_per_page' => -1 ) );
		$this->view( 'calendar', compact( 'schedules' ) );
	}

	public function render_pairing() {
		$this->require_cap();
		$this->view( 'pairing', array() );
	}

	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		$this->view( 'settings', array() );
	}

	private function view( $name, array $vars ) {
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		include DS_PLUGIN_DIR . 'admin/views/' . $name . '.php';
	}

	private function require_cap() {
		if ( ! current_user_can( DS_Roles::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access Digital Signage.', 'digital-signage' ) );
		}
	}

	/* =================================================================
	 * Form handlers (admin-post.php) — verify nonce + capability, call
	 * DS_CRUD, then redirect back into the custom UI (never to post.php).
	 * ================================================================= */

	public function handle_save_channel() {
		$this->require_cap();
		check_admin_referer( 'ds_save_channel' );

		$id = DS_CRUD::save_channel( absint( $_POST['id'] ?? 0 ), wp_unslash( $_POST ) );

		wp_safe_redirect( admin_url( 'admin.php?page=ds-channel-edit&id=' . $id . '&ds_saved=1' ) );
		exit;
	}

	public function handle_delete_channel() {
		$this->require_cap();
		check_admin_referer( 'ds_delete_channel' );
		DS_CRUD::delete_channel( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-channels&ds_deleted=1' ) );
		exit;
	}

	public function handle_duplicate_channel() {
		$this->require_cap();
		check_admin_referer( 'ds_duplicate_channel' );
		$new_id = DS_CRUD::duplicate_channel( absint( $_GET['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-channel-edit&id=' . $new_id . '&ds_saved=1' ) );
		exit;
	}

	public function handle_save_slide() {
		$this->require_cap();
		check_admin_referer( 'ds_save_slide' );

		$id         = DS_CRUD::save_slide( absint( $_POST['id'] ?? 0 ), wp_unslash( $_POST ) );
		$channel_id = absint( $_POST['channel_id'] ?? 0 );

		wp_safe_redirect( admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel_id . '&ds_saved=1#ds-playlist' ) );
		exit;
	}

	public function handle_delete_slide() {
		$this->require_cap();
		check_admin_referer( 'ds_delete_slide' );
		$channel_id = absint( $_POST['channel_id'] ?? 0 );
		DS_CRUD::delete_slide( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel_id . '&ds_deleted=1#ds-playlist' ) );
		exit;
	}

	public function handle_duplicate_slide() {
		$this->require_cap();
		check_admin_referer( 'ds_duplicate_slide' );
		$channel_id = absint( $_POST['channel_id'] ?? 0 );
		DS_CRUD::duplicate_slide( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel_id . '&ds_saved=1#ds-playlist' ) );
		exit;
	}

	public function handle_save_slide_order() {
		$this->require_cap();
		check_admin_referer( 'ds_save_slide_order' );
		$channel_id = absint( $_POST['channel_id'] ?? 0 );
		DS_CRUD::save_slide_order( (array) ( $_POST['order'] ?? array() ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel_id . '&ds_saved=1#ds-playlist' ) );
		exit;
	}

	public function handle_save_screen() {
		$this->require_cap();
		check_admin_referer( 'ds_save_screen' );
		$id = DS_CRUD::save_screen( absint( $_POST['id'] ?? 0 ), wp_unslash( $_POST ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-screen-edit&id=' . $id . '&ds_saved=1' ) );
		exit;
	}

	public function handle_delete_screen() {
		$this->require_cap();
		check_admin_referer( 'ds_delete_screen' );
		DS_CRUD::delete_screen( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-screens&ds_deleted=1' ) );
		exit;
	}

	public function handle_save_schedule() {
		$this->require_cap();
		check_admin_referer( 'ds_save_schedule' );
		$id = DS_CRUD::save_schedule( absint( $_POST['id'] ?? 0 ), wp_unslash( $_POST ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-schedule-edit&id=' . $id . '&ds_saved=1' ) );
		exit;
	}

	public function handle_delete_schedule() {
		$this->require_cap();
		check_admin_referer( 'ds_delete_schedule' );
		DS_CRUD::delete_schedule( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-schedules&ds_deleted=1' ) );
		exit;
	}

	public function handle_clone_schedule() {
		$this->require_cap();
		check_admin_referer( 'ds_clone_schedule' );
		$schedule_id = absint( $_POST['schedule_id'] ?? 0 );
		$screen_ids  = array_map( 'absint', (array) ( $_POST['screen_ids'] ?? array() ) );
		DS_CRUD::clone_schedule_to_screens( $schedule_id, $screen_ids );
		wp_safe_redirect( admin_url( 'admin.php?page=ds-schedules&ds_saved=1' ) );
		exit;
	}

	/* ---------------- Pairing / bulk / remote control ---------------- */

	public function handle_pairing() {
		$this->require_cap();
		check_admin_referer( 'ds_pair_screen' );

		global $wpdb;
		$code  = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
		$title = sanitize_text_field( wp_unslash( $_POST['screen_name'] ?? '' ) );
		$table = $wpdb->prefix . 'ds_pairing_codes';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", $code ) );

		if ( ! $row || strtotime( $row->expires_at . ' UTC' ) < time() || $row->paired_at ) {
			wp_safe_redirect( add_query_arg( 'ds_error', 'invalid_code', admin_url( 'admin.php?page=ds-pairing' ) ) );
			exit;
		}

		$screen_id = DS_CRUD::save_screen( 0, array( 'title' => $title ? $title : __( 'New Screen', 'digital-signage' ), 'orientation' => 'landscape' ) );
		update_post_meta( $screen_id, 'ds_pairing_token', $row->token );

		$wpdb->update( $table, array( 'screen_id' => $screen_id, 'paired_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen_id . '&ds_paired=1' ) );
		exit;
	}

	public function handle_bulk_assign() {
		$this->require_cap();
		check_admin_referer( 'ds_bulk_assign_channel' );

		$channel_id = absint( $_POST['channel_id'] ?? 0 );
		$screen_ids = array_map( 'absint', (array) ( $_POST['screen_ids'] ?? array() ) );

		foreach ( $screen_ids as $screen_id ) {
			update_post_meta( $screen_id, 'ds_channel_id', $channel_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ds-screens&ds_saved=1' ) );
		exit;
	}

	public function handle_remote_action() {
		$this->require_cap();
		check_admin_referer( 'ds_remote_action' );

		$screen_id = absint( $_POST['screen_id'] ?? 0 );
		$action    = sanitize_key( $_POST['remote_action'] ?? '' );

		if ( $screen_id && in_array( $action, array( 'refresh', 'reload', 'clear_override' ), true ) ) {
			update_post_meta( $screen_id, 'ds_remote_command', $action );
			update_post_meta( $screen_id, 'ds_remote_command_ts', time() );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen_id . '&ds_saved=1' ) );
		exit;
	}

	/**
	 * Queues a command for the on-device agent (raspberry-pi-kiosk/ds-agent) —
	 * WiFi change, screen rotation, reboot, restart the browser, or check for
	 * OS/package updates. Delivered next time that device's heartbeat answers.
	 */
	public function handle_device_command() {
		$this->require_cap();
		check_admin_referer( 'ds_device_command' );

		$screen_id = absint( $_POST['screen_id'] ?? 0 );
		$type      = sanitize_key( $_POST['command_type'] ?? '' );

		if ( ! $screen_id ) {
			wp_die( esc_html__( 'Missing screen.', 'digital-signage' ) );
		}

		switch ( $type ) {
			case 'wifi':
				$ssid = sanitize_text_field( wp_unslash( $_POST['wifi_ssid'] ?? '' ) );
				if ( ! $ssid ) {
					wp_safe_redirect( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen_id . '&ds_error=wifi_ssid' ) );
					exit;
				}
				DS_CRUD::queue_device_command(
					$screen_id,
					array(
						'type'     => 'wifi',
						'ssid'     => $ssid,
						// Sent once, applied by the device, never stored anywhere after that —
						// see raspberry-pi-kiosk/ds-agent, which discards it immediately after nmcli.
						'password' => (string) ( $_POST['wifi_password'] ?? '' ),
					)
				);
				break;

			case 'rotation':
				DS_CRUD::queue_device_command(
					$screen_id,
					array(
						'type'     => 'rotation',
						'rotation' => sanitize_key( $_POST['rotation'] ?? 'normal' ),
					)
				);
				break;

			case 'resolution':
				$resolution = trim( sanitize_text_field( wp_unslash( $_POST['resolution'] ?? '' ) ) );
				// Blank clears it — the device goes back to auto-detecting its
				// display. Otherwise it must be WIDTHxHEIGHT, e.g. 1920x440 for an
				// uncommon/stretched screen the Pi wouldn't detect correctly.
				if ( '' !== $resolution && ! preg_match( '/^[0-9]{2,5}x[0-9]{2,5}$/', $resolution ) ) {
					wp_safe_redirect( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen_id . '&ds_error=resolution' ) );
					exit;
				}
				DS_CRUD::queue_device_command(
					$screen_id,
					array(
						'type'       => 'resolution',
						'resolution' => $resolution,
					)
				);
				break;

			case 'reboot':
			case 'restart_browser':
			case 'check_updates':
				DS_CRUD::queue_device_command( $screen_id, array( 'type' => $type ) );
				break;

			default:
				wp_die( esc_html__( 'Unknown device command.', 'digital-signage' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ds-screen-edit&id=' . $screen_id . '&ds_device_queued=1' ) );
		exit;
	}
}
