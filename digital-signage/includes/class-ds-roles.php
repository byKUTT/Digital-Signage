<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Signage Manager" role for non-technical staff: full control over
 * channels/screens/slides/schedules, but no access to plugins/themes/users/etc.
 */
class DS_Roles {

	private static $instance = null;

	const ROLE = 'ds_signage_manager';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
		// Self-heal: re-apply capabilities whenever the plugin version changes, so an
		// in-place upgrade (re-uploading the plugin without deactivate/reactivate)
		// still picks up newly added caps — this is what fixes "Update" on a post
		// silently failing/redirecting wrong for sites that activated an older build.
		add_action( 'init', array( __CLASS__, 'maybe_refresh_caps' ), 5 );
	}

	public static function capabilities() {
		$caps = array(
			'read'         => true,
			'upload_files' => true,
		);

		foreach ( array( 'ds_channel', 'ds_screen', 'ds_slide', 'ds_schedule' ) as $type ) {
			$plural = $type . 's';
			$caps   = array_merge(
				$caps,
				array(
					// Singular caps: what WP core's map_meta_cap() actually checks for
					// edit_post/read_post/delete_post on a post type registered with
					// capability_type as array( singular, plural ) + map_meta_cap => true.
					"edit_{$type}"                => true,
					"read_{$type}"                => true,
					"delete_{$type}"              => true,
					// Plural caps: list-table access (edit_posts) and bulk/global actions.
					"edit_{$plural}"              => true,
					"edit_others_{$plural}"       => true,
					"publish_{$plural}"           => true,
					"edit_published_{$plural}"    => true,
					"edit_private_{$plural}"      => true,
					"delete_{$plural}"            => true,
					"delete_others_{$plural}"     => true,
					"delete_published_{$plural}"  => true,
					"delete_private_{$plural}"    => true,
					"read_private_{$plural}"      => true,
				)
			);
		}

		return $caps;
	}

	public static function add_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Signage Manager', 'digital-signage' ), self::capabilities() );
		}

		// Give administrators the same caps automatically.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::capabilities() as $cap => $grant ) {
				$admin->add_cap( $cap );
			}
			$admin->add_cap( 'manage_digital_signage' );
		}

		$manager = get_role( self::ROLE );
		if ( $manager ) {
			foreach ( self::capabilities() as $cap => $grant ) {
				$manager->add_cap( $cap );
			}
			$manager->add_cap( 'manage_digital_signage' );
		}

		update_option( 'ds_roles_version', DS_VERSION );
	}

	public static function maybe_refresh_caps() {
		if ( get_option( 'ds_roles_version' ) !== DS_VERSION ) {
			self::add_role();
		}
	}

	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		return $caps;
	}
}
