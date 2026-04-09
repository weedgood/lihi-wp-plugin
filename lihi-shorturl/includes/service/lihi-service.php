<?php
/**
 * Lihi service layer.
 *
 * Encapsulates business logic that sits between the HTTP client and WordPress hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Service {

    private Lihi_Client_Interface $client;

    public function __construct( Lihi_Client_Interface $client ) {
        $this->client = $client;
    }

    /**
     * Check whether the current lihi_token cookie exists and has not expired.
     *
     * @return bool True if the token is present and valid; false otherwise.
     */
    public function has_valid_token(): bool {
        $token = $_COOKIE['lihi_token'] ?? '';

        if ( ! $token ) {
            return false;
        }

        $parts   = explode( '.', $token );
        $payload = json_decode( base64_decode( strtr( $parts[1] ?? '', '-_', '+/' ) ), true );

        return isset( $payload['exp'] ) && $payload['exp'] > time();
    }

    /**
     * Authenticate against the Lihi API and store the token as an httponly cookie.
     *
     * @return bool True if a token was received and the cookie was set; false otherwise.
     */
    public function login( string $api_key = '' ): bool {
        // Retrieve the email of the currently logged-in WordPress user.
        $email  = wp_get_current_user()->user_email;
        $result = $this->client->login( $email, $api_key );
        $token  = $result['token'] ?? '';

        if ( ! $token ) {
            return false;
        }

        // Set an httponly cookie so the token is inaccessible to JavaScript.
        setcookie( 'lihi_token', $token, [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ] );

        return true;
    }
}
