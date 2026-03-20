/**
 * Google Chat Notifier — Admin UI
 * Adds a WordPress Media Library picker to the Card Icon URL field.
 * Adds real-time URL validation to all URL fields.
 */
( function ( $ ) {
	'use strict';

	// ── Media Library picker for Card Icon URL ────────────────────────────────
	$( document ).ready( function () {
		var $input   = $( 'input[name="_gform_setting_card_icon_url"]' );
		if ( $input.length ) {
			var $btn     = $( '<button type="button" class="button" style="margin-left:6px;">📁 Select Image</button>' );
			var $preview = $( '<span id="gfgc-icon-preview" style="display:inline-block;margin-left:10px;vertical-align:middle;"></span>' );

			$input.after( $preview ).after( $btn );

			function updatePreview( url ) {
				if ( url ) {
					$preview.html( '<img src="' + url + '" style="height:40px;width:40px;object-fit:cover;border-radius:50%;border:1px solid #ddd;" />' );
				} else {
					$preview.html( '' );
				}
			}
			updatePreview( $input.val() );
			$input.on( 'input', function () { updatePreview( $( this ).val() ); } );

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
		}

		// ── Real-time URL validation ──────────────────────────────────────────
		// Targets webhook URL, card icon URL, and all 5 button URL fields.
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

		// ── Formatting toolbar for Card Body ─────────────────────────────────
		var $body = $( 'textarea[name="_gform_setting_message_body"]' );
		if ( $body.length ) {
			var toolbarHtml =
				'<div class="gfgc-toolbar" style="display:flex;gap:4px;margin-bottom:4px;">' +
				'<button type="button" class="button gfgc-fmt" data-open="**" data-close="**" title="Bold (**text**)"><b>B</b></button>' +
				'<button type="button" class="button gfgc-fmt" data-open="_" data-close="_" title="Italic (_text_)"><i>I</i></button>' +
				'<button type="button" class="button gfgc-fmt" data-open="__" data-close="__" title="Underline (__text__)"><u>U</u></button>' +
				'<button type="button" class="button gfgc-fmt" data-open="~~" data-close="~~" title="Strikethrough (~~text~~)"><s>S</s></button>' +
				'<button type="button" class="button gfgc-fmt" data-open="[" data-close="](https://)" title="Link ([text](url))">🔗</button>' +
				'</div>' +
				'<p style="font-size:11px;color:#666;margin:0 0 4px;">' +
				'Formatting: <code>**bold**</code> &nbsp;|&nbsp; <code>_italic_</code> &nbsp;|&nbsp; <code>__underline__</code> &nbsp;|&nbsp; <code>~~strike~~</code>' +
				'</p>';

			$body.before( toolbarHtml );

			$( '.gfgc-fmt' ).on( 'click', function () {
				var open  = $( this ).data( 'open' );
				var close = $( this ).data( 'close' );
				var el    = $body[0];
				var start = el.selectionStart;
				var end   = el.selectionEnd;
				var sel   = el.value.substring( start, end ) || 'text';
				var replacement = open + sel + close;
				el.value = el.value.substring( 0, start ) + replacement + el.value.substring( end );
				// Restore cursor inside the inserted markers.
				var newStart = start + open.length;
				var newEnd   = newStart + sel.length;
				el.setSelectionRange( newStart, newEnd );
				el.focus();
			} );
		}
	} );
} )( jQuery );
