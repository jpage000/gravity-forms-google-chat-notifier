<?php
/**
 * Plugin Name:  Gravity Forms Google Chat Notifier
 * Plugin URI:   https://gravitypipeline.io
 * Description:  Send rich Google Chat card notifications (with clickable buttons) to any Space or DM when a Gravity Form is submitted.
 * Version:      1.4.0
 * Author:       Goat Getter
 * Author URI:   https://goat-getter.com
 * License:      GPL-2.0+
 * Text Domain:  gf-google-chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GFGC_VERSION', '1.4.0' );
define( 'GFGC_PLUGIN_FILE', __FILE__ );
define( 'GFGC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GFGC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ─── Load the GFFeedAddOn class (admin UI + feed storage) ─────────────────────
add_action( 'gform_loaded', 'gfgc_load_addon', 5 );

function gfgc_load_addon() {
    if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
        return;
    }

    GFForms::include_feed_addon_framework();

    require_once GFGC_PLUGIN_DIR . 'includes/class-gf-google-chat-message.php';
    require_once GFGC_PLUGIN_DIR . 'includes/class-gf-google-chat.php';

    GFAddOn::register( 'GF_Google_Chat_AddOn' );
}

// ─── Direct submission hook (primary processing path) ────────────────────────
// This bypasses GFFeedAddOn's internal processing pipeline entirely and fetches
// our feeds directly — far more reliable across all GF versions.
add_action( 'gform_after_submission', 'gfgc_process_submission', 10, 2 );

function gfgc_process_submission( $entry, $form ) {
    // Classes may not be loaded yet if GF init is still in progress.
    if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GF_Google_Chat_Message' ) ) {
        return;
    }

    $feeds = GFAPI::get_feeds( null, $form['id'], 'gf-google-chat' );
    if ( is_wp_error( $feeds ) || empty( $feeds ) ) {
        return;
    }

    foreach ( $feeds as $feed ) {
        // Skip inactive feeds.
        if ( ! rgar( $feed, 'is_active' ) ) {
            continue;
        }

        $settings = rgar( $feed, 'meta' );

        // Check conditional logic if enabled.
        $condition_enabled = rgar( $settings, 'feed_condition_enabled' );
        if ( $condition_enabled ) {
            $logic = rgar( $settings, 'feed_condition_conditional_logic' );
            if ( ! GFCommon::evaluate_conditional_logic( $logic, $form, $entry ) ) {
                continue;
            }
        }

        // Button slots (btn1–btn5) are read directly by GF_Google_Chat_Message from $settings.

        $webhook_url = trim( rgar( $settings, 'webhook_url' ) );
        $feed_name   = rgar( $settings, 'feed_name', 'Google Chat Notification' );
        $entry_id    = absint( rgar( $entry, 'id' ) );

        if ( empty( $webhook_url ) ) {
            gfgc_add_note( $entry_id, sprintf( '❌ Google Chat Notifier [%s]: Webhook URL is empty.', $feed_name ) );
            continue;
        }

        // Build and send the card.
        $payload  = ( new GF_Google_Chat_Message( $form, $entry, $settings ) )->build();
        $response = wp_remote_post( $webhook_url, [
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => wp_json_encode( $payload ),
            'timeout'     => 15,
            'redirection' => 0,
            'data_format' => 'body',
        ] );

        if ( is_wp_error( $response ) ) {
            gfgc_add_note( $entry_id, sprintf( '❌ Google Chat Notifier [%s]: %s', $feed_name, $response->get_error_message() ) );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                gfgc_add_note( $entry_id, sprintf( '✅ Google Chat Notifier [%s]: Message sent successfully.', $feed_name ) );
            } else {
                gfgc_add_note( $entry_id, sprintf( '❌ Google Chat Notifier [%s]: HTTP %d — %s', $feed_name, $code, wp_remote_retrieve_body( $response ) ) );
            }
        }
    }
}

/**
 * Add a system note to a GF entry.
 */
function gfgc_add_note( int $entry_id, string $message ) {
    if ( class_exists( 'GFFormsModel' ) && $entry_id > 0 ) {
        GFFormsModel::add_note( $entry_id, 0, 'Google Chat Notifier', $message );
    }
    error_log( 'GFGC: ' . $message );
}

// ─── "Resend to Google Chat" entry action (bypasses batch system) ─────────────

/**
 * Add a "Resend to Google Chat" item to the GF entry detail actions.
 */
add_filter( 'gform_entry_actions', 'gfgc_add_entry_action', 10, 2 );

function gfgc_add_entry_action( $actions, $entry ) {
    if ( empty( $entry['id'] ) || empty( $entry['form_id'] ) ) {
        return $actions;
    }

    $url = wp_nonce_url(
        admin_url(
            'admin.php?gfgc_resend=1&entry_id=' . absint( $entry['id'] )
            . '&form_id=' . absint( $entry['form_id'] )
        ),
        'gfgc_resend_' . $entry['id']
    );

    $actions['gfgc_resend'] = [
        'label'      => '💬 Resend to Google Chat',
        'url'        => $url,
        'menu_class' => 'gfgc-resend',
    ];

    return $actions;
}

/**
 * Handle the resend request on admin_init (before any output).
 */
add_action( 'admin_init', 'gfgc_maybe_handle_resend' );

function gfgc_maybe_handle_resend() {
    if ( ! isset( $_GET['gfgc_resend'] ) ) {
        return;
    }

    $entry_id = absint( rgar( $_GET, 'entry_id' ) );
    $form_id  = absint( rgar( $_GET, 'form_id' ) );

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'gfgc_resend_' . $entry_id ) ) {
        wp_die( 'Invalid security token.' );
    }

    if ( ! current_user_can( 'edit_others_posts' ) ) {
        wp_die( 'Insufficient permissions.' );
    }

    if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GF_Google_Chat_Message' ) ) {
        wp_die( 'Gravity Forms or Google Chat Notifier not loaded.' );
    }

    $entry = GFAPI::get_entry( $entry_id );
    $form  = GFAPI::get_form( $form_id );

    $status = 'error';
    if ( ! is_wp_error( $entry ) && is_array( $form ) ) {
        gfgc_process_submission( $entry, $form );
        $status = 'sent';
    }

    wp_safe_redirect(
        admin_url(
            'admin.php?page=gf_entries&view=entry&id=' . $form_id
            . '&lid=' . $entry_id
            . '&gfgc_status=' . $status
        )
    );
    exit;
}

/**
 * Show admin notice after a manual resend.
 */
add_action( 'admin_notices', 'gfgc_resend_admin_notice' );

function gfgc_resend_admin_notice() {
    $status = sanitize_text_field( wp_unslash( $_GET['gfgc_status'] ?? '' ) );
    if ( $status === 'sent' ) {
        echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Google Chat Notifier:</strong> Notification(s) sent. Check the entry notes for details.</p></div>';
    } elseif ( $status === 'error' ) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>Google Chat Notifier:</strong> Failed to send. Check the entry notes and PHP error log.</p></div>';
    }
}
