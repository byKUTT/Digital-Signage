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
		if ( get_query_var( 'ds_screen_token' ) || get_query_var( 'ds_preview_channel' ) || $this->get_short_request_code() || $this->is_tv_launcher_request() || $this->get_controller_request() ) {
			return false;
		}
		return $show;
	}

	public function maybe_render_player() {
		$controller_request = $this->get_controller_request();
		if ( $controller_request ) {
			$this->render_controller_pairing( $controller_request['public_id'], $controller_request['output_key'] );
			return;
		}
		if ( $this->is_tv_launcher_request() ) {
			$this->render_tv_launcher();
			return;
		}

		$preview_channel = get_query_var( 'ds_preview_channel' );
		if ( $preview_channel ) {
			$this->maybe_render_preview( absint( $preview_channel ) );
			return;
		}

		$short_code = $this->get_short_request_code();
		if ( $short_code ) {
			$this->render_short_player( $short_code );
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

	private function get_relative_request_path() {
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
		$home_path    = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( $home_path && 0 === strpos( $request_path, $home_path . '/' ) ) {
			$request_path = substr( $request_path, strlen( $home_path ) + 1 );
		}
		return trim( $request_path, '/' );
	}

	/**
	 * Recognize the stable TV launcher even when a host fails to flush saved
	 * WordPress rewrite rules during an in-place plugin update.
	 */
	private function is_tv_launcher_request() {
		if ( get_query_var( 'ds_tv_launcher' ) ) {
			return true;
		}

		return 'signage/tv' === $this->get_relative_request_path();
	}

	private function get_short_request_code() {
		$code = get_query_var( 'ds_short_code' );
		if ( $code ) {
			return strtoupper( sanitize_text_field( $code ) );
		}
		$path = $this->get_relative_request_path();
		return preg_match( '#^s/([A-Za-z0-9]{6})$#', $path, $matches ) ? strtoupper( $matches[1] ) : '';
	}

	/**
	 * Resolve a controller/output bootstrap URL, including a direct path fallback
	 * for hosts whose rewrite rules have not yet been flushed after upgrading.
	 */
	private function get_controller_request() {
		$public_id  = sanitize_text_field( get_query_var( 'ds_controller_id' ) );
		$output_key = sanitize_key( get_query_var( 'ds_controller_output' ) );
		if ( $public_id && $output_key ) {
			return array( 'public_id' => $public_id, 'output_key' => $output_key );
		}

		$path = $this->get_relative_request_path();
		if ( preg_match( '#^signage/controller/([a-f0-9-]{36})/([A-Za-z0-9_-]{1,100})$#', $path, $matches ) ) {
			return array( 'public_id' => $matches[1], 'output_key' => sanitize_key( $matches[2] ) );
		}
		return null;
	}

	private function render_controller_pairing( $public_id, $output_key ) {
		nocache_headers();
		$status_url = esc_url_raw( rest_url( 'ds/v1/controller/public/' . rawurlencode( $public_id ) . '/' . rawurlencode( $output_key ) ) );
		$site_name  = get_bloginfo( 'name' );
		include DS_PLUGIN_DIR . 'public/templates/controller-pairing.php';
		exit;
	}

	private function render_short_player( $code ) {
		$screens = get_posts(
			array(
				'post_type'      => 'ds_screen',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array( 'key' => 'ds_short_code', 'value' => $code ),
					array( 'key' => 'ds_short_url_enabled', 'value' => '1' ),
				),
				'post_status'    => 'any',
			)
		);
		nocache_headers();
		if ( ! $screens ) {
			wp_die( esc_html__( 'Short player URL not found or disabled.', 'digital-signage' ), '', array( 'response' => 404 ) );
		}
		$token = get_post_meta( $screens[0]->ID, 'ds_pairing_token', true );
		if ( ! $token ) {
			wp_die( esc_html__( 'This screen has not been paired.', 'digital-signage' ), '', array( 'response' => 404 ) );
		}
		$this->render_player( $screens[0], $token );
		exit;
	}

	public static function get_player_url( $screen ) {
		$screen_id = is_object( $screen ) ? absint( $screen->ID ) : absint( $screen );
		if ( ! $screen_id ) {
			return '';
		}
		$enabled = '1' === (string) get_post_meta( $screen_id, 'ds_short_url_enabled', true );
		$code    = strtoupper( sanitize_text_field( get_post_meta( $screen_id, 'ds_short_code', true ) ) );
		if ( $enabled && preg_match( '/^[A-Z0-9]{6}$/', $code ) ) {
			return home_url( '/s/' . $code . '/' );
		}
		$token = sanitize_text_field( get_post_meta( $screen_id, 'ds_pairing_token', true ) );
		return $token ? home_url( '/signage/play/' . $token . '/' ) : '';
	}

	/**
	 * Render the stable browser entry point used by VIDAA and other smart TVs.
	 * It owns only device identity/bootstrap; all playback remains in the normal
	 * token player so TV, Pi and Windows screens share one renderer.
	 */
	private function render_tv_launcher() {
		nocache_headers();

		$pair_request_url = esc_url_raw( rest_url( 'ds/v1/pair/request' ) );
		$pair_status_base = esc_url_raw( rest_url( 'ds/v1/pair/status/' ) );
		$player_base      = esc_url_raw( home_url( '/signage/play/' ) );
		$site_name        = get_bloginfo( 'name' );

		include DS_PLUGIN_DIR . 'public/templates/vidaa-launcher.php';
		exit;
	}

	/**
	 * Render an unpaired token without rotating twice. The status endpoint
	 * rotates the code immediately before the countdown reloads this page, so
	 * a still-current row must be reused here rather than replaced again.
	 *
	 * If this token was paired before but its screen post no longer exists,
	 * clear that orphaned pairing state. Otherwise the newly displayed code
	 * still carries paired_at and wp-admin rejects it as already used.
	 */
	private function render_unpaired_screen( $token ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'ds_pairing_codes';
		$row         = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) );
		$now         = time();
		$stale_pair  = $row && ( ! empty( $row->paired_at ) || ! empty( $row->screen_id ) );
		$age         = $row ? max( 0, $now - strtotime( $row->created_at . ' UTC' ) ) : PHP_INT_MAX;
		$needs_code  = ! $row || $stale_pair || $age >= DS_REST::PAIRING_CODE_ROTATE_SECONDS;

		if ( $needs_code ) {
			$code = DS_REST::generate_unique_pairing_code( $table ) ?: strtoupper( substr( str_shuffle( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789' ), 0, 6 ) );
		} else {
			$code = $row->code;
		}

		if ( $row ) {
			$update = array();
			if ( $stale_pair ) {
				$update['screen_id'] = null;
				$update['paired_at'] = null;
			}
			if ( $needs_code ) {
				if ( ! $stale_pair ) {
					set_transient(
						DS_REST::pairing_grace_key( $row->code ),
						(int) $row->id,
						DS_REST::PAIRING_CODE_GRACE_SECONDS
					);
				}
				$update['code']       = $code;
				$update['created_at'] = current_time( 'mysql', true );
				$update['expires_at'] = gmdate( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS );
			}
			if ( $update ) {
				$wpdb->update( $table, $update, array( 'id' => $row->id ) );
			}
		} else {
			$wpdb->insert(
				$table,
				array(
					'code'       => $code,
					'token'      => $token,
					'created_at' => current_time( 'mysql', true ),
					'expires_at' => gmdate( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS ),
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
