<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu, Screens dashboard (live status), pairing flow, settings page,
 * calendar view and bulk actions (assign channel to screens, duplicate playlist,
 * clone schedule).
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
		add_action( 'admin_post_ds_pair_screen', array( $this, 'handle_pairing' ) );
		add_action( 'admin_post_ds_bulk_assign_channel', array( $this, 'handle_bulk_assign' ) );
		add_action( 'admin_post_ds_clone_schedule', array( $this, 'handle_clone_schedule' ) );
		add_action( 'admin_post_ds_duplicate_playlist', array( $this, 'handle_duplicate_playlist' ) );
		add_action( 'admin_post_ds_remote_action', array( $this, 'handle_remote_action' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'manage_ds_screen_posts_columns', array( $this, 'screen_columns' ) );
		add_action( 'manage_ds_screen_posts_custom_column', array( $this, 'screen_column_content' ), 10, 2 );
		add_filter( 'manage_ds_slide_posts_columns', array( $this, 'slide_columns' ) );
		add_action( 'manage_ds_slide_posts_custom_column', array( $this, 'slide_column_content' ), 10, 2 );
	}

	public function menu() {
		add_menu_page(
			__( 'Digital Signage', 'digital-signage' ),
			__( 'Digital Signage', 'digital-signage' ),
			'manage_digital_signage',
			'digital-signage',
			array( $this, 'render_dashboard' ),
			'dashicons-desktop',
			26
		);

		add_submenu_page( 'digital-signage', __( 'Screens Dashboard', 'digital-signage' ), __( 'Dashboard', 'digital-signage' ), 'manage_digital_signage', 'digital-signage', array( $this, 'render_dashboard' ) );

		add_submenu_page( 'digital-signage', __( 'Channels', 'digital-signage' ), __( 'Channels', 'digital-signage' ), 'edit_ds_channels', 'edit.php?post_type=ds_channel' );
		add_submenu_page( 'digital-signage', __( 'Screens', 'digital-signage' ), __( 'Screens', 'digital-signage' ), 'edit_ds_screens', 'edit.php?post_type=ds_screen' );
		add_submenu_page( 'digital-signage', __( 'Slides', 'digital-signage' ), __( 'Slides', 'digital-signage' ), 'edit_ds_slides', 'edit.php?post_type=ds_slide' );
		add_submenu_page( 'digital-signage', __( 'Schedules', 'digital-signage' ), __( 'Schedules', 'digital-signage' ), 'edit_ds_schedules', 'edit.php?post_type=ds_schedule' );

		add_submenu_page( 'digital-signage', __( 'Calendar', 'digital-signage' ), __( 'Calendar', 'digital-signage' ), 'manage_digital_signage', 'ds-calendar', array( $this, 'render_calendar' ) );
		add_submenu_page( 'digital-signage', __( 'Pair a Screen', 'digital-signage' ), __( 'Pair a Screen', 'digital-signage' ), 'manage_digital_signage', 'ds-pairing', array( $this, 'render_pairing' ) );
		add_submenu_page( 'digital-signage', __( 'Proof of Play', 'digital-signage' ), __( 'Proof of Play', 'digital-signage' ), 'manage_digital_signage', 'ds-analytics', array( 'DS_Analytics', 'render_page' ) );
		add_submenu_page( 'digital-signage', __( 'Import / Export', 'digital-signage' ), __( 'Import / Export', 'digital-signage' ), 'manage_digital_signage', 'ds-import-export', array( 'DS_Import_Export', 'render_page' ) );
		add_submenu_page( 'digital-signage', __( 'Settings', 'digital-signage' ), __( 'Settings', 'digital-signage' ), 'manage_options', 'ds-settings', array( $this, 'render_settings' ) );
	}

	public function assets( $hook ) {
		$screen = get_current_screen();
		$is_ds  = $screen && ( strpos( $screen->id, 'ds_' ) !== false || strpos( $hook, 'digital-signage' ) !== false || strpos( $hook, 'ds-' ) !== false );

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

	/** ---------------- Screens Dashboard ---------------- */

	public function render_dashboard() {
		global $wpdb;
		$screens    = get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1, 'post_status' => 'any' ) );
		$heartbeats = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ds_heartbeats", OBJECT_K );
		$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );

		include DS_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function screen_status( $screen_id, $heartbeats, $offline_after ) {
		if ( empty( $heartbeats[ $screen_id ] ) ) {
			return 'never';
		}
		$last = strtotime( $heartbeats[ $screen_id ]->last_seen . ' UTC' );
		return ( time() - $last ) <= $offline_after ? 'online' : 'offline';
	}

	/** ---------------- Pairing flow ---------------- */

	public function render_pairing() {
		include DS_PLUGIN_DIR . 'admin/views/pairing.php';
	}

	public function handle_pairing() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_pair_screen' );

		global $wpdb;
		$code       = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
		$title      = sanitize_text_field( wp_unslash( $_POST['screen_name'] ?? '' ) );
		$table      = $wpdb->prefix . 'ds_pairing_codes';
		$row        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", $code ) );

		if ( ! $row || strtotime( $row->expires_at . ' UTC' ) < time() || $row->paired_at ) {
			wp_safe_redirect( add_query_arg( 'ds_error', 'invalid_code', wp_get_referer() ) );
			exit;
		}

		$screen_id = wp_insert_post(
			array(
				'post_type'   => 'ds_screen',
				'post_title'  => $title ? $title : __( 'New Screen', 'digital-signage' ),
				'post_status' => 'publish',
			)
		);

		update_post_meta( $screen_id, 'ds_pairing_token', $row->token );
		update_post_meta( $screen_id, 'ds_orientation', 'landscape' );

		$wpdb->update( $table, array( 'screen_id' => $screen_id, 'paired_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ) );

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $screen_id ) );
		exit;
	}

	/** ---------------- Bulk actions ---------------- */

	public function handle_bulk_assign() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_bulk_assign_channel' );

		$channel_id = absint( $_POST['channel_id'] ?? 0 );
		$screen_ids = array_map( 'absint', (array) ( $_POST['screen_ids'] ?? array() ) );

		foreach ( $screen_ids as $screen_id ) {
			update_post_meta( $screen_id, 'ds_channel_id', $channel_id );
		}

		wp_safe_redirect( add_query_arg( 'ds_notice', 'assigned', admin_url( 'edit.php?post_type=ds_screen' ) ) );
		exit;
	}

	public function handle_duplicate_playlist() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_duplicate_playlist' );

		$channel_id = absint( $_GET['channel_id'] ?? 0 );
		$source     = get_post( $channel_id );
		if ( ! $source || 'ds_channel' !== $source->post_type ) {
			wp_die( esc_html__( 'Channel not found.', 'digital-signage' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => 'ds_channel',
				'post_title'  => $source->post_title . ' ' . __( '(Copy)', 'digital-signage' ),
				'post_status' => 'publish',
			)
		);

		foreach ( get_post_meta( $channel_id ) as $key => $values ) {
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		$slides = get_posts(
			array(
				'post_type'      => 'ds_slide',
				'posts_per_page' => -1,
				'meta_key'       => 'ds_channel_id',
				'meta_value'     => $channel_id,
			)
		);

		foreach ( $slides as $slide ) {
			$new_slide = wp_insert_post(
				array(
					'post_type'   => 'ds_slide',
					'post_title'  => $slide->post_title,
					'post_status' => 'publish',
				)
			);
			foreach ( get_post_meta( $slide->ID ) as $key => $values ) {
				foreach ( $values as $value ) {
					add_post_meta( $new_slide, $key, maybe_unserialize( $value ) );
				}
			}
			update_post_meta( $new_slide, 'ds_channel_id', $new_id );
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
		exit;
	}

	public function handle_clone_schedule() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_clone_schedule' );

		$schedule_id = absint( $_POST['schedule_id'] ?? 0 );
		$screen_ids  = array_map( 'absint', (array) ( $_POST['screen_ids'] ?? array() ) );
		$source      = get_post( $schedule_id );

		if ( ! $source || 'ds_schedule' !== $source->post_type ) {
			wp_die( esc_html__( 'Schedule not found.', 'digital-signage' ) );
		}

		foreach ( $screen_ids as $screen_id ) {
			$new_id = wp_insert_post(
				array(
					'post_type'   => 'ds_schedule',
					'post_title'  => $source->post_title . ' - ' . get_the_title( $screen_id ),
					'post_status' => 'publish',
				)
			);
			foreach ( get_post_meta( $schedule_id ) as $key => $values ) {
				foreach ( $values as $value ) {
					add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
				}
			}
			update_post_meta( $new_id, 'ds_screen_ids', array( $screen_id ) );
		}

		wp_safe_redirect( add_query_arg( 'ds_notice', 'cloned', admin_url( 'edit.php?post_type=ds_schedule' ) ) );
		exit;
	}

	/** Remote control: force refresh / reload / push override from the Screens list. */
	public function handle_remote_action() {
		if ( ! current_user_can( 'manage_digital_signage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'digital-signage' ) );
		}
		check_admin_referer( 'ds_remote_action' );

		$screen_id = absint( $_POST['screen_id'] ?? 0 );
		$action    = sanitize_key( $_POST['remote_action'] ?? '' );

		if ( $screen_id && in_array( $action, array( 'refresh', 'reload', 'clear_override' ), true ) ) {
			update_post_meta( $screen_id, 'ds_remote_command', $action );
			update_post_meta( $screen_id, 'ds_remote_command_ts', time() );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=ds_screen' ) );
		exit;
	}

	/** ---------------- Settings & Calendar pages ---------------- */

	public function render_settings() {
		include DS_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function render_calendar() {
		include DS_PLUGIN_DIR . 'admin/views/calendar.php';
	}

	/** ---------------- List table helpers ---------------- */

	public function row_actions( $actions, $post ) {
		if ( 'ds_channel' === $post->post_type && current_user_can( 'manage_digital_signage' ) ) {
			$url               = wp_nonce_url( admin_url( 'admin-post.php?action=ds_duplicate_playlist&channel_id=' . $post->ID ), 'ds_duplicate_playlist' );
			$actions['ds_dup'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'digital-signage' ) . '</a>';
		}
		return $actions;
	}

	public function screen_columns( $columns ) {
		$columns['ds_status']      = __( 'Status', 'digital-signage' );
		$columns['ds_channel']     = __( 'Channel', 'digital-signage' );
		$columns['ds_orientation'] = __( 'Orientation', 'digital-signage' );
		$columns['ds_last_seen']   = __( 'Last Heartbeat', 'digital-signage' );
		$columns['ds_player_url']  = __( 'Player URL', 'digital-signage' );
		return $columns;
	}

	public function screen_column_content( $column, $post_id ) {
		global $wpdb;
		$offline_after = (int) DS_Settings::get( 'offline_status_sec', 120 );

		switch ( $column ) {
			case 'ds_status':
				$hb     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ds_heartbeats WHERE screen_id = %d", $post_id ) );
				$status = $hb && ( time() - strtotime( $hb->last_seen . ' UTC' ) ) <= $offline_after ? 'online' : 'offline';
				echo '<span class="ds-badge ds-badge-' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
				break;
			case 'ds_channel':
				$channel_id = get_post_meta( $post_id, 'ds_channel_id', true );
				echo $channel_id ? esc_html( get_the_title( $channel_id ) ) : '&mdash;';
				break;
			case 'ds_orientation':
				echo esc_html( get_post_meta( $post_id, 'ds_orientation', true ) ?: 'landscape' );
				break;
			case 'ds_last_seen':
				$hb = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ds_heartbeats WHERE screen_id = %d", $post_id ) );
				echo $hb ? esc_html( human_time_diff( strtotime( $hb->last_seen . ' UTC' ) ) . ' ' . __( 'ago', 'digital-signage' ) ) : esc_html__( 'never', 'digital-signage' );
				break;
			case 'ds_player_url':
				$token = get_post_meta( $post_id, 'ds_pairing_token', true );
				if ( $token ) {
					$url = home_url( '/signage/play/' . $token . '/' );
					echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Open Player', 'digital-signage' ) . '</a>';
				}
				break;
		}
	}

	public function slide_columns( $columns ) {
		$columns['ds_type']     = __( 'Type', 'digital-signage' );
		$columns['ds_channel']  = __( 'Channel', 'digital-signage' );
		$columns['ds_zone']     = __( 'Zone', 'digital-signage' );
		$columns['ds_duration'] = __( 'Duration', 'digital-signage' );
		return $columns;
	}

	public function slide_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'ds_type':
				echo esc_html( get_post_meta( $post_id, 'ds_slide_type', true ) ?: 'image' );
				break;
			case 'ds_channel':
				$channel_id = get_post_meta( $post_id, 'ds_channel_id', true );
				echo $channel_id ? esc_html( get_the_title( $channel_id ) ) : '&mdash;';
				break;
			case 'ds_zone':
				echo esc_html( get_post_meta( $post_id, 'ds_zone', true ) ?: 'main' );
				break;
			case 'ds_duration':
				$override = get_post_meta( $post_id, 'ds_duration_override', true );
				echo $override ? esc_html( $override . 's' ) : esc_html__( 'default', 'digital-signage' );
				break;
		}
	}
}
