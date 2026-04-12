<?php
namespace Lihi\ShortUrl;

/**
 * Bootstrap: loads all plugin dependencies in the correct order.
 *
 * Load order: helper → settings → interface → client → service → feature files.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/helper.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-exceptions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client-interface.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/service/lihi-service.php';

// Stop here when the email has not been configured yet; show a notice instead.
// Class definitions above are always available; only feature hooks are skipped.
if ( lihi_email() === '' ) {
    add_action( 'admin_notices', function () {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $url = esc_url( admin_url( 'options-general.php?page=lihi-settings' ) );
        echo '<div class="notice notice-warning"><p>'
            . wp_kses(
                sprintf(
                    /* translators: %s: URL to the Lihi Short URL settings page */
                    __( 'Lihi Short URL: please <a href="%s">configure your email address</a> to enable the plugin.', 'lihi-shorturl' ),
                    $url
                ),
                [ 'a' => [ 'href' => [] ] ]
            )
            . '</p></div>';
    } );
    return;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/add-shorturl-column.php';
