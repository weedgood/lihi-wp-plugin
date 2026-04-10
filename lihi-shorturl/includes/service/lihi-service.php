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

    /**
     * Return the Lihi short URL for a post, creating it if it does not yet exist.
     *
     * Checks whether a short link already exists for the given post ID via
     * get_short_links(). If found, returns the existing site_name. Otherwise
     * creates a new site and returns its site_name.
     *
     * @param int    $post_id WordPress post ID.
     * @param string $type    Post type (e.g. `post`, `page`), passed from the button's data-type attribute.
     * @return string site_name of the existing or newly created short link.
     * @throws RuntimeException If any API call fails.
     */
    public function get_or_create_short_url( int $post_id, string $type ): string {
        $result = $this->client->get_short_links( $type, $post_id );
        $sites  = $result['data']['sites']['data'] ?? [];

        foreach ( $sites as $site ) {
            if ( (int) ( $site['shopify_link']['type_id'] ?? 0 ) === $post_id ) {
                return $site['site_name'];
            }
        }

        $created = $this->client->create_site( [
            'urls'    => [ get_permalink( $post_id ) ],
            'type'    => $type,
            'type_id' => $post_id,
            'domain'  => '',
            'tags'    => '',
            'alias'   => '',
        ] );

        $site_name = $created['data']['site_name'] ?? '';

        if ( ! $site_name ) {
            throw new RuntimeException( __( 'No site_name returned from Lihi API.', 'lihi-shorturl' ) );
        }

        return $site_name;
    }
}
