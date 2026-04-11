<?php
namespace Lihi\ShortUrl;

/**
 * Bootstrap: loads all plugin dependencies in the correct order.
 *
 * Load order: helper → settings → option check → interface → client → mock → service → feature files.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/helper.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/lihi-settings.php';

// Require email and API key to be configured before loading plugin features.
if ( ! get_option( 'lihi_email' ) || ! get_option( 'lihi_api_key' ) ) {
    add_action( 'admin_notices', function () {
        $url = admin_url( 'options-general.php?page=lihi-settings' );
        echo '<div class="notice notice-warning"><p>' .
            sprintf(
                /* translators: %s: settings page URL */
                __( 'Lihi Short URL: please configure your <a href="%s">email and API key</a> to activate the plugin.', 'lihi-shorturl' ),
                esc_url( $url )
            ) .
            '</p></div>';
    } );
    return;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client-interface.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client-mock.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/service/lihi-service.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/lihi-auth.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/add-shorturl-column.php';
