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

    protected $_version                  = '2.0.5';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug                     = 'gf-google-chat';
    protected $_path                     = 'gravity-forms-google-chat-notifier/gravity-forms-google-chat-notifier.php';
    protected $_full_path                = '';
    protected $_title                    = 'Google Chat Notifier';
    protected $_short_title              = 'Google Chat';

    /** @var GF_Google_Chat_AddOn|null */
    private static $_instance = null;

    /**
     * Google Chat icon — teal speech-bubble with three dots.
     * GF form-settings nav needs a raw SVG string (not base64).
     */
    public function get_menu_icon(): string {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">'
             . '<path fill="#00BCD4" d="M40 6H8C5.8 6 4 7.8 4 10v24c0 2.2 1.8 4 4 4h8v6l7-6h17c2.2 0 4-1.8 4-4V10c0-2.2-1.8-4-4-4z"/>'
             . '<circle fill="#fff" cx="16" cy="22" r="3"/>'
             . '<circle fill="#fff" cx="24" cy="22" r="3"/>'
             . '<circle fill="#fff" cx="32" cy="22" r="3"/>'
             . '</svg>';
    }

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
        add_action( 'admin_enqueue_scripts', [ $this, 'localize_settings_js' ], 20 );

        // Show live Pro/free status on our settings page — runs after options are
        // saved, so it always reflects the current license state even right after save.
        add_action( 'admin_notices', [ $this, 'show_pro_status_notice' ] );
    }

    /**
     * Shows the Pro/free status banner on the Google Chat plugin settings screen.
     * Kept in admin_notices (not plugin_settings_fields) so it reflects the
     * saved DB value rather than a value baked in before the POST is processed.
     */
    public function show_pro_status_notice(): void {
        $screen = get_current_screen();
        // Only show on our plugin settings page.
        if ( ! $screen || strpos( $screen->id, 'gf-google-chat' ) === false ) {
            return;
        }
        // Skip if the Pro plugin's own notice will handle this.
        if ( apply_filters( 'gfgc_is_pro_active', false ) ) {
            return; // Pro plugin shows its own "✅ active" notice.
        }
        echo '<div class="notice notice-info">'
           . '<p>ℹ️ <strong>Google Chat Notifier</strong> — running the <strong>free</strong> version. '
           . 'Limited to 1 active feed per form. '
           . '<a href="https://goat-getter.com/gf-google-chat-pro" target="_blank"><strong>Upgrade to Pro →</strong></a>'
           . '</p></div>';
    }

    /**
     * Returns true when the Pro plugin is active and licensed.
     */
    public static function is_pro(): bool {
        return (bool) apply_filters( 'gfgc_is_pro_active', false );
    }

    /**
     * Allow reprocessing for our own feeds only.
     */
    public function allow_feed_reprocessing( $allow, $feed, $entry, $form, $addon ) {
        return ( $addon instanceof self ) ? true : $allow;
    }

    /**
     * Override textarea rendering — use WP Editor (TinyMCE) for the card body field
     * so the user gets the same WYSIWYG experience as GF's email notification body.
     */
    public function settings_textarea( $field, $echo = true ) {
        if ( rgar( $field, 'name' ) !== 'message_body' ) {
            return parent::settings_textarea( $field, $echo );
        }

        // Free users get a standard merge-tag-aware textarea.
        // Pro users get the full WP Editor with the {Merge Tags} toolbar button.
        if ( ! self::is_pro() ) {
            return parent::settings_textarea( $field, $echo );
        }

        // Saved value is HTML-encoded (so GF's sanitizer doesn't strip angle brackets).
        // Decode for display in wp_editor, keep encoded for the hidden GF textarea.
        $raw_encoded = $this->get_setting( 'message_body', '' );
        $decoded     = html_entity_decode( $raw_encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        ob_start();

        // wp_editor uses a PRIVATE textarea name — GF never reads from this.
        // The encoded value is in the hidden textarea below; JS keeps it in sync.
        wp_editor(
            $decoded,
            'gfgc_message_body',
            [
                'textarea_name' => 'gfgc_body_raw',   // ← private, GF ignores it
                'textarea_rows' => 10,
                'teeny'         => false,
                'media_buttons' => false,
                'quicktags'     => true,
                'tinymce'       => [
                    'toolbar1' => 'bold,italic,underline,strikethrough,forecolor,link,unlink,removeformat,|,bullist,numlist,|,undo,redo,|,gfmergetag',
                    'toolbar2' => '',
                    // WordPress passes strings starting with 'function' as raw JS (not JSON-quoted).
                    // This setup callback runs BEFORE the toolbar renders, so gfmergetag is registered in time.
                    'setup'    => 'function(editor){ if(typeof window.gfgcSetupEditor==="function") window.gfgcSetupEditor(editor); }',
                ],
            ]
        );

        // Real GF hidden textarea — always contains HTML-encoded content.
        // JS listens to TinyMCE onChange and immediately updates this.
        printf(
            '<textarea name="_gform_setting_message_body" id="gfgc_body_encoded" style="display:none;">%s</textarea>',
            esc_textarea( $raw_encoded )
        );

        // Real textarea with merge-tag-support NOT needed — merge tags are handled
        // by the gfmergetag TinyMCE toolbar button registered in gfgc-settings.js.

        $html = ob_get_clean();

        if ( $echo ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return $html;
    }

    /**
     * Enqueue admin scripts for the feed settings page.
     * Adds the Media Library picker to the Card Icon URL field.
     */
    public function scripts(): array {
        $scripts = [
            [
                'handle'  => 'gfgc-admin',
                'src'     => GFGC_PLUGIN_URL . 'assets/js/gfgc-settings.js',
                'version' => GFGC_VERSION,
                'deps'    => [ 'jquery', 'media-upload', 'thickbox', 'editor' ],
                'enqueue' => [
                    [ 'admin_page' => [ 'form_settings' ] ],
                ],
            ],
        ];

        return array_merge( parent::scripts(), $scripts );
    }

    /**
     * Localize JS data (nonce, admin_url) for the duplicate-feed feature.
     * Hooked on admin_enqueue_scripts — runs after our script is registered.
     */
    public function localize_settings_js(): void {
        $form_id   = absint( rgget( 'id' ) );
        $form      = $form_id ? GFAPI::get_form( $form_id ) : [];
        $mt_groups = [];

        // Build a simple flat list of merge tags for the TinyMCE menu button.
        if ( ! empty( $form['fields'] ) ) {
            foreach ( $form['fields'] as $field ) {
                $label = GFCommon::get_label( $field );
                if ( empty( $label ) ) {
                    continue;
                }
                // Multi-input fields (Name, Address) get individual sub-tags.
                $inputs = is_array( $field->inputs ) ? $field->inputs : [];
                if ( ! empty( $inputs ) ) {
                    foreach ( $inputs as $input ) {
                        if ( ! empty( $input['isHidden'] ) ) continue;
                        $sub_label = GFCommon::get_label( $field, $input['id'] );
                        $mt_groups[] = [
                            'text'  => $label . ' (' . $sub_label . ')',
                            'value' => '{' . $label . ':' . $input['id'] . '}',
                        ];
                    }
                } else {
                    $mt_groups[] = [
                        'text'  => $label,
                        'value' => '{' . $label . ':' . $field->id . '}',
                    ];
                }
            }
            // Standard GF special tags.
            $mt_groups[] = [ 'text' => '— Special —', 'value' => '' ];
            $mt_groups[] = [ 'text' => 'Entry ID',     'value' => '{entry_id}' ];
            $mt_groups[] = [ 'text' => 'Form Title',   'value' => '{form_title}' ];
            $mt_groups[] = [ 'text' => 'Entry Date',   'value' => '{date_mdy}' ];
        }

        wp_localize_script( 'gfgc-admin', 'gfgc_settings', [
            'admin_url'   => admin_url(),
            'form_id'     => $form_id,
            'dup_nonce'   => wp_create_nonce( 'gfgc_duplicate_feed' ),
            'merge_tags'  => $mt_groups,
        ] );
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
                        'type'        => self::is_pro() ? 'text' : 'html',
                        'html'        => self::is_pro() ? '' : $this->pro_notice( 'Card Icon' ),
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
                        'name'        => 'notification_subtitle',
                        'label'       => 'Card Subtitle',
                        'type'        => 'textarea',
                        'class'       => 'large merge-tag-support mt-position-right mt-hide_all_fields',
                        'tooltip'     => 'Shown below the title in the card header. Supports merge tags. Leave blank to hide the subtitle.',
                        'placeholder' => 'e.g. Form: {form_title} - Entry #{entry_id}',
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
                'description' => 'Add clickable link buttons to the card.',
                'fields'      => self::is_pro()
                    ? [
                        [
                            'name'    => 'include_entry_link',
                            'label'   => 'View Entry Button',
                            'type'    => 'checkbox',
                            'tooltip' => 'Automatically add a "📋 View Entry" button that links to the entry in WP Admin.',
                            'choices' => [
                                [
                                    'name'          => 'include_entry_link',
                                    'label'         => 'Include a "View Entry" admin button',
                                    'default_value' => '0',
                                ],
                            ],
                        ],
                        [ 'name' => 'btn1_label', 'label' => 'Button 1 — Label', 'type' => 'text', 'class' => 'medium', 'placeholder' => 'e.g. View CRM' ],
                        [ 'name' => 'btn1_url',   'label' => 'Button 1 — URL',   'type' => 'text', 'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields', 'placeholder' => 'https://yourcrm.com/lead/{entry_id}' ],
                        [ 'name' => 'btn2_label', 'label' => 'Button 2 — Label', 'type' => 'text', 'class' => 'medium', 'placeholder' => 'e.g. Open Policy' ],
                        [ 'name' => 'btn2_url',   'label' => 'Button 2 — URL',   'type' => 'text', 'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields', 'placeholder' => 'https://...' ],
                        [ 'name' => 'btn3_label', 'label' => 'Button 3 — Label', 'type' => 'text', 'class' => 'medium', 'placeholder' => 'e.g. Run Quote' ],
                        [ 'name' => 'btn3_url',   'label' => 'Button 3 — URL',   'type' => 'text', 'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields', 'placeholder' => 'https://...' ],
                        [ 'name' => 'btn4_label', 'label' => 'Button 4 — Label', 'type' => 'text', 'class' => 'medium', 'placeholder' => '' ],
                        [ 'name' => 'btn4_url',   'label' => 'Button 4 — URL',   'type' => 'text', 'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields', 'placeholder' => 'https://...' ],
                        [ 'name' => 'btn5_label', 'label' => 'Button 5 — Label', 'type' => 'text', 'class' => 'medium', 'placeholder' => '' ],
                        [ 'name' => 'btn5_url',   'label' => 'Button 5 — URL',   'type' => 'text', 'class' => 'large merge-tag-support mt-position-right mt-hide_all_fields', 'placeholder' => 'https://...' ],
                    ]
                    : [
                        [
                            'name' => 'pro_buttons_notice',
                            'type' => 'html',
                            'html' => $this->pro_notice( 'Buttons (View Entry, custom links — up to 6 per card)' ),
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
     * Plugin-level settings page (Forms → Settings → Google Chat).
     * Returns the base fields and allows Pro to inject the license key field
     * via the `gfgc_plugin_settings_fields` filter.
     */
    public function plugin_settings_fields(): array {
        $fields = [
            [
                'title'  => 'Google Chat Notifier',
                'fields' => [
                    [
                        'name' => 'gfgc_info',
                        'type' => 'html',
                        'html' => '<p>Configure Google Chat notification feeds on each individual form under <strong>Form Settings → Google Chat</strong>.</p>',
                    ],
                ],
            ],
        ];

        // Allow the Pro plugin to inject the license key field.
        return apply_filters( 'gfgc_plugin_settings_fields', $fields );
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
        // Intentionally empty — all processing is handled by gfgc_process_submission()
        // which is hooked directly on gform_after_submission in the main plugin file.
        // Leaving this active caused every submission to send two Chat notifications:
        // once via this GFFeedAddOn path and once via the standalone hook.
        return;
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

    /**
     * Returns a "Pro feature" upgrade notice HTML string shown in feed settings.
     */
    private function pro_notice( string $feature ): string {
        return sprintf(
            '<p style="margin:4px 0;padding:8px 12px;background:#f9f9f9;border-left:4px solid #e2c000;border-radius:2px;">'
            . '🔒 <strong>%s</strong> is a <strong>Pro</strong> feature. '
            . '<a href="https://goat-getter.com/gf-google-chat-pro" target="_blank" rel="noopener">Upgrade to Pro →</a>'
            . '</p>',
            esc_html( $feature )
        );
    }
}
