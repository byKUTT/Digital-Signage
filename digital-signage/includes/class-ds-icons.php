<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small inline-SVG icon set used throughout the custom admin UI — slide type
 * badges in playlists, the type picker, and onboarding illustrations. Self
 * contained (no icon font/CDN), so it works offline and matches every theme.
 */
class DS_Icons {

	public static function icon( $name, $class = '' ) {
		$paths = array(
			'image'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 15l-5-5-9 9"/>',
			'video'   => '<rect x="3" y="5" width="14" height="14" rx="2"/><path d="M21 8l-4 3 4 3z"/>',
			'webpage' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><circle cx="6" cy="6.5" r=".6" fill="currentColor" stroke="none"/>',
			'html'    => '<path d="M4 3l1.5 17L12 22l6.5-2L20 3H4z"/><path d="M8 8h8l-.3 3H8.5M8.5 11l.3 3.5 3.2 1 3.2-1 .3-3"/>',
			'rss'     => '<circle cx="6" cy="18" r="1.5" fill="currentColor" stroke="none"/><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 5a15 15 0 0 1 15 15"/>',
			'weather' => '<path d="M17 17.5H8a4 4 0 1 1 1.2-7.8 5 5 0 0 1 9.6 1.9A3.5 3.5 0 0 1 17 17.5z"/>',
			'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
			'pdf'     => '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><path d="M9 13h1.5a1.5 1.5 0 0 1 0 3H9v-3zM13 13h1.5v5M13 15.5h1.2M17 13v5M17 15.5h1.5"/>',
			'social'  => '<circle cx="6" cy="12" r="2.4"/><circle cx="17" cy="6" r="2.4"/><circle cx="17" cy="18" r="2.4"/><path d="M8.1 10.8l6.8-3.6M8.1 13.2l6.8 3.6"/>',
			// Onboarding illustrations.
			'channel' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
			'screen'  => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
			'schedule' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
			'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="ds-icon %s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
			esc_attr( $class ),
			$paths[ $name ] // phpcs:ignore -- static, trusted SVG markup, not user input.
		);
	}

	public static function type_label( $type ) {
		$labels = array(
			'image'   => __( 'Image', 'digital-signage' ),
			'video'   => __( 'Video', 'digital-signage' ),
			'webpage' => __( 'Webpage', 'digital-signage' ),
			'html'    => __( 'HTML', 'digital-signage' ),
			'rss'     => __( 'RSS', 'digital-signage' ),
			'weather' => __( 'Weather', 'digital-signage' ),
			'clock'   => __( 'Clock', 'digital-signage' ),
			'pdf'     => __( 'PDF', 'digital-signage' ),
			'social'  => __( 'Social', 'digital-signage' ),
		);
		return $labels[ $type ] ?? ucfirst( $type );
	}
}
