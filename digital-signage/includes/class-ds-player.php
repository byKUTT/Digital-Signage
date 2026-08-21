<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the chrome-less fullscreen frontend player at /signage/play/{token}/,
 * and the equivalent wp-admin-only live preview at /signage/preview/{channel_id}/.
 * Both bypass the active theme entirely (no header/footer/sidebar/admin bar) and
 * load a minimal, dependency-light HTML/CSS/JS shell that talks to the REST API.
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
		if ( get_query_var( 'ds_screen_token' ) || get_query_var( 'ds_preview_channel' ) ) {
			return false;
		}
		return $show;
	}

	public function maybe_render_player() {
		$preview_channel = get_query_var( 'ds_preview_channel' );
		if ( $preview_channel ) {
			$this->maybe_render_preview( absint( $preview_channel ) );
			return;
		}

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
	 *
	 * A fresh code is generated every time this is hit fresh — i.e. every
	 * full page load, which in practice means every boot/browser (re)start —
	 * not reused from a previous visit. Between full loads the pairing
	 * screen's own JS keeps rotating it further every 30s on its own (see
	 * DS_REST::pair_status()); this is what makes a code minted right before
	 * boot never linger as a stale, already-displayed one.
	 */
	private function render_unpaired_screen( $token ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ds_pairing_codes';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) );
		$code  = DS_REST::generate_unique_pairing_code( $table ) ?: strtoupper( substr( str_shuffle( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789' ), 0, 6 ) );

		if ( $row ) {
			$wpdb->update(
				$table,
				array(
					'code'       => $code,
					'created_at' => current_time( 'mysql', true ),
					'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				),
				array( 'id' => $row->id )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'code'       => $code,
					'token'      => $token,
					'created_at' => current_time( 'mysql', true ),
					'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				)
			);
		}

		$pairing_url = admin_url( 'admin.php?page=ds-pairing&code=' . rawurlencode( $code ) );

		include DS_PLUGIN_DIR . 'public/templates/pairing-screen.php';
	}

	private function render_player( $screen, $token ) {
		$rest_url    = esc_url_raw( rest_url( 'ds/v1/screen/' . $token ) );
		$is_preview  = false;
		$preview_id  = 0;
		$preview_nonce = '';
		include DS_PLUGIN_DIR . 'public/templates/player-template.php';
	}

	/**
	 * /signage/preview/{channel_id}/ — same chrome-less renderer as the real player,
	 * but requires a logged-in wp-admin user with the signage capability, and reads
	 * straight from the /preview/{id} REST route instead of a paired screen.
	 */
	private function maybe_render_preview( $channel_id ) {
		nocache_headers();

		if ( ! is_user_logged_in() ) {
			auth_redirect(); // Sends them to wp-login.php, then back here.
			exit;
		}
		if ( ! current_user_can( DS_Roles::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to preview this channel.', 'digital-signage' ) );
		}

		if ( ! $channel_id || 'ds_channel' !== get_post_type( $channel_id ) ) {
			wp_die( esc_html__( 'Channel not found.', 'digital-signage' ) );
		}

		$screen        = (object) array( 'ID' => 0, 'post_title' => get_the_title( $channel_id ) );
		$token         = '';
		$rest_url      = esc_url_raw( rest_url( 'ds/v1' ) );
		$is_preview    = true;
		$preview_id    = $channel_id;
		$preview_nonce = wp_create_nonce( 'wp_rest' );

		include DS_PLUGIN_DIR . 'public/templates/player-template.php';
		exit;
	}
}
