<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$layouts = array(
	'fullscreen'   => __( 'Full Screen (single zone)', 'digital-signage' ),
	'main_ticker'  => __( 'Main + Bottom Ticker', 'digital-signage' ),
	'split_screen' => __( 'Split Screen (two zones)', 'digital-signage' ),
	'grid'         => __( 'Grid (main + ticker + corner)', 'digital-signage' ),
);
?>
<p><?php esc_html_e( 'Choose a pre-built zone layout. Assign each slide to a zone (Main, Ticker, Corner, Secondary) from the slide\'s Playlist Assignment box.', 'digital-signage' ); ?></p>
<div class="ds-layout-picker">
	<?php foreach ( $layouts as $key => $label ) : ?>
		<label class="ds-layout-option">
			<input type="radio" name="ds_layout_template" value="<?php echo esc_attr( $key ); ?>" <?php checked( $layout, $key ); ?> />
			<span class="ds-layout-thumb ds-layout-thumb-<?php echo esc_attr( $key ); ?>"></span>
			<?php echo esc_html( $label ); ?>
		</label>
	<?php endforeach; ?>
</div>
