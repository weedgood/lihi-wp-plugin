<?php
namespace Lihi\ShortUrl;

/**
 * Plugin Name: lihi Short URL
 * Description: Adds a one-click "lihi" button to generate and copy short URLs, including posts, pages, media and all post-type list tables.
 * Version: 1.0.0
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

// All plugin functionality is admin-only; bail early on front-end requests.
if ( ! is_admin() ) {
    return;
}

require_once plugin_dir_path( __FILE__ ) . 'bootstrap.php';
