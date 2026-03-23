/**
 * Google Chat Notifier — Admin UI (gfgc-settings.js)
 *
 * - Subtitle textarea → single-line appearance
 * - Media Library picker for Card Icon URL
 * - Real-time URL validation
 * - Live HTML encoding for TinyMCE body (so GF validator never sees < >)
 * - gfmergetag TinyMCE toolbar button (populated from gfgc_settings.merge_tags)
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

		// ── gfmergetag TinyMCE toolbar button ─────────────────────────────────
		// Registered when the editor is first created. Populates a dropdown
		// from gfgc_settings.merge_tags (localized by PHP) so the user can
		// insert {Field:id} tags at cursor position without leaving the editor.
		if ( typeof tinyMCE !== 'undefined' ) {
			tinyMCE.on( 'AddEditor', function ( e ) {
				if ( e.editor.id !== 'gfgc_message_body' ) return;
				var editor = e.editor;

				// Live-encode on every content change.
				editor.on( 'change input NodeChange keyup', function () {
					syncEditorToHidden();
				} );

				// Register custom merge-tag menu button.
				var mergeTags = ( typeof gfgc_settings !== 'undefined' && gfgc_settings.merge_tags )
					? gfgc_settings.merge_tags
					: [];

				var menuItems = [];
				mergeTags.forEach( function ( tag ) {
					if ( ! tag.value ) {
						// Separator / group header — use disabled item as visual divider.
						menuItems.push( {
							text    : tag.text,
							disabled: true,
						} );
					} else {
						menuItems.push( {
							text   : tag.text,
							value  : tag.value,
							onclick: function () {
								editor.insertContent( this.settings.value );
								syncEditorToHidden();
							},
						} );
					}
				} );

				editor.addButton( 'gfmergetag', {
					type   : 'menubutton',
					text   : '{: }',
					icon   : false,
					tooltip: 'Insert Merge Tag',
					menu   : menuItems,
				} );
			} );
		}

		// Safety net: sync on save click.
		$( document ).on( 'click', '[data-js="gform_save_feed"], .gform-settings-save-button, #gform-settings-save', function () {
			syncEditorToHidden();
		} );

		// ── Duplicate feed links ──────────────────────────────────────────────
		// GF doesn't natively support duplicating feeds. We inject a "Duplicate"
		// link into each feed row. Try multiple selectors to cover different GF versions.
		if ( typeof gfgc_settings !== 'undefined' && gfgc_settings.dup_nonce ) {

			function injectDuplicateLinks( $rows ) {
				$rows.each( function () {
					var $row = $( this );
					if ( $row.data( 'gfgc-dup-injected' ) ) return;
					$row.data( 'gfgc-dup-injected', true );

					var $editLink = $row.find( 'a[href*="fid="]' ).first();
					if ( ! $editLink.length ) return;

					var href     = $editLink.attr( 'href' );
					var fidMatch = href.match( /[?&]fid=(\d+)/ );
					if ( ! fidMatch ) return;

					var feedId = fidMatch[1];
					var formId = gfgc_settings.form_id || ( href.match( /[?&]id=(\d+)/ ) || [] )[1] || '';

					var dupUrl = gfgc_settings.admin_url +
						'admin.php?page=gf_edit_forms&view=settings&subview=gf-google-chat&id=' + formId +
						'&gfgc_action=duplicate_feed&fid=' + feedId +
						'&form_id=' + formId +
						'&_wpnonce=' + gfgc_settings.dup_nonce;

					var $dupLink = $( '<a href="' + dupUrl + '" style="margin-left:8px;">Duplicate</a>' );

					// Try inserting into row-actions area; fall back to after edit link.
					var $rowActions = $row.find( '.row-actions' );
					if ( $rowActions.length ) {
						$rowActions.append( $( '<span> | </span>' ) ).append( $dupLink );
					} else {
						$editLink.closest( 'td, .gform-settings-field' ).append(
							$( '<span style="margin-left:8px;"> | </span>' )
						).append( $dupLink );
					}
				} );
			}

			// Try immediately, then after a short delay for React-rendered tables.
			function tryInjectLinks() {
				injectDuplicateLinks( $( 'tr' ).filter( function () {
					return $( this ).find( 'a[href*="fid="]' ).length > 0;
				} ) );
			}

			tryInjectLinks();
			setTimeout( tryInjectLinks, 800 );
		}

	} );
} )( jQuery );
