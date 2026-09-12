<?php
/**
 * Fires on plugin deletion (not deactivation) from wp-admin > Plugins.
 * Removes custom tables, options, and all CPT content created by the plugin.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Only run the destructive cleanup if the site owner opted in via this filter/constant,
// otherwise leave signage content intact in case the plugin is reinstalled.
if ( ! apply_filters( 'ds_uninstall_remove_data', defined( 'DS_REMOVE_DATA_ON_UNINSTALL' ) && DS_REMOVE_DATA_ON_UNINSTALL ) ) {
	return;
}

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ds_heartbeats" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ds_proof_of_play" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ds_pairing_codes" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ds_controller_commands" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ds_controller_displays" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ds_controllers" );

delete_option( 'ds_settings' );
delete_option( 'ds_db_version' );
delete_option( 'ds_flush_rewrite_rules' );
delete_option( 'ds_github_updater' );
delete_option( 'ds_github_release_cache' );
delete_option( 'ds_groups' );
delete_option( 'ds_controller_groups' );
delete_option( 'ds_controller_owners' );

foreach ( array( 'ds_channel', 'ds_screen', 'ds_slide', 'ds_schedule' ) as $post_type ) {
	$posts = get_posts( array( 'post_type' => $post_type, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

remove_role( 'ds_signage_manager' );
