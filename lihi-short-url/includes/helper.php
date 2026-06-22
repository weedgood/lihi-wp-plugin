<?php
namespace Lihi\ShortUrl;

/**
 * Global helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/lihi-singletons.php';

/**
 * Return a configuration value loaded from includes/config.php.
 *
 * The config file is required once and cached for the request lifetime.
 *
 * @param string $key Configuration key (e.g. "api_domain").
 * @return mixed Value for the key, or null if the key is unknown.
 */
function lihi_config( string $key ) {
    static $cfg = null;
    if ( $cfg === null ) {
        $cfg = require __DIR__ . '/config.php';
    }
    return $cfg[ $key ] ?? null;
}

/**
 * Return the email address used to authenticate with the lihi API.
 */
function lihi_email(): string {
    return (string) get_option( 'lihi_email', '' );
}

/**
 * Return the site-scoped lihi UUID, creating it when missing.
 */
function lihi_uuid(): string {
    return Lihi_Singletons::lihi_uuid_store()->get();
}

/**
 * Return the current WordPress site's host.
 *
 * @throws \RuntimeException When home_url() has no usable host.
 */
function lihi_site_host(): string {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );
    if ( ! is_string( $host ) || $host === '' ) {
        throw new \RuntimeException( esc_html__( 'Could not resolve WordPress site host.', 'lihi-short-url' ) );
    }

    return $host;
}

/**
 * Return the browser-facing passthrough redirect endpoint URL.
 */
function lihi_passthrough_redirect_url(): string {
    $base_url = lihi_config( 'api_domain' );
    if ( ! is_string( $base_url ) || $base_url === '' ) {
        return '';
    }

    return rtrim( $base_url, '/' ) . '/api/wordpress/v1/passthrough/redirect';
}

/**
 * Resolve the permalink / file URL for a given item.
 *
 * @throws \RuntimeException When the item does not exist or has no URL.
 */
function lihi_resolve_url( int $item_id, string $type ): string {
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
