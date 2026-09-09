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
		html,body{width:100%;height:100%;margin:0;background:#0b0f14;color:#f5f7fa;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}body{display:grid;place-items:center}.card{text-align:center;padding:6vw;max-width:900px}.eyebrow{color:#8fa1b5;font-size:clamp(16px,2vw,28px);letter-spacing:.08em;text-transform:uppercase}.code{font:700 clamp(58px,12vw,150px)/1 ui-monospace,SFMono-Regular,Consolas,monospace;letter-spacing:.12em;margin:.18em 0}.output{font-size:clamp(18px,2.5vw,34px);color:#72d6a0}.hint{font-size:clamp(16px,2vw,28px);color:#aab6c3;line-height:1.45}.error{color:#ff8d8d}
	</style>
</head>
<body>
	<main class="card">
		<div class="eyebrow"><?php esc_html_e( 'Pair this controller', 'digital-signage' ); ?></div>
		<div class="code" id="code">••••••</div>
		<div class="output" id="output"><?php echo esc_html( $output_key ); ?></div>
		<p class="hint" id="hint"><?php esc_html_e( 'Enter this code in Digital Signage → Pair a Screen. This output will open its assigned Screen automatically.', 'digital-signage' ); ?></p>
	</main>
	<script>
	(function(){
		'use strict';
		var endpoint=<?php echo wp_json_encode( $status_url ); ?>;
		function poll(){
			fetch(endpoint,{cache:'no-store'}).then(function(response){if(!response.ok){throw new Error('status');}return response.json();}).then(function(data){
				document.getElementById('code').textContent=data.code||'------';
				document.getElementById('output').textContent=data.output_label||<?php echo wp_json_encode( $output_key ); ?>;
				if(data.paired&&data.player_url){window.location.replace(data.player_url);return;}
				setTimeout(poll,3000);
			}).catch(function(){document.getElementById('hint').className='hint error';document.getElementById('hint').textContent=<?php echo wp_json_encode( __( 'Cannot contact the signage server. Retrying…', 'digital-signage' ) ); ?>;setTimeout(poll,5000);});
		}
		poll();
	}());
	</script>
</body>
</html>
