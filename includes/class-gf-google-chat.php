<?php
/**
 * GF Google Chat Add-On — GFFeedAddOn implementation.
 *
 * Registers a "feeds" integration so admins can create multiple notification
 * destinations (spaces, DMs) per form, each with its own webhook URL, message
 * template, and custom buttons.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GF_Google_Chat_AddOn extends GFFeedAddOn {

    // -------------------------------------------------------------------------
    // Add-On identity
    // -------------------------------------------------------------------------

    protected $_version                  = '1.4.5';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug                     = 'gf-google-chat';
    protected $_path                     = 'gravity-forms-google-chat-notifier/gravity-forms-google-chat-notifier.php';
    protected $_full_path                = '';
    protected $_title                    = 'Google Chat Notifier';
    protected $_short_title              = 'Google Chat';

    /** @var GF_Google_Chat_AddOn|null */
    private static $_instance = null;

    /**
     * Returns the single instance of this add-on.
     *
     * @return GF_Google_Chat_AddOn
     */
    public static function get_instance(): GF_Google_Chat_AddOn {
        if ( self::$_instance === null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Always report ourselves as active so GF's batch processor doesn't skip
     * us due to a wrong $_full_path-based is_plugin_active() check.
     */
    public function is_active(): bool {
        return true;
    }

    /**
     * Hook into init() (called by parent after proper setup) rather than the
     * constructor, so reprocessing filters are added at the right time.
     */
    public function init() {
        parent::init();

        // Allow Feed Forge and GF native reprocessing to invoke process_feed().
        // gform_allow_feed_reprocessing defaults to false for all add-ons.
        add_filter( 'gform_allow_feed_reprocessing', [ $this, 'allow_feed_reprocessing' ], 10, 5 );
    }

    /**
     * Allow reprocessing for our own feeds only.
     */
    public function allow_feed_reprocessing( $allow, $feed, $entry, $form, $addon ) {
        return ( $addon instanceof self ) ? true : $allow;
    }

    /**
     * Enqueue admin scripts for the feed settings page.
     * Adds the Media Library picker to the Card Icon URL field.
     */
    public function scripts(): array {
        $scripts = [
            [
                'handle'  => 'gfgc-admin',
                'src'     => GFGC_PLUGIN_URL . 'assets/js/admin.js',
                'version' => GFGC_VERSION,
                'deps'    => [ 'jquery', 'media-upload', 'thickbox' ],
                'enqueue' => [
                    [ 'admin_page' => [ 'form_settings' ] ],
                ],
            ],
        ];

        return array_merge( parent::scripts(), $scripts );
    }

    // -------------------------------------------------------------------------
    // Feed settings fields
    // -------------------------------------------------------------------------

    /**
     * Feed settings definition — rendered automatically by GFFeedAddOn.
     */
    public function feed_settings_fields(): array {
        return [
            // ── Section 1: Destination ──────────────────────────────────────
            [
                'title'  => 'Destination',
                'fields' => [
                    [
                        'name'              => 'feed_name',
                        'label'             => 'Feed Name',
                        'type'              => 'text',
                        'required'          => true,
                        'default_value'     => 'Google Chat Notification',
                        'tooltip'           => 'A label to identify this feed (e.g. "Sales Space", "John DM").',
                        'class'             => 'medium',
                    ],
                    [
                        'name'          => 'webhook_url',
                        'label'         => 'Webhook URL',
                        'type'          => 'text',
                        'required'      => true,
                        'class'         => 'large',
                        'tooltip'       => '<h6>Google Chat Webhook URL</h6>In your Google Chat Space or DM: ⚙️ → Apps & Integrations → Webhooks → Add webhook → copy the URL and paste it here.',
                        'placeholder'   => 'https://chat.googleapis.com/v1/spaces/...',
                    ],
                    [
                        'name'        => 'card_icon_url',
                        'label'       => 'Card Icon URL',
                        'type'        => 'text',
                        'class'       => 'large',
                        'tooltip'     => 'Optional. URL of an image to show in the card header (square PNG or SVG recommended, min 256×256px). Leave blank for the default icon.',
                        'placeholder' => 'https://example.com/your-icon.png',
                    ],
                ],
            ],

            // ── Section 2: Message Content ────────────────────────────────
            [
                'title'       => 'Message Content',
                'description' => 'Use Gravity Forms merge tags (e.g. <code>{Name (First):1.3}</code>, <code>{Email:4}</code>) to include submitted values.',
                'fields'      => [
                    [
                        'name'    => 'notification_title',
                        'label'   => 'Card Title',
                        'type'    => 'text',
                        'class'   => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'tooltip' => 'Bold header of the card. Supports merge tags.',
                        'default_value' => 'New Form Submission — {form_title}',
                    ],
                    [
                        'name'    => 'message_body',
                        'label'   => 'Card Body',
                        'type'    => 'textarea',
                        'class'   => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'tooltip' => 'Main content of the card. Supports merge tags and newlines.<br><br><strong>Formatting:</strong> <code>&lt;b&gt;</code> bold, <code>&lt;i&gt;</code> italic, <code>&lt;u&gt;</code> underline, <code>&lt;s&gt;</code> strikethrough, <code>&lt;font color="#hex"&gt;</code> colour, <code>&lt;a href=""&gt;</code> link.',
                        'default_value' => "Name: {Name (First):1.3} {Name (Last):1.6}\nEmail: {Email:2}\nPhone: {Phone:3}\n\n{all_fields}",
                    ],
                ],
            ],

            // ── Section 3: Buttons ────────────────────────────────────────
            [
                'title'       => 'Buttons',
                'description' => 'Add up to 5 custom link buttons to the card. Leave a row blank to skip it. URLs support merge tags like <code>{entry_id}</code>.',
                'fields'      => [
                    [
                        'name'    => 'include_entry_link',
                        'label'   => 'View Entry Button',
                        'type'    => 'checkbox',
                        'tooltip' => 'Automatically add a "📋 View Entry" button that links to the entry in WP Admin.',
                        'choices' => [
                            [
                                'name'          => 'include_entry_link',
                                'label'         => 'Include a "View Entry" admin button',
                                'default_value' => '1',
                            ],
                        ],
                    ],
                    // ── Button slots 1–5 ──────────────────────────────────
                    [
                        'name'  => 'btn1_label',
                        'label' => 'Button 1 — Label',
                        'type'  => 'text',
                        'class' => 'medium',
                        'placeholder' => 'e.g. View CRM',
                    ],
                    [
                        'name'  => 'btn1_url',
                        'label' => 'Button 1 — URL',
                        'type'  => 'text',
                        'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'placeholder' => 'https://yourcrm.com/lead/{entry_id}',
                    ],
                    [
                        'name'  => 'btn2_label',
                        'label' => 'Button 2 — Label',
                        'type'  => 'text',
                        'class' => 'medium',
                        'placeholder' => 'e.g. Open Policy',
                    ],
                    [
                        'name'  => 'btn2_url',
                        'label' => 'Button 2 — URL',
                        'type'  => 'text',
                        'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'placeholder' => 'https://...',
                    ],
                    [
                        'name'  => 'btn3_label',
                        'label' => 'Button 3 — Label',
                        'type'  => 'text',
                        'class' => 'medium',
                        'placeholder' => 'e.g. Run Quote',
                    ],
                    [
                        'name'  => 'btn3_url',
                        'label' => 'Button 3 — URL',
                        'type'  => 'text',
                        'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'placeholder' => 'https://...',
                    ],
                    [
                        'name'  => 'btn4_label',
                        'label' => 'Button 4 — Label',
                        'type'  => 'text',
                        'class' => 'medium',
                        'placeholder' => '',
                    ],
                    [
                        'name'  => 'btn4_url',
                        'label' => 'Button 4 — URL',
                        'type'  => 'text',
                        'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'placeholder' => 'https://...',
                    ],
                    [
                        'name'  => 'btn5_label',
                        'label' => 'Button 5 — Label',
                        'type'  => 'text',
                        'class' => 'medium',
                        'placeholder' => '',
                    ],
                    [
                        'name'  => 'btn5_url',
                        'label' => 'Button 5 — URL',
                        'type'  => 'text',
                        'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'placeholder' => 'https://...',
                    ],
                ],
            ],

            // ── Section 4: Conditional Logic ─────────────────────────────
            [
                'title'  => 'Conditional Logic',
                'fields' => [
                    [
                        'name'           => 'feed_condition',
                        'label'          => 'Condition',
                        'type'           => 'feed_condition',
                        'checkbox_label' => 'Enable Condition',
                        'instructions'   => 'Send this notification only when the following conditions are met:',
                    ],
                ],
            ],
        ];
    }

    /**
     * Human-readable column shown in the Feeds list table.
     */
    public function feed_list_columns(): array {
        return [
            'feed_name'   => 'Name',
            'webhook_url' => 'Webhook URL',
        ];
    }

    /**
     * Disable async/batch processing so GF calls process_feed() synchronously
     * on manual feed reprocessing — avoids the "failed to create batch" error.
     */
    public function supports_async_feed_processing(): bool {
        return false;
    }

    // -------------------------------------------------------------------------
    // Feed processing
    // -------------------------------------------------------------------------

    /**
     * Called by GFFeedAddOn automatically after form submission for each
     * active feed whose conditional logic passes.
     *
     * @param array $feed   Feed data (meta + settings).
     * @param array $entry  GF entry array.
     * @param array $form   GF form array.
     */
    public function process_feed( $feed, $entry, $form ) {
        $settings  = rgar( $feed, 'meta' );
        $feed_name = rgar( $settings, 'feed_name', 'Google Chat Notification' );
        $entry_id  = absint( rgar( $entry, 'id' ) );

        $webhook_url = trim( rgar( $settings, 'webhook_url' ) );
        if ( empty( $webhook_url ) ) {
            $msg = sprintf( '❌ Google Chat Notifier [%s]: Webhook URL is empty — feed not sent.', $feed_name );
            $this->log_error( __METHOD__ . '(): ' . $msg );
            $this->add_entry_note( $entry_id, $msg, 'error' );
            return;
        }

        // Button data (btn1_label/btn1_url…btn5_label/btn5_url) is read directly
        // from $settings by GF_Google_Chat_Message — no normalization needed.
        $message_builder = new GF_Google_Chat_Message( $form, $entry, $settings );
        $payload         = $message_builder->build();

        $response = wp_remote_post(
            $webhook_url,
            [
                'headers'     => [ 'Content-Type' => 'application/json' ],
                'body'        => wp_json_encode( $payload ),
                'timeout'     => 15,
                'redirection' => 0,
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            $this->log_error( __METHOD__ . '(): wp_remote_post error — ' . $error_msg );
            $this->add_entry_note(
                $entry_id,
                sprintf( '❌ Google Chat Notifier [%s]: Failed to send — %s', $feed_name, $error_msg ),
                'error'
            );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            $this->log_error( __METHOD__ . '(): Google Chat returned HTTP ' . $code . ' — ' . $body );
            $this->add_entry_note(
                $entry_id,
                sprintf( '❌ Google Chat Notifier [%s]: HTTP %d error — %s', $feed_name, $code, $body ),
                'error'
            );
        } else {
            $this->log_debug( __METHOD__ . '(): Notification sent successfully. HTTP ' . $code );
            $this->add_entry_note(
                $entry_id,
                sprintf( '✅ Google Chat Notifier [%s]: Message sent successfully.', $feed_name ),
                'success'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * The `generic_map` field stores key/value rows in an array of
     * ['key' => '...', 'value' => '...'] items. Convert to the shape
     * GF_Google_Chat_Message expects: [['button_label' => '...', 'button_url' => '...']].
     *
     * @param mixed $raw
     * @return array
     */
    private function normalize_buttons( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $out = [];
        foreach ( $raw as $row ) {
            $label = trim( rgar( $row, 'key' ) );
            $url   = trim( rgar( $row, 'value' ) );
            if ( $label !== '' && $url !== '' ) {
                $out[] = [
                    'button_label' => $label,
                    'button_url'   => $url,
                ];
            }
        }
        return $out;
    }
}
