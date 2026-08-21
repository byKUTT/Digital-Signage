<?php
/**
 * Shown fullscreen on an unpaired display: the code staff read off the TV
 * (or scan via QR code on their phone) and enter in wp-admin > Digital
 * Signage > Pair a Screen. Persists across reboots automatically: the
 * device always opens the same /signage/play/{token}/ URL (the token is
 * generated once by the kiosk installer and stored on the device), and this
 * screen looks up — rather than regenerates — the pairing code for that
 * token, so the same code keeps showing until it's used or expires.
 *
 * @var string $code
 * @var string $token
 * @var string $pairing_url
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qr_data  = rawurlencode( $pairing_url );
$qr_src   = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=8&data=' . $qr_data;
$status_url = esc_url_raw( rest_url( 'ds/v1/pair/status/' . $token ) );
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
		html, body { margin:0; padding:0; height:100%; background:#0b0e14; color:#fff; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; overflow:hidden; }
		.wrap { display:grid; grid-template-columns: 1.1fr 0.9fr; align-items:center; height:100%; padding: 5vh 6vw; gap: 4vw; }
		.left { display:flex; flex-direction:column; justify-content:center; }
		.eyebrow { font-size: 13px; letter-spacing: .1em; text-transform: uppercase; color: #6b7690; margin: 0 0 14px; }
		h1 { font-weight: 300; font-size: clamp(24px, 3vw, 34px); margin: 0 0 6px; color: #cdd4e0; }
		.code { font-size: clamp(64px, 9vw, 108px); font-weight: 800; letter-spacing: .12em; margin: 10px 0 22px; background: linear-gradient(120deg, var(--ds-red), var(--ds-orange), var(--ds-yellow)); -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1; }
		.steps { list-style: none; margin: 8px 0 0; padding: 0; max-width: 560px; }
		.steps li { display:flex; gap: 14px; align-items:flex-start; margin-bottom: 16px; font-size: clamp(14px, 1.4vw, 18px); color: #cdd4e0; }
		.steps .num {
			flex-shrink:0; width: 30px; height: 30px; border-radius: 50%;
			background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
			display:flex; align-items:center; justify-content:center; font-weight:700; font-size: 14px; color: #fff;
		}
		.right { display:flex; flex-direction:column; align-items:center; justify-content:center; gap: 18px; }
		.qr-card { background:#fff; border-radius: 20px; padding: 20px; box-shadow: 0 12px 40px rgba(0,0,0,.35); }
		.qr-card img { display:block; width: min(280px, 24vw); height: auto; }
		.qr-caption { color:#8b93a7; font-size: 14px; text-align:center; max-width: 260px; }
		.fs-hint {
			position: fixed; inset: 0; z-index: 20; display:none;
			align-items:center; justify-content:center; background: rgba(0,0,0,.4);
			color:#fff; font-size: 20px; cursor: pointer; text-align:center; padding: 20px;
		}
		.fs-hint.ds-visible { display:flex; }
		@media (max-width: 900px) {
			.wrap { grid-template-columns: 1fr; text-align:center; }
			.left { align-items:center; }
			.steps li { text-align:left; }
		}
	</style>
</head>
<body>
	<div class="wrap">
		<div class="left">
			<p class="eyebrow"><?php echo esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></p>
			<h1><?php esc_html_e( 'This screen is not yet paired.', 'digital-signage' ); ?></h1>
			<div class="code"><?php echo esc_html( $code ); ?></div>

			<ol class="steps">
				<li><span class="num">1</span><span><?php esc_html_e( 'On your phone or computer, scan the QR code (or open wp-admin manually).', 'digital-signage' ); ?></span></li>
				<li><span class="num">2</span><span><?php esc_html_e( 'Sign in to WordPress if asked — the pairing code will already be filled in.', 'digital-signage' ); ?></span></li>
				<li><span class="num">3</span><span><?php esc_html_e( 'Name this screen and confirm. It will start playing automatically — no need to touch this device again.', 'digital-signage' ); ?></span></li>
			</ol>
		</div>

		<div class="right">
			<div class="qr-card">
				<img src="<?php echo esc_url( $qr_src ); ?>" alt="<?php esc_attr_e( 'QR code to pair this screen', 'digital-signage' ); ?>" onerror="this.closest('.qr-card').style.display='none'" />
			</div>
			<p class="qr-caption"><?php esc_html_e( 'Quick setup: scan with your phone camera', 'digital-signage' ); ?></p>
		</div>
	</div>

	<div class="fs-hint" id="fs-hint"><?php esc_html_e( 'Tap anywhere for fullscreen', 'digital-signage' ); ?></div>

	<script>
		(function poll() {
			fetch( <?php echo wp_json_encode( $status_url ); ?> )
				.then(function(r){ return r.json(); })
				.then(function(data){
					if ( data && data.paired ) {
						window.location.reload();
						return;
					}
					setTimeout( poll, 5000 );
				})
				.catch(function(){ setTimeout( poll, 8000 ); });
		})();

		// This screen should be as full-screen/chrome-less as the player itself.
		// Auto-request it on load; if the browser blocks that (needs a user
		// gesture), show a tap-anywhere hint instead of silently staying windowed.
		(function () {
			function isFullscreen() {
				return !!( document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement );
			}
			function requestFs() {
				var el = document.documentElement;
				var request = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
				if ( request ) {
					try {
						var result = request.call( el );
						if ( result && result.catch ) { result.catch( function () {} ); }
					} catch ( e ) {}
				}
			}
			// Set by the Raspberry Pi / Windows kiosk installers on the URL they launch.
			// That browser is already started with --kiosk (OS-level fullscreen, no
			// chrome) — the Fullscreen API needs a user gesture the device has no
			// input for, so skip asking for it and never show the tap hint.
			var isKioskBrowser = /(?:^|[?&])kiosk=1(?:&|$)/.test( window.location.search );

			var hint = document.getElementById( 'fs-hint' );
			if ( isKioskBrowser ) { return; }
			requestFs();
			setTimeout( function () {
				if ( ! isFullscreen() ) { hint.classList.add( 'ds-visible' ); }
			}, 500 );
			document.addEventListener( 'fullscreenchange', function () {
				if ( isFullscreen() ) { hint.classList.remove( 'ds-visible' ); }
			} );
			hint.addEventListener( 'click', function () {
				requestFs();
				hint.classList.remove( 'ds-visible' );
			} );
		})();
	</script>
</body>
</html>
