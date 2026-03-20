/**
 * Google Chat Notifier — Admin UI
 *
 * - WordPress Media Library picker for Card Icon URL field
 * - Real-time URL validation for webhook + button URL fields
 * - TinyMCE sync before GF's AJAX save (keeps WP Editor content)
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {

		// ── Subtitle: constrain textarea to single-line height (same look as title) ──
		$( 'textarea[name="_gform_setting_notification_subtitle"]' ).css( {
			height: '32px', minHeight: '32px', maxHeight: '32px',
			resize: 'none', overflow: 'hidden', lineHeight: '20px', padding: '5px 8px',
		} );

		var $input = $( 'input[name="_gform_setting_card_icon_url"]' );
		if ( $input.length ) {
			var $btn     = $( '<button type="button" class="button" style="margin-left:6px;">📁 Select Image</button>' );
			var $preview = $( '<span id="gfgc-icon-preview" style="display:inline-block;margin-left:10px;vertical-align:middle;"></span>' );

			$input.after( $preview ).after( $btn );

			function updatePreview( url ) {
				$preview.html( url ? '<img src="' + url + '" style="height:40px;width:40px;object-fit:cover;border-radius:50%;border:1px solid #ddd;" />' : '' );
			}
			updatePreview( $input.val() );
			$input.on( 'input', function () { updatePreview( $( this ).val() ); } );

			$btn.on( 'click', function ( e ) {
				e.preventDefault();
				var frame = wp.media( {
					title  : 'Select Card Icon',
					button : { text: 'Use this image' },
					multiple: false,
					library : { type: 'image' },
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					$input.val( attachment.url ).trigger( 'input' );
				} );
				frame.open();
			} );
		}

		// ── Real-time URL validation ──────────────────────────────────────────
		// Shows a red border + warning if a URL field doesn't start with https://
		var urlFieldSelectors = [
			'input[name="_gform_setting_webhook_url"]',
			'input[name="_gform_setting_card_icon_url"]',
			'input[name="_gform_setting_btn1_url"]',
			'input[name="_gform_setting_btn2_url"]',
			'input[name="_gform_setting_btn3_url"]',
			'input[name="_gform_setting_btn4_url"]',
			'input[name="_gform_setting_btn5_url"]',
		].join( ', ' );

		function validateUrl( $field ) {
			var val = $.trim( $field.val() );
			$field.siblings( '.gfgc-url-error' ).remove();

			if ( val === '' ) {
				$field.css( 'border-color', '' );
				return;
			}

			if ( ! /^https?:\/\/.+/i.test( val ) ) {
				$field.css( 'border-color', '#d63638' );
				$field.after(
					'<p class="gfgc-url-error" style="color:#d63638;margin:4px 0 0;font-size:12px;">' +
					'⚠️ URL must start with <strong>https://</strong> — fix this or the notification card will be empty.' +
					'</p>'
				);
			} else {
				$field.css( 'border-color', '' );
			}
		}

		$( document ).on( 'blur change', urlFieldSelectors, function () {
			validateUrl( $( this ) );
		} );

		// ── Sync TinyMCE before GF saves the feed ────────────────────────────
		// GF saves feed settings via AJAX. TinyMCE doesn't auto-sync to the
		// underlying textarea on AJAX submissions, so we trigger it manually
		// when the user clicks any GF save button.
		$( document ).on( 'click', '[data-js="gform_save_feed"], .gform-settings-save-button, #gform-settings-save', function () {
			if ( typeof tinyMCE !== 'undefined' ) {
				tinyMCE.triggerSave();
			}
		} );

	} );
} )( jQuery );
