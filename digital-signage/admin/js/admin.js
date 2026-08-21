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
