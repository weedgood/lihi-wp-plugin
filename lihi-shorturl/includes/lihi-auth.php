<?php
/**
 * Admin authentication flow.
 *
 * On every admin page load, checks whether a valid (non-expired) lihi_token cookie exists.
 * If absent or expired, enqueues lihi-login.js to trigger a background token refresh via AJAX.
 * The wp_ajax_lihi_login handler calls Lihi_Service::login(), sets a new httponly cookie,
 * and returns wp_send_json_error() with the exception message on failure.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue lihi-login.js on every admin page when the token is absent or expired.
add_action( 'admin_enqueue_scripts', function () {
    if ( lihi_service()->has_valid_token() ) {
        return;
    }

    wp_enqueue_script(
        'lihi-login',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/lihi-login.js',
        [],
        '1.0.0',
        true
    );

    wp_localize_script( 'lihi-login', 'lihiLogin', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'lihi_login' ),
        'action'  => 'lihi_login',
    ] );
} );

// Handle the AJAX login request: verify nonce, obtain token, set httponly cookie.
add_action( 'wp_ajax_lihi_login', function () {
    check_ajax_referer( 'lihi_login', 'nonce' );

    try {
        $token = lihi_service()->login();

        setcookie( 'lihi_token', $token, [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ] );

        wp_send_json_success();
    } catch ( Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
} );
