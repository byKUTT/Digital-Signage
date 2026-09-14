<?php
/** Copy-ready administrator and Ubuntu controller guides. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$branch = 'claude/wordpress-digital-signage-plugin-mbfbdt';
$commands = array(
	'install' => "git clone --branch {$branch} https://github.com/byKUTT/Digital-Signage.git\ncd Digital-Signage\nsudo bash ubuntu/install-kiosk.sh \"https://screens.kutt.ee\" \"\$USER\"\nsudo reboot",
	'update' => "sudo digital-signage-update && sudo reboot",
	'repair' => "cd /opt/bykutt-digital-signage\nsudo git fetch origin {$branch}\nsudo git checkout -B {$branch} origin/{$branch}\nsudo bash ubuntu/install-kiosk.sh \"https://screens.kutt.ee\" \"\$USER\" --upgrade\nsudo reboot",
	'status' => "systemctl status digital-signage-ubuntu-update.service --no-pager\nsudo journalctl -u digital-signage-ubuntu-update.service -n 100 --no-pager",
	'vellum' => "cd /path/to/Digital-Signage\nsudo bash wp/bin/update-vellum.sh",
);
?>
<div class="ds-app-wrap ds-ops">
	<nav class="ds-ops-nav" aria-label="<?php esc_attr_e( 'Screens byKUTT administration', 'digital-signage' ); ?>"><a class="ds-ops-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=digital-signage' ) ); ?>"><span class="ds-ops-mark"></span><strong>Screens <small>byKUTT</small></strong></a><div><a href="<?php echo esc_url( admin_url( 'admin.php?page=digital-signage' ) ); ?>"><?php esc_html_e( 'Overview', 'digital-signage' ); ?></a><a class="active" href="<?php echo esc_url( admin_url( 'admin.php?page=ds-guides' ) ); ?>"><?php esc_html_e( 'Guides', 'digital-signage' ); ?></a><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-settings' ) ); ?>"><?php esc_html_e( 'Global settings', 'digital-signage' ); ?></a></div><a class="ds-ops-button" href="<?php echo esc_url( DS_Portal::url() ); ?>"><?php esc_html_e( 'Open Screen Manager', 'digital-signage' ); ?></a></nav>
	<header class="ds-ops-page-head"><p><?php esc_html_e( 'Controller handbook', 'digital-signage' ); ?></p><h1><?php esc_html_e( 'Short commands for the moments that matter.', 'digital-signage' ); ?></h1><span><?php esc_html_e( 'Run controller commands locally in Ubuntu Terminal. Every update command ends with a reboot.', 'digital-signage' ); ?></span></header>
	<section class="ds-guide-grid" id="updates">
		<?php foreach ( array(
			'update' => array( __( 'Normal controller update', 'digital-signage' ), __( 'Keeps the site URL, controller identity, display assignments, schedules, and browser profiles.', 'digital-signage' ) ),
			'install' => array( __( 'Fresh Ubuntu installation', 'digital-signage' ), __( 'Clone the supported branch, install the controller, then reboot into kiosk mode.', 'digital-signage' ) ),
			'repair' => array( __( 'Repair updater privileges', 'digital-signage' ), __( 'Use when WordPress reports that the controller privilege policy is missing or stale.', 'digital-signage' ) ),
			'status' => array( __( 'Read update failure details', 'digital-signage' ), __( 'Shows the service state and the latest one hundred update log lines.', 'digital-signage' ) ),
			'vellum' => array( __( 'Update the bundled Vellum designer', 'digital-signage' ), __( 'Run from a checked-out repository on the WordPress server, then rebuild the plugin ZIP.', 'digital-signage' ) ),
		) as $key => $guide ) : ?><article class="ds-guide-card"><header><div><h2><?php echo esc_html( $guide[0] ); ?></h2><p><?php echo esc_html( $guide[1] ); ?></p></div><button type="button" class="ds-copy-command" data-copy="<?php echo esc_attr( $commands[ $key ] ); ?>"><?php esc_html_e( 'Copy', 'digital-signage' ); ?></button></header><pre><code><?php echo esc_html( $commands[ $key ] ); ?></code></pre></article><?php endforeach; ?>
	</section>
	<aside class="ds-guide-note"><strong><?php esc_html_e( 'Passwordless remote actions are intentionally narrow.', 'digital-signage' ); ?></strong><p><?php esc_html_e( 'The installer grants the kiosk user access only to the signed controller helper actions. Screens byKUTT never stores an Ubuntu password and does not expose an arbitrary browser terminal.', 'digital-signage' ); ?></p></aside>
</div>
