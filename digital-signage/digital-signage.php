<?php
/**
 * Plugin Name:       Digital Signage CMS
 * Plugin URI:        https://github.com/bykutt/digital-signage
 * Description:       Turns WordPress into a full digital signage platform — manage channels, screens, playlists and schedules from wp-admin, and drive TVs/kiosks/tablets from a chrome-less fullscreen player.
 * Version:           2.1.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            byKUTT
 * Author URI:        https://github.com/byKUTT
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       digital-signage
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'DS_VERSION', '2.1.0' );
define( 'DS_PLUGIN_FILE', __FILE__ );
define( 'DS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DS_DB_VERSION', '1.0.0' );

/**
 * Autoload plugin classes.
 */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'DS_' ) !== 0 ) {
			return;
		}
		$file = DS_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

require_once DS_PLUGIN_DIR . 'includes/class-ds-activator.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-cpt.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-roles.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-settings.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-icons.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-admin.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-crud.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-schedule-resolver.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-rest.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-player.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-heartbeat.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-cron.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-analytics.php';
require_once DS_PLUGIN_DIR . 'includes/class-ds-import-export.php';

register_activation_hook( __FILE__, array( 'DS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DS_Activator', 'deactivate' ) );

/**
 * Bootstraps every plugin subsystem on `plugins_loaded`.
 */
function ds_bootstrap() {
	load_plugin_textdomain( 'digital-signage', false, dirname( plugin_basename( DS_PLUGIN_FILE ) ) . '/languages' );

	DS_CPT::instance();
	DS_Roles::instance();
	DS_Settings::instance();
	DS_Admin::instance();
	DS_CRUD::instance();
	DS_REST::instance();
	DS_Player::instance();
	DS_Heartbeat::instance();
	DS_Cron::instance();
	DS_Analytics::instance();
	DS_Import_Export::instance();

	DS_Activator::maybe_upgrade();
}
add_action( 'plugins_loaded', 'ds_bootstrap' );

/**
 * Flush rewrite rules once after the player route is registered on activation.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'ds_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'ds_flush_rewrite_rules' );
		}
	},
	20
);
