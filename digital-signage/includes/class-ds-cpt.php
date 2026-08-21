<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the custom post types that store the signage data: Channels,
 * Screens, Slides (playlist items) and Schedules. These exist purely as a
 * storage/query layer (get_posts, post meta, WP-CLI/backup compatibility) —
 * there is no native WP edit screen for any of them ('show_ui' => false).
 * All create/edit/list UI is fully custom, built by DS_Admin/DS_CRUD and
 * gated by the single 'manage_digital_signage' capability rather than WP's
 * per-post-type meta capabilities.
 */
class DS_CPT {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'ds_screen_token';
		$vars[] = 'ds_preview_channel';
		return $vars;
	}

	public function register_rewrite_rules() {
		// yourdomain.com/signage/play/{token} — the real, unauthenticated player URL.
		add_rewrite_rule( '^signage/play/([^/]+)/?$', 'index.php?ds_screen_token=$matches[1]', 'top' );
		// yourdomain.com/signage/preview/{channel_id} — wp-admin-only live preview, same
		// chrome-less renderer, gated by login + capability in DS_Player.
		add_rewrite_rule( '^signage/preview/([0-9]+)/?$', 'index.php?ds_preview_channel=$matches[1]', 'top' );
	}

	public function register_post_types() {
		$common = array(
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'supports'     => array( 'title' ),
			'map_meta_cap' => false,
		);

		register_post_type( 'ds_channel', array_merge( $common, array( 'label' => __( 'Channels', 'digital-signage' ) ) ) );
		register_post_type( 'ds_screen', array_merge( $common, array( 'label' => __( 'Screens', 'digital-signage' ) ) ) );
		register_post_type( 'ds_slide', array_merge( $common, array( 'label' => __( 'Slides', 'digital-signage' ) ) ) );
		register_post_type( 'ds_schedule', array_merge( $common, array( 'label' => __( 'Schedules', 'digital-signage' ) ) ) );
	}

	public function register_taxonomies() {
		// Locations let multiple screens be grouped by physical site (multi-tenant support).
		register_taxonomy(
			'ds_location',
			array( 'ds_screen' ),
			array(
				'label'             => __( 'Locations', 'digital-signage' ),
				'hierarchical'      => true,
				'show_ui'           => false,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'public'            => false,
			)
		);
	}
}
