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
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-auth-client-interface.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/client/lihi-auth-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/store/lihi-token-store.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/service/lihi-service.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/add-shorturl-column.php';

// Show a setup notice when the email has not been configured yet.
// Feature hooks in add-shorturl-column.php guard themselves on lihi_email();
// the AJAX handler is always registered so stale buttons get a friendly error
// instead of WordPress's bare "0" response.
if ( lihi_email() === '' ) {
    add_action( 'admin_notices', function () {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $url = esc_url( admin_url( 'options-general.php?page=lihi-settings' ) );
        echo '<div class="notice notice-warning"><p>'
            . wp_kses(
                sprintf(
                    /* translators: %s: URL to the lihi Short URL settings page */
                    __( 'lihi Short URL: please <a href="%s">configure your email address</a> to enable the plugin.', 'lihi-shorturl' ),
                    $url
                ),
                [ 'a' => [ 'href' => [] ] ]
            )
            . '</p></div>';
    } );
}
