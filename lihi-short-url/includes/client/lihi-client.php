<?php
namespace Lihi\ShortUrl;

/**
 * Production lihi Wordpress API client.
 *
 * Sends HTTP requests to the lihi Wordpress API using WordPress's
 * wp_remote_request(). Base URL and site UUID are injected by the caller so
 * this client does not read plugin config or option-backed stores directly.
 * Auth endpoints live under the same /api/wordpress/v1 namespace as the
 * bearer-token short-URL endpoints.
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
    private string $uuid;

    public function __construct( string $base_url, string $uuid ) {
        $this->base_url = rtrim( $base_url, '/' );
        $this->uuid     = $uuid;
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public function update_email( string $email ): array {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'POST', '/api/wordpress/v1/auth/update-email', $this->auth_payload( [
            'email' => $email,
        ] ) );
        $data = $this->decode( $code, $body, false );

        if ( $code === 400 ) {
            throw new Lihi_Validation_Exception( esc_html( $this->msg( $data ) ) );
        }
        if ( $code === 429 ) {
            throw new Lihi_Rate_Limit_Exception( esc_html( $this->msg( $data ) ) );
        }
        if ( $code >= 500 ) {
            throw new Lihi_Server_Exception( esc_html( $this->msg( $data ) ) );
        }

        $payload = $data['data'] ?? [];
        return is_array( $payload ) ? $payload : [];
    }

    public function login( string $email ): array {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'POST', '/api/wordpress/v1/auth/login', $this->auth_payload( [
            'email'     => $email,
            'is_mobile' => wp_is_mobile(),
        ] ) );
        $data = $this->decode( $code, $body, false );

        if ( $code === 400 ) {
            throw new Lihi_Validation_Exception( esc_html( $this->msg( $data ) ) );
        }
        if ( $code === 403 ) {
            throw new Lihi_Auth_Exception( esc_html( $this->msg( $data ) ) );
        }
        if ( $code >= 500 || empty( $data['result'] ) ) {
            throw new Lihi_Server_Exception( esc_html( $this->msg( $data ) ) );
        }

        $payload = $data['data'] ?? [];
        return is_array( $payload ) ? $payload : [];
    }

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------

    /** @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception */
    public function get_profile( string $token ): array {
        [ 'code' => $code, 'body' => $body ] = $this->request( 'GET', '/api/wordpress/v1/profile', [], $token );
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
            throw new Lihi_Validation_Exception( esc_html( $this->msg( $data ) ) );
        }

        return $data;
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
            throw new Lihi_Server_Exception( esc_html( $response->get_error_message() ) );
        }

        return [
            'code' => wp_remote_retrieve_response_code( $response ),
            'body' => wp_remote_retrieve_body( $response ),
        ];
    }

    /**
     * Add site identity to auth payloads.
     */
    private function auth_payload( array $body ): array {
        return array_merge( $body, [
            'hostname' => $this->tenant_host(),
            'uuid'     => $this->uuid,
        ] );
    }

    private function tenant_host(): string {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return is_string( $host ) ? $host : '';
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
            $message = sprintf( 'HTTP %d: %s', $code, $detail );

            if ( $code === 404 ) {
                throw new Lihi_Not_Found_Exception( esc_html( $message ) );
            }

            if ( $authenticated && $title === '網站升級中...' ) {
                throw new Lihi_Token_Invalid_Exception( esc_html( $message ) );
            }

            throw new Lihi_Server_Exception( esc_html( $message ) );
        }

        return $decoded;
    }

    /**
     * Extract a human-readable message from a decoded error response.
     */
    private function msg( array $data ): string {
        $msg = $data['msg'] ?? ( $data['data']['message'] ?? 'Unknown error' );
        return is_string( $msg ) ? $msg : wp_json_encode( $msg );
    }
}
