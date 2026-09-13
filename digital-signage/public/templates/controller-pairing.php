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
	<title><?php esc_html_e( 'Screens byKUTT — Controller setup', 'digital-signage' ); ?></title>
	<style>
		html,body{width:100%;height:100%;margin:0;background:#e9ff54;color:#102018;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}*{box-sizing:border-box}body{display:grid;place-items:center;overflow:hidden}.card{position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(190px,31%);align-items:center;gap:min(8vw,100px);width:min(1320px,100%);padding:clamp(24px,6vw,88px)}.card:before{content:"";position:fixed;right:-18vw;bottom:-40vh;width:70vw;height:85vh;border-radius:50%;background:#153329;opacity:.12}.copy{position:relative;text-align:left}.eyebrow{color:#3d5e4e;font-size:clamp(13px,1.25vw,20px);font-weight:750;letter-spacing:.08em;text-transform:uppercase}.code{font:850 clamp(58px,11vw,148px)/.92 system-ui,sans-serif;letter-spacing:.055em;margin:.24em 0;color:#102018}.output{font-size:clamp(18px,2.1vw,30px);font-weight:800;color:#236b43}.hint{max-width:34ch;font-size:clamp(16px,1.7vw,25px);color:#365348;line-height:1.42}.error{color:#871f1a}.qr{position:relative;background:#fff;border-radius:24px;padding:min(2vw,24px);line-height:0;box-shadow:18px 22px 0 #153329}.qr img{display:block;width:100%;height:auto;border-radius:8px}@media(orientation:portrait),(max-width:760px){body{overflow:auto}.card{grid-template-columns:1fr;text-align:center;align-content:center;min-height:100%;gap:4vh}.copy{text-align:center}.hint{margin-left:auto;margin-right:auto}.qr{width:min(70vw,420px);justify-self:center;box-shadow:12px 14px 0 #153329}}@media(max-height:480px){.card{grid-template-columns:1fr minmax(120px,24%);padding:3vh 4vw;gap:5vw}.copy{text-align:left}.code{font-size:clamp(34px,16vh,76px)}.hint{font-size:clamp(12px,3.5vh,18px)}.qr{box-shadow:8px 9px 0 #153329}}
	</style>
</head>
<body>
	<main class="card">
		<div class="copy"><div class="eyebrow"><?php esc_html_e( 'Screens byKUTT · Connect this display', 'digital-signage' ); ?></div>
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
