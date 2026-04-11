<?php
namespace Lihi\ShortUrl;

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
     * Reads email and API key from the plugin settings (lihi_email, lihi_api_key).
     *
     * @return string JWT token.
     * @throws RuntimeException If the API call fails or returns no token.
     */
    public function login(): string {
        $result = $this->client->login( lihi_email(), lihi_api_key() );
        $token  = $result['token'] ?? '';

        if ( ! $token ) {
            throw new \RuntimeException( __( 'No token returned from Lihi API.', 'lihi-shorturl' ) );
        }

        return $token;
    }

    /**
     * Return the Lihi short URL for a post, creating it if it does not yet exist.
     *
     * Checks whether a short link already exists for the given post ID via
     * get_short_links(). If found, returns the existing short_url. Otherwise
     * creates a new site and returns its short_url.
     *
     * @param int    $item_id WordPress post/attachment ID.
     * @param string $type    Post type (e.g. `post`, `page`, `attachment`), passed from the button's data-type attribute.
     * @return string short_url of the existing or newly created short link.
     * @throws RuntimeException If any API call fails.
     */
    public function get_or_create_short_url( int $item_id, string $type ): string {
        $result = $this->client->get_short_links( $type, $item_id );
        $sites  = $result['data']['sites']['data'] ?? [];

        foreach ( $sites as $site ) {
            if ( (int) ( $site['shopify_link']['type_id'] ?? 0 ) === $item_id ) {
                return $site['short_url'];
            }
        }

        $created = $this->client->create_site( [
            'urls'    => [ $this->resolve_url( $item_id, $type ) ],
            'type'    => $type,
            'type_id' => $item_id,
            'domain'  => '',
            'tags'    => '',
            'alias'   => '',
        ] );

        $short_url = $created['data']['short_url'] ?? '';

        if ( ! $short_url ) {
            throw new \RuntimeException( __( 'No short_url returned from Lihi API.', 'lihi-shorturl' ) );
        }

        return $short_url;
    }

    private function resolve_url( int $item_id, string $type ): string {
        return $type === 'attachment'
            ? wp_get_attachment_url( $item_id )
            : get_permalink( $item_id );
    }
}
