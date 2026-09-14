<?php
/**
 * Shown fullscreen on an unpaired display: the code staff read off the TV
 * (or scan via QR code on their phone) and enter in the authenticated frontend
 * Signage Manager. Persists across reboots automatically: the
 * device always opens the same /play/{token}/ URL (the token is
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
$qr_src     = 'https://api.qrserver.com/v1/create-qr-code/?size=1000x1000&margin=16&data=' . $qr_data;
$status_url = esc_url_raw( rest_url( 'ds/v1/pair/status/' . $token ) );
$pair_base  = esc_url_raw( DS_Portal::url( 'pair', array( 'code' => '__CODE__' ) ) );
$rotate_s   = DS_REST::PAIRING_CODE_ROTATE_SECONDS;
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php esc_html_e( 'Screens byKUTT — Pair this screen', 'digital-signage' ); ?></title>
	<style>
		:root{--forest:#071812;--forest-2:#0e2b20;--ivory:#f8f6ed;--muted:#aabbb2;--signal:#e6ff3b}*{box-sizing:border-box}html,body{width:100%;height:100%;margin:0}body{overflow:hidden;background:var(--forest);color:var(--ivory);font-family:Geist,"Helvetica Neue",Arial,sans-serif}.shell{position:relative;display:grid;grid-template-rows:auto 1fr;min-height:100%;padding:clamp(22px,3.6vw,60px);isolation:isolate}.shell:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,24,18,.98) 0 47%,rgba(7,24,18,.76) 70%,rgba(7,24,18,.94)),url('<?php echo esc_url( DS_PLUGIN_URL . 'public/images/templates/welcome.webp' ); ?>') center/cover;filter:saturate(.72);z-index:-2}.shell:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 80% 46%,rgba(230,255,59,.11),transparent 31%);z-index:-1}.brand{display:flex;align-items:center;justify-content:space-between}.brand-lockup{display:flex;align-items:center;gap:12px;font-weight:760;font-size:clamp(16px,1.4vw,24px);line-height:.9}.brand-lockup img{width:clamp(34px,3.4vw,56px);height:auto}.brand-lockup small{display:block;margin-top:6px;font-size:.5em;letter-spacing:.18em}.bykutt{font-size:clamp(10px,.8vw,14px);font-weight:650;letter-spacing:.24em}.wrap{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,34%);align-items:center;gap:clamp(34px,7vw,130px);width:min(1380px,100%);margin:auto}.left{min-width:0}.eyebrow{margin:0 0 18px;color:var(--muted);font-size:clamp(11px,.9vw,15px);font-weight:700;letter-spacing:.18em;text-transform:uppercase}.eyebrow:after{content:"";display:inline-block;width:64px;height:1px;margin:0 0 4px 18px;background:rgba(248,246,237,.35)}h1{max-width:12ch;margin:0;font-size:clamp(38px,5.2vw,88px);font-weight:560;line-height:.94;letter-spacing:-.045em}.lead{max-width:34ch;margin:22px 0 0;color:#c8d3cd;font-size:clamp(15px,1.35vw,22px);line-height:1.42}.signal{width:34px;height:3px;margin:26px 0 14px;background:var(--signal)}.code{color:var(--ivory);font-size:clamp(34px,4.8vw,74px);font-weight:760;line-height:1;letter-spacing:.13em;transition:opacity .2s ease}.code.ds-rotating{opacity:.3}.countdown{margin:10px 0 0;color:var(--muted);font-size:clamp(12px,1vw,16px)}.countdown b{color:var(--ivory);font-variant-numeric:tabular-nums}.steps{display:none}.right{display:grid;justify-items:center;gap:15px}.qr-card{padding:clamp(14px,1.6vw,24px);border-radius:18px;background:var(--ivory);box-shadow:0 24px 70px rgba(0,0,0,.35);line-height:0;animation:arrive .6s cubic-bezier(.2,.8,.2,1) both}.qr-card img{display:block;width:min(340px,27vw);height:auto}.qr-caption{max-width:28ch;margin:0;color:#c7d2cc;text-align:center;font-size:clamp(12px,1vw,16px);line-height:1.4}@keyframes arrive{from{opacity:0;transform:translateY(16px) scale(.985)}to{opacity:1;transform:none}}@media(max-width:760px),(orientation:portrait){body{overflow:auto}.shell{min-height:100%;padding:24px}.wrap{grid-template-columns:1fr;align-content:center;padding:6vh 0;gap:4vh;text-align:center}.left{display:grid;justify-items:center}h1{max-width:11ch;font-size:clamp(38px,10vw,72px)}.lead{font-size:clamp(16px,3.6vw,24px)}.eyebrow:after{display:none}.signal{margin-top:20px}.qr-card img{width:min(66vw,46vh,420px)}.qr-caption{font-size:clamp(14px,3vw,20px)}}@media(max-height:500px) and (orientation:landscape){.shell{padding:18px 28px}.wrap{grid-template-columns:1fr minmax(160px,27%);gap:5vw}.brand-lockup img{width:30px}h1{font-size:clamp(28px,10vh,52px)}.lead{margin-top:10px;font-size:clamp(12px,3vh,17px)}.signal{margin:12px 0 8px}.code{font-size:clamp(28px,10vh,58px)}.qr-card{padding:10px}.qr-card img{width:min(190px,42vh)}.qr-caption{display:none}}@media(prefers-reduced-motion:reduce){.qr-card{animation:none}}
	</style>
</head>
<body><main class="shell">
	<header class="brand"><div class="brand-lockup"><img src="<?php echo esc_url( DS_PLUGIN_URL . 'public/images/screens-bykutt-mark.svg' ); ?>" alt=""><span>Screens<small>byKUTT</small></span></div><span class="bykutt">byKUTT</span></header>
	<div class="wrap">
		<div class="left">
			<p class="eyebrow"><?php esc_html_e( 'Connect a screen', 'digital-signage' ); ?></p>
			<h1><?php esc_html_e( 'Scan to pair this screen.', 'digital-signage' ); ?></h1>
			<p class="lead"><?php esc_html_e( 'Open the QR code, sign in, and add this display to your workspace.', 'digital-signage' ); ?></p>
			<div class="signal" aria-hidden="true"></div>
			<div class="code" id="ds-code"><?php echo esc_html( $code ); ?></div>
			<p class="countdown"><?php esc_html_e( 'New code in', 'digital-signage' ); ?> <b id="ds-countdown"><?php echo (int) $rotate_s; ?></b>s</p>

		</div>

		<div class="right">
			<div class="qr-card">
				<img id="ds-qr" src="<?php echo esc_url( $qr_src ); ?>" alt="<?php esc_attr_e( 'QR code to pair this screen', 'digital-signage' ); ?>" onerror="this.closest('.qr-card').style.display='none'" />
			</div>
			<p class="qr-caption"><?php esc_html_e( 'Point your phone camera at the code. Pairing takes only a few seconds.', 'digital-signage' ); ?></p>
		</div>
	</div></main>

	<script>
		( function () {
			var codeEl      = document.getElementById( 'ds-code' );
			var qrEl        = document.getElementById( 'ds-qr' );
			var countdownEl = document.getElementById( 'ds-countdown' );
			var pairBase    = <?php echo wp_json_encode( $pair_base ); ?>;
			var statusUrl   = <?php echo wp_json_encode( $status_url ); ?>;
			var defaultRotateS = <?php echo (int) $rotate_s; ?>;
			var pairedRedirectKey = 'ds-paired-redirect:' + statusUrl;

			var countdownTimer = null;
			var refreshInProgress = false;

			function refreshForNextCode() {
				if ( refreshInProgress ) { return; }
				refreshInProgress = true;
				clearInterval( countdownTimer );

				var reloaded = false;
				function reloadOnce() {
					if ( reloaded ) { return; }
					reloaded = true;
					var nextUrl = new URL( window.location.href );
					nextUrl.searchParams.set( '_ds_rotate', String( Date.now() ) );
					window.location.replace( nextUrl.toString() );
				}

				// Call status first so the server rotates the expired code before
				// the page asks for fresh HTML. Always reload, even if the request
				// fails or hangs, because zero must never remain on screen.
				fetch( statusUrl + '?_=' + Date.now(), { cache: 'no-store' } )
					.then( reloadOnce )
					.catch( reloadOnce );
				setTimeout( reloadOnce, 3000 );
			}

			function startCountdown( seconds ) {
				clearInterval( countdownTimer );
				var remaining = seconds;
				countdownEl.textContent = remaining;
				countdownTimer = setInterval( function () {
					remaining = Math.max( 0, remaining - 1 );
					countdownEl.textContent = remaining;
					if ( remaining <= 0 ) {
						clearInterval( countdownTimer );
						refreshForNextCode();
					}
				}, 1000 );
			}

			function applyCode( code ) {
				if ( ! code || code === codeEl.textContent ) { return; }
				codeEl.classList.add( 'ds-rotating' );
				setTimeout( function () {
					codeEl.textContent = code;
					qrEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=1000x1000&margin=16&data=' + encodeURIComponent( pairBase.replace('__CODE__', code) );
					codeEl.classList.remove( 'ds-rotating' );
				}, 200 );
			}

			// Fetch once on load to synchronize the countdown with the server.
			// When it reaches zero, refreshForNextCode() calls this endpoint to
			// rotate the code and then reloads the whole page. cache: 'no-store'
			// plus a cache-busting query
			// param keep a page cache / caching plugin / the browser's own HTTP
			// cache from serving the same stale response on every poll, which
			// would otherwise look exactly like rotation isn't happening at all.
			( function poll() {
				fetch( statusUrl + '?_=' + Date.now(), { cache: 'no-store' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data && data.paired ) {
							// A full-page cache can briefly keep serving this pairing template
							// after the screen is paired. Never reload it every poll: retry at
							// most once per minute and use a cache-busting URL.
							var now = Date.now();
							var lastRedirect = 0;
							try { lastRedirect = parseInt( sessionStorage.getItem( pairedRedirectKey ) || '0', 10 ); } catch ( e ) {}
							if ( now - lastRedirect >= 60000 ) {
								try { sessionStorage.setItem( pairedRedirectKey, String( now ) ); } catch ( e ) {}
								var nextUrl = new URL( window.location.href );
								nextUrl.searchParams.set( '_ds_paired', String( now ) );
								window.location.replace( nextUrl.toString() );
							}
							return;
						}
						var rotateS = ( data && data.rotates_in ) ? data.rotates_in : defaultRotateS;
						if ( data && data.code ) { applyCode( data.code ); }
						startCountdown( rotateS );
					} )
					.catch( function () {
						// The existing countdown still guarantees a refresh at zero.
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
