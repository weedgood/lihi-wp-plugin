<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_enqueue_scripts', function () {
    $token = $_COOKIE['lihi_token'] ?? '';

    if ( $token ) {
        $parts   = explode( '.', $token );
        $payload = json_decode( base64_decode( strtr( $parts[1] ?? '', '-_', '+/' ) ), true );

        if ( isset( $payload['exp'] ) && $payload['exp'] > time() ) {
            return;
        }
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

add_action( 'wp_ajax_lihi_login', function () {
    check_ajax_referer( 'lihi_login', 'nonce' );

    lihi_service()->login( '', '' );

    wp_send_json_success();
} );
