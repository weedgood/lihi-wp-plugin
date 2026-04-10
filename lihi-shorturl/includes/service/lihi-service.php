<?php
/**
 * Lihi service layer.
 *
 * Encapsulates business logic that sits between the HTTP client and WordPress hooks.
 * Methods throw RuntimeException on failure; callers are responsible for error handling.
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
     * Authenticate against the Lihi API and return the JWT token.
     *
     * @param string $api_key Lihi API key.
     * @return string JWT token.
     * @throws RuntimeException If the API call fails or returns no token.
     */
    public function login( string $api_key = '' ): string {
        $email  = wp_get_current_user()->user_email;
        $result = $this->client->login( $email, $api_key );
        $token  = $result['token'] ?? '';

        if ( ! $token ) {
            throw new RuntimeException( __( 'No token returned from Lihi API.', 'lihi-shorturl' ) );
        }

        return $token;
    }
}
