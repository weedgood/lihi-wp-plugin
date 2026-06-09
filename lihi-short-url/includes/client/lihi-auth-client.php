<?php
namespace Lihi\ShortUrl;

/**
 * Production lihi auth client.
 *
 * Every request includes the current WP site's host (from home_url()) and the
 * site-scoped UUID in the JSON payload so the auth service can identify the
 * tenant without relying on the HTTP Host header, which may be rewritten by
 * proxies or load balancers. Login requests additionally include whether the
 * current request appears to be from a mobile device.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Auth_Client implements Lihi_Auth_Client_Interface {

    private string $base_url;

    public function __construct() {
        $this->base_url = rtrim( lihi_config( 'auth_domain' ), '/' );
    }

    public function update_email( string $email ): array {
        [ 'code' => $code, 'data' => $data ] = $this->post( '/auth/update-email', [ 'email' => $email ] );

        if ( $code === 400 ) {
            throw new Lihi_Validation_Exception( esc_html( $this->message( $data ) ) );
        }
        if ( $code === 429 ) {
            throw new Lihi_Rate_Limit_Exception( esc_html( $this->message( $data ) ) );
        }
        if ( $code >= 500 ) {
            throw new Lihi_Server_Exception( esc_html( $this->message( $data ) ) );
        }

        return $data;
    }

    public function login( string $email ): array {
        [ 'code' => $code, 'data' => $data ] = $this->post( '/auth/login', [
            'email'     => $email,
            'is_mobile' => wp_is_mobile(),
        ] );

        if ( $code === 400 ) {
            throw new Lihi_Validation_Exception( esc_html( $this->message( $data ) ) );
        }
        if ( $code === 403 ) {
            throw new Lihi_Auth_Exception( esc_html( $this->message( $data ) ) );
        }
        if ( $code >= 500 ) {
            throw new Lihi_Server_Exception( esc_html( $this->message( $data ) ) );
        }

        return $data;
    }

    /**
     * Send a JSON POST and return ['code' => int, 'data' => array].
     *
     * @throws Lihi_Server_Exception on network failure or unparseable body.
     */
    private function post( string $path, array $body ): array {
        $payload = array_merge( $body, [
            'hostname' => $this->tenant_host(),
            'uuid'     => lihi_uuid(),
        ] );

        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ];

        $response = wp_remote_request( $this->base_url . $path, $args );

        if ( is_wp_error( $response ) ) {
            throw new Lihi_Server_Exception( esc_html( $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            throw new Lihi_Server_Exception( esc_html( sprintf( 'HTTP %d: unexpected response body', $code ) ) );
        }

        $data = $decoded['data'] ?? [];

        return [
            'code' => $code,
            'data' => is_array( $data ) ? $data : [],
        ];
    }

    private function tenant_host(): string {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return is_string( $host ) ? $host : '';
    }

    private function message( array $data ): string {
        $msg = $data['message'] ?? '';
        return is_string( $msg ) ? $msg : '';
    }
}
