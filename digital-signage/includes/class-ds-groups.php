<?php
/**
 * Group-scoped access for signage content, controllers, settings, and media.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Groups {
	const OPTION = 'ds_groups';
	const USER_META = 'ds_group_ids';
	const POST_META = 'ds_group_id';
	const CONTROLLER_OPTION = 'ds_controller_groups';
	private static $instance = null;

	public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }
	private function __construct() {
		add_action( 'add_attachment', array( $this, 'tag_attachment' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query' ) );
	}

	public static function all() { return (array) get_option( self::OPTION, array() ); }
	public static function get( $id ) { $groups = self::all(); return $groups[ absint( $id ) ] ?? null; }
	public static function create( $name ) {
		$groups = self::all(); $id = $groups ? max( array_map( 'absint', array_keys( $groups ) ) ) + 1 : 1;
		$groups[ $id ] = array( 'id' => $id, 'name' => sanitize_text_field( $name ), 'created_by' => get_current_user_id() );
		update_option( self::OPTION, $groups, false ); return $id;
	}
	public static function delete( $id ) { $groups = self::all(); unset( $groups[ absint( $id ) ] ); update_option( self::OPTION, $groups, false ); }
	public static function user_group_ids( $user_id = 0 ) { return array_values( array_unique( array_filter( array_map( 'absint', (array) get_user_meta( $user_id ?: get_current_user_id(), self::USER_META, true ) ) ) ) ); }
	public static function current_group_id() {
		$requested = absint( $_REQUEST['group'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $requested && ( current_user_can( 'manage_options' ) || in_array( $requested, self::user_group_ids(), true ) ) ) { return $requested; }
		$ids = self::user_group_ids(); return $ids ? $ids[0] : 0;
	}
	public static function add_user( $group_id, $user_id ) { $ids = self::user_group_ids( $user_id ); $ids[] = absint( $group_id ); update_user_meta( $user_id, self::USER_META, array_values( array_unique( $ids ) ) ); }
	public static function remove_user( $group_id, $user_id ) { update_user_meta( $user_id, self::USER_META, array_values( array_diff( self::user_group_ids( $user_id ), array( absint( $group_id ) ) ) ) ); }
	public static function users( $group_id ) { return get_users( array( 'meta_key' => self::USER_META, 'meta_compare' => 'EXISTS' ) ); }
	public static function post_group_id( $post ) { $post = $post instanceof WP_Post ? $post : get_post( absint( $post ) ); return $post ? absint( get_post_meta( $post->ID, self::POST_META, true ) ) : 0; }
	public static function set_post_group( $post_id, $group_id ) { update_post_meta( absint( $post_id ), self::POST_META, absint( $group_id ) ); }
	public static function can_access_post( $post ) {
		$post = $post instanceof WP_Post ? $post : get_post( absint( $post ) );
		if ( ! $post || ! current_user_can( DS_Roles::CAP ) ) { return false; }
		if ( current_user_can( 'manage_options' ) ) { return true; }
		$group_id = self::post_group_id( $post );
		if ( $group_id ? in_array( $group_id, self::user_group_ids(), true ) : (int) $post->post_author === get_current_user_id() ) { return true; }
		if ( 'ds_screen' === $post->post_type ) { $controller_id = absint( get_post_meta( $post->ID, 'ds_controller_id', true ) ); return $controller_id && self::can_access_controller( $controller_id ); }
		if ( 'ds_channel' === $post->post_type ) {
			foreach ( get_posts( array( 'post_type' => 'ds_screen', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => 'ds_channel_id', 'meta_value' => $post->ID ) ) as $screen ) { if ( self::can_access_post( $screen ) ) { return true; } }
		}
		if ( 'ds_slide' === $post->post_type ) { $channel_id = absint( get_post_meta( $post->ID, 'ds_channel_id', true ) ); return $channel_id && self::can_access_post( $channel_id ); }
		if ( 'ds_schedule' === $post->post_type ) {
			$channel_id = absint( get_post_meta( $post->ID, 'ds_channel_id', true ) );
			if ( $channel_id && self::can_access_post( $channel_id ) ) { return true; }
			foreach ( array_map( 'absint', (array) get_post_meta( $post->ID, 'ds_screen_ids', true ) ) as $screen_id ) { if ( self::can_access_post( $screen_id ) ) { return true; } }
		}
		return false;
	}
	private static function direct_post_access( $post ) { if ( ! $post instanceof WP_Post ) { return false; } $group_id = self::post_group_id( $post ); return $group_id ? in_array( $group_id, self::user_group_ids(), true ) : (int) $post->post_author === get_current_user_id(); }
	public static function controller_group_id( $controller_id ) { $map = (array) get_option( self::CONTROLLER_OPTION, array() ); return absint( $map[ absint( $controller_id ) ] ?? 0 ); }
	public static function set_controller_group( $controller_id, $group_id ) {
		$map = (array) get_option( self::CONTROLLER_OPTION, array() ); $map[ absint( $controller_id ) ] = absint( $group_id ); update_option( self::CONTROLLER_OPTION, $map, false );
		foreach ( DS_Controllers::get_displays( $controller_id ) as $display ) { if ( $display->screen_id ) { self::set_post_group( $display->screen_id, $group_id ); } }
	}
	public static function can_access_controller( $controller_id ) {
		if ( current_user_can( 'manage_options' ) || in_array( self::controller_group_id( $controller_id ), self::user_group_ids(), true ) ) { return true; }
		foreach ( DS_Controllers::get_displays( $controller_id ) as $display ) { if ( $display->screen_id && self::direct_post_access( get_post( $display->screen_id ) ) ) { return true; } }
		return false;
	}
	public static function settings( $group_id ) { return wp_parse_args( (array) get_option( 'ds_group_settings_' . absint( $group_id ), array() ), DS_Activator::default_settings() ); }
	public static function settings_for_post( $post_id ) { $group_id = self::post_group_id( $post_id ); return $group_id ? self::settings( $group_id ) : DS_Settings::get_all(); }
	public static function save_settings( $group_id, array $input ) { update_option( 'ds_group_settings_' . absint( $group_id ), DS_Settings::instance()->sanitize( $input ), false ); }
	public function tag_attachment( $attachment_id ) { $group_id = self::current_group_id(); if ( $group_id ) { self::set_post_group( $attachment_id, $group_id ); } }
	public function filter_media_query( $args ) {
		if ( current_user_can( 'manage_options' ) ) { return $args; }
		$ids = self::user_group_ids(); $args['meta_query'] = array( array( 'key' => self::POST_META, 'value' => $ids, 'compare' => 'IN', 'type' => 'NUMERIC' ) ); return $args;
	}
}
