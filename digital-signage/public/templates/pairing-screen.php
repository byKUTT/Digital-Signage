<?php
/**
 * Shown fullscreen on an unpaired display: the code staff read off the TV
 * (or scan via QR code on their phone) and enter in wp-admin > Digital
 * Signage > Pair a Screen. Persists across reboots automatically: the
 * device always opens the same /signage/play/{token}/ URL (the token is
 * generated once by the kiosk installer and stored on the device).
 *
 * The code rotates every 30s while unclaimed (see
 * DS_REST::PAIRING_CODE_ROTATE_SECONDS / pair_status()) — this page polls
 * that same endpoint on that cadence and swaps the on-screen code/QR in
 * place, no reload, so an old code left visible on an unattended screen
 * can't be picked up and used later. A visible countdown shows how long
 * until the next one.
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
$rotate_s   = DS_REST::PAIRING_CODE_ROTATE_SECONDS;
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
		/* Text is sized off the viewport WIDTH by default — right for a normal
		   landscape TV and for a tall narrow screen (a portrait bar display),
		   where width is the constrained dimension anyway. A short/wide bar
		   display (e.g. 1920x440) is the opposite case and gets its own
		   height-based override below, since vw alone would badly overflow it. */
		.wrap { display:grid; grid-template-columns: 1.1fr 0.9fr; align-items:center; height:100%; padding: 5vh 6vw; gap: 4vw; }
		.left { display:flex; flex-direction:column; justify-content:center; min-width:0; }
		.eyebrow { font-size: clamp(10px, 1.3vw, 13px); letter-spacing: .1em; text-transform: uppercase; color: #6b7690; margin: 0 0 .8em; }
		h1 { font-weight: 300; font-size: clamp(18px, 3vw, 34px); margin: 0 0 .2em; color: #cdd4e0; }
		.code { font-size: clamp(40px, 9vw, 108px); font-weight: 800; letter-spacing: .1em; margin: .2em 0 .3em; background: linear-gradient(120deg, var(--ds-red), var(--ds-orange), var(--ds-yellow)); -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1; transition: opacity .2s ease; }
		.code.ds-rotating { opacity: .3; }
		.countdown { font-size: clamp(11px, 1.2vw, 15px); color: #6b7690; margin: 0 0 .8em; }
		.countdown b { color: #9aa4bc; font-variant-numeric: tabular-nums; }
		.steps { list-style: none; margin: .5em 0 0; padding: 0; max-width: 560px; }
		.steps li { display:flex; gap: 14px; align-items:flex-start; margin-bottom: .8em; font-size: clamp(12px, 1.4vw, 18px); color: #cdd4e0; }
		.steps .num {
			flex-shrink:0; width: 1.8em; height: 1.8em; border-radius: 50%;
			background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
			display:flex; align-items:center; justify-content:center; font-weight:700; font-size: .85em; color: #fff;
		}
		.right { display:flex; flex-direction:column; align-items:center; justify-content:center; gap: 18px; min-width:0; }
		.qr-card { background:#fff; border-radius: 12px; padding: 20px; box-shadow: 0 12px 40px rgba(0,0,0,.35); line-height:0; }
		.qr-card img { display:block; width: min(280px, 24vw); height: auto; }
		.qr-caption { color:#8b93a7; font-size: clamp(10px, 1.1vw, 14px); text-align:center; max-width: 260px; }
		/* Narrow (portrait phone-ish kiosk): stop trying to fit two side-by-side
		   columns and stack instead. */
		@media (max-width: 900px) {
			.wrap { grid-template-columns: 1fr; text-align:center; }
			.left { align-items:center; }
			.steps li { text-align:left; }
		}
		/* Short/wide bar display (e.g. 1920x440): vw-based sizing above would
		   badly overflow such limited height, so switch every size to a vh
		   basis here instead, and drop the steps list entirely — there's no
		   room for it once the code + QR are legible. */
		@media (max-height: 480px) {
			.wrap { grid-template-columns: 1fr 1fr; padding: 3vh 3vw; gap: 3vw; }
			.left { align-items: flex-start; text-align: left; }
			.eyebrow { font-size: clamp(9px, 2.2vh, 13px); margin-bottom: .4em; }
			h1 { font-size: clamp(14px, 3.8vh, 24px); margin-bottom: .1em; }
			.code { font-size: clamp(28px, 13vh, 90px); margin: .1em 0 .2em; }
			.countdown { font-size: clamp(10px, 2vh, 13px); }
			.steps { display: none; }
			.qr-card { padding: min(16px, 2.5vh); }
			.qr-card img { width: min(200px, 30vh); }
			.qr-caption { display: none; }
		}
	</style>
</head>
<body>
	<div class="wrap">
		<div class="left">
			<p class="eyebrow"><?php echo esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></p>
			<h1><?php esc_html_e( 'This screen is not yet paired.', 'digital-signage' ); ?></h1>
			<div class="code" id="ds-code"><?php echo esc_html( $code ); ?></div>
			<p class="countdown"><?php esc_html_e( 'New code in', 'digital-signage' ); ?> <b id="ds-countdown"><?php echo (int) $rotate_s; ?></b>s</p>

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
			var codeEl      = document.getElementById( 'ds-code' );
			var qrEl        = document.getElementById( 'ds-qr' );
			var countdownEl = document.getElementById( 'ds-countdown' );
			var pairBase    = <?php echo wp_json_encode( $pair_base ); ?>;
			var statusUrl   = <?php echo wp_json_encode( $status_url ); ?>;
			var defaultRotateS = <?php echo (int) $rotate_s; ?>;

			var countdownTimer = null;
			function startCountdown( seconds ) {
				clearInterval( countdownTimer );
				var remaining = seconds;
				countdownEl.textContent = remaining;
				countdownTimer = setInterval( function () {
					remaining = Math.max( 0, remaining - 1 );
					countdownEl.textContent = remaining;
					if ( remaining <= 0 ) { clearInterval( countdownTimer ); }
				}, 1000 );
			}

			function applyCode( code ) {
				if ( ! code || code === codeEl.textContent ) { return; }
				codeEl.classList.add( 'ds-rotating' );
				setTimeout( function () {
					codeEl.textContent = code;
					qrEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=8&data=' + encodeURIComponent( pairBase + code );
					codeEl.classList.remove( 'ds-rotating' );
				}, 200 );
			}

			// Polls every 30s — matches the server's own rotation interval (see
			// DS_REST::PAIRING_CODE_ROTATE_SECONDS), so the code shown here is
			// always the one currently valid. Swaps it in place; only a real
			// pairing reloads. cache: 'no-store' plus a cache-busting query
			// param keep a page cache / caching plugin / the browser's own HTTP
			// cache from serving the same stale response on every poll, which
			// would otherwise look exactly like rotation isn't happening at all.
			( function poll() {
				fetch( statusUrl + '?_=' + Date.now(), { cache: 'no-store' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data && data.paired ) {
							window.location.reload();
							return;
						}
						var rotateS = ( data && data.rotates_in ) ? data.rotates_in : defaultRotateS;
						if ( data && data.code ) { applyCode( data.code ); }
						startCountdown( rotateS );
						setTimeout( poll, rotateS * 1000 );
					} )
					.catch( function () {
						setTimeout( poll, defaultRotateS * 1000 );
					} );
			} )();
			startCountdown( defaultRotateS );

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
