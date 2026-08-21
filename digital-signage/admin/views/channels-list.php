<?php
/** @var WP_Post[] $channels */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ds-app-wrap">
	<div class="ds-app-header">
		<div>
			<h1><?php esc_html_e( 'Channels', 'digital-signage' ); ?></h1>
			<p class="ds-app-subtitle"><?php esc_html_e( 'A channel is a playlist of slides that plays on one or more screens.', 'digital-signage' ); ?></p>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channel-edit' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'New Channel', 'digital-signage' ); ?></a>
	</div>

	<?php if ( isset( $_GET['ds_deleted'] ) ) : ?>
		<div class="ds-notice ds-notice-success"><?php esc_html_e( 'Channel deleted.', 'digital-signage' ); ?></div>
	<?php endif; ?>

	<?php if ( $channels ) : ?>
		<div class="ds-card-grid">
			<?php foreach ( $channels as $channel ) : ?>
				<?php
				$slide_count  = count( get_posts( array( 'post_type' => 'ds_slide', 'posts_per_page' => -1, 'meta_key' => 'ds_channel_id', 'meta_value' => $channel->ID, 'fields' => 'ids' ) ) );
				$screen_count = count( get_posts( array( 'post_type' => 'ds_screen', 'posts_per_page' => -1, 'meta_key' => 'ds_channel_id', 'meta_value' => $channel->ID, 'fields' => 'ids' ) ) );
				$is_priority  = get_post_meta( $channel->ID, 'ds_is_priority', true );
				$edit_url     = admin_url( 'admin.php?page=ds-channel-edit&id=' . $channel->ID );
				?>
				<a class="ds-card ds-channel-card" href="<?php echo esc_url( $edit_url ); ?>">
					<div class="ds-card-top">
						<h3><?php echo esc_html( $channel->post_title ); ?></h3>
						<?php if ( $is_priority ) : ?><span class="ds-badge ds-badge-priority"><?php esc_html_e( 'Priority', 'digital-signage' ); ?></span><?php endif; ?>
					</div>
					<div class="ds-card-meta">
						<span><?php echo esc_html( $slide_count ); ?> <?php echo esc_html( _n( 'slide', 'slides', $slide_count, 'digital-signage' ) ); ?></span>
						<span>&middot;</span>
						<span><?php echo esc_html( $screen_count ); ?> <?php echo esc_html( _n( 'screen', 'screens', $screen_count, 'digital-signage' ) ); ?></span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="ds-empty-state">
			<p><?php esc_html_e( 'No channels yet.', 'digital-signage' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ds-channel-edit' ) ); ?>" class="ds-btn ds-btn-primary">+ <?php esc_html_e( 'Create your first Channel', 'digital-signage' ); ?></a>
		</div>
	<?php endif; ?>
</div>
