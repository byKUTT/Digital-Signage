<?php
/**
 * Shown fullscreen on an unpaired display: the code staff read off the TV
 * and enter in wp-admin > Digital Signage > Pair a Screen.
 *
 * @var string $code
 * @var string $token
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$status_url = esc_url_raw( rest_url( 'ds/v1/pair/status/' . $token ) );
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php esc_html_e( 'Pair this Screen', 'digital-signage' ); ?></title>
	<style>
		html, body { margin:0; padding:0; height:100%; background:#0b0e14; color:#fff; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
		.wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center; padding:24px; box-sizing:border-box; }
		h1 { font-weight:300; font-size:28px; margin-bottom:4px; }
		p.sub { color:#8b93a7; margin-top:0; }
		.code { font-size:96px; font-weight:700; letter-spacing:18px; margin:32px 0; background:#161b26; padding:24px 48px; border-radius:16px; }
		.hint { color:#8b93a7; max-width:520px; }
	</style>
</head>
<body>
	<div class="wrap">
		<h1><?php esc_html_e( 'Digital Signage Player', 'digital-signage' ); ?></h1>
		<p class="sub"><?php esc_html_e( 'This screen is not yet paired.', 'digital-signage' ); ?></p>
		<div class="code"><?php echo esc_html( $code ); ?></div>
		<p class="hint"><?php esc_html_e( 'In wp-admin, go to Digital Signage → Pair a Screen and enter this code. This page will automatically switch to the live playlist once paired.', 'digital-signage' ); ?></p>
	</div>
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
	</script>
</body>
</html>
