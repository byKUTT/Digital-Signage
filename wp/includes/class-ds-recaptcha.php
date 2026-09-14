<?php
/** Optional Google reCAPTCHA v2 protection for frontend authentication. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Recaptcha {
	const OPTION = 'ds_recaptcha_settings';

	public static function settings() { return wp_parse_args( (array) get_option( self::OPTION, array() ), array( 'site_key' => '', 'secret_key' => '' ) ); }
	public static function enabled() { $settings = self::settings(); return ! empty( $settings['site_key'] ) && ! empty( $settings['secret_key'] ); }
	public static function site_key() { $settings = self::settings(); return sanitize_text_field( $settings['site_key'] ); }

	public static function save( $site_key, $secret_key ) {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$current = self::settings();
		update_option( self::OPTION, array(
			'site_key'   => sanitize_text_field( $site_key ),
			'secret_key' => '' !== $secret_key ? sanitize_text_field( $secret_key ) : $current['secret_key'],
		), false );
	}

	public static function verify( $response ) {
		if ( ! self::enabled() ) { return true; }
		$response = sanitize_text_field( $response );
		if ( ! $response ) { return false; }
		$settings = self::settings();
		$request = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $settings['secret_key'],
				'response' => $response,
				'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
			),
		) );
		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) { return false; }
		$body = json_decode( wp_remote_retrieve_body( $request ), true );
		return is_array( $body ) && ! empty( $body['success'] );
	}
}
