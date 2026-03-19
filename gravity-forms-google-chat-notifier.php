<?php
/**
 * Plugin Name:  Gravity Forms Google Chat Notifier
 * Plugin URI:   https://gravitypipeline.io
 * Description:  Send rich Google Chat card notifications (with clickable buttons) to any Space or DM when a Gravity Form is submitted.
 * Version:      1.0.0
 * Author:       Goat Getter
 * Author URI:   https://goat-getter.com
 * License:      GPL-2.0+
 * Text Domain:  gf-google-chat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GFGC_VERSION', '1.0.0' );
define( 'GFGC_PLUGIN_FILE', __FILE__ );
define( 'GFGC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GFGC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load the add-on class once GF's add-on framework is ready.
add_action( 'gform_loaded', 'gfgc_load_addon', 5 );

function gfgc_load_addon() {
    if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
        // Gravity Forms is not active or too old — bail silently.
        return;
    }

    GFForms::include_feed_addon_framework();

    require_once GFGC_PLUGIN_DIR . 'includes/class-gf-google-chat-message.php';
    require_once GFGC_PLUGIN_DIR . 'includes/class-gf-google-chat.php';

    GFAddOn::register( 'GF_Google_Chat_AddOn' );
}
