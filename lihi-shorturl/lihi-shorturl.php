<?php
namespace Lihi\ShortUrl;

/**
 * Plugin Name: Lihi Short URL
 * Description: Adds a one-click "Lihi" button to generate and copy short URLs, including posts, pages, media and all post-type list tables.
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

// Load the plugin text domain for translations once all plugins are initialised.
add_action( 'plugins_loaded', function () {
    // load_plugin_textdomain() maps .mo files in /languages to the lihi-shorturl text domain.
    load_plugin_textdomain( 'lihi-shorturl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

require_once plugin_dir_path( __FILE__ ) . 'bootstrap.php';
