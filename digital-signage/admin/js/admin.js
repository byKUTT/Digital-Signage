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
