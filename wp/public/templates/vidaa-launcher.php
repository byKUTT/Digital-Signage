<?php
/**
 * Stable smart-TV launcher at /signage/tv/.
 *
 * @var string $pair_request_url
 * @var string $pair_status_base
 * @var string $player_base
 * @var string $site_name
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no" />
	<title><?php esc_html_e( 'TV Player', 'digital-signage' ); ?> &mdash; <?php echo esc_html( $site_name ); ?></title>
	<meta name="robots" content="noindex, nofollow" />
	<style>
		* { box-sizing: border-box; }
		html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; background: #050505; color: #fff; }
		body { font-family: Arial, Helvetica, sans-serif; display: flex; align-items: center; justify-content: center; }
		.ds-tv-card { width: min(84vw, 1080px); text-align: center; }
		.ds-tv-kicker { margin: 0 0 2vh; color: #aaa; font-size: clamp(18px, 1.5vw, 30px); letter-spacing: .14em; text-transform: uppercase; }
		h1 { margin: 0 0 3vh; font-size: clamp(38px, 4vw, 78px); line-height: 1.08; font-weight: 700; }
		.ds-tv-status { min-height: 2em; margin: 0 auto 3vh; color: #c8c8c8; font-size: clamp(22px, 2vw, 38px); line-height: 1.35; }
		.ds-tv-code { display: inline-block; min-width: 8.5em; padding: .35em .55em; border: 3px solid #fff; border-radius: 18px; font-size: clamp(56px, 8vw, 150px); font-weight: 700; letter-spacing: .12em; line-height: 1; }
		.ds-tv-help { max-width: 900px; margin: 4vh auto 0; color: #aaa; font-size: clamp(18px, 1.5vw, 30px); line-height: 1.45; }
		.ds-tv-error { color: #ff7878; }
		.ds-tv-reset { position: fixed; right: 3vw; bottom: 3vh; color: #777; font-size: clamp(14px, 1vw, 22px); }
		.ds-tv-progress { width: min(50vw, 620px); height: 6px; margin: 3vh auto 0; overflow: hidden; background: #222; border-radius: 3px; }
		.ds-tv-progress span { display: block; width: 40%; height: 100%; background: #fff; animation: ds-tv-load 1.2s linear infinite; }
		@keyframes ds-tv-load { from { transform: translateX(-110%); } to { transform: translateX(260%); } }
		@media (prefers-reduced-motion: reduce) { .ds-tv-progress span { animation: none; width: 100%; } }
	</style>
</head>
<body>
	<main class="ds-tv-card">
		<p class="ds-tv-kicker"><?php echo esc_html( $site_name ); ?></p>
		<h1 id="ds-tv-title"><?php esc_html_e( 'Starting TV player', 'digital-signage' ); ?></h1>
		<p id="ds-tv-status" class="ds-tv-status"><?php esc_html_e( 'Connecting…', 'digital-signage' ); ?></p>
		<div id="ds-tv-code" class="ds-tv-code" hidden></div>
		<div id="ds-tv-progress" class="ds-tv-progress"><span></span></div>
		<p id="ds-tv-help" class="ds-tv-help" hidden><?php esc_html_e( 'Open Digital Signage → Pair a New Screen in WordPress and enter this code.', 'digital-signage' ); ?></p>
	</main>
	<p class="ds-tv-reset"><?php esc_html_e( 'Hold OK for 5 seconds to reset pairing', 'digital-signage' ); ?></p>

	<script>
	( function () {
		'use strict';

		var pairRequestUrl = <?php echo wp_json_encode( $pair_request_url ); ?>;
		var pairStatusBase = <?php echo wp_json_encode( $pair_status_base ); ?>;
		var playerBase = <?php echo wp_json_encode( $player_base ); ?>;
		var storageKey = 'ds-tv-device-token:' + window.location.host;
		var titleEl = document.getElementById( 'ds-tv-title' );
		var statusEl = document.getElementById( 'ds-tv-status' );
		var codeEl = document.getElementById( 'ds-tv-code' );
		var helpEl = document.getElementById( 'ds-tv-help' );
		var progressEl = document.getElementById( 'ds-tv-progress' );
		var token = '';
		var pollTimer = null;
		var resetStartedAt = 0;

		function getStoredToken() {
			var match;
			try { token = window.localStorage.getItem( storageKey ) || ''; } catch ( e ) {}
			if ( ! token && window.location.hash ) {
				match = window.location.hash.match( /(?:^#|&)token=([a-zA-Z0-9]+)/ );
				if ( match ) { token = match[1]; }
			}
			return token;
		}

		function storeToken( value ) {
			token = value;
			try { window.localStorage.setItem( storageKey, value ); } catch ( e ) {}
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', window.location.pathname + '#token=' + value );
			} else {
				window.location.hash = 'token=' + value;
			}
		}

		function clearToken() {
			clearTimeout( pollTimer );
			try { window.localStorage.removeItem( storageKey ); } catch ( e ) {}
			token = '';
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', window.location.pathname );
			}
		}

		function showLoading( message ) {
			titleEl.textContent = <?php echo wp_json_encode( __( 'Starting TV player', 'digital-signage' ) ); ?>;
			statusEl.textContent = message;
			statusEl.className = 'ds-tv-status';
			codeEl.hidden = true;
			helpEl.hidden = true;
			progressEl.hidden = false;
		}

		function showError() {
			titleEl.textContent = <?php echo wp_json_encode( __( 'Unable to connect', 'digital-signage' ) ); ?>;
			statusEl.textContent = <?php echo wp_json_encode( __( 'Check the TV internet connection. Retrying automatically…', 'digital-signage' ) ); ?>;
			statusEl.className = 'ds-tv-status ds-tv-error';
			codeEl.hidden = true;
			helpEl.hidden = true;
			progressEl.hidden = false;
		}

		function showCode( code, rotatesIn ) {
			titleEl.textContent = <?php echo wp_json_encode( __( 'Pair this TV', 'digital-signage' ) ); ?>;
			statusEl.textContent = rotatesIn ? <?php echo wp_json_encode( __( 'Pairing code refreshes automatically', 'digital-signage' ) ); ?> : '';
			statusEl.className = 'ds-tv-status';
			codeEl.textContent = code || '------';
			codeEl.hidden = false;
			helpEl.hidden = false;
			progressEl.hidden = true;
		}

		function requestIdentity() {
			showLoading( <?php echo wp_json_encode( __( 'Creating this TV’s identity…', 'digital-signage' ) ); ?> );
			fetch( pairRequestUrl, {
				method: 'POST',
				cache: 'no-store',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: '{}'
			} ).then( function ( response ) {
				if ( ! response.ok ) { throw new Error( 'Pair request failed' ); }
				return response.json();
			} ).then( function ( data ) {
				if ( ! data || ! data.token ) { throw new Error( 'Missing token' ); }
				storeToken( data.token );
				showCode( data.code, 30 );
				pollStatus( 250 );
			} ).catch( function () {
				showError();
				pollTimer = setTimeout( requestIdentity, 5000 );
			} );
		}

		function pollStatus( delay ) {
			clearTimeout( pollTimer );
			pollTimer = setTimeout( function () {
				fetch( pairStatusBase + encodeURIComponent( token ) + '?_ds=' + Date.now(), {
					cache: 'no-store', credentials: 'same-origin'
				} ).then( function ( response ) {
					if ( 404 === response.status ) {
						clearToken();
						requestIdentity();
						return null;
					}
					if ( ! response.ok ) { throw new Error( 'Pair status failed' ); }
					return response.json();
				} ).then( function ( data ) {
					if ( ! data ) { return; }
					if ( data.paired ) {
						showLoading( <?php echo wp_json_encode( __( 'Paired. Opening player…', 'digital-signage' ) ); ?> );
						window.location.replace( data.player_url || ( playerBase + token + '/' ) );
						return;
					}
					if ( data.expired ) {
						clearToken();
						requestIdentity();
						return;
					}
					showCode( data.code, data.rotates_in );
					pollStatus( 1000 );
				} ).catch( function () {
					showError();
					pollStatus( 5000 );
				} );
			}, delay );
		}

		function startResetHold( event ) {
			var key = event.key || '';
			var code = event.keyCode || event.which;
			if ( 'Enter' !== key && 'OK' !== key && 13 !== code ) { return; }
			if ( ! resetStartedAt ) {
				resetStartedAt = Date.now();
				return;
			}
			if ( Date.now() - resetStartedAt >= 5000 ) {
				resetStartedAt = 0;
				clearToken();
				requestIdentity();
			}
		}

		function cancelResetHold() {
			resetStartedAt = 0;
		}

		document.addEventListener( 'keydown', startResetHold );
		document.addEventListener( 'keyup', cancelResetHold );
		window.addEventListener( 'online', function () {
			if ( token ) { pollStatus( 100 ); } else { requestIdentity(); }
		} );

		if ( getStoredToken() ) {
			showLoading( <?php echo wp_json_encode( __( 'Checking this TV…', 'digital-signage' ) ); ?> );
			pollStatus( 100 );
		} else {
			requestIdentity();
		}
	}() );
	</script>
</body>
</html>
