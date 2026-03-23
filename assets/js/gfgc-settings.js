/**
 * Google Chat Notifier — Admin UI (gfgc-settings.js)
 */

// ── gfgcSetupEditor must be defined at script-load time (NOT inside document.ready)
// so TinyMCE can call it synchronously during editor initialization (before toolbar renders).
window.gfgcSetupEditor = function ( editor ) {
	if ( editor.id !== 'gfgc_message_body' ) return;

	var mergeTags = ( typeof gfgc_settings !== 'undefined' && gfgc_settings.merge_tags )
		? gfgc_settings.merge_tags
		: [];

	var menuItems = [];
	mergeTags.forEach( function ( tag ) {
		if ( ! tag.value ) {
			menuItems.push( { text: tag.text, disabled: true } );
		} else {
			menuItems.push( {
				text   : tag.text,
				value  : tag.value,
				onclick: function () {
					editor.insertContent( this.settings.value );
					if ( typeof window.gfgcSyncEditorToHidden === 'function' ) {
						window.gfgcSyncEditorToHidden();
					}
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

	// Live-encode on every content change.
	editor.on( 'change input NodeChange keyup', function () {
		if ( typeof window.gfgcSyncEditorToHidden === 'function' ) {
			window.gfgcSyncEditorToHidden();
		}
	} );
};

( function ( $ ) {
	'use strict';

	// ── Live encoding helper (global so gfgcSetupEditor can call it) ──────────
	window.gfgcSyncEditorToHidden = function () {
		var editor  = typeof tinyMCE !== 'undefined' ? tinyMCE.get( 'gfgc_message_body' ) : null;
		var $hidden = $( '#gfgc_body_encoded' );
		if ( editor && $hidden.length ) {
			var encoded = editor.getContent()
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' );
			$hidden.val( encoded );
		}
	};

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

		// Safety net: sync on save click.
		$( document ).on( 'click', '[data-js="gform_save_feed"], .gform-settings-save-button, #gform-settings-save', function () {
			window.gfgcSyncEditorToHidden();
		} );

		// ── Duplicate feed links ──────────────────────────────────────────────
		if ( typeof gfgc_settings !== 'undefined' && gfgc_settings.dup_nonce ) {

			function injectDuplicateLinks() {
				$( 'tr' ).filter( function () {
					return ! $( this ).data( 'gfgc-dup' ) && $( this ).find( 'a[href*="fid="]' ).length;
				} ).each( function () {
					var $row = $( this );
					$row.data( 'gfgc-dup', true );

					var $editLink = $row.find( 'a[href*="fid="]' ).first();
					var href      = $editLink.attr( 'href' );
					var fidMatch  = href.match( /[?&]fid=(\d+)/ );
					if ( ! fidMatch ) return;

					var feedId = fidMatch[1];
					var formId = gfgc_settings.form_id || ( ( href.match( /[?&]id=(\d+)/ ) || [] )[1] ) || '';

					var dupUrl = gfgc_settings.admin_url +
						'admin.php?page=gf_edit_forms&view=settings&subview=gf-google-chat&id=' + formId +
						'&gfgc_action=duplicate_feed&fid=' + feedId +
						'&form_id=' + formId +
						'&_wpnonce=' + gfgc_settings.dup_nonce;

					var $dup = $( '<a href="' + dupUrl + '">Duplicate</a>' );

					var $rowActions = $row.find( '.row-actions' );
					if ( $rowActions.length ) {
						$rowActions.append( $( '<span> | </span>' ) ).append( $dup );
					} else {
						$editLink.after( $( '<span> | </span>' ) ).after( $dup );
					}
				} );
			}

			injectDuplicateLinks();
			setTimeout( injectDuplicateLinks, 800 );
		}

	} );
} )( jQuery );
