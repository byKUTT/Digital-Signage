<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the chrome-less fullscreen frontend player at /signage/play/{token}/.
 * Bypasses the active theme entirely (no header/footer/sidebar/admin bar) and
 * loads a minimal, dependency-light HTML/CSS/JS shell that talks to the REST API.
 */
class DS_Player {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_render_player' ) );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
	}

	public function hide_admin_bar( $show ) {
		if ( get_query_var( 'ds_screen_token' ) ) {
			return false;
		}
		return $show;
	}

	public function maybe_render_player() {
		$token = get_query_var( 'ds_screen_token' );
		if ( ! $token ) {
			return;
		}

		$token = sanitize_text_field( $token );

		$screens = get_posts(
			array(
				'post_type'      => 'ds_screen',
				'posts_per_page' => 1,
				'meta_key'       => 'ds_pairing_token',
				'meta_value'     => $token,
				'post_status'    => 'any',
			)
		);

		nocache_headers();

		if ( ! $screens ) {
			$this->render_unpaired_screen( $token );
			exit;
		}

		$this->render_player( $screens[0], $token );
		exit;
	}

	/**
	 * A screen visits its player URL before it has been claimed in the admin.
	 * We mint a pairing code/token pair on the fly and show it full-screen so
	 * staff can read it off the TV and enter it in wp-admin > Pair a Screen.
	 */
	private function render_unpaired_screen( $token ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ds_pairing_codes';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) );

		if ( ! $row ) {
			$code = strtoupper( substr( str_shuffle( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789' ), 0, 6 ) );
			$wpdb->insert(
				$table,
				array(
					'code'       => $code,
					'token'      => $token,
					'created_at' => current_time( 'mysql', true ),
					'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				)
			);
		} else {
			$code = $row->code;
		}

		include DS_PLUGIN_DIR . 'public/templates/pairing-screen.php';
	}

	private function render_player( $screen, $token ) {
		$rest_url = esc_url_raw( rest_url( 'ds/v1/screen/' . $token ) );
		include DS_PLUGIN_DIR . 'public/templates/player-template.php';
	}
}
