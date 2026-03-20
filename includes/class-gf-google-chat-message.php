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
        $subtitle = sprintf(
            'Form: %s  •  Entry #%d',
            esc_html( rgar( $this->form, 'title' ) ),
            absint( rgar( $this->entry, 'id' ) )
        );
        $body = $this->merge( $this->settings['message_body'] ?? '' );

        // Build widget list.
        $widgets = [];

        // Body paragraph — only add if non-empty.
        // wp_kses() allows the subset of HTML that Google Chat Cards v2 supports
        // in textParagraph: bold, italic, underline, strikethrough, colour, links.
        if ( $body !== '' ) {
            $allowed_tags = [
                'b'      => [],
                'strong' => [],
                'i'      => [],
                'em'     => [],
                'u'      => [],
                's'      => [],
                'del'    => [],
                'strike' => [],
                'font'   => [ 'color' => [] ],
                'a'      => [ 'href' => [], 'target' => [] ],
                'br'     => [],
            ];
            $widgets[] = [
                'textParagraph' => [
                    'text' => nl2br( wp_kses( $body, $allowed_tags ) ),
                ],
            ];
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
