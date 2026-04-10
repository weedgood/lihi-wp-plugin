<?php
namespace Lihi\ShortUrl;

/**
 * Plugin Name: Lihi WP Plugin
 * Description: Lihi custom WordPress plugin.
 * Version: 0.1.0
 * Author: Lihi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// All plugin functionality is admin-only; bail early on front-end requests.
if ( ! is_admin() ) {
    return;
}

// Display an admin notice confirming the plugin is active.
add_action( 'admin_notices', function () {
    echo '<div class="notice notice-success"><p><strong>Lihi WP Plugin is active and working!</strong></p></div>';
} );

// Load the plugin text domain for translations once all plugins are initialised.
add_action( 'plugins_loaded', function () {
    // load_plugin_textdomain() maps .mo files in /languages to the lihi-shorturl text domain.
    load_plugin_textdomain( 'lihi-shorturl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

require_once plugin_dir_path( __FILE__ ) . 'bootstrap.php';
