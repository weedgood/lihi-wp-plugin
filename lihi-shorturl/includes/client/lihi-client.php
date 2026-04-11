<?php
namespace Lihi\ShortUrl;

/**
 * Production Lihi API client.
 *
 * Sends HTTP requests to the Lihi API using WordPress's wp_remote_request().
 * The base URL is read from lihi_api_domain().
 * Every method except login() requires a JWT $token passed by the caller.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Client implements Lihi_Client_Interface {

    private string $base_url;

    public function __construct() {
        $this->base_url = rtrim( lihi_api_domain(), '/' );
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public function login( string $email, string $api_key, string $country = 'TW' ): array {
        return $this->request( 'POST', '/api/wordpress/v1/login', [
            'email'   => $email,
            'api_key' => $api_key,
            'country' => $country,
        ] );
    }

    // -------------------------------------------------------------------------
    // Posts
    // -------------------------------------------------------------------------

    public function get_posts( string $token, string $locale = 'zh-TW' ): array {
        return $this->request( 'GET', '/api/wordpress/v1/posts', [ 'locale' => $locale ], $token );
    }

    // -------------------------------------------------------------------------
    // Sites
    // -------------------------------------------------------------------------

    public function get_sites( string $token, array $params = [] ): array {
        return $this->request( 'GET', '/api/wordpress/v1/sites', $params, $token );
    }

    public function get_short_links( string $token, string $type, $type_ids ): array {
        return $this->request( 'GET', '/api/wordpress/v1/sites', [
            'per_page' => 20,
            'type'     => $type,
            'type_id'  => $type_ids,
        ], $token );
    }

    public function create_site( string $token, array $body ): array {
        return $this->request( 'POST', '/api/wordpress/v1/sites', $body, $token );
    }

    public function update_site( string $token, int $id, array $body ): array {
        return $this->request( 'PUT', "/api/wordpress/v1/sites/{$id}", $body, $token );
    }

    public function delete_site( string $token, int $id ): bool {
        $this->request( 'DELETE', "/api/wordpress/v1/sites/{$id}", [], $token );
        return true;
    }

    // -------------------------------------------------------------------------
    // Site URLs
    // -------------------------------------------------------------------------

    public function create_site_url( string $token, array $body ): array {
        return $this->request( 'POST', '/api/wordpress/v1/site-urls', $body, $token );
    }

    public function update_site_url( string $token, int $id, array $body ): array {
        return $this->request( 'PUT', "/api/wordpress/v1/site-urls/{$id}", $body, $token );
    }

    public function delete_site_url( string $token, int $id ): bool {
        $this->request( 'DELETE', "/api/wordpress/v1/site-urls/{$id}", [], $token );
        return true;
    }

    // -------------------------------------------------------------------------
    // Core
    // -------------------------------------------------------------------------

    /**
     * Execute an HTTP request against the Lihi API.
     *
     * GET requests append $data as a query string; other methods encode it as JSON body.
     * When $token is non-empty an Authorization: Bearer header is added.
     * Throws RuntimeException on network errors, invalid JSON, or HTTP 4xx/5xx responses.
     *
     * @throws RuntimeException
     */
    private function request( string $method, string $path, array $data = [], string $token = '' ): array {
        $headers = [ 'Content-Type' => 'application/json' ];

        if ( $token !== '' ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 15,
        ];

        if ( $method === 'GET' && ! empty( $data ) ) {
            $url = $this->base_url . $path . '?' . http_build_query( $data );
        } else {
            $url = $this->base_url . $path;
            if ( ! empty( $data ) ) {
                $args['body'] = wp_json_encode( $data );
            }
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code === 204 || $body === '' ) {
            return [];
        }

        $decoded = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new \RuntimeException( 'Expected JSON but got: ' . substr( $body, 0, 200 ) );
        }

        if ( $code >= 400 ) {
            throw new \RuntimeException( "API request failed with status {$code}: " . wp_json_encode( $decoded ) );
        }

        return $decoded;
    }
}
