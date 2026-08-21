/**
 * Digital Signage frontend player.
 * - Requests fullscreen on load, falls back to a "click to start" overlay if the
 *   browser blocks auto-fullscreen (requires a user gesture).
 * - Polls the DS REST API for the resolved playlist and re-renders zones/layout.
 * - Each zone runs its own independent slide rotation with per-slide timing.
 * - Preloads the next slide's media to avoid flicker.
 * - Caches the last good playlist in localStorage and keeps playing it if the
 *   network drops, retrying silently in the background.
 * - Sends a heartbeat ("I'm alive") on an interval with resolution/orientation/IP.
 * - Logs proof-of-play for each slide shown.
 *
 * No build step / framework: kept intentionally small for low-powered kiosk hardware.
 */
( function () {
	'use strict';

	var CONFIG = window.DS_PLAYER || {};
	var CACHE_KEY = 'ds_playlist_cache_' + CONFIG.screenId;

	var state = {
		playlist: null,
		zones: {}, // zoneName -> { items, index, timer }
		online: true,
	};

	/* ---------------------------------------------------------------- */
	/* Fullscreen handling                                               */
	/* ---------------------------------------------------------------- */

	function requestFullscreen() {
		var el = document.documentElement;
		var request = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
		if ( request ) {
			try {
				var result = request.call( el );
				if ( result && result.catch ) {
					result.catch( function () {
						/* Blocked without a gesture — overlay stays visible. */
					} );
				}
			} catch ( e ) { /* noop */ }
		}
	}

	function isFullscreen() {
		return !! ( document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement );
	}

	function isKioskBrowser() {
		// Set by the Raspberry Pi / Windows kiosk installers on the URL they launch.
		// A browser started with --kiosk (or Windows equivalent) is already OS-level
		// fullscreen with no chrome to hide — the Fullscreen API here would just need
		// a user gesture the device has no mouse/keyboard/touch to provide, so there's
		// nothing useful left for it to do.
		return /(?:^|[?&])kiosk=1(?:&|$)/.test( window.location.search );
	}

	function initFullscreen() {
		var overlay = document.getElementById( 'ds-start-overlay' );
		var button  = document.getElementById( 'ds-start-button' );

		if ( isKioskBrowser() ) {
			if ( overlay ) { overlay.classList.add( 'ds-hidden' ); }
			return;
		}

		// Try automatically first (works if the page itself was opened by a user gesture,
		// e.g. a kiosk browser launched fresh, or on browsers that allow it for top-level nav).
		requestFullscreen();

		setTimeout( function () {
			if ( isFullscreen() ) {
				overlay.classList.add( 'ds-hidden' );
			}
		}, 400 );

		// Signage screens are almost always unattended with no mouse/touch/keyboard —
		// a screen that never gets clicked must still start playing. If nothing
		// dismissed the overlay shortly after load (fullscreen was blocked and this
		// isn't a recognized kiosk browser either), start playback behind it anyway
		// instead of waiting forever for a click that will never come.
		setTimeout( function () {
			overlay.classList.add( 'ds-hidden' );
		}, 4000 );

		button.addEventListener( 'click', function () {
			requestFullscreen();
			overlay.classList.add( 'ds-hidden' );
		} );

		// If auto-fullscreen worked instantly, hide overlay right away too.
		document.addEventListener( 'fullscreenchange', function () {
			if ( isFullscreen() ) {
				overlay.classList.add( 'ds-hidden' );
			}
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Networking helpers                                                 */
	/* ---------------------------------------------------------------- */

	function apiGet( path ) {
		var headers = {};
		if ( CONFIG.nonce ) {
			headers['X-WP-Nonce'] = CONFIG.nonce; // Only needed for the cookie-authenticated preview endpoint.
		}
		return fetch( CONFIG.restUrl + path, { cache: 'no-store', credentials: 'same-origin', headers: headers } ).then( function ( r ) {
			if ( ! r.ok ) {
				throw new Error( 'HTTP ' + r.status );
			}
			return r.json();
		} );
	}

	function apiPost( path, body ) {
		if ( CONFIG.isPreview ) {
			return Promise.resolve(); // Previewing in wp-admin never writes heartbeats/proof-of-play.
		}
		return fetch( CONFIG.restUrl + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( body || {} ),
		} ).catch( function () {
			/* Best-effort; never let a failed heartbeat/proof-of-play break playback. */
		} );
	}

	function setOffline( offline ) {
		state.online = ! offline;
		var indicator = document.getElementById( 'ds-offline-indicator' );
		if ( indicator ) {
			indicator.hidden = ! offline;
		}
	}

	/* ---------------------------------------------------------------- */
	/* Playlist fetch + cache                                            */
	/* ---------------------------------------------------------------- */

	function loadCachedPlaylist() {
		try {
			var raw = localStorage.getItem( CACHE_KEY );
			return raw ? JSON.parse( raw ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function cachePlaylist( playlist ) {
		try {
			localStorage.setItem( CACHE_KEY, JSON.stringify( playlist ) );
		} catch ( e ) { /* storage full/unavailable — keep playing from memory */ }
	}

	function fetchPlaylist() {
		var path = CONFIG.isPreview ? ( '/preview/' + CONFIG.previewChannelId ) : '/playlist';
		apiGet( path )
			.then( function ( data ) {
				setOffline( false );
				if ( ! CONFIG.isPreview ) {
					cachePlaylist( data );
				}
				applyPlaylist( data );
				handleRemoteCommand( data );
			} )
			.catch( function () {
				setOffline( true );
				// Keep playing whatever is already rendered; if nothing has rendered yet
				// (first load with no connection), fall back to the last cached playlist.
				if ( ! state.playlist ) {
					var cached = loadCachedPlaylist();
					if ( cached ) {
						applyPlaylist( cached );
					}
				}
			} );
	}

	function handleRemoteCommand( data ) {
		if ( 'reload' !== data.remote_command && 'refresh' !== data.remote_command ) {
			return;
		}

		// A proxy/page cache may repeat the same one-shot command even after the
		// server consumed it. Persist its timestamp before reloading so one stale
		// response cannot trap an unattended display in a reload loop.
		var commandKey = data.remote_command + ':' + ( data.remote_ts || 'legacy' );
		var storageKey = 'ds-last-remote-command';
		try {
			if ( sessionStorage.getItem( storageKey ) === commandKey ) {
				return;
			}
			sessionStorage.setItem( storageKey, commandKey );
		} catch ( e ) {
			// Storage can be unavailable in hardened kiosk profiles. The REST
			// endpoint still consumes commands, so retain the normal behavior.
		}
		window.location.reload();
	}

	/* ---------------------------------------------------------------- */
	/* Rendering                                                          */
	/* ---------------------------------------------------------------- */

	function applyPlaylist( data ) {
		state.playlist = data;

		var stage = document.getElementById( 'ds-stage' );
		stage.className = 'ds-stage ds-layout-' + ( data.layout || 'fullscreen' );
		if ( data.zone_bg ) {
			stage.style.setProperty( '--ds-zone-bg', data.zone_bg );
		}

		document.documentElement.setAttribute(
			'data-ds-orientation',
			data.orientation && 'auto' !== data.orientation ? data.orientation : detectOrientation()
		);

		var zones = data.zones || {};
		var seenZones = {};

		Object.keys( zones ).forEach( function ( zoneName ) {
			seenZones[ zoneName ] = true;
			startZone( zoneName, zones[ zoneName ] || [] );
		} );

		// Stop rotations for zones that no longer have content.
		Object.keys( state.zones ).forEach( function ( zoneName ) {
			if ( ! seenZones[ zoneName ] ) {
				stopZone( zoneName );
			}
		} );

		// A screen with no channel assigned (or an assigned channel with no
		// slides for right now) would otherwise just show a blank stage —
		// make that state visible instead of looking like a dead/frozen screen.
		var noChannelEl = document.getElementById( 'ds-no-channel' );
		if ( noChannelEl ) {
			noChannelEl.hidden = !! ( data.channel_id && Object.keys( zones ).length );
		}
	}

	function detectOrientation() {
		return window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
	}

	function zoneEl( zoneName ) {
		return document.getElementById( 'ds-zone-' + zoneName );
	}

	function startZone( zoneName, items ) {
		var container = zoneEl( zoneName );
		if ( ! container ) {
			return; // Unknown zone name in this layout — ignore gracefully.
		}

		var existing = state.zones[ zoneName ];

		// If the complete zone payload is unchanged, don't restart its rotation or
		// continuous slider — polling stays invisible to the viewer.
		if ( existing && sameItems( existing.items, items ) ) {
			existing.items = items;
			setZoneTransition( container, items );
			return;
		}

		if ( existing ) {
			clearTimeout( existing.timer );
			stopTimers( container );
		}

		container.innerHTML = '';
		setZoneTransition( container, items );

		state.zones[ zoneName ] = { items: items, index: 0, timer: null, els: {} };

		if ( ! items.length ) {
			return;
		}

		if ( canUseInfiniteSlider( items ) ) {
			renderInfiniteSlider( zoneName );
		} else {
			renderSlide( zoneName, 0 );
		}
	}

	function setZoneTransition( container, items ) {
		var transition = ( items[0] && items[0].transition ) || 'fade';
		if ( 'infinite_slider' === transition ) {
			transition = canUseInfiniteSlider( items ) ? 'none' : 'fade';
		}
		var transitionClass = 'ds-transition-' + transition;
		var zoneName = container.id.replace( 'ds-zone-', '' );
		container.className = 'ds-zone ds-zone-' + zoneName + ' ' + transitionClass;
	}

	function canUseInfiniteSlider( items ) {
		return !! items.length && 'infinite_slider' === items[0].transition && items.every( function ( item ) {
			return 'image' === item.type && !! item.src;
		} );
	}

	function stopZone( zoneName ) {
		var zone = state.zones[ zoneName ];
		if ( zone ) {
			clearTimeout( zone.timer );
		}
		delete state.zones[ zoneName ];
		var el = zoneEl( zoneName );
		if ( el ) {
			stopTimers( el );
			el.innerHTML = '';
		}
	}

	function sameItems( a, b ) {
		return JSON.stringify( a ) === JSON.stringify( b );
	}

	function renderInfiniteSlider( zoneName ) {
		var zone = state.zones[ zoneName ];
		if ( ! zone || ! zone.items.length ) {
			return;
		}

		var container = zoneEl( zoneName );
		var slide = document.createElement( 'div' );
		slide.className = 'ds-slide ds-active ds-infinite-slider-slide';
		slide.dataset.slideId = 'infinite-slider';
		slide.appendChild( buildInfiniteSliderEl( zone.items ) );
		container.appendChild( slide );

		zone.items.forEach( function ( item ) {
			logProofOfPlay( zoneName, item );
		} );
	}

	function buildSlideEl( item ) {
		var el = document.createElement( 'div' );
		el.className = 'ds-slide' + ( 'contain' === item.fit ? ' ds-fit-contain' : '' );
		el.dataset.slideId = item.id;

		switch ( item.type ) {
			case 'image': {
				var img = document.createElement( 'img' );
				img.src = item.src || '';
				img.alt = item.title || '';
				el.appendChild( img );
				break;
			}
			case 'video': {
				var video = document.createElement( 'video' );
				video.src = item.src || '';
				video.autoplay = true;
				video.muted = true;
				video.playsInline = true;
				video.loop = 'fixed_duration' === item.play_mode;
				el.appendChild( video );
				break;
			}
			case 'webpage': {
				var iframe = document.createElement( 'iframe' );
				iframe.src = item.url || 'about:blank';
				iframe.setAttribute( 'sandbox', 'allow-scripts allow-same-origin allow-popups' );
				el.appendChild( iframe );
				break;
			}
			case 'html': {
				var wrap = document.createElement( 'div' );
				wrap.className = 'ds-html-block';
				wrap.innerHTML = item.html || '';
				el.appendChild( wrap );
				break;
			}
			case 'rss': {
				el.appendChild( buildRssEl( item ) );
				break;
			}
			case 'weather': {
				el.appendChild( buildWeatherEl( item ) );
				break;
			}
			case 'clock': {
				el.appendChild( buildClockEl() );
				break;
			}
			case 'pdf': {
				var pdfFrame = document.createElement( 'iframe' );
				pdfFrame.src = item.src || 'about:blank';
				el.appendChild( pdfFrame );
				break;
			}
			case 'social': {
				var socialFrame = document.createElement( 'iframe' );
				socialFrame.src = item.embed_url || 'about:blank';
				socialFrame.setAttribute( 'sandbox', 'allow-scripts allow-same-origin' );
				el.appendChild( socialFrame );
				break;
			}
			case 'infinite_scroll': {
				el.appendChild( buildSlidingCarouselEl( item ) );
				break;
			}
			default: {
				el.textContent = item.title || '';
			}
		}

		return el;
	}

	// Estonian weekday/month names, used instead of relying on the kiosk browser's
	// system locale (which varies per device) — every screen shows the same format:
	// 24-hour time and dd.mm.yyyy dates, per house style.
	var ET_WEEKDAYS = [ 'Pühapäev', 'Esmaspäev', 'Teisipäev', 'Kolmapäev', 'Neljapäev', 'Reede', 'Laupäev' ];

	function pad2( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function buildClockEl() {
		var wrap = document.createElement( 'div' );
		wrap.className = 'ds-clock ds-has-timer';
		var time = document.createElement( 'div' );
		time.className = 'ds-time';
		var date = document.createElement( 'div' );
		date.className = 'ds-date';
		wrap.appendChild( time );
		wrap.appendChild( date );

		function tick() {
			var now = new Date();
			time.textContent = pad2( now.getHours() ) + ':' + pad2( now.getMinutes() );
			date.textContent = ET_WEEKDAYS[ now.getDay() ] + ', ' + pad2( now.getDate() ) + '.' + pad2( now.getMonth() + 1 ) + '.' + now.getFullYear();
		}
		tick();
		var interval = setInterval( tick, 1000 );
		wrap.dataset.dsTimerKind = 'interval';
		wrap.dataset.dsTimerId = String( interval );
		return wrap;
	}

	/**
	 * Stops any interval/requestAnimationFrame loop started by an element this
	 * player created (clock ticks, carousel animation) before it's
	 * removed from the DOM — otherwise those loops keep running invisibly.
	 */
	function stopTimers( container ) {
		container.querySelectorAll( '.ds-has-timer' ).forEach( function ( el ) {
			if ( el.dsCleanup ) {
				el.dsCleanup();
			}
			var id = Number( el.dataset.dsTimerId );
			if ( ! id ) {
				return;
			}
			if ( 'raf' === el.dataset.dsTimerKind ) {
				cancelAnimationFrame( id );
			} else {
				clearInterval( id );
			}
		} );
	}

	function buildInfiniteSliderEl( items ) {
		var settings = items[0] || {};
		return buildContinuousImageSlider(
			items.map( function ( item ) { return item.src; } ),
			{
				className: 'ds-infinite-slider',
				verticalSpacing: settings.slider_vertical_spacing,
				horizontalSpacing: settings.slider_horizontal_spacing,
				speed: settings.slider_speed,
				borderRadius: settings.slider_border_radius,
			}
		);
	}

	function buildSlidingCarouselEl( item ) {
		return buildContinuousImageSlider(
			item.images || [],
			{
				className: 'ds-infinite-scroll-gallery',
				background: item.bg_color || '#000',
				verticalSpacing: item.spacing,
				horizontalSpacing: item.spacing,
				speed: item.speed,
				borderRadius: 0,
			}
		);
	}

	/**
	 * Dependency-free equivalent of the Motion Primitives Infinite Slider:
	 * repeat one logical sequence until the viewport is covered, then move by
	 * exactly one sequence length for a seamless continuous loop.
	 */
	function buildContinuousImageSlider( images, options ) {
		var wrap = document.createElement( 'div' );
		wrap.className = ( options.className || 'ds-continuous-slider' ) + ' ds-continuous-slider ds-has-timer';
		if ( options.background ) {
			wrap.style.background = options.background;
		}

		var track = document.createElement( 'div' );
		track.className = 'ds-continuous-slider-track';
		wrap.appendChild( track );

		if ( ! images.length ) {
			return wrap;
		}

		var speed = Math.max( 5, Number( options.speed ) || 60 ); // px/second
		var borderRadius = Math.max( 0, Number( options.borderRadius ) || 0 );
		var position = 0;
		var loopLength = 0;
		var lastFrame = null;
		var renderedCopies = 0;
		var portrait = null;
		var resizeObserver = null;
		var resizeHandler = null;

		function appendCopy() {
			images.forEach( function ( src ) {
				var img = document.createElement( 'img' );
				img.alt = '';
				img.style.borderRadius = borderRadius + 'px';
				img.addEventListener( 'load', measure, { once: true } );
				img.src = src;
				track.appendChild( img );
			} );
			renderedCopies++;
		}

		function measure() {
			var measuredPortrait = wrap.clientHeight > wrap.clientWidth;
			if ( ! wrap.clientHeight || ! wrap.clientWidth ) {
				measuredPortrait = 'portrait' === document.documentElement.getAttribute( 'data-ds-orientation' );
			}
			if ( portrait !== measuredPortrait ) {
				portrait = measuredPortrait;
				position = 0;
				track.style.transform = 'translate3d(0,0,0)';
			}

			track.className = 'ds-continuous-slider-track ' + ( portrait ? 'ds-continuous-vertical' : 'ds-continuous-horizontal' );
			var spacing = portrait
				? options.verticalSpacing
				: options.horizontalSpacing;
			spacing = Math.max( 0, Number( spacing ) || 0 );
			track.style.gap = spacing + 'px';

			var firstSequence = Array.prototype.slice.call( track.children, 0, images.length );
			var contentLength = firstSequence.reduce( function ( total, img ) {
				var rect = img.getBoundingClientRect();
				return total + ( portrait ? rect.height : rect.width );
			}, 0 );
			if ( contentLength <= 0 ) {
				return;
			}

			loopLength = contentLength + ( spacing * images.length );
			var viewportLength = portrait ? wrap.clientHeight : wrap.clientWidth;
			var requiredCopies = Math.max( 2, Math.ceil( ( viewportLength + loopLength ) / loopLength ) + 1 );
			while ( renderedCopies < requiredCopies ) {
				appendCopy();
			}
		}

		appendCopy();
		appendCopy();

		// Re-measure on image load and whenever the zone changes size/orientation.
		if ( 'ResizeObserver' in window ) {
			resizeObserver = new ResizeObserver( measure );
			resizeObserver.observe( wrap );
		} else {
			resizeHandler = measure;
			window.addEventListener( 'resize', resizeHandler );
		}
		wrap.dsCleanup = function () {
			if ( resizeObserver ) {
				resizeObserver.disconnect();
			}
			if ( resizeHandler ) {
				window.removeEventListener( 'resize', resizeHandler );
			}
		};
		measure();

		function frame( now ) {
			if ( null === lastFrame ) {
				lastFrame = now;
			}
			var dt = ( now - lastFrame ) / 1000;
			lastFrame = now;

			if ( loopLength > 0 ) {
				position -= speed * dt;
				if ( position <= -loopLength ) {
					position += loopLength;
				}
				track.style.transform = portrait
					? 'translate3d(0,' + position + 'px,0)'
					: 'translate3d(' + position + 'px,0,0)';
			}

			var id = requestAnimationFrame( frame );
			wrap.dataset.dsTimerId = String( id );
		}
		wrap.dataset.dsTimerKind = 'raf';
		wrap.dataset.dsTimerId = String( requestAnimationFrame( frame ) );

		return wrap;
	}

	function buildWeatherEl( item ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'ds-weather';
		wrap.textContent = item.location || '';

		if ( item.api_key && item.location ) {
			fetch( 'https://api.openweathermap.org/data/2.5/weather?q=' + encodeURIComponent( item.location ) + '&units=metric&appid=' + encodeURIComponent( item.api_key ) )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data && data.main ) {
						wrap.innerHTML = '<div style="font-size:6vw">' + Math.round( data.main.temp ) + '&deg;C</div><div>' + ( data.weather && data.weather[0] ? data.weather[0].description : '' ) + '</div><div>' + ( item.location || '' ) + '</div>';
					}
				} )
				.catch( function () { /* keep the plain location label on failure */ } );
		}

		return wrap;
	}

	function buildRssEl( item ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'ds-rss';
		var track = document.createElement( 'div' );
		track.className = 'ds-rss-track';
		track.textContent = item.title || '';
		wrap.appendChild( track );

		if ( item.feed_url ) {
			// Uses the WordPress REST proxy pattern is avoided here for simplicity; fetch directly
			// (feed URL should be a JSON/RSS endpoint that permits CORS, or same-origin).
			fetch( item.feed_url )
				.then( function ( r ) { return r.text(); } )
				.then( function ( text ) {
					try {
						var xml = new window.DOMParser().parseFromString( text, 'text/xml' );
						var titles = Array.prototype.slice.call( xml.querySelectorAll( 'item > title, entry > title' ) ).slice( 0, 10 ).map( function ( n ) { return n.textContent; } );
						if ( titles.length ) {
							track.textContent = titles.join( '   •   ' );
						}
					} catch ( e ) { /* keep fallback title */ }
				} )
				.catch( function () { /* offline/CORS — keep fallback title */ } );
		}

		return wrap;
	}

	function preload( item ) {
		if ( ! item ) {
			return;
		}
		if ( 'image' === item.type && item.src ) {
			var img = new Image();
			img.src = item.src;
		} else if ( 'video' === item.type && item.src ) {
			var video = document.createElement( 'video' );
			video.preload = 'auto';
			video.src = item.src;
			video.muted = true;
			video.style.display = 'none';
		}
	}

	function renderSlide( zoneName, index ) {
		var zone = state.zones[ zoneName ];
		if ( ! zone || ! zone.items.length ) {
			return;
		}

		var container = zoneEl( zoneName );
		var item = zone.items[ index ];

		// Fade previous slide out, current slide in; keep max 2 elements in DOM for perf.
		var newEl = buildSlideEl( item );
		container.appendChild( newEl );

		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				newEl.classList.add( 'ds-active' );
			} );
		} );

		var previous = Array.prototype.filter.call( container.querySelectorAll( '.ds-slide' ), function ( slideEl ) {
			return slideEl !== newEl;
		} );
		previous.forEach( function ( prevEl ) {
			prevEl.classList.remove( 'ds-active' );
			prevEl.classList.add( 'ds-prev' );
			setTimeout( function () {
				stopTimers( prevEl );
				prevEl.remove();
			}, 700 );
		} );

		logProofOfPlay( zoneName, item );

		var nextIndex = ( index + 1 ) % zone.items.length;
		preload( zone.items[ nextIndex ] );

		var video = newEl.querySelector( 'video' );
		if ( 'video' === item.type && video && 'full_length' === item.play_mode ) {
			video.addEventListener( 'ended', function () {
				advanceZone( zoneName );
			}, { once: true } );
		} else {
			var duration = Math.max( 1, item.duration || 10 ) * 1000;
			zone.timer = setTimeout( function () {
				advanceZone( zoneName );
			}, duration );
		}
	}

	function advanceZone( zoneName ) {
		var zone = state.zones[ zoneName ];
		if ( ! zone || ! zone.items.length ) {
			return;
		}
		zone.index = ( zone.index + 1 ) % zone.items.length;
		renderSlide( zoneName, zone.index );
	}

	function logProofOfPlay( zoneName, item ) {
		if ( ! state.playlist ) {
			return;
		}
		apiPost( '/proof', {
			channel_id: state.playlist.channel_id,
			slide_id: item.id,
			zone: zoneName,
			duration_seconds: item.duration || 0,
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Heartbeat                                                          */
	/* ---------------------------------------------------------------- */

	function sendHeartbeat() {
		apiPost( '/heartbeat', {
			resolution: window.screen.width + 'x' + window.screen.height,
			orientation: detectOrientation(),
			user_agent: navigator.userAgent,
			channel_id: state.playlist ? state.playlist.channel_id : 0,
			app_version: 'player-js',
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Boot                                                               */
	/* ---------------------------------------------------------------- */

	function boot() {
		if ( ! CONFIG.isPreview ) {
			initFullscreen();
		}

		// Show cached content immediately if we have it, then fetch fresh in the background.
		var cached = ! CONFIG.isPreview ? loadCachedPlaylist() : null;
		if ( cached ) {
			applyPlaylist( cached );
		}

		fetchPlaylist();

		var pollMs = Math.max( 10, CONFIG.pollInterval || 60 ) * 1000;
		setInterval( fetchPlaylist, pollMs );

		if ( ! CONFIG.isPreview ) {
			sendHeartbeat();
			var hbMs = Math.max( 10, CONFIG.heartbeatInterval || 30 ) * 1000;
			setInterval( sendHeartbeat, hbMs );
		}

		window.addEventListener( 'online', function () { setOffline( false ); fetchPlaylist(); } );
		window.addEventListener( 'offline', function () { setOffline( true ); } );
		window.addEventListener( 'resize', function () {
			if ( state.playlist && ( ! state.playlist.orientation || 'auto' === state.playlist.orientation ) ) {
				document.documentElement.setAttribute( 'data-ds-orientation', detectOrientation() );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
