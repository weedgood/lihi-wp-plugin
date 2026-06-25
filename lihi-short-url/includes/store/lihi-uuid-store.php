<?php
namespace Lihi\ShortUrl;

/**
 * Storage for the site-scoped lihi UUID.
 *
 * Wraps the underlying WordPress option so no other code needs to know the
 * option keys. The UUID is persistent site identity, so it intentionally uses
 * an option rather than the transient-backed token store. The first-use lock
 * is also option-backed so it works without a persistent object cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Uuid_Store {

    private const OPTION_KEY = 'lihi_uuid';
    private const LOCK_KEY   = 'lihi_uuid_lock';
    private const LOCK_TTL   = 30;

    private string $lock_value = '';

    /**
     * Return the site UUID, creating it on first use.
     *
     * @param int $max_wait_us Upper bound on how long to wait for another
     *     request to finish generating the UUID, in microseconds.
     *
     * @throws Lihi_Server_Exception When the UUID cannot be initialized.
     */
    public function get( int $max_wait_us = 3_000_000 ): string {
        $uuid = $this->read_uuid();
        if ( $uuid !== '' ) {
            return $uuid;
        }

        $locked = $this->acquire_lock();
        if ( ! $locked ) {
            $uuid = $this->wait_for_uuid( $max_wait_us );
            if ( $uuid !== '' ) {
                return $uuid;
            }

            $locked = $this->acquire_lock();
        }

        if ( ! $locked ) {
            throw new Lihi_Server_Exception( esc_html( 'Unable to initialize lihi site UUID.' ) );
        }

        try {
            return $this->create_or_replace_uuid();
        } finally {
            if ( $locked ) {
                $this->release_lock();
            }
        }
    }

    private function read_uuid(): string {
        $uuid = (string) get_option( self::OPTION_KEY, '' );
        if ( $this->is_uuid_v4( $uuid ) ) {
            return $uuid;
        }

        return '';
    }

    private function create_or_replace_uuid(): string {
        $stored = (string) get_option( self::OPTION_KEY, '' );
        if ( $this->is_uuid_v4( $stored ) ) {
            return $stored;
        }

        $uuid = wp_generate_uuid4();
        if ( $stored === '' ) {
            add_option( self::OPTION_KEY, $uuid, '', 'no' );
        } else {
            update_option( self::OPTION_KEY, $uuid, false );
        }

        $stored = $this->read_uuid();
        if ( $stored !== '' ) {
            return $stored;
        }

        throw new Lihi_Server_Exception( esc_html( 'Unable to persist lihi site UUID.' ) );
    }

    private function wait_for_uuid( int $max_wait_us ): string {
        $sleep_us = 100_000;
        $waited   = 0;

        while ( $waited < $max_wait_us ) {
            usleep( $sleep_us );
            $waited += $sleep_us;

            $uuid = $this->read_uuid();
            if ( $uuid !== '' ) {
                return $uuid;
            }
        }

        return '';
    }

    private function acquire_lock(): bool {
        $value = time() . ':' . uniqid( '', true );
        if ( add_option( self::LOCK_KEY, $value, '', 'no' ) ) {
            $this->lock_value = $value;
            return true;
        }

        $stored = (string) get_option( self::LOCK_KEY, '' );
        $locked_at = (int) strtok( $stored, ':' );
        if ( $locked_at > 0 && $locked_at + self::LOCK_TTL < time() ) {
            delete_option( self::LOCK_KEY );
            if ( add_option( self::LOCK_KEY, $value, '', 'no' ) ) {
                $this->lock_value = $value;
                return true;
            }
        }

        return false;
    }

    private function release_lock(): void {
        if ( $this->lock_value === '' ) {
            return;
        }

        if ( (string) get_option( self::LOCK_KEY, '' ) === $this->lock_value ) {
            delete_option( self::LOCK_KEY );
        }

        $this->lock_value = '';
    }

    private function is_uuid_v4( string $uuid ): bool {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        ) === 1;
    }
}
