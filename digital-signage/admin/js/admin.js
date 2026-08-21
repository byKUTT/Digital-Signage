/* global DS_Admin, jQuery, wp */
( function ( $ ) {
	'use strict';

	$( function () {

		/* ---- Slide type field toggling ---- */
		function toggleTypeFields() {
			var type = $( '#ds_slide_type' ).val();
			if ( ! type ) {
				return;
			}
			$( '.ds-type-field' ).removeClass( 'ds-visible' );
			$( '.ds-type-field' ).each( function () {
				var types = ( $( this ).data( 'type' ) + '' ).split( ',' );
				if ( types.indexOf( type ) !== -1 ) {
					$( this ).addClass( 'ds-visible' );
				}
			} );
		}
		$( document ).on( 'change', '#ds_slide_type', toggleTypeFields );
		toggleTypeFields();

		/* ---- Media picker for slide content ---- */
		var frame;
		$( document ).on( 'click', '#ds-select-media', function ( e ) {
			e.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = wp.media( { title: 'Select Media', multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$( '#ds_media_id' ).val( attachment.id );
				var preview = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				$( '#ds-media-preview' ).html( '<img src="' + preview + '" style="max-width:300px;height:auto" />' );
			} );
			frame.open();
		} );
		$( document ).on( 'click', '#ds-clear-media', function ( e ) {
			e.preventDefault();
			$( '#ds_media_id' ).val( '' );
			$( '#ds-media-preview' ).empty();
		} );

		/* ---- Infinite-scroll gallery: multi-image picker ---- */
		var galleryFrame;
		function addGalleryImage( attachment ) {
			var thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
			$( '#ds-scroll-images-inputs' ).append( '<input type="hidden" name="scroll_images[]" value="' + attachment.id + '" />' );
			$( '#ds-scroll-gallery-preview' ).append(
				'<li data-id="' + attachment.id + '"><img src="' + thumb + '" alt="" /><button type="button" class="ds-scroll-gallery-remove" aria-label="Remove image">&times;</button></li>'
			);
		}
		$( document ).on( 'click', '#ds-select-gallery', function ( e ) {
			e.preventDefault();
			if ( galleryFrame ) {
				galleryFrame.open();
				return;
			}
			galleryFrame = wp.media( { title: 'Select Images', multiple: true, library: { type: 'image' } } );
			galleryFrame.on( 'select', function () {
				galleryFrame.state().get( 'selection' ).map( function ( a ) { return a.toJSON(); } ).forEach( addGalleryImage );
			} );
			galleryFrame.open();
		} );
		$( document ).on( 'click', '.ds-scroll-gallery-remove', function () {
			var $li = $( this ).closest( 'li' );
			var id = $li.data( 'id' );
			$( '#ds-scroll-images-inputs input[value="' + id + '"]' ).remove();
			$li.remove();
		} );
		if ( $.fn.sortable ) {
			$( '#ds-scroll-gallery-preview' ).sortable( {
				update: function () {
					var inputs = $( '#ds-scroll-images-inputs' );
					inputs.empty();
					$( '#ds-scroll-gallery-preview li' ).each( function () {
						inputs.append( '<input type="hidden" name="scroll_images[]" value="' + $( this ).data( 'id' ) + '" />' );
					} );
				},
			} );
		}

		/* ---- Drag-and-drop playlist reordering ---- */
		if ( $.fn.sortable ) {
			$( '#ds-sortable-playlist' ).sortable( {
				handle: '.ds-drag-handle',
				update: function () {
					$( '#ds-sortable-playlist .ds-sortable-item' ).each( function ( index ) {
						$( this ).find( '.ds-order-input' ).val( index * 10 );
					} );
				},
			} );
		}

		/* ---- Schedule: recurring vs one-off toggle ---- */
		function toggleScheduleType() {
			var oneOff = $( '.ds-schedule-type:checked' ).val() === '1';
			$( '#ds_is_one_off' ).val( oneOff ? '1' : '' );
			$( '.ds-recurring-only' ).toggle( ! oneOff );
		}
		$( document ).on( 'change', '.ds-schedule-type', toggleScheduleType );
		toggleScheduleType();

		/* ---- Pairing screen: generate a token/URL ---- */
		$( document ).on( 'click', '#ds-generate-token', function ( e ) {
			e.preventDefault();
			var token = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace( /x/g, function () {
				return ( Math.random() * 36 | 0 ).toString( 36 );
			} );
			var url = window.location.origin + '/signage/play/' + token + '/';
			$( '#ds-generated-url' ).html( '<a href="' + url + '" target="_blank">' + url + '</a>' );
		} );

		/* ---- Pairing screen: scan the QR code with the camera instead of
		   typing the 6-character code by hand. Uses the browser's built-in
		   BarcodeDetector (Shape Detection API, no external library/CDN needed)
		   — Chrome/Edge support it; browsers that don't just keep the "type it
		   in" flow, since the button only appears when scanning is actually
		   possible. */
		( function () {
			var $scanBtn   = $( '#ds-scan-qr' );
			var $hint      = $( '#ds-scan-hint' );
			var $modal     = $( '#ds-scan-modal' );
			var $status    = $( '#ds-scan-status' );
			var video      = document.getElementById( 'ds-scan-video' );
			var stream     = null;
			var detector   = null;
			var scanning   = false;

			if ( ! $scanBtn.length ) {
				return;
			}

			if ( ! window.BarcodeDetector ) {
				$hint.text( __ds_scan_unsupported() );
				return;
			}
			if ( ! ( navigator.mediaDevices && navigator.mediaDevices.getUserMedia ) ) {
				$hint.text( __ds_scan_unsupported() );
				return;
			}

			function __ds_scan_unsupported() {
				return 'Camera scanning isn’t supported in this browser — enter the code manually below.';
			}

			// Formats vary by BarcodeDetector implementation; ask for the ones
			// that matter here and let the browser ignore ones it doesn't know.
			try {
				detector = new window.BarcodeDetector( { formats: [ 'qr_code' ] } );
			} catch ( e ) {
				$hint.text( __ds_scan_unsupported() );
				return;
			}

			$scanBtn.prop( 'hidden', false );

			function extractCode( text ) {
				if ( ! text ) { return null; }
				// The QR encodes the full "Pair a Screen" admin URL with ?code=;
				// also accept a bare 6-character code in case it's scanned from
				// somewhere else.
				var match = text.match( /[?&]code=([A-Za-z0-9]{4,8})/ );
				if ( match ) { return match[ 1 ].toUpperCase(); }
				var bare = text.trim().toUpperCase();
				return /^[A-Z0-9]{4,8}$/.test( bare ) ? bare : null;
			}

			function stopScan() {
				scanning = false;
				if ( stream ) {
					stream.getTracks().forEach( function ( t ) { t.stop(); } );
					stream = null;
				}
				video.srcObject = null;
				$modal.prop( 'hidden', true );
			}

			function scanFrame() {
				if ( ! scanning ) { return; }
				detector.detect( video )
					.then( function ( codes ) {
						if ( codes && codes.length ) {
							var found = extractCode( codes[ 0 ].rawValue );
							if ( found ) {
								$( '#ds_code' ).val( found );
								$status.text( 'Code scanned: ' + found );
								stopScan();
								$( '#ds_screen_name' ).trigger( 'focus' );
								return;
							}
						}
						requestAnimationFrame( scanFrame );
					} )
					.catch( function () {
						requestAnimationFrame( scanFrame );
					} );
			}

			$scanBtn.on( 'click', function () {
				$status.text( 'Point the camera at the QR code on the display.' );
				$modal.prop( 'hidden', false );
				navigator.mediaDevices.getUserMedia( { video: { facingMode: 'environment' } } )
					.then( function ( s ) {
						stream = s;
						video.srcObject = s;
						scanning = true;
						requestAnimationFrame( scanFrame );
					} )
					.catch( function () {
						$status.text( 'Couldn’t access the camera — check the browser’s permission prompt, or enter the code manually.' );
					} );
			} );

			$( '#ds-scan-close' ).on( 'click', stopScan );
			$modal.on( 'click', function ( e ) {
				if ( e.target === $modal[ 0 ] ) { stopScan(); }
			} );
		} )();

		/* ---- Live screen status refresh via WP Heartbeat API ---- */
		$( document ).on( 'heartbeat-send', function ( e, data ) {
			if ( $( '.ds-screens-table' ).length ) {
				data.ds_dashboard = true;
			}
		} );
		$( document ).on( 'heartbeat-tick', function ( e, data ) {
			if ( ! data.ds_screen_statuses ) {
				return;
			}
			$.each( data.ds_screen_statuses, function ( screenId, row ) {
				var $tr = $( 'input[value="' + screenId + '"]' ).closest( 'tr' );
				if ( ! $tr.length ) {
					return;
				}
				$tr.find( '.ds-badge' )
					.attr( 'class', 'ds-badge ds-badge-' + row.status )
					.text( row.status.charAt( 0 ).toUpperCase() + row.status.slice( 1 ) );
			} );
		} );

	} );
} )( jQuery );
