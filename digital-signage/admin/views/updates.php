<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$release_version = is_wp_error( $release ) ? '' : $release['version'];
$has_update       = $release_version && version_compare( $release_version, DS_VERSION, '>' );
?>
<div class="wrap ds-admin-wrap">
	<div class="ds-app-header">
		<div>
			<h1><?php esc_html_e( 'Updates', 'digital-signage' ); ?></h1>
			<p class="ds-app-subtitle"><?php esc_html_e( 'Verified WordPress and controller releases from the public byKUTT/Digital-Signage GitHub repository.', 'digital-signage' ); ?></p>
		</div>
		<a class="ds-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=ds-updates&ds_checked=1' ) ); ?>"><?php esc_html_e( 'Check Now', 'digital-signage' ); ?></a>
	</div>

	<?php if ( isset( $_GET['ds_updated'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'WordPress plugin update installed.', 'digital-signage' ); ?></div>
	<?php elseif ( isset( $_GET['ds_error'] ) ) : ?>
		<div class="ds-notice ds-notice-error"><?php esc_html_e( 'The WordPress plugin update was not installed. Check the release diagnostics and server filesystem permissions.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<?php if ( is_array( $controller_update_notice ) ) : ?>
		<?php if ( ! empty( $controller_update_notice['queued'] ) ) : ?>
			<div class="ds-notice ds-notice-success">
				<?php
				echo esc_html(
					sprintf(
						_n( '%d controller update was queued.', '%d controller updates were queued.', (int) $controller_update_notice['queued'], 'digital-signage' ),
						(int) $controller_update_notice['queued']
					)
				);
				?>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $controller_update_notice['skipped'] ) ) : ?>
			<div class="ds-notice"><?php echo esc_html( sprintf( __( '%d controllers were skipped because they are current, newer, or already have this update queued.', 'digital-signage' ), (int) $controller_update_notice['skipped'] ) ); ?></div>
		<?php endif; ?>
		<?php foreach ( (array) ( $controller_update_notice['errors'] ?? array() ) as $error_message ) : ?>
			<div class="ds-notice ds-notice-error"><?php echo esc_html( $error_message ); ?></div>
		<?php endforeach; ?>
	<?php endif; ?>

	<div class="ds-dashboard-grid">
		<div class="ds-panel">
			<h2><?php esc_html_e( 'WordPress plugin', 'digital-signage' ); ?></h2>
			<div class="ds-version-pair">
				<span><small><?php esc_html_e( 'Installed', 'digital-signage' ); ?></small><strong><?php echo esc_html( DS_VERSION ); ?></strong></span>
				<span><small><?php esc_html_e( 'Latest verified', 'digital-signage' ); ?></small><strong><?php echo $release_version ? esc_html( $release_version ) : '—'; ?></strong></span>
			</div>
			<?php if ( $has_update ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ds_install_plugin_update" />
					<?php wp_nonce_field( 'ds_install_plugin_update' ); ?>
					<button class="ds-btn ds-btn-primary" type="submit"><?php esc_html_e( 'Update WordPress Plugin', 'digital-signage' ); ?></button>
				</form>
			<?php else : ?>
				<p class="ds-hint"><?php esc_html_e( 'This WordPress installation is current, or no newer complete release is available.', 'digital-signage' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ds-panel">
			<h2><?php esc_html_e( 'Update policy', 'digital-signage' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_update_settings" />
				<?php wp_nonce_field( 'ds_update_settings' ); ?>
				<label><input type="checkbox" name="auto_update" value="1" <?php checked( $auto_update ); ?> /> <?php esc_html_e( 'Automatically install verified WordPress plugin releases', 'digital-signage' ); ?></label>
				<p class="ds-hint"><?php esc_html_e( 'Controller updates stay manual so you can choose when each signage computer restarts its software.', 'digital-signage' ); ?></p>
				<button class="ds-btn" type="submit"><?php esc_html_e( 'Save Update Policy', 'digital-signage' ); ?></button>
			</form>
		</div>
	</div>

	<div class="ds-panel">
		<h2><?php esc_html_e( 'Controller software', 'digital-signage' ); ?></h2>
		<?php if ( is_wp_error( $release ) ) : ?>
			<div class="ds-notice ds-notice-error"><?php echo esc_html( $release->get_error_message() ); ?></div>
			<p class="ds-hint"><?php esc_html_e( 'A branch push is not an update. Publish a version tag as a GitHub Release with all three packages and SHA256SUMS.', 'digital-signage' ); ?></p>
		<?php elseif ( empty( $controllers ) ) : ?>
			<p><?php esc_html_e( 'No Linux or Windows controllers are paired yet.', 'digital-signage' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ds_update_controllers" />
				<?php wp_nonce_field( 'ds_update_controllers' ); ?>
				<div class="ds-table-wrap">
					<table class="widefat striped ds-table">
						<thead>
							<tr>
								<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'digital-signage' ); ?></span></td>
								<th><?php esc_html_e( 'Controller', 'digital-signage' ); ?></th>
								<th><?php esc_html_e( 'Platform', 'digital-signage' ); ?></th>
								<th><?php esc_html_e( 'Connection', 'digital-signage' ); ?></th>
								<th><?php esc_html_e( 'Installed', 'digital-signage' ); ?></th>
								<th><?php esc_html_e( 'Target', 'digital-signage' ); ?></th>
								<th><?php esc_html_e( 'Latest update command', 'digital-signage' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $controllers as $controller ) : ?>
							<?php
							$id       = (int) $controller->id;
							$current  = (string) $controller->software_version;
							$online   = $controller->last_seen && ( time() - strtotime( $controller->last_seen . ' UTC' ) ) <= DS_Controllers::ONLINE_SECONDS;
							$current_is_valid = (bool) preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $current );
							$outdated = ! $current_is_valid || version_compare( $current, $release_version, '<' );
							$command  = $update_commands[ $id ] ?? null;
							?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="controller_ids[]" value="<?php echo esc_attr( $id ); ?>" /></th>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-controller-edit&id=' . $id ) ); ?>"><strong><?php echo esc_html( $controller->name ?: $controller->hostname ); ?></strong></a></td>
								<td><?php echo esc_html( ucfirst( $controller->platform ) ); ?></td>
								<td><span class="ds-badge <?php echo $online ? 'ds-badge-online' : 'ds-badge-offline'; ?>"><?php echo $online ? esc_html__( 'Online', 'digital-signage' ) : esc_html__( 'Offline', 'digital-signage' ); ?></span><?php if ( ! $online ) : ?><br><small><?php esc_html_e( 'Update remains queued', 'digital-signage' ); ?></small><?php endif; ?></td>
								<td><strong><?php echo $current ? esc_html( $current ) : '—'; ?></strong><?php if ( $outdated ) : ?> <span class="ds-badge"><?php esc_html_e( 'Update available', 'digital-signage' ); ?></span><?php endif; ?></td>
								<td><?php echo esc_html( $release_version ); ?></td>
								<td>
									<?php if ( $command ) : ?>
										<strong><?php echo esc_html( ucfirst( $command->status ) ); ?></strong>
										<?php if ( $command->result ) : ?><br><small><?php echo esc_html( $command->result ); ?></small><?php endif; ?>
									<?php else : ?>—<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="ds-controller-actions">
					<button class="ds-btn" type="submit" name="update_mode" value="selected"><?php esc_html_e( 'Update Selected Controllers', 'digital-signage' ); ?></button>
					<button class="ds-btn ds-btn-primary" type="submit" name="update_mode" value="all_outdated"><?php esc_html_e( 'Update All Outdated Controllers', 'digital-signage' ); ?></button>
				</p>
				<p class="ds-hint"><?php esc_html_e( 'Selecting a controller already on the target version reinstalls that release. Controllers newer than the public release are never downgraded.', 'digital-signage' ); ?></p>
			</form>
		<?php endif; ?>
	</div>

	<div class="ds-panel">
		<h2><?php esc_html_e( 'Release details', 'digital-signage' ); ?></h2>
		<?php if ( is_wp_error( $release ) ) : ?>
			<div class="ds-notice ds-notice-error"><?php echo esc_html( $release->get_error_message() ); ?></div>
		<?php else : ?>
			<p><strong><?php echo esc_html( $release['name'] ); ?></strong> · <?php echo esc_html( $release['published_at'] ); ?></p>
			<pre class="ds-release-notes"><?php echo esc_html( $release['notes'] ); ?></pre>
			<p class="ds-hint"><?php echo esc_html( sprintf( __( '%d verified release assets found.', 'digital-signage' ), count( $release['assets'] ) ) ); ?></p>
		<?php endif; ?>
	</div>
</div>
