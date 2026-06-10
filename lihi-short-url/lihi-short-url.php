<?php
namespace Lihi\ShortUrl;

/**
 * Plugin Name: lihi Short URL
 * Description: Adds a one-click "lihi" button to generate and copy short URLs, including posts, pages, media and all post-type list tables.
 * Version: 1.0.3
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Author: lihi
 * Author URI: https://lihi.io
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lihi-short-url
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Remove all plugin-owned site data when the plugin is deactivated.
 */
function deactivate(): void {
    delete_option( 'lihi_email' );
    delete_option( 'lihi_domain' );
    delete_option( 'lihi_uuid' );
    delete_option( 'lihi_uuid_lock' );
    delete_transient( 'lihi_token' );
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

// All plugin functionality is admin-only; bail early on front-end requests.
if ( ! is_admin() ) {
    return;
}

require_once plugin_dir_path( __FILE__ ) . 'bootstrap.php';
