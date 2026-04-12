<?php
namespace Lihi\ShortUrl;

/**
 * Storage and locking for the shared Lihi JWT.
 *
 * Wraps the underlying WordPress transient + wp_cache lock so no other code
 * needs to know the cache keys. The token is site-scoped (one Lihi account per
 * site), so a single shared entry is correct.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Token_Store {

    private const TRANSIENT_KEY = 'lihi_token';
    private const LOCK_KEY      = 'lihi_token_lock';
    private const LOCK_TTL      = 30;

    /** @return string|false The cached token, or false on cache miss. */
    public function get() {
        return get_transient( self::TRANSIENT_KEY );
    }

    public function set( string $token ): void {
        set_transient( self::TRANSIENT_KEY, $token, DAY_IN_SECONDS );
    }

    public function delete(): void {
        delete_transient( self::TRANSIENT_KEY );
    }

    /** Try to acquire the login lock. Returns true when this caller is the winner. */
    public function acquire_lock(): bool {
        return wp_cache_add( self::LOCK_KEY, 1, 'transient', self::LOCK_TTL );
    }

    public function release_lock(): void {
        wp_cache_delete( self::LOCK_KEY, 'transient' );
    }

    /**
     * Delete the cached token under the login lock so an in-flight login()
     * finishes writing before we clear it — otherwise the winner could
     * set_transient() after our delete and strand a stale JWT.
     *
     * @param int $max_wait_us Upper bound on how long to wait for the lock, in microseconds.
     */
    public function flush( int $max_wait_us = 3_000_000 ): void {
        $sleep_us = 100_000;
        $waited   = 0;

        while ( ! $this->acquire_lock() && $waited < $max_wait_us ) {
            usleep( $sleep_us );
            $waited += $sleep_us;
        }

        $this->delete();
        $this->release_lock();
    }
}
