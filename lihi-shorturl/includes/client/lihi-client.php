<?php
namespace Lihi\ShortUrl;

/**
 * Production lihi short-url API client.
 *
 * Sends HTTP requests to the lihi short-url API using WordPress's
 * wp_remote_request(). The base URL is read from lihi_config( 'api_domain' ).
 * Every method requires a bearer $token obtained from Lihi_Auth_Client::login().
 *
 * request() handles only network errors and non-JSON (HTML) responses.
 * Each public method is responsible for interpreting its own JSON error payload
 * and throwing the appropriate Lihi_*_Exception.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Client implements Lihi_Client_Interface {

    private string $base_url;

    public function __construct() {
        $this->base_url = rtrim( lihi_config( 'api_domain' ), '/' );
    }

    // -------------------------------------------------------------------------
    // Posts
    // -------------------------------------------------------------------------

    /** @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception */
    public function get_posts( string $token, string $locale = 'zh-TW' ): array {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'GET', '/api/wordpress/v1/posts', [ 'locale' => $locale ], $token );
        return $this->decode( $code, $body );
    }

    // -------------------------------------------------------------------------
    // Sites
    // -------------------------------------------------------------------------

    /** @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception */
    public function get_sites( string $token, array $params = [] ): array {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'GET', '/api/wordpress/v1/sites', $params, $token );
        return $this->decode( $code, $body );
    }

    /** @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception */
    public function get_short_links( string $token, string $type, $type_ids ): array {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'GET', '/api/wordpress/v1/sites', [
            'per_page' => 20,
            'type'     => $type,
            'type_id'  => $type_ids,
        ], $token );
        return $this->decode( $code, $body );
    }

    /**
     * @throws Lihi_Validation_Exception missing required fields (HTTP 400)
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function create_site( string $token, array $body ): array {
        [ 'code' => $code, 'body' => $raw ] = $this->request( 'POST', '/api/wordpress/v1/sites', $body, $token );
        $data = $this->decode( $code, $raw );

        if ( $code === 400 ) {
            throw new Lihi_Validation_Exception( $this->msg( $data ) );
        }

        return $data;
    }

    /**
     * @throws Lihi_Not_Found_Exception  ID not found (HTTP 404 HTML)
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function update_site( string $token, int $id, array $body ): array {
        [ 'code' => $code, 'body' => $raw ] = $this->request( 'PUT', "/api/wordpress/v1/sites/{$id}", $body, $token );
        return $this->decode( $code, $raw );
    }

    /**
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     * ID not found returns HTTP 500 HTML (API inconsistency).
     */
    public function delete_site( string $token, int $id ): bool {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'DELETE', "/api/wordpress/v1/sites/{$id}", [], $token );
        $this->decode( $code, $body );
        return true;
    }

    // -------------------------------------------------------------------------
    // Site URLs
    // -------------------------------------------------------------------------

    /**
     * @throws Lihi_Validation_Exception missing required fields (HTTP 400)
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function create_site_url( string $token, array $body ): array {
        [ 'code' => $code, 'body' => $raw ] = $this->request( 'POST', '/api/wordpress/v1/site-urls', $body, $token );
        $data = $this->decode( $code, $raw );

        if ( $code === 400 ) {
            throw new Lihi_Validation_Exception( $this->msg( $data ) );
        }

        return $data;
    }

    /**
     * @throws Lihi_Not_Found_Exception  ID not found (HTTP 404 HTML)
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function update_site_url( string $token, int $id, array $body ): array {
        [ 'code' => $code, 'body' => $raw ] = $this->request( 'PUT', "/api/wordpress/v1/site-urls/{$id}", $body, $token );
        return $this->decode( $code, $raw );
    }

    /**
     * @throws Lihi_Not_Found_Exception  ID not found (HTTP 404 HTML)
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function delete_site_url( string $token, int $id ): bool {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'DELETE', "/api/wordpress/v1/site-urls/{$id}", [], $token );
        $this->decode( $code, $body );
        return true;
    }

    // -------------------------------------------------------------------------
    // Core
    // -------------------------------------------------------------------------

    /**
     * Execute an HTTP request and return ['code' => int, 'body' => string].
     *
     * Only throws for network failures (WP_Error). All HTTP status code and
     * body interpretation is left to the calling method.
     *
     * @return array{code: int, body: string}
     * @throws Lihi_Server_Exception on WP_Error (network failure).
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
            throw new Lihi_Server_Exception( $response->get_error_message() );
        }

        return [
            'code' => wp_remote_retrieve_response_code( $response ),
            'body' => wp_remote_retrieve_body( $response ),
        ];
    }

    /**
     * Decode a JSON body. Returns [] on 204 or empty body.
     *
     * @throws Lihi_Not_Found_Exception  on 404 HTML.
     * @throws Lihi_Token_Invalid_Exception on "網站升級中..." HTML (authenticated requests only).
     * @throws Lihi_Server_Exception     on any other non-JSON body.
     */
    private function decode( int $code, string $body, bool $authenticated = true ): array {
        if ( $code === 204 || $body === '' ) {
            return [];
        }

        $decoded = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            preg_match( '/<title>([^<]*)<\/title>/i', $body, $m );
            $title   = isset( $m[1] ) ? trim( $m[1] ) : '';
            $detail  = $title ?: substr( $body, 0, 100 );
            $message = "HTTP {$code}: {$detail}";

            if ( $code === 404 ) {
                throw new Lihi_Not_Found_Exception( $message );
            }

            if ( $authenticated && $title === '網站升級中...' ) {
                throw new Lihi_Token_Invalid_Exception( $message );
            }

            throw new Lihi_Server_Exception( $message );
        }

        return $decoded;
    }

    /**
     * Extract a human-readable message from a decoded error response.
     */
    private function msg( array $data ): string {
        $msg = $data['msg'] ?? 'Unknown error';
        return is_string( $msg ) ? $msg : wp_json_encode( $msg );
    }
}
