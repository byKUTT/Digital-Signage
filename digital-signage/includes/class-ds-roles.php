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
	}

	public static function capabilities() {
		$caps = array(
			'read'                   => true,
			'upload_files'           => true,
			'edit_ds_channels'       => true,
			'edit_others_ds_channels' => true,
			'publish_ds_channels'    => true,
			'edit_published_ds_channels' => true,
			'delete_ds_channels'     => true,
		);

		foreach ( array( 'ds_channel', 'ds_screen', 'ds_slide', 'ds_schedule' ) as $type ) {
			$plural = $type . 's';
			$caps   = array_merge(
				$caps,
				array(
					"edit_{$plural}"           => true,
					"edit_others_{$plural}"    => true,
					"publish_{$plural}"        => true,
					"edit_published_{$plural}" => true,
					"delete_{$plural}"         => true,
					"delete_others_{$plural}"  => true,
					"delete_published_{$plural}" => true,
					"read_private_{$plural}"   => true,
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
			$manager->add_cap( 'manage_digital_signage' );
		}
	}

	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		return $caps;
	}
}
