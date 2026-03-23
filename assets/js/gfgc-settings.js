/**
 * Google Chat Notifier — Admin UI (gfgc-settings.js)
 *
 * - Subtitle textarea → single-line appearance
 * - Media Library picker for Card Icon URL
 * - Real-time URL validation
 * - Live HTML encoding for TinyMCE body (so GF validator never sees < >)
 * - Merge tag forwarding: gfgc_mt_anchor → TinyMCE
 * - Duplicate feed link injection in feed list
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {

		// ── Subtitle: constrain textarea to single-line height ────────────────
		$( 'textarea[name="_gform_setting_notification_subtitle"]' ).css( {
			height: '32px', minHeight: '32px', maxHeight: '32px',
			resize: 'none', overflow: 'hidden', lineHeight: '20px', padding: '5px 8px',
		} );

		// ── Media Library picker for Card Icon URL ────────────────────────────
		var $iconInput = $( 'input[name="_gform_setting_card_icon_url"]' );
		if ( $iconInput.length ) {
			var $btn     = $( '<button type="button" class="button" style="margin-left:6px;">📁 Select Image</button>' );
			var $preview = $( '<span id="gfgc-icon-preview" style="display:inline-block;margin-left:10px;vertical-align:middle;"></span>' );

			$iconInput.after( $preview ).after( $btn );

			function updatePreview( url ) {
				$preview.html( url ? '<img src="' + url + '" style="height:40px;width:40px;object-fit:cover;border-radius:50%;border:1px solid #ddd;" />' : '' );
			}
			updatePreview( $iconInput.val() );
			$iconInput.on( 'input', function () { updatePreview( $( this ).val() ); } );

			$btn.on( 'click', function ( e ) {
				e.preventDefault();
				var frame = wp.media( {
					title   : 'Select Card Icon',
					button  : { text: 'Use this image' },
					multiple: false,
					library : { type: 'image' },
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					$iconInput.val( attachment.url ).trigger( 'input' );
				} );
				frame.open();
			} );
		}

		// ── Real-time URL validation ──────────────────────────────────────────
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
			if ( val === '' ) { $field.css( 'border-color', '' ); return; }
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
		$( document ).on( 'blur change', urlFieldSelectors, function () { validateUrl( $( this ) ); } );

		// ── TinyMCE live encoding ─────────────────────────────────────────────
		// Encodes HTML entities in real-time so GF's validator never sees < >.
		function encodeForGf( str ) {
			return str.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
		}

		function syncEditorToHidden() {
			var editor  = typeof tinyMCE !== 'undefined' ? tinyMCE.get( 'gfgc_message_body' ) : null;
			var $hidden = $( '#gfgc_body_encoded' );
			if ( editor && $hidden.length ) {
				$hidden.val( encodeForGf( editor.getContent() ) );
			}
		}

		if ( typeof tinyMCE !== 'undefined' ) {
			tinyMCE.on( 'AddEditor', function ( e ) {
				if ( e.editor.id === 'gfgc_message_body' ) {
					e.editor.on( 'change input NodeChange keyup', function () {
						syncEditorToHidden();
					} );
				}
			} );
		}

		// Safety net: sync on save click too.
		$( document ).on( 'click', '[data-js="gform_save_feed"], .gform-settings-save-button, #gform-settings-save', function () {
			syncEditorToHidden();
		} );

		// ── Merge tag forwarding: gfgc_mt_anchor → TinyMCE ───────────────────
		// GF places the {:-} button next to #gfgc_mt_anchor (a positioned textarea
		// inside the Card Body field wrapper). When the user picks a tag, GF writes
		// it to that textarea. We poll and forward it to TinyMCE.
		var $mtAnchor = $( '#gfgc_mt_anchor' );
		var lastMt    = '';
		if ( $mtAnchor.length ) {
			setInterval( function () {
				var val = $mtAnchor.val();
				if ( val && val !== lastMt ) {
					lastMt = val;
					var editor = typeof tinyMCE !== 'undefined' ? tinyMCE.get( 'gfgc_message_body' ) : null;
					if ( editor ) {
						editor.insertContent( val );
						syncEditorToHidden();
					}
					$mtAnchor.val( '' );
					lastMt = '';
				}
			}, 150 );
		}

		// ── Duplicate feed links ──────────────────────────────────────────────
		if ( typeof gfgc_settings !== 'undefined' && gfgc_settings.dup_nonce ) {
			$( 'table.gform-settings-feed-list tbody tr, .gf-form-action-links tr' ).each( function () {
				var $row = $( this );
				// Find the edit/settings link that contains fid= in its href.
				var $editLink = $row.find( 'a[href*="fid="]' ).first();
				if ( ! $editLink.length ) return;

				var href      = $editLink.attr( 'href' );
				var fidMatch  = href.match( /[?&]fid=(\d+)/ );
				var formMatch = href.match( /[?&]id=(\d+)/ );
				if ( ! fidMatch ) return;

				var feedId = fidMatch[1];
				var formId = formMatch ? formMatch[1] : gfgc_settings.form_id;

				var dupUrl = gfgc_settings.admin_url +
					'admin.php?page=gf_edit_forms&view=settings&subview=gf-google-chat&id=' + formId +
					'&gfgc_action=duplicate_feed&fid=' + feedId +
					'&form_id=' + formId +
					'&_wpnonce=' + gfgc_settings.dup_nonce;

				// Append after the last link in the row's action cell.
				var $actionArea = $editLink.closest( 'td' ).find( '.row-actions' );
				if ( $actionArea.length ) {
					$actionArea.append( ' | <a href="' + dupUrl + '">Duplicate</a>' );
				} else {
					$editLink.after( ' | <a href="' + dupUrl + '">Duplicate</a>' );
				}
			} );
		}

	} );
} )( jQuery );
