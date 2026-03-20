<?php
/**
 * Plugin Name:  Gravity Forms Google Chat Notifier
 * Plugin URI:   https://gravitypipeline.io
 * Description:  Send rich Google Chat card notifications (with clickable buttons) to any Space or DM when a Gravity Form is submitted.
 * Version:      1.3.0
 * Author:       Goat Getter
 * Author URI:   https://goat-getter.com
 * License:      GPL-2.0+
 * Text Domain:  gf-google-chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GFGC_VERSION', '1.3.0' );
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

        // Repeater field stores rows directly as {button_label, button_url} — pass through as-is.
        $settings['buttons'] = is_array( rgar( $settings, 'buttons' ) ) ? rgar( $settings, 'buttons' ) : [];

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
