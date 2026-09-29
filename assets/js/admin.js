/* global jQuery, wp, cnecAdmin */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;

		$( '#cnec_placeholder_pick' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = wp.media( {
					title: cnecAdmin.chooseTitle,
					button: { text: cnecAdmin.chooseButton },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					$( '#cnec_placeholder_image' ).val( attachment.url );
				} );
			}
			frame.open();
		} );
	} );
} )( jQuery );
