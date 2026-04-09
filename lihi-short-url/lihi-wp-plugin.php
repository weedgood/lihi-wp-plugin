<?php
/**
 * Plugin Name: Lihi WP Plugin
 * Description: Lihi custom WordPress plugin.
 * Version: 0.1.0
 * Author: Lihi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_notices', function () {
    echo '<div class="notice notice-success"><p><strong>Lihi WP Plugin is active and working!</strong></p></div>';
} );

if ( is_admin() ) {
    add_action( 'plugins_loaded', function () {
        load_plugin_textdomain( 'lihi-wp-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    } );
    require_once plugin_dir_path( __FILE__ ) . 'includes/add-lihi-column.php';
}
