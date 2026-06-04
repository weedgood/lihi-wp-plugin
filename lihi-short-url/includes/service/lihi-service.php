<?php
namespace Lihi\ShortUrl;

/**
 * lihi service layer.
 *
 * Encapsulates business logic that sits between the HTTP client and WordPress hooks.
 * Methods throw RuntimeException on failure; callers are responsible for error handling.
 *
 * Token management: get_token() lazily reads the lihi_token transient. If the token is
 * absent it calls login() to obtain a fresh token and stores it in the transient.
 * No login is triggered on page load — only when an API call is actually needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Service {

    private Lihi_Client_Interface $client;
    private Lihi_Auth_Client_Interface $auth_client;
    private Lihi_Token_Store $tokens;

    public function __construct(
        Lihi_Client_Interface $client,
        Lihi_Auth_Client_Interface $auth_client,
        ?Lihi_Token_Store $tokens = null
    ) {
        $this->client      = $client;
        $this->auth_client = $auth_client;
        $this->tokens      = $tokens ?? new Lihi_Token_Store();
    }

    /**
     * Exchange the configured email for a fresh upstream lihi bearer token
     * via the lihi auth service.
     *
     * @return string Bearer token.
     * @throws RuntimeException If the auth call fails or returns no token.
     */
    public function login(): string {
        $result = $this->auth_client->login( lihi_email() );
        $token  = $result['token'] ?? '';

        if ( ! $token ) {
            throw new \RuntimeException( esc_html__( 'No token returned from lihi API.', 'lihi-short-url' ) );
        }

        return $token;
    }

    /**
     * Fetch the authenticated user's profile (role, plan end date, redirect domains).
     *
     * Uses the same token-then-call pattern as get_or_create_short_url():
     * on Lihi_Token_Invalid_Exception the cached token is discarded and the
     * call is retried once with a freshly obtained token.
     *
     * @return array{user_role: ?string, end_date: ?string, domains: list<string>}
     *
     * @throws Lihi_Auth_Exception       auth service rejected the login (email unverified).
     * @throws Lihi_Server_Exception     auth service or short-URL API unavailable.
     */
    public function get_profile(): array {
        $token = $this->get_token();
        try {
            $result = $this->client->get_profile( $token );
        } catch ( Lihi_Token_Invalid_Exception $e ) {
            $this->invalidate_token();
            $token  = $this->get_token();
            $result = $this->client->get_profile( $token );
        }

        return $result['data'] ?? [];
    }

    /**
     * Return the lihi short URL for a post, creating it if it does not yet exist.
     *
     * On Lihi_Token_Invalid_Exception the cached token is discarded and the
     * call is retried once with a freshly obtained token.
     */
    public function get_or_create_short_url( int $item_id, string $type ): string {
        $token = $this->get_token();
        try {
            return $this->fetch_or_create( $token, $item_id, $type );
        } catch ( Lihi_Token_Invalid_Exception $e ) {
            $this->invalidate_token();
            $token = $this->get_token();
            return $this->fetch_or_create( $token, $item_id, $type );
        }
    }

    /**
     * Core logic for get_or_create_short_url(); extracted so the retry path can reuse it.
     *
     * @throws Lihi_Token_Invalid_Exception propagated to trigger a retry.
     * @throws RuntimeException on other failures.
     */
    private function fetch_or_create( string $token, int $item_id, string $type ): string {
        $host     = wp_parse_url( home_url(), PHP_URL_HOST );
        $api_type = $type . ':' . $host;
        $result   = $this->client->get_short_links( $token, $api_type, $item_id );
        $sites    = $result['data']['sites']['data'] ?? [];

        foreach ( $sites as $site ) {
            if ( (string) ( $site['wordpress_link']['type_id'] ?? '' ) === (string) $item_id ) {
                return $site['short_url'];
            }
        }

        $created = $this->client->create_site( $token, [
            'urls'    => [ $this->resolve_url( $item_id, $type ) ],
            'type'    => $api_type,
            'type_id' => (string) $item_id,
            'domain'  => (string) get_option( 'lihi_domain', '' ),
            'tags'    => 'wordpress,' . $host . ',' . $type,
        ] );

        $short_url = $created['data']['short_url'] ?? '';

        if ( ! $short_url ) {
            throw new \RuntimeException( esc_html__( 'No short_url returned from lihi API.', 'lihi-short-url' ) );
        }

        return $short_url;
    }

    /**
     * Discard the cached token so the next get_token() call forces a fresh login.
     */
    private function invalidate_token(): void {
        $this->tokens->delete();
    }

    /**
     * Resolve the permalink / file URL for a given item.
     *
     * @throws \RuntimeException When the item does not exist or has no URL
     *   (wp_get_attachment_url()/get_permalink() return false).
     */
    public function resolve_url( int $item_id, string $type ): string {
        $url = $type === 'attachment'
            ? wp_get_attachment_url( $item_id )
            : get_permalink( $item_id );

        if ( ! is_string( $url ) || $url === '' ) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: 1: item type, 2: item ID */
                    esc_html__( 'Could not resolve URL for %1$s %2$d.', 'lihi-short-url' ),
                    esc_html( $type ),
                    absint( $item_id )
                )
            );
        }

        return $url;
    }

    /**
     * Return a valid JWT token, logging in only when necessary.
     *
     * Lookup order:
     *   1. WordPress transient — shared across concurrent requests so most of
     *      them never reach login().
     *   2. Atomic lock via wp_cache_add — only the first concurrent request
     *      that finds no transient calls login(); the rest wait up to 3 s and
     *      then re-read the transient. If the wait times out they fall back to
     *      calling login() themselves.
     *
     * @throws RuntimeException If login fails.
     */
    private function get_token(): string {
        $cached = $this->tokens->get();
        if ( false !== $cached ) {
            return $cached;
        }

        if ( $this->tokens->acquire_lock() ) {
            try {
                // Double-check: another request may have written the token
                // between the first read and acquiring the lock.
                $cached = $this->tokens->get();
                if ( false !== $cached ) {
                    return $cached;
                }

                $token = $this->login();
                $this->tokens->set( $token );
                return $token;
            } finally {
                $this->tokens->release_lock();
            }
        }

        // Did not get the lock — poll until the winner stores the token.
        $max_wait_us = 3_000_000;
        $sleep_us    = 100_000;
        $waited      = 0;

        while ( $waited < $max_wait_us ) {
            usleep( $sleep_us );
            $waited += $sleep_us;

            $cached = $this->tokens->get();
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Fallback: winner never showed up — login independently and clear the
        // stale lock so future requests don't keep waiting.
        $token = $this->login();
        $this->tokens->set( $token );
        $this->tokens->release_lock();
        return $token;
    }
}
