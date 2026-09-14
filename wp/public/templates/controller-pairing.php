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
		:root{--forest:#071812;--ivory:#f8f6ed;--muted:#aebdb5;--signal:#e6ff3b}*{box-sizing:border-box}html,body{width:100%;height:100%;margin:0}body{overflow:hidden;background:var(--forest);color:var(--ivory);font-family:Geist,"Helvetica Neue",Arial,sans-serif}.shell{position:relative;display:grid;grid-template-rows:auto 1fr;min-height:100%;padding:clamp(22px,3.6vw,60px);isolation:isolate}.shell:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,24,18,.98) 0 48%,rgba(7,24,18,.74) 72%,rgba(7,24,18,.94)),url('<?php echo esc_url( DS_PLUGIN_URL . 'public/images/templates/welcome.webp' ); ?>') center/cover;filter:saturate(.72);z-index:-2}.shell:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 80% 48%,rgba(230,255,59,.12),transparent 30%);z-index:-1}.brand{display:flex;align-items:center;justify-content:space-between}.lockup{display:flex;align-items:center;gap:12px;font-size:clamp(16px,1.4vw,24px);font-weight:760;line-height:.9}.lockup img{width:clamp(34px,3.4vw,56px)}.lockup small{display:block;margin-top:6px;font-size:.5em;letter-spacing:.18em}.bykutt{font-size:clamp(10px,.8vw,14px);font-weight:650;letter-spacing:.24em}.card{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,34%);align-items:center;gap:clamp(34px,7vw,130px);width:min(1380px,100%);margin:auto}.copy{min-width:0}.eyebrow{margin-bottom:18px;color:var(--muted);font-size:clamp(11px,.9vw,15px);font-weight:700;letter-spacing:.18em;text-transform:uppercase}.eyebrow:after{content:"";display:inline-block;width:64px;height:1px;margin:0 0 4px 18px;background:rgba(248,246,237,.35)}h1{max-width:11ch;margin:0;font-size:clamp(38px,5.2vw,88px);font-weight:560;line-height:.94;letter-spacing:-.045em}.hint{max-width:36ch;margin:22px 0 0;color:#c8d3cd;font-size:clamp(15px,1.35vw,22px);line-height:1.42}.signal{width:34px;height:3px;margin:26px 0 14px;background:var(--signal)}.code{font-size:clamp(34px,4.8vw,74px);font-weight:760;line-height:1;letter-spacing:.13em}.output{margin-top:10px;color:var(--muted);font-size:clamp(12px,1vw,16px);font-weight:650}.error{color:#ffaaa4}.qr{justify-self:center;padding:clamp(14px,1.6vw,24px);border-radius:18px;background:var(--ivory);box-shadow:0 24px 70px rgba(0,0,0,.35);line-height:0;animation:arrive .6s cubic-bezier(.2,.8,.2,1) both}.qr img{display:block;width:min(340px,27vw);height:auto}.qr[hidden]{display:none}@keyframes arrive{from{opacity:0;transform:translateY(16px) scale(.985)}to{opacity:1;transform:none}}@media(max-width:760px),(orientation:portrait){body{overflow:auto}.shell{padding:24px}.card{grid-template-columns:1fr;align-content:center;padding:6vh 0;gap:4vh;text-align:center}.copy{display:grid;justify-items:center}.eyebrow:after{display:none}h1{font-size:clamp(38px,10vw,72px)}.hint{font-size:clamp(16px,3.6vw,24px)}.qr{width:auto}.qr img{width:min(66vw,46vh,420px)}}@media(max-height:500px) and (orientation:landscape){.shell{padding:18px 28px}.card{grid-template-columns:1fr minmax(160px,27%);gap:5vw}.lockup img{width:30px}h1{font-size:clamp(28px,10vh,52px)}.hint{margin-top:10px;font-size:clamp(12px,3vh,17px)}.signal{margin:12px 0 8px}.code{font-size:clamp(28px,10vh,58px)}.qr{padding:10px}.qr img{width:min(190px,42vh)}}@media(prefers-reduced-motion:reduce){.qr{animation:none}}
	</style>
</head>
<body>
	<main class="shell"><header class="brand"><div class="lockup"><img src="<?php echo esc_url( DS_PLUGIN_URL . 'public/images/screens-bykutt-mark.svg' ); ?>" alt=""><span>Screens<small>byKUTT</small></span></div><span class="bykutt">byKUTT</span></header><section class="card">
		<div class="copy"><div class="eyebrow"><?php esc_html_e( 'Connect a display', 'digital-signage' ); ?></div><h1><?php esc_html_e( 'Scan to pair this screen.', 'digital-signage' ); ?></h1>
		<p class="hint" id="hint"><?php esc_html_e( 'Scan the QR code, sign in, and add this display to your workspace.', 'digital-signage' ); ?></p><div class="signal" aria-hidden="true"></div>
		<div class="code" id="code">••••••</div>
		<div class="output" id="output"><?php echo esc_html( $output_key ); ?></div></div>
		<div class="qr" id="qr-card" hidden></div>
	</section></main>
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
