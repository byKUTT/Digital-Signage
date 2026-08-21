<?php
/**
 * Shown fullscreen on an unpaired display: the code staff read off the TV
 * (or scan via QR code on their phone) and enter in wp-admin > Digital
 * Signage > Pair a Screen. Persists across reboots automatically: the
 * device always opens the same /signage/play/{token}/ URL (the token is
 * generated once by the kiosk installer and stored on the device).
 *
 * The code rotates every 15s while unclaimed (see DS_REST::pair_status()) —
 * this page polls that same endpoint on that cadence and swaps the on-screen
 * code/QR in place, no reload, so an old code left visible on an unattended
 * screen can't be picked up and used later.
 *
 * @var string $code
 * @var string $token
 * @var string $pairing_url
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qr_data    = rawurlencode( $pairing_url );
$qr_src     = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=8&data=' . $qr_data;
$status_url = esc_url_raw( rest_url( 'ds/v1/pair/status/' . $token ) );
$pair_base  = esc_url_raw( admin_url( 'admin.php?page=ds-pairing&code=' ) );
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php esc_html_e( 'Pair this Screen', 'digital-signage' ); ?></title>
	<style>
		:root {
			--ds-red: #f24957; --ds-orange: #ff9a3c; --ds-yellow: #ffe14d; --ds-pink: #fbdff0;
		}
		* { box-sizing: border-box; }
		html, body { margin:0; padding:0; width:100%; height:100%; background:#0b0e14; color:#fff; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; overflow:hidden; }
		/* Sized with min(vw,vh) throughout, not vw alone, so this fits both a
		   normal landscape TV and an extreme bar-shaped display (very wide but
		   short, or very tall but narrow) without anything clipping off-screen. */
		.wrap { display:grid; grid-template-columns: 1.1fr 0.9fr; align-items:center; height:100%; padding: min(5vh,5vw) min(6vw,6vh); gap: min(4vw,4vh); }
		.left { display:flex; flex-direction:column; justify-content:center; min-width:0; }
		.eyebrow { font-size: clamp(10px, min(1.3vw,2.6vh), 13px); letter-spacing: .1em; text-transform: uppercase; color: #6b7690; margin: 0 0 .8em; }
		h1 { font-weight: 300; font-size: clamp(16px, min(3vw,4.5vh), 34px); margin: 0 0 .2em; color: #cdd4e0; }
		.code { font-size: clamp(32px, min(9vw,14vh), 108px); font-weight: 800; letter-spacing: .1em; margin: .2em 0 .5em; background: linear-gradient(120deg, var(--ds-red), var(--ds-orange), var(--ds-yellow)); -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1; transition: opacity .2s ease; }
		.code.ds-rotating { opacity: .3; }
		.steps { list-style: none; margin: .5em 0 0; padding: 0; max-width: 560px; }
		.steps li { display:flex; gap: 14px; align-items:flex-start; margin-bottom: .8em; font-size: clamp(11px, min(1.4vw,2.6vh), 18px); color: #cdd4e0; }
		.steps .num {
			flex-shrink:0; width: 1.8em; height: 1.8em; border-radius: 50%;
			background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
			display:flex; align-items:center; justify-content:center; font-weight:700; font-size: .85em; color: #fff;
		}
		.right { display:flex; flex-direction:column; align-items:center; justify-content:center; gap: min(18px,2vh); min-width:0; }
		.qr-card { background:#fff; border-radius: 12px; padding: min(20px,3vh); box-shadow: 0 12px 40px rgba(0,0,0,.35); line-height:0; }
		.qr-card img { display:block; width: min(280px, 24vw, 40vh); height: auto; }
		.qr-caption { color:#8b93a7; font-size: clamp(10px, min(1.1vw,2.2vh), 14px); text-align:center; max-width: 260px; }
		/* Narrow-but-tall (portrait phone-ish kiosk) OR short-but-wide (a bar
		   display like 1920x440) both need the same fix: stop trying to fit two
		   side-by-side columns and stack instead, trimming the steps list when
		   there truly isn't room for it. */
		@media (max-width: 900px), (max-height: 480px) {
			.wrap { grid-template-columns: 1fr; text-align:center; padding: min(3vh,3vw); gap: min(2vh,2vw); }
			.left { align-items:center; order:2; }
			.right { order:1; }
			.steps li { text-align:left; }
		}
		@media (max-height: 480px) {
			.steps { display:none; }
			h1 { margin-bottom: .1em; }
		}
	</style>
</head>
<body>
	<div class="wrap">
		<div class="left">
			<p class="eyebrow"><?php echo esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></p>
			<h1><?php esc_html_e( 'This screen is not yet paired.', 'digital-signage' ); ?></h1>
			<div class="code" id="ds-code"><?php echo esc_html( $code ); ?></div>

			<ol class="steps">
				<li><span class="num">1</span><span><?php esc_html_e( 'On your phone or computer, scan the QR code (or open wp-admin manually).', 'digital-signage' ); ?></span></li>
				<li><span class="num">2</span><span><?php esc_html_e( 'Sign in to WordPress if asked — the pairing code will already be filled in.', 'digital-signage' ); ?></span></li>
				<li><span class="num">3</span><span><?php esc_html_e( 'Name this screen and confirm. It will start playing automatically — no need to touch this device again.', 'digital-signage' ); ?></span></li>
			</ol>
		</div>

		<div class="right">
			<div class="qr-card">
				<img id="ds-qr" src="<?php echo esc_url( $qr_src ); ?>" alt="<?php esc_attr_e( 'QR code to pair this screen', 'digital-signage' ); ?>" onerror="this.closest('.qr-card').style.display='none'" />
			</div>
			<p class="qr-caption"><?php esc_html_e( 'Quick setup: scan with your phone camera', 'digital-signage' ); ?></p>
		</div>
	</div>

	<script>
		( function () {
			var codeEl = document.getElementById( 'ds-code' );
			var qrEl   = document.getElementById( 'ds-qr' );
			var pairBase = <?php echo wp_json_encode( $pair_base ); ?>;

			function applyCode( code ) {
				if ( ! code || code === codeEl.textContent ) { return; }
				codeEl.classList.add( 'ds-rotating' );
				setTimeout( function () {
					codeEl.textContent = code;
					qrEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=8&data=' + encodeURIComponent( pairBase + code );
					codeEl.classList.remove( 'ds-rotating' );
				}, 200 );
			}

			// Polls every 15s — matches the server's own rotation interval (see
			// DS_REST::pair_status()), so the code shown here is always the one
			// currently valid. Swaps it in place; only a real pairing reloads.
			( function poll() {
				fetch( <?php echo wp_json_encode( $status_url ); ?> )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data && data.paired ) {
							window.location.reload();
							return;
						}
						if ( data && data.code ) { applyCode( data.code ); }
						setTimeout( poll, ( data && data.rotates_in ? data.rotates_in : 15 ) * 1000 );
					} )
					.catch( function () { setTimeout( poll, 15000 ); } );
			} )();

			// Best-effort only: try native fullscreen for a browser opened
			// normally (not one of the kiosk installers, which already launch
			// with --kiosk / no chrome). No visible prompt either way — a
			// screen with no input device can never dismiss one, so there's
			// nothing to gain by asking and failing loudly.
			var isKioskBrowser = /(?:^|[?&])kiosk=1(?:&|$)/.test( window.location.search );
			if ( ! isKioskBrowser ) {
				var el = document.documentElement;
				var request = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
				if ( request ) {
					try {
						var result = request.call( el );
						if ( result && result.catch ) { result.catch( function () {} ); }
					} catch ( e ) { /* noop */ }
				}
			}
		} )();
	</script>
</body>
</html>
