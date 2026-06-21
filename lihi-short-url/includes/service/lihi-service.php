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
    private Lihi_Token_Store $tokens;

    public function __construct(
        Lihi_Client_Interface $client,
        Lihi_Token_Store $tokens
    ) {
        $this->client = $client;
        $this->tokens = $tokens;
    }

    /**
     * Exchange the configured email for a fresh upstream lihi bearer token
     * via the lihi Wordpress API auth endpoint.
     *
     * @return string Bearer token.
     * @throws RuntimeException If the auth call fails or returns no token.
     */
    public function login( string $email ): string {
        $result = $this->client->login( $email );
        $token  = $result['token'] ?? '';

        if ( ! $token ) {
            throw new \RuntimeException( esc_html__( 'No token returned from lihi API.', 'lihi-short-url' ) );
        }

        return $token;
    }

    /**
     * Fetch the authenticated user's profile (role, plan end date, redirect domains).
     *
     * Uses the same token-then-call pattern as get_or_create_short_url().
     * Lihi_User_Invalid_Exception clears the cached token and is surfaced so
     * the next AJAX request can perform a fresh login.
     *
     * @return array{user_role: ?string, end_date: ?string, domains: list<string>}
     *
     * @throws Lihi_Auth_Exception       lihi API rejected the login (email unverified).
     * @throws Lihi_User_Invalid_Exception lihi account is unavailable server-side.
     * @throws Lihi_Server_Exception     lihi API unavailable.
     */
    public function get_profile(): array {
        $email = lihi_email();
        $token = $this->get_token( $email );
        try {
            $result = $this->client->get_profile( $token );
        } catch ( Lihi_User_Invalid_Exception $e ) {
            $this->invalidate_token();
            throw $e;
        } catch ( Lihi_Token_Invalid_Exception $e ) {
            $this->invalidate_token();
            $token  = $this->get_token( $email );
            $result = $this->client->get_profile( $token );
        }

        return $result['data'] ?? [];
    }

    /**
     * Return the lihi short URL for a post, creating it if it does not yet exist.
     *
     * On Lihi_User_Invalid_Exception the cached token is discarded and the
     * exception is surfaced so the next AJAX request can perform a fresh login.
     *
     * On Lihi_Token_Invalid_Exception the cached token is discarded and the
     * call is retried once with a freshly obtained token.
     */
    public function get_or_create_short_url( int $item_id, string $type, array $options = [] ): string {
        $email = lihi_email();
        $token = $this->get_token( $email );
        try {
            return $this->fetch_or_create( $token, $item_id, $type, $options );
        } catch ( Lihi_User_Invalid_Exception $e ) {
            $this->invalidate_token();
            throw $e;
        } catch ( Lihi_Token_Invalid_Exception $e ) {
            $this->invalidate_token();
            $token = $this->get_token( $email );
            return $this->fetch_or_create( $token, $item_id, $type, $options );
        }
    }

    public function get_existing_short_url( int $item_id, string $type ): string {
        $email = lihi_email();
        $token = $this->get_token( $email );
        try {
            return $this->fetch_existing( $token, $item_id, $type );
        } catch ( Lihi_User_Invalid_Exception $e ) {
            $this->invalidate_token();
            throw $e;
        } catch ( Lihi_Token_Invalid_Exception $e ) {
            $this->invalidate_token();
            $token = $this->get_token( $email );
            return $this->fetch_existing( $token, $item_id, $type );
        }
    }

    /**
     * Core logic for get_or_create_short_url(); extracted so the retry path can reuse it.
     *
     * @throws Lihi_Token_Invalid_Exception propagated to trigger a retry.
     * @throws RuntimeException on other failures.
     */
    private function fetch_or_create( string $token, int $item_id, string $type, array $options ): string {
        $host     = lihi_site_host();
        $api_type = $type . ':' . $host;
        $result   = $this->client->get_short_links( $token, $api_type, $item_id );
        $sites    = $result['data']['sites']['data'] ?? [];

        $existing = $this->find_existing_short_url( $sites, $item_id );
        if ( $existing !== '' ) {
            return $existing;
        }

        $domain = isset( $options['domain'] ) && is_string( $options['domain'] )
            ? $options['domain']
            : '';
        $tags = $this->build_tags( $host, $type, $options['tags'] ?? [] );
        $utm  = $this->normalize_utm( $options['utm'] ?? [] );
        $url  = $this->apply_utm_to_url( lihi_resolve_url( $item_id, $type ), $utm );

        $body = [
            'urls'    => [ $url ],
            'type'    => $api_type,
            'type_id' => (string) $item_id,
            'domain'  => $domain,
            'tags'    => implode( ',', $tags ),
        ];

        $created = $this->client->create_site( $token, $body );

        $short_url = $created['data']['short_url'] ?? '';

        if ( ! $short_url ) {
            throw new \RuntimeException( esc_html__( 'No short_url returned from lihi API.', 'lihi-short-url' ) );
        }

        return $short_url;
    }

    private function fetch_existing( string $token, int $item_id, string $type ): string {
        $host     = lihi_site_host();
        $api_type = $type . ':' . $host;
        $result   = $this->client->get_short_links( $token, $api_type, $item_id );
        $sites    = $result['data']['sites']['data'] ?? [];
        $existing = $this->find_existing_short_url( $sites, $item_id );

        if ( $existing === '' ) {
            throw new Lihi_Not_Found_Exception( esc_html__( 'Short URL has been removed. Please create it again.', 'lihi-short-url' ) );
        }

        return $existing;
    }

    private function find_existing_short_url( array $sites, int $item_id ): string {
        foreach ( $sites as $site ) {
            if ( (string) ( $site['wordpress_link']['type_id'] ?? '' ) === (string) $item_id ) {
                return (string) ( $site['short_url'] ?? '' );
            }
        }

        return '';
    }

    private function build_tags( string $host, string $type, array $custom_tags ): array {
        $tags = [ 'wordpress', $host, $type ];

        foreach ( $custom_tags as $tag ) {
            if ( ! is_scalar( $tag ) ) {
                continue;
            }

            $tag = trim( (string) $tag );
            if ( $tag !== '' ) {
                $tags[] = $tag;
            }
        }

        return array_values( array_unique( $tags ) );
    }

    private function normalize_utm( $utm ): array {
        if ( ! is_array( $utm ) ) {
            return [];
        }

        $normalized = [];
        foreach ( [ 'source', 'medium', 'campaign', 'term', 'content' ] as $key ) {
            if ( ! isset( $utm[ $key ] ) || ! is_scalar( $utm[ $key ] ) ) {
                continue;
            }

            $value = trim( (string) $utm[ $key ] );
            if ( $value !== '' ) {
                $normalized[ $key ] = $value;
            }
        }

        return $normalized;
    }

    private function apply_utm_to_url( string $url, array $utm ): string {
        if ( $utm === [] ) {
            return $url;
        }

        $params = [];
        foreach ( $utm as $key => $value ) {
            $params[ 'utm_' . $key ] = $value;
        }

        return add_query_arg( $params, $url );
    }

    /**
     * Discard the cached token so the next get_token() call forces a fresh login.
     */
    private function invalidate_token(): void {
        $this->tokens->delete();
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
    private function get_token( string $email ): string {
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

                $token = $this->login( $email );
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
        $token = $this->login( $email );
        $this->tokens->set( $token );
        $this->tokens->release_lock();
        return $token;
    }
}
