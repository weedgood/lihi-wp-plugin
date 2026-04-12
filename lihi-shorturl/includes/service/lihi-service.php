<?php
namespace Lihi\ShortUrl;

/**
 * Lihi service layer.
 *
 * Encapsulates business logic that sits between the HTTP client and WordPress hooks.
 * Methods throw RuntimeException on failure; callers are responsible for error handling.
 *
 * Token management: get_token() lazily checks the lihi_token cookie. If the token is
 * absent or expired it calls login() to obtain a fresh token and stores it in the cookie.
 * No login is triggered on page load — only when an API call is actually needed.
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
     * Uses the current WordPress user's email and the env-based API key.
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
     * On Lihi_Token_Invalid_Exception the cached token is discarded and the
     * call is retried once with a freshly obtained token.
     *
     * @param int    $item_id WordPress post/attachment ID.
     * @param string $type    Post type (e.g. `post`, `page`, `attachment`), passed from the button's data-type attribute.
     * @return string short_url of the existing or newly created short link.
     * @throws RuntimeException If any API call fails.
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
        $result = $this->client->get_short_links( $token, $type, $item_id );
        $sites  = $result['data']['sites']['data'] ?? [];

        foreach ( $sites as $site ) {
            if ( (string) ( $site['wordpress_link']['type_id'] ?? '' ) === (string) $item_id ) {
                return $site['short_url'];
            }
        }

        $host    = wp_parse_url( home_url(), PHP_URL_HOST );
        $created = $this->client->create_site( $token, [
            'urls'    => [ $this->resolve_url( $item_id, $type ) ],
            'type'    => $type,
            'type_id' => (string) $item_id,
            'domain'  => lihi_redirect_domain(),
            'tags'    => 'wordpress,' . $host . ',' . $type,
        ] );

        $short_url = $created['data']['short_url'] ?? '';

        if ( ! $short_url ) {
            throw new \RuntimeException( __( 'No short_url returned from Lihi API.', 'lihi-shorturl' ) );
        }

        return $short_url;
    }

    /**
     * Discard any cached token so the next get_token() call forces a fresh login.
     */
    private function invalidate_token(): void {
        $user_id       = get_current_user_id();
        $transient_key = 'lihi_token_' . $user_id;
        delete_transient( $transient_key );
        unset( $_COOKIE['lihi_token'] );
    }

    public function resolve_url( int $item_id, string $type ): string {
        return $type === 'attachment'
            ? wp_get_attachment_url( $item_id )
            : get_permalink( $item_id );
    }

    /**
     * Return a valid JWT token, logging in only when necessary.
     *
     * Lookup order (fast → slow):
     *   1. lihi_token cookie — cheapest, no DB hit.
     *   2. WordPress transient keyed by user ID — shared across concurrent
     *      requests so most of them never reach login().
     *   3. Atomic lock via wp_cache_add — only the first concurrent request
     *      that finds no transient calls login(); the rest wait up to 3 s and
     *      then re-read the transient. If the wait times out they fall back to
     *      calling login() themselves.
     *
     * @throws RuntimeException If login fails.
     */
    private function get_token(): string {
        // 1. Cookie still valid — fastest path, no DB.
        if ( $this->has_valid_token() ) {
            return $_COOKIE['lihi_token'];
        }

        $user_id       = get_current_user_id();
        $transient_key = 'lihi_token_' . $user_id;
        $lock_key      = 'lihi_token_lock_' . $user_id;

        // 2. Transient cache hit — no login needed.
        $cached = get_transient( $transient_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // 3a. Atomic lock: only the first request proceeds to login().
        $got_lock = wp_cache_add( $lock_key, 1, 'transient', 30 );

        if ( $got_lock ) {
            try {
                // Double-check: another request may have written the transient
                // between step 2 and acquiring the lock.
                $cached = get_transient( $transient_key );
                if ( false !== $cached ) {
                    return $cached;
                }

                $token = $this->login();
                $this->persist_token( $token, $transient_key );
                return $token;
            } finally {
                wp_cache_delete( $lock_key, 'transient' );
            }
        }

        // 3b. Did not get the lock — poll until the winner stores the token.
        $max_wait_us = 3_000_000; // 3 seconds
        $sleep_us    = 100_000;   // 0.1 seconds
        $waited      = 0;

        while ( $waited < $max_wait_us ) {
            usleep( $sleep_us );
            $waited += $sleep_us;

            $cached = get_transient( $transient_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // 3c. Fallback: winner never showed up — login independently and
        //     clear the stale lock so future requests don't keep waiting.
        $token = $this->login();
        $this->persist_token( $token, $transient_key );
        wp_cache_delete( $lock_key, 'transient' );
        return $token;
    }

    /**
     * Store a JWT in the WordPress transient cache and in the browser cookie.
     */
    private function persist_token( string $token, string $transient_key ): void {
        set_transient( $transient_key, $token, 10 * MINUTE_IN_SECONDS );

        setcookie( 'lihi_token', $token, [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => is_ssl(),
        ] );

        $_COOKIE['lihi_token'] = $token;
    }
}
