<?php
namespace Lihi\ShortUrl;

/**
 * Bootstrap: loads all plugin dependencies in the correct order.
 *
 * Load order: helper → interface → client → mock → service → feature files.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/helper.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client-interface.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client-mock.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/service/lihi-service.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/add-shorturl-column.php';
