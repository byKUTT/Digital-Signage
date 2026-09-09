<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verified public GitHub Release updater for the plugin and controller fleet.
 */
class DS_Updater {

	private static $instance = null;
	const REPOSITORY = 'byKUTT/Digital-Signage';
	const API_URL = 'https://api.github.com/repos/byKUTT/Digital-Signage/releases/latest';
	const CACHE_KEY = 'ds_github_release';
	const CHECKSUM_ASSET = 'SHA256SUMS';

	/** @var string[] */
	private $package_names = array(
		'digital-signage.zip',
		'digital-signage-linux-controller.tar.gz',
		'digital-signage-windows-controller.zip',
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_download' ), 10, 4 );
		add_filter( 'auto_update_plugin', array( $this, 'allow_auto_update' ), 10, 2 );
	}

	/**
	 * Fetch and validate the latest complete public release.
	 *
	 * @param bool $force Ignore the release cache.
	 * @return array|WP_Error
	 */
	public function get_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		} else {
			delete_transient( self::CACHE_KEY );
		}

		$response = wp_safe_remote_get(
			self::API_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'Digital-Signage/' . DS_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ds_github_unreachable', __( 'GitHub could not be reached. Check outbound HTTPS access on this server.', 'digital-signage' ), $response );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 404 === $status ) {
			return new WP_Error( 'ds_github_no_release', __( 'No published GitHub Release exists. Pushing a ZIP to a branch does not publish an update.', 'digital-signage' ) );
		}
		if ( 403 === $status || 429 === $status ) {
			return new WP_Error( 'ds_github_rate_limit', __( 'GitHub temporarily refused the release check or its API rate limit was reached. Try again later.', 'digital-signage' ) );
		}
		if ( 200 !== $status ) {
			return new WP_Error( 'ds_github_status', sprintf( __( 'GitHub returned HTTP %d while checking for updates.', 'digital-signage' ), absint( $status ) ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) || empty( $body['assets'] ) || ! is_array( $body['assets'] ) ) {
			return new WP_Error( 'ds_github_payload', __( 'GitHub release metadata is incomplete.', 'digital-signage' ) );
		}

		$version = ltrim( sanitize_text_field( $body['tag_name'] ), 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version ) ) {
			return new WP_Error( 'ds_github_version', __( 'The GitHub Release tag is not a valid plugin version.', 'digital-signage' ) );
		}

		$assets = array();
		foreach ( array_slice( $body['assets'], 0, 30 ) as $asset ) {
			$name = sanitize_file_name( $asset['name'] ?? '' );
			$url  = esc_url_raw( $asset['browser_download_url'] ?? '' );
			if ( ! $name || ! $this->is_trusted_release_url( $url, $version ) ) {
				continue;
			}
			$digest = sanitize_text_field( $asset['digest'] ?? '' );
			$assets[ $name ] = array(
				'name'   => $name,
				'url'    => $url,
				'sha256' => preg_match( '/^sha256:([a-f0-9]{64})$/i', $digest, $matches ) ? strtolower( $matches[1] ) : '',
				'size'   => absint( $asset['size'] ?? 0 ),
			);
		}

		$missing = array_diff( array_merge( $this->package_names, array( self::CHECKSUM_ASSET ) ), array_keys( $assets ) );
		if ( $missing ) {
			return new WP_Error( 'ds_github_assets', sprintf( __( 'The GitHub Release is incomplete. Missing: %s', 'digital-signage' ), implode( ', ', $missing ) ) );
		}

		$needs_checksums = false;
		foreach ( $this->package_names as $name ) {
			if ( empty( $assets[ $name ]['sha256'] ) ) {
				$needs_checksums = true;
				break;
			}
		}
		if ( $needs_checksums ) {
			$checksums = $this->fetch_checksum_file( $assets[ self::CHECKSUM_ASSET ]['url'] );
			if ( is_wp_error( $checksums ) ) {
				return $checksums;
			}
			foreach ( $this->package_names as $name ) {
				if ( empty( $assets[ $name ]['sha256'] ) && isset( $checksums[ $name ] ) ) {
					$assets[ $name ]['sha256'] = $checksums[ $name ];
				}
			}
		}

		foreach ( $this->package_names as $name ) {
			if ( empty( $assets[ $name ]['sha256'] ) || empty( $assets[ $name ]['size'] ) ) {
				return new WP_Error( 'ds_github_digest', sprintf( __( 'The release package %s has no valid SHA-256 digest or is empty.', 'digital-signage' ), $name ) );
			}
		}

		$release = array(
			'version'      => $version,
			'name'         => sanitize_text_field( $body['name'] ?? $version ),
			'notes'        => sanitize_textarea_field( $body['body'] ?? '' ),
			'published_at' => sanitize_text_field( $body['published_at'] ?? '' ),
			'html_url'     => esc_url_raw( $body['html_url'] ?? '' ),
			'assets'       => $assets,
		);
		set_transient( self::CACHE_KEY, $release, 15 * MINUTE_IN_SECONDS );
		return $release;
	}

	private function is_trusted_release_url( $url, $version = '' ) {
		$parts = wp_parse_url( $url );
		$trusted = is_array( $parts )
			&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& 'github.com' === strtolower( (string) ( $parts['host'] ?? '' ) )
			&& 0 === strpos( strtolower( (string) ( $parts['path'] ?? '' ) ), '/bykutt/digital-signage/releases/download/' );
		if ( ! $trusted || ! $version ) {
			return $trusted;
		}
		return (bool) preg_match( '#^/bykutt/digital-signage/releases/download/v?' . preg_quote( strtolower( $version ), '#' ) . '/[^/]+$#', strtolower( (string) $parts['path'] ) );
	}

	/**
	 * Download and parse the small release checksum manifest.
	 *
	 * @return array|WP_Error
	 */
	private function fetch_checksum_file( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 20,
				'redirection'         => 5,
				'limit_response_size' => 65536,
				'headers'             => array( 'User-Agent' => 'Digital-Signage/' . DS_VERSION ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'ds_checksum_download', __( 'The release checksum file could not be downloaded.', 'digital-signage' ) );
		}

		$checksums = array();
		foreach ( preg_split( '/\r\n|\r|\n/', wp_remote_retrieve_body( $response ) ) as $line ) {
			if ( preg_match( '/^([a-f0-9]{64})\s+\*?(\S+)$/i', trim( $line ), $matches ) ) {
				$filename = sanitize_file_name( wp_basename( $matches[2] ) );
				if ( $filename === $matches[2] ) {
					$checksums[ $filename ] = strtolower( $matches[1] );
				}
			}
		}
		return $checksums;
	}

	public function inject_plugin_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$release = $this->get_release();
		if ( is_wp_error( $release ) || ! version_compare( $release['version'], DS_VERSION, '>' ) ) {
			return $transient;
		}
		$asset = $release['assets']['digital-signage.zip'];
		$item  = (object) array(
			'id'           => 'github.com/' . self::REPOSITORY,
			'slug'         => 'digital-signage',
			'plugin'       => plugin_basename( DS_PLUGIN_FILE ),
			'new_version'  => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $asset['url'],
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
		);
		$transient->response[ plugin_basename( DS_PLUGIN_FILE ) ] = $item;
		set_transient( 'ds_expected_plugin_update', array( 'url' => $asset['url'], 'sha256' => $asset['sha256'], 'version' => $release['version'] ), 30 * MINUTE_IN_SECONDS );
		return $transient;
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'digital-signage' !== $args->slug ) {
			return $result;
		}
		$release = $this->get_release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Digital Signage CMS',
			'slug'          => 'digital-signage',
			'version'       => $release['version'],
			'homepage'      => $release['html_url'],
			'sections'      => array( 'description' => esc_html__( 'Multi-display digital signage management.', 'digital-signage' ), 'changelog' => nl2br( esc_html( $release['notes'] ) ) ),
			'download_link' => $release['assets']['digital-signage.zip']['url'],
		);
	}

	public function verify_download( $reply, $package, $upgrader, $hook_extra ) {
		if ( false !== $reply ) {
			return $reply;
		}
		$expected = get_transient( 'ds_expected_plugin_update' );
		if ( ! is_array( $expected ) || empty( $expected['url'] ) || ! hash_equals( $expected['url'], (string) $package ) ) {
			return $reply;
		}
		if ( empty( $expected['version'] ) || ! $this->is_trusted_release_url( $package, $expected['version'] ) ) {
			return new WP_Error( 'ds_update_url', __( 'The plugin update URL is not a trusted project Release asset.', 'digital-signage' ) );
		}
		$file = download_url( $package, 120 );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$actual = hash_file( 'sha256', $file );
		if ( ! is_string( $actual ) || ! hash_equals( strtolower( $expected['sha256'] ), strtolower( $actual ) ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'ds_update_digest', __( 'The downloaded plugin digest does not match the GitHub Release.', 'digital-signage' ) );
		}
		return $file;
	}

	public function allow_auto_update( $update, $item ) {
		if ( ! empty( $item->plugin ) && plugin_basename( DS_PLUGIN_FILE ) === $item->plugin ) {
			return (bool) get_option( 'ds_github_auto_update', false );
		}
		return $update;
	}

	public function get_device_asset( $platform, $force = false ) {
		$platform = sanitize_key( $platform );
		if ( ! in_array( $platform, array( 'linux', 'windows' ), true ) ) {
			return new WP_Error( 'ds_device_platform', __( 'This controller platform cannot be updated remotely.', 'digital-signage' ) );
		}
		$release = $this->get_release( $force );
		if ( is_wp_error( $release ) ) {
			return $release;
		}
		$name = 'windows' === $platform ? 'digital-signage-windows-controller.zip' : 'digital-signage-linux-controller.tar.gz';
		return array_merge( $release['assets'][ $name ], array( 'version' => $release['version'] ) );
	}

	public function install_plugin_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return new WP_Error( 'ds_update_cap', __( 'You cannot update plugins.', 'digital-signage' ) );
		}
		delete_transient( self::CACHE_KEY );
		$transient = $this->inject_plugin_update( get_site_transient( 'update_plugins' ) );
		set_site_transient( 'update_plugins', $transient );
		if ( empty( $transient->response[ plugin_basename( DS_PLUGIN_FILE ) ] ) ) {
			return new WP_Error( 'ds_no_update', __( 'No newer complete and verified GitHub Release is available.', 'digital-signage' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		return $upgrader->upgrade( plugin_basename( DS_PLUGIN_FILE ) );
	}
}
