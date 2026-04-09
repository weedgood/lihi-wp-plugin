<?php
/**
 * Admin authentication flow.
 *
 * On every admin page load, checks whether a valid (non-expired) lihi_token cookie exists.
 * If absent or expired, enqueues lihi-login.js to trigger a background token refresh via AJAX.
 * The wp_ajax_lihi_login handler calls Lihi_Service::login() and sets a new httponly cookie.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue lihi-login.js on every admin page when the token is absent or expired.
add_action( 'admin_enqueue_scripts', function () {
    // Skip re-login if the token is still valid.
    if ( lihi_service()->has_valid_token() ) {
        return;
    }

    // Register lihi-login.js to be output in the admin footer.
    wp_enqueue_script(
        'lihi-login',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/lihi-login.js',
        [],
        '1.0.0',
        true
    );

    // Expose ajaxUrl, nonce, and action to lihi-login.js as the global `lihiLogin` object.
    wp_localize_script( 'lihi-login', 'lihiLogin', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ), // WordPress AJAX endpoint
        'nonce'   => wp_create_nonce( 'lihi_login' ), // CSRF token for the login action
        'action'  => 'lihi_login',
    ] );
} );

// Handle the AJAX login request: verify nonce, obtain token, set httponly cookie.
add_action( 'wp_ajax_lihi_login', function () {
    // Abort with 403 if the nonce is invalid or missing.
    check_ajax_referer( 'lihi_login', 'nonce' );

    lihi_service()->login();

    // Respond with { "success": true } and exit.
    wp_send_json_success();
} );
