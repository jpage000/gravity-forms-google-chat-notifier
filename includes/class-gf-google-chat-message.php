<?php
/**
 * Google Chat Cards v2 message builder.
 *
 * Responsible for:
 *  - Replacing GF merge tags in title / body text.
 *  - Building the full Cards v2 JSON payload.
 *  - Returning the payload array ready for wp_remote_post().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GF_Google_Chat_Message {

    /** @var array  Gravity Forms form array */
    private $form;

    /** @var array  Gravity Forms entry array */
    private $entry;

    /** @var array  Parsed feed settings */
    private $settings;

    /**
     * @param array $form     GF form array.
     * @param array $entry    GF entry array.
     * @param array $settings Feed settings from GFFeedAddOn.
     */
    public function __construct( array $form, array $entry, array $settings ) {
        $this->form     = $form;
        $this->entry    = $entry;
        $this->settings = $settings;
    }

    /**
     * Build and return the Cards v2 JSON payload as an associative array.
     *
     * @return array
     */
    public function build(): array {
        $title    = $this->merge( $this->settings['notification_title'] ?? 'New Form Submission' );
        // Subtitle: user-defined, supports merge tags, empty = hidden
        $raw_sub  = $this->settings['notification_subtitle'] ?? 'Form: {form_title}  •  Entry #{entry_id}';
        $subtitle = $this->merge( $raw_sub );
        $body     = $this->merge( $this->settings['message_body'] ?? '' );

        // Build widget list.
        $widgets = [];

        // Body paragraph — build Chat-compatible HTML from TinyMCE or raw body.
        if ( $body !== '' ) {
            // 1. Convert any markdown shortcuts someone may have typed (**bold** etc.)
            $body = $this->markdown_to_html( $body );
            // 2. Convert block-level HTML from TinyMCE to flat Chat-compatible markup.
            $body = $this->html_to_chat( $body );
            if ( $body !== '' ) {
                $widgets[] = [
                    'textParagraph' => [
                        'text' => $body,
                    ],
                ];
            }
        }

        // Collect buttons.
        $buttons = $this->build_buttons();
        if ( ! empty( $buttons ) ) {
            $widgets[] = [
                'buttonList' => [
                    'buttons' => $buttons,
                ],
            ];
        }

        $card = [
            'cardsV2' => [
                [
                    'cardId' => 'gf-gchat-' . uniqid(),
                    'card'   => [
                        'header' => [
                            'title'    => $title,
                            'subtitle' => $subtitle,
                            'imageUrl' => ! empty( $this->settings['card_icon_url'] )
                                ? esc_url_raw( $this->settings['card_icon_url'] )
                                : 'https://www.gstatic.com/images/icons/material/system/2x/assignment_turned_in_black_48dp.png',
                            'imageType' => 'CIRCLE',
                        ],
                        'sections' => [
                            [
                                'collapsible'         => false,
                                'widgets'             => $widgets,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $card;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Replace GF merge tags in a string.
     */
    private function merge( string $text ): string {
        if ( trim( $text ) === '' ) {
            return '';
        }
        return GFCommon::replace_variables( $text, $this->form, $this->entry, false, false, false, 'text' );
    }

    /**
     * Convert markdown-style shortcuts to HTML for Google Chat Cards v2.
     *
     * Supported shortcuts (safe to type in any textarea without triggering
     * GF/WordPress HTML sanitization):
     *   **text**   → <b>text</b>   (bold)
     *   _text_     → <i>text</i>   (italic)
     *   ~~text~~   → <s>text</s>   (strikethrough)
     *   __text__   → <u>text</u>   (underline)
     */
    private function markdown_to_html( string $text ): string {
        // Order matters: bold+italic before bold before italic.
        // Bold+italic: ***text*** or ___text___
        $text = preg_replace( '/\*{3}(.+?)\*{3}/s', '<b><i>$1</i></b>', $text );
        // Bold: **text**
        $text = preg_replace( '/\*{2}(.+?)\*{2}/s', '<b>$1</b>', $text );
        // Underline: __text__  (before italic _ to avoid conflict)
        $text = preg_replace( '/_{2}(.+?)_{2}/s', '<u>$1</u>', $text );
        // Italic: _text_
        $text = preg_replace( '/(?<![a-zA-Z0-9_])_(.+?)_(?![a-zA-Z0-9_])/s', '<i>$1</i>', $text );
        // Strikethrough: ~~text~~
        $text = preg_replace( '/~{2}(.+?)~{2}/s', '<s>$1</s>', $text );
        return $text;
    }

    /**
     * Convert block-level HTML produced by TinyMCE into the flat, inline-only
     * subset that Google Chat's textParagraph widget accepts.
     *
     * Google Chat supports: <b>, <i>, <u>, <s>, <font color="">, <a href="">, <br>
     * It does NOT support: <p>, <div>, <ul>, <ol>, <li>, <h1>…<h6>, <span>, etc.
     *
     * @param  string $html Raw HTML (e.g. TinyMCE output or markdown-converted output).
     * @return string Flat HTML safe for textParagraph.text.
     */
    private function html_to_chat( string $html ): string {
        // Normalise line endings.
        $html = str_replace( [ "\r\n", "\r" ], "\n", $html );

        // Headings → bold + double line-break.
        $html = preg_replace( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', '<b>$1</b><br><br>', $html );

        // List items → bullet + single line-break (strip enclosing ul/ol later).
        $html = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', '• $1<br>', $html );

        // Block paragraphs → content + double line-break.
        $html = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', '$1<br><br>', $html );

        // Divs and other block wrappers → content + single line-break.
        $html = preg_replace( '/<(?:div|blockquote|pre|section|article)[^>]*>(.*?)<\/(?:div|blockquote|pre|section|article)>/is', '$1<br>', $html );

        // Map semantic tags to Chat equivalents.
        $html = preg_replace( '/<strong[^>]*>(.*?)<\/strong>/is', '<b>$1</b>', $html );
        $html = preg_replace( '/<em[^>]*>(.*?)<\/em>/is', '<i>$1</i>', $html );
        $html = preg_replace( '/<del[^>]*>(.*?)<\/del>/is', '<s>$1</s>', $html );
        $html = preg_replace( '/<strike[^>]*>(.*?)<\/strike>/is', '<s>$1</s>', $html );

        // Normalise self-closing <br /> → <br>.
        $html = preg_replace( '/<br\s*\/?>/i', '<br>', $html );

        // Strip remaining unsupported tags but keep their inner text.
        $allowed_tags = [
            'b'    => [],
            'i'    => [],
            'u'    => [],
            's'    => [],
            'font' => [ 'color' => [] ],
            'a'    => [ 'href' => [], 'target' => [] ],
            'br'   => [],
        ];
        $html = wp_kses( $html, $allowed_tags );

        // Collapse 3+ consecutive <br> into 2.
        $html = preg_replace( '/(<br>\s*){3,}/i', '<br><br>', $html );

        // Strip bare newline characters left over (already handled via <br>).
        $html = str_replace( "\n", '', $html );

        return trim( $html );
    }

    /**
     * Build the buttons array (Cards v2 format).
     *
     * @return array
     */
    private function build_buttons(): array {
        $buttons = [];

        // "View Entry" admin link (optional).
        if ( ! empty( $this->settings['include_entry_link'] ) ) {
            $entry_id  = absint( rgar( $this->entry, 'id' ) );
            $form_id   = absint( rgar( $this->form, 'id' ) );
            $entry_url = admin_url( sprintf( 'admin.php?page=gf_entries&view=entry&id=%d&lid=%d', $form_id, $entry_id ) );

            $buttons[] = $this->make_button( '📋 View Entry', $entry_url, true );
        }

        // Custom buttons from fixed slots 1–5.
        // Validate each URL first — esc_url_raw() rejects invalid schemes (e.g. typos
        // like "htps://"). A button with url="" causes Google Chat to reject the whole card.
        for ( $i = 1; $i <= 5; $i++ ) {
            $label = trim( rgar( $this->settings, "btn{$i}_label" ) );
            $raw   = trim( $this->merge( rgar( $this->settings, "btn{$i}_url" ) ) );
            $url   = esc_url_raw( $raw );
            if ( $label !== '' && $url !== '' ) {
                $buttons[] = $this->make_button( $label, $url );
            }
        }

        return $buttons;
    }

    /**
     * Build a single Cards v2 button element.
     *
     * @param string $label   Button label text.
     * @param string $url     URL to open.
     * @param bool   $filled  Whether to use a filled (primary) style button.
     * @return array
     */
    private function make_button( string $label, string $url, bool $filled = false ): array {
        $button = [
            'text'    => $label,
            'onClick' => [
                'openLink' => [
                    'url' => esc_url_raw( $url ),
                ],
            ],
        ];

        if ( $filled ) {
            $button['color'] = [
                'red'   => 0.0,
                'green' => 0.502,
                'blue'  => 0.502,
                'alpha' => 1.0,
            ];
        }

        return $button;
    }
}
