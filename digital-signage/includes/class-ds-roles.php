<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Signage Manager" role for non-technical staff: full control over
 * the custom Digital Signage admin (channels/screens/slides/schedules), but
 * no access to plugins/themes/users/etc.
 *
 * The whole custom admin (DS_Admin, DS_CRUD) is gated behind one capability,
 * 'manage_digital_signage', rather than WordPress's per-post-type meta
 * capabilities — there's no native post-editor screen involved anymore, so
 * there's nothing for WP core's capability mapping to intercept.
 */
class DS_Roles {

	private static $instance = null;

	const ROLE = 'ds_signage_manager';
	const CAP  = 'manage_digital_signage';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Self-heal: re-apply the capability whenever the plugin version changes,
		// so an in-place upgrade (no deactivate/reactivate) still picks it up.
		add_action( 'init', array( __CLASS__, 'maybe_refresh_caps' ), 5 );
	}

	public static function add_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role(
				self::ROLE,
				__( 'Signage Manager', 'digital-signage' ),
				array(
					'read'         => true,
					'upload_files' => true,
					self::CAP      => true,
				)
			);
		} else {
			get_role( self::ROLE )->add_cap( self::CAP );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP );
		}

		update_option( 'ds_roles_version', DS_VERSION );
	}

	public static function maybe_refresh_caps() {
		if ( get_option( 'ds_roles_version' ) !== DS_VERSION ) {
			self::add_role();
		}
	}
}
