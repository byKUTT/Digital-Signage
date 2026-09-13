<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="cache-control" content="no-store">
	<title><?php echo esc_html( $site_name ); ?> — Controller setup</title>
	<style>
		html,body{width:100%;height:100%;margin:0;background:#0b0f14;color:#f5f7fa;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}body{display:grid;place-items:center}.card{display:grid;grid-template-columns:minmax(0,1fr) minmax(180px,32%);align-items:center;gap:min(7vw,80px);padding:6vw;width:min(1200px,100%)}.copy{text-align:left}.eyebrow{color:#8fa1b5;font-size:clamp(16px,2vw,28px);letter-spacing:.08em;text-transform:uppercase}.code{font:700 clamp(58px,11vw,140px)/1 ui-monospace,SFMono-Regular,Consolas,monospace;letter-spacing:.1em;margin:.18em 0}.output{font-size:clamp(18px,2.5vw,34px);color:#72d6a0}.hint{font-size:clamp(16px,2vw,28px);color:#aab6c3;line-height:1.45}.error{color:#ff8d8d}.qr{background:#fff;border-radius:14px;padding:min(2vw,22px);line-height:0;box-shadow:0 20px 60px rgba(0,0,0,.35)}.qr img{display:block;width:100%;height:auto}@media(orientation:portrait){.card{grid-template-columns:1fr;text-align:center}.copy{text-align:center}.qr{width:min(66vw,420px);justify-self:center}}@media(max-height:480px){.card{grid-template-columns:1fr minmax(120px,24%);padding:3vh 4vw}.code{font-size:clamp(34px,16vh,76px)}.hint{font-size:clamp(12px,3.5vh,18px)}}
	</style>
</head>
<body>
	<main class="card">
		<div class="copy"><div class="eyebrow"><?php esc_html_e( 'Connect this display', 'digital-signage' ); ?></div>
		<div class="code" id="code">••••••</div>
		<div class="output" id="output"><?php echo esc_html( $output_key ); ?></div>
		<p class="hint" id="hint"><?php esc_html_e( 'Scan the QR code, sign in, and choose a group. You can then bind this display to a screen.', 'digital-signage' ); ?></p></div>
		<div class="qr" id="qr-card" hidden></div>
	</main>
	<script>
	(function(){
		'use strict';
		var endpoint=<?php echo wp_json_encode( $status_url ); ?>;
		var qrCard=document.getElementById('qr-card');var qr=document.createElement('img');qr.alt=<?php echo wp_json_encode( __( 'QR code to connect this display', 'digital-signage' ) ); ?>;qrCard.appendChild(qr);
		function setQr(url){if(!url){qrCard.hidden=true;return;}qr.src='https://api.qrserver.com/v1/create-qr-code/?size=900x900&margin=16&data='+encodeURIComponent(url);qrCard.hidden=false;}
		function poll(){
			fetch(endpoint,{cache:'no-store'}).then(function(response){if(!response.ok){throw new Error('status');}return response.json();}).then(function(data){
				document.getElementById('code').textContent=data.code||'------';
				document.getElementById('output').textContent=data.output_label||<?php echo wp_json_encode( $output_key ); ?>;
				if(data.paired&&data.player_url){window.location.replace(data.player_url);return;}
				setQr(data.paired?data.manage_url:data.pairing_url);
				if(data.paired){document.getElementById('hint').textContent=<?php echo wp_json_encode( __( 'This display is not assigned. Scan to open its controller and choose a screen.', 'digital-signage' ) ); ?>;document.getElementById('code').textContent=<?php echo wp_json_encode( __( 'READY', 'digital-signage' ) ); ?>;}
				setTimeout(poll,3000);
			}).catch(function(){document.getElementById('hint').className='hint error';document.getElementById('hint').textContent=<?php echo wp_json_encode( __( 'Cannot contact the signage server. Retrying…', 'digital-signage' ) ); ?>;setTimeout(poll,5000);});
		}
		poll();
	}());
	</script>
</body>
</html>
