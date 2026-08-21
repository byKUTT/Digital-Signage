<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for the player and external kiosk hardware (e.g. Raspberry Pi
 * running a browser in kiosk mode). Endpoints are public but gated by the
 * screen's unguessable pairing token — never by sequential post IDs.
 *
 * Namespace: ds/v1
 *   GET  /screen/{token}/playlist   Resolved channel + zones + slides for right now
 *   POST /screen/{token}/heartbeat  "I'm alive" ping (resolution, orientation, IP, channel,
 *                                  optional device telemetry) — response carries any pending
 *                                  device command (WiFi change, rotation, reboot, ...) for
 *                                  hardware running the ds-agent (see raspberry-pi-kiosk/ds-agent)
 *   POST /screen/{token}/proof      Proof-of-play log entry
 *   POST /pair/request              Generates a pairing code for an unpaired player
 *   GET  /pair/status/{token}       Poll: has this pairing token been claimed yet?
 *   GET  /preview/{channel_id}      wp-admin-only live preview of a channel (cookie+nonce auth)
 */
class DS_REST {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ds/v1',
			'/screen/(?P<token>[a-zA-Z0-9]+)/playlist',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_playlist' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ds/v1',
			'/screen/(?P<token>[a-zA-Z0-9]+)/heartbeat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_heartbeat' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ds/v1',
			'/screen/(?P<token>[a-zA-Z0-9]+)/proof',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_proof' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ds/v1',
			'/pair/request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'pair_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ds/v1',
			'/pair/status/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'pair_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ds/v1',
			'/preview/(?P<channel_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_preview' ),
				'permission_callback' => function () {
					return current_user_can( DS_Roles::CAP );
				},
			)
		);
	}

	/**
	 * Same shape as get_playlist(), but resolves a specific channel directly
	 * (no screen/schedule involved) — used by the "Preview" button in wp-admin.
	 */
	public function get_preview( WP_REST_Request $request ) {
		$channel_id = absint( $request['channel_id'] );
		if ( ! $channel_id || 'ds_channel' !== get_post_type( $channel_id ) ) {
			return new WP_Error( 'ds_unknown_channel', __( 'Channel not found.', 'digital-signage' ), array( 'status' => 404 ) );
		}

		$zones    = DS_Schedule_Resolver::get_active_playlist( $channel_id );
		$layout   = get_post_meta( $channel_id, 'ds_layout_template', true ) ?: 'fullscreen';
		$zone_bg  = get_post_meta( $channel_id, 'ds_zone_bg_color', true );
		$settings = DS_Settings::get_all();

		return rest_ensure_response(
			array(
				'channel_id'    => $channel_id,
				'channel_name'  => get_the_title( $channel_id ),
				'orientation'   => 'auto',
				'layout'        => $layout,
				'zone_bg'       => $zone_bg ?: '',
				'zones'         => (object) $zones,
				'defaults'      => $settings,
				'poll_interval' => 5, // Fast refresh while an editor is actively previewing.
				'generated_at'  => current_time( 'timestamp' ),
			)
		);
	}

	private function get_screen_by_token( $token ) {
		$screens = get_posts(
			array(
				'post_type'      => 'ds_screen',
				'posts_per_page' => 1,
				'meta_key'       => 'ds_pairing_token',
				'meta_value'     => sanitize_text_field( $token ),
				'post_status'    => 'any',
			)
		);
		return $screens ? $screens[0] : null;
	}

	public function get_playlist( WP_REST_Request $request ) {
		$screen = $this->get_screen_by_token( $request['token'] );
		if ( ! $screen ) {
			return new WP_Error( 'ds_unknown_screen', __( 'Unknown or unpaired screen.', 'digital-signage' ), array( 'status' => 404 ) );
		}

		$channel_id = DS_Schedule_Resolver::resolve_channel_id_for_screen( $screen->ID );
		$zones      = $channel_id ? DS_Schedule_Resolver::get_active_playlist( $channel_id ) : array();
		$layout     = $channel_id ? ( get_post_meta( $channel_id, 'ds_layout_template', true ) ?: 'fullscreen' ) : 'fullscreen';
		$zone_bg    = $channel_id ? get_post_meta( $channel_id, 'ds_zone_bg_color', true ) : '';
		$settings   = DS_Settings::get_all();

		$remote_command = get_post_meta( $screen->ID, 'ds_remote_command', true );
		$remote_ts      = (int) get_post_meta( $screen->ID, 'ds_remote_command_ts', true );
		if ( $remote_command ) {
			delete_post_meta( $screen->ID, 'ds_remote_command' );
		}

		return rest_ensure_response(
			array(
				'screen_id'      => $screen->ID,
				'screen_name'    => $screen->post_title,
				'orientation'    => get_post_meta( $screen->ID, 'ds_orientation', true ) ?: 'landscape',
				'channel_id'     => $channel_id,
				'channel_name'   => $channel_id ? get_the_title( $channel_id ) : '',
				'layout'         => $layout,
				'zone_bg'        => $zone_bg ?: '',
				'zones'          => (object) $zones,
				'defaults'       => $settings,
				'poll_interval'  => (int) $settings['poll_interval'],
				'generated_at'   => current_time( 'timestamp' ),
				'remote_command' => $remote_command,
				'remote_ts'      => $remote_ts,
			)
		);
	}

	public function post_heartbeat( WP_REST_Request $request ) {
		global $wpdb;
		$screen = $this->get_screen_by_token( $request['token'] );
		if ( ! $screen ) {
			return new WP_Error( 'ds_unknown_screen', __( 'Unknown or unpaired screen.', 'digital-signage' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$table = $wpdb->prefix . 'ds_heartbeats';
		$data  = array(
			'screen_id' => $screen->ID,
			'last_seen' => current_time( 'mysql', true ),
			'ip_address' => $this->get_client_ip(),
		);

		// resolution/orientation/user_agent/channel/app_version are reported by the
		// browser-based player. ds-agent's own heartbeat (piggybacking device
		// telemetry — see raspberry-pi-kiosk/ds-agent) hits this same endpoint but
		// doesn't know any of these, so only fold a field in when this particular
		// call actually reports something meaningful for it — otherwise the two
		// heartbeat sources clobber each other's data every ~30s (e.g. the agent's
		// call would blank out the real resolution, and previously even reported
		// its own screen-rotation setting — "left"/"right"/"inverted" — into the
		// same column the player uses for viewport "landscape"/"portrait", which is
		// why the wp-admin "Orientation reported" field kept flipping between the two).
		if ( ! empty( $params['resolution'] ) ) {
			$data['resolution'] = sanitize_text_field( $params['resolution'] );
		}
		if ( ! empty( $params['orientation'] ) && in_array( $params['orientation'], array( 'landscape', 'portrait' ), true ) ) {
			$data['orientation'] = sanitize_key( $params['orientation'] );
		}
		if ( ! empty( $params['user_agent'] ) ) {
			$data['user_agent'] = sanitize_text_field( mb_substr( $params['user_agent'], 0, 255 ) );
		}
		if ( ! empty( $params['channel_id'] ) ) {
			$data['current_channel_id'] = absint( $params['channel_id'] );
		}
		if ( ! empty( $params['app_version'] ) && 0 !== strpos( $params['app_version'], 'ds-agent/' ) ) {
			$data['app_version'] = sanitize_text_field( $params['app_version'] );
		}
		if ( isset( $params['device'] ) && is_array( $params['device'] ) ) {
			$data['device_info'] = wp_json_encode( self::sanitize_device_info( $params['device'] ) );
		}

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT screen_id FROM {$table} WHERE screen_id = %d", $screen->ID ) );
		if ( $exists ) {
			$wpdb->update( $table, $data, array( 'screen_id' => $screen->ID ) );
		} else {
			// A brand-new row needs sensible defaults for whatever this first call
			// didn't report — later calls (from whichever source) fill them in.
			$data += array(
				'resolution'         => '',
				'orientation'        => '',
				'user_agent'         => '',
				'current_channel_id' => 0,
				'app_version'        => DS_VERSION,
				'device_info'        => null,
			);
			$wpdb->insert( $table, $data );
		}

		// Deliver any pending device command (WiFi change, rotation, reboot, ...) in the
		// same round trip — ds-agent polls via heartbeat rather than a separate endpoint.
		$command = get_post_meta( $screen->ID, 'ds_device_command', true );
		if ( $command ) {
			delete_post_meta( $screen->ID, 'ds_device_command' );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'command' => $command ? json_decode( $command, true ) : null,
			)
		);
	}

	/**
	 * Whitelist + sanitize the device telemetry object ds-agent reports each
	 * heartbeat (never trust hardware-reported strings going into storage/UI).
	 */
	private static function sanitize_device_info( array $device ) {
		$out = array();
		foreach ( array( 'wifi_ssid', 'hostname', 'os_version', 'agent_version', 'rotation', 'resolution' ) as $key ) {
			if ( isset( $device[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( mb_substr( (string) $device[ $key ], 0, 100 ) );
			}
		}
		foreach ( array( 'wifi_signal', 'cpu_temp_c', 'disk_free_mb', 'mem_free_mb' ) as $key ) {
			if ( isset( $device[ $key ] ) ) {
				$out[ $key ] = is_numeric( $device[ $key ] ) ? $device[ $key ] + 0 : null;
			}
		}
		// Recent agent activity (last commands applied, errors, etc.) — a short
		// list of plain-text lines shown as-is on the Screen edit page so you can
		// see what a device has been doing without SSHing into it.
		if ( isset( $device['recent_log'] ) && is_array( $device['recent_log'] ) ) {
			$lines = array();
			foreach ( array_slice( $device['recent_log'], -20 ) as $line ) {
				$lines[] = sanitize_text_field( mb_substr( (string) $line, 0, 300 ) );
			}
			$out['recent_log'] = $lines;
		}
		return $out;
	}

	public function post_proof( WP_REST_Request $request ) {
		global $wpdb;
		$screen = $this->get_screen_by_token( $request['token'] );
		if ( ! $screen ) {
			return new WP_Error( 'ds_unknown_screen', __( 'Unknown or unpaired screen.', 'digital-signage' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$wpdb->insert(
			$wpdb->prefix . 'ds_proof_of_play',
			array(
				'screen_id'        => $screen->ID,
				'channel_id'       => absint( $params['channel_id'] ?? 0 ),
				'slide_id'         => absint( $params['slide_id'] ?? 0 ),
				'zone'             => sanitize_key( $params['zone'] ?? 'main' ),
				'played_at'        => current_time( 'mysql', true ),
				'duration_seconds' => absint( $params['duration_seconds'] ?? 0 ),
			)
		);

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Rate-limited: max 20 pairing requests per IP per 10 minutes via a transient counter.
	 */
	public function pair_request( WP_REST_Request $request ) {
		global $wpdb;

		$ip  = $this->get_client_ip();
		$key = 'ds_pair_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits > 20 ) {
			return new WP_Error( 'ds_rate_limited', __( 'Too many pairing attempts. Try again later.', 'digital-signage' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		$table = $wpdb->prefix . 'ds_pairing_codes';
		$code  = strtoupper( substr( str_shuffle( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789' ), 0, 6 ) );
		$token = wp_generate_password( 40, false, false );

		$wpdb->insert(
			$table,
			array(
				'code'       => $code,
				'token'      => $token,
				'created_at' => current_time( 'mysql', true ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS ),
			)
		);

		return rest_ensure_response(
			array(
				'code'       => $code,
				'token'      => $token,
				'expires_in' => 15 * MINUTE_IN_SECONDS,
			)
		);
	}

	/**
	 * How often an unclaimed pairing code rotates to a new one, so a code left
	 * showing on an unattended screen can't be found/used by someone later —
	 * only a code currently on-screen (within the last ROTATE_SECONDS) works.
	 */
	const PAIRING_CODE_ROTATE_SECONDS = 15;

	public function pair_status( WP_REST_Request $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ds_pairing_codes';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $request['token'] ) );

		if ( ! $row ) {
			return new WP_Error( 'ds_unknown_token', __( 'Unknown pairing token.', 'digital-signage' ), array( 'status' => 404 ) );
		}

		// Rotate to a fresh code every 15s while nobody has claimed it yet — the
		// pairing screen polls this same endpoint on that cadence and swaps the
		// on-screen code/QR in place, so this keeps them in lockstep.
		if ( empty( $row->paired_at ) ) {
			$age = time() - strtotime( $row->created_at . ' UTC' );
			if ( $age >= self::PAIRING_CODE_ROTATE_SECONDS ) {
				$new_code = self::generate_unique_pairing_code( $table );
				if ( $new_code ) {
					$wpdb->update(
						$table,
						array(
							'code'       => $new_code,
							'created_at' => current_time( 'mysql', true ),
							'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
						),
						array( 'id' => $row->id )
					);
					$row->code = $new_code;
				}
			}
		}

		return rest_ensure_response(
			array(
				'paired'      => ! empty( $row->paired_at ),
				'player_url'  => ! empty( $row->paired_at ) ? home_url( '/signage/play/' . $row->token . '/' ) : null,
				'expired'     => strtotime( $row->expires_at . ' UTC' ) < time(),
				'code'        => $row->code,
				'rotates_in'  => self::PAIRING_CODE_ROTATE_SECONDS,
			)
		);
	}

	/**
	 * Generates a 6-char pairing code not already in use, retrying a handful
	 * of times against the table's UNIQUE KEY on collision (astronomically
	 * unlikely with ~33^6 combinations, but cheap to guard against anyway).
	 */
	private static function generate_unique_pairing_code( $table ) {
		global $wpdb;
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$code   = strtoupper( substr( str_shuffle( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789' ), 0, 6 ) );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE code = %s", $code ) );
			if ( ! $exists ) {
				return $code;
			}
		}
		return null;
	}

	private function get_client_ip() {
		foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = explode( ',', $_SERVER[ $key ] )[0];
				return trim( sanitize_text_field( $ip ) );
			}
		}
		return '';
	}

	/**
	 * Normalizes a ds_slide post + its meta into the payload shape the player JS expects.
	 */
	public static function slide_to_array( $slide ) {
		$type    = get_post_meta( $slide->ID, 'ds_slide_type', true ) ?: 'image';
		$media   = absint( get_post_meta( $slide->ID, 'ds_media_id', true ) );
		$settings = DS_Settings::get_all();

		$duration_override = get_post_meta( $slide->ID, 'ds_duration_override', true );
		$duration           = $duration_override ? absint( $duration_override ) : absint( $settings[ 'duration_' . $type ] ?? 10 );

		$transition_override = get_post_meta( $slide->ID, 'ds_transition_override', true );
		$transition           = ( $transition_override && 'default' !== $transition_override ) ? $transition_override : $settings['transition'];

		$data = array(
			'id'          => $slide->ID,
			'title'       => $slide->post_title,
			'type'        => $type,
			'duration'    => $duration,
			'transition'  => $transition,
			'fit'         => get_post_meta( $slide->ID, 'ds_fit', true ) ?: 'cover',
			'order'       => absint( get_post_meta( $slide->ID, 'ds_order', true ) ),
		);

		switch ( $type ) {
			case 'image':
				$data['src'] = $media ? wp_get_attachment_image_url( $media, 'full' ) : get_post_meta( $slide->ID, 'ds_content_url', true );
				break;
			case 'video':
				$data['src']       = $media ? wp_get_attachment_url( $media ) : get_post_meta( $slide->ID, 'ds_content_url', true );
				$data['play_mode'] = get_post_meta( $slide->ID, 'ds_video_play_mode', true ) ?: 'fixed_duration';
				break;
			case 'webpage':
				$data['url'] = esc_url_raw( get_post_meta( $slide->ID, 'ds_content_url', true ) );
				break;
			case 'html':
				$data['html'] = get_post_meta( $slide->ID, 'ds_content_html', true );
				break;
			case 'rss':
				$data['feed_url'] = esc_url_raw( get_post_meta( $slide->ID, 'ds_feed_url', true ) );
				break;
			case 'weather':
				$data['location'] = get_post_meta( $slide->ID, 'ds_weather_location', true );
				$data['api_key']  = get_post_meta( $slide->ID, 'ds_weather_api_key', true );
				break;
			case 'clock':
				break;
			case 'pdf':
				$data['src'] = $media ? wp_get_attachment_url( $media ) : get_post_meta( $slide->ID, 'ds_content_url', true );
				break;
			case 'social':
				$data['embed_url'] = esc_url_raw( get_post_meta( $slide->ID, 'ds_content_url', true ) );
				break;
			case 'infinite_scroll':
				$image_ids     = (array) get_post_meta( $slide->ID, 'ds_scroll_images', true );
				$data['images'] = array_values(
					array_filter(
						array_map(
							function ( $attachment_id ) {
								return wp_get_attachment_image_url( $attachment_id, 'full' );
							},
							$image_ids
						)
					)
				);
				// Background/spacing/speed are set once per channel (not per slide) so every
				// Infinite Scroll gallery in that channel shares the same look and feel.
				$scroll_channel_id = absint( get_post_meta( $slide->ID, 'ds_channel_id', true ) );
				$data['bg_color']  = $scroll_channel_id ? ( get_post_meta( $scroll_channel_id, 'ds_scroll_bg_color', true ) ?: '#000000' ) : '#000000';
				$data['spacing']   = $scroll_channel_id ? absint( get_post_meta( $scroll_channel_id, 'ds_scroll_spacing', true ) ?: 20 ) : 20;
				$data['speed']     = $scroll_channel_id ? absint( get_post_meta( $scroll_channel_id, 'ds_scroll_speed', true ) ?: 60 ) : 60;
				break;
		}

		return $data;
	}
}
