<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the custom post types that model the signage data:
 * Channels, Screens, Slides (playlist items) and Schedules.
 * REST is enabled on all of them so the React-ish admin screens and the
 * external REST API (DS_REST) can read/write via wp-json.
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
		return $vars;
	}

	public function register_rewrite_rules() {
		// yourdomain.com/signage/play/{token}
		add_rewrite_rule( '^signage/play/([^/]+)/?$', 'index.php?ds_screen_token=$matches[1]', 'top' );
	}

	public function register_post_types() {

		register_post_type(
			'ds_channel',
			array(
				'label'        => __( 'Channels', 'digital-signage' ),
				'labels'       => array(
					'name'          => __( 'Channels', 'digital-signage' ),
					'singular_name' => __( 'Channel', 'digital-signage' ),
					'add_new_item'  => __( 'Add New Channel', 'digital-signage' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'digital-signage',
				'show_in_rest' => true,
				'rest_base'    => 'ds_channels',
				'supports'     => array( 'title', 'custom-fields' ),
				'capability_type' => array( 'ds_channel', 'ds_channels' ),
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			'ds_screen',
			array(
				'label'        => __( 'Screens', 'digital-signage' ),
				'labels'       => array(
					'name'          => __( 'Screens', 'digital-signage' ),
					'singular_name' => __( 'Screen', 'digital-signage' ),
					'add_new_item'  => __( 'Add New Screen', 'digital-signage' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'digital-signage',
				'show_in_rest' => true,
				'rest_base'    => 'ds_screens',
				'supports'     => array( 'title', 'custom-fields' ),
				'capability_type' => array( 'ds_screen', 'ds_screens' ),
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			'ds_slide',
			array(
				'label'        => __( 'Slides', 'digital-signage' ),
				'labels'       => array(
					'name'          => __( 'Slides', 'digital-signage' ),
					'singular_name' => __( 'Slide', 'digital-signage' ),
					'add_new_item'  => __( 'Add New Slide', 'digital-signage' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'digital-signage',
				'show_in_rest' => true,
				'rest_base'    => 'ds_slides',
				'supports'     => array( 'title', 'custom-fields' ),
				'capability_type' => array( 'ds_slide', 'ds_slides' ),
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			'ds_schedule',
			array(
				'label'        => __( 'Schedules', 'digital-signage' ),
				'labels'       => array(
					'name'          => __( 'Schedules', 'digital-signage' ),
					'singular_name' => __( 'Schedule', 'digital-signage' ),
					'add_new_item'  => __( 'Add New Schedule', 'digital-signage' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'digital-signage',
				'show_in_rest' => true,
				'rest_base'    => 'ds_schedules',
				'supports'     => array( 'title', 'custom-fields' ),
				'capability_type' => array( 'ds_schedule', 'ds_schedules' ),
				'map_meta_cap' => true,
			)
		);
	}

	public function register_taxonomies() {
		// Locations let multiple screens be grouped by physical site (multi-tenant support).
		register_taxonomy(
			'ds_location',
			array( 'ds_screen' ),
			array(
				'label'             => __( 'Locations', 'digital-signage' ),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'public'            => false,
			)
		);
	}
}
