<?php
namespace Lihi\ShortUrl;

/**
 * Production lihi auth client.
 *
 * Every request overrides the HTTP Host header with the current WP site's
 * host (from home_url()) because the auth service identifies the tenant from
 * that header, not from the URL host.
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
            throw new Lihi_Validation_Exception( $this->message( $data ) );
        }
        if ( $code === 429 ) {
            throw new Lihi_Rate_Limit_Exception( $this->message( $data ) );
        }
        if ( $code >= 500 ) {
            throw new Lihi_Server_Exception( $this->message( $data ) );
        }

        return $data;
    }

    public function login( string $email ): array {
        [ 'code' => $code, 'data' => $data ] = $this->post( '/auth/login', [ 'email' => $email ] );

        if ( $code === 400 ) {
            throw new Lihi_Validation_Exception( $this->message( $data ) );
        }
        if ( $code === 403 ) {
            throw new Lihi_Auth_Exception( $this->message( $data ) );
        }
        if ( $code >= 500 ) {
            throw new Lihi_Server_Exception( $this->message( $data ) );
        }

        return $data;
    }

    /**
     * Send a JSON POST and return ['code' => int, 'data' => array].
     *
     * @throws Lihi_Server_Exception on network failure or unparseable body.
     */
    private function post( string $path, array $body ): array {
        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Host'         => $this->tenant_host(),
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ];

        $response = wp_remote_request( $this->base_url . $path, $args );

        if ( is_wp_error( $response ) ) {
            throw new Lihi_Server_Exception( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            throw new Lihi_Server_Exception( "HTTP {$code}: unexpected response body" );
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
