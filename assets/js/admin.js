/**
 * Google Chat Notifier — Admin UI
 * Adds a WordPress Media Library picker to the Card Icon URL field.
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		var $input   = $( 'input[name="_gform_setting_card_icon_url"]' );
		if ( ! $input.length ) return;

		// Append "Select Image" button and a small live preview next to the field.
		var $btn = $( '<button type="button" class="button" style="margin-left:6px;">📁 Select Image</button>' );
		var $preview = $( '<span id="gfgc-icon-preview" style="display:inline-block;margin-left:10px;vertical-align:middle;"></span>' );

		$input.after( $preview ).after( $btn );

		// Show preview for any existing URL.
		function updatePreview( url ) {
			if ( url ) {
				$preview.html( '<img src="' + url + '" style="height:40px;width:40px;object-fit:cover;border-radius:50%;border:1px solid #ddd;" />' );
			} else {
				$preview.html( '' );
			}
		}
		updatePreview( $input.val() );
		$input.on( 'input', function () { updatePreview( $( this ).val() ); } );

		// Open the WP Media Library when the button is clicked.
		$btn.on( 'click', function ( e ) {
			e.preventDefault();

			var mediaUploader = wp.media( {
				title   : 'Select Card Icon',
				button  : { text: 'Use this image' },
				multiple: false,
				library : { type: 'image' },
			} );

			mediaUploader.on( 'select', function () {
				var attachment = mediaUploader.state().get( 'selection' ).first().toJSON();
				$input.val( attachment.url ).trigger( 'input' );
			} );

			mediaUploader.open();
		} );
	} );
} )( jQuery );
