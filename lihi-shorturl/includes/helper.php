<?php
namespace Lihi\ShortUrl;

/**
 * Global helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return whether the plugin is running in production mode.
 *
 * @return bool True when LIHI_ENV is "production".
 */
function is_production(): bool {
    return defined( 'LIHI_ENV' ) && LIHI_ENV === 'production';
}

/**
 * Return the Lihi API base URL for the current environment.
 *
 * @return string "https://app.lihi.com" in production, "https://app.lihidev.com" otherwise.
 */
function lihi_api_domain(): string {
    return is_production()
        ? 'https://app.lihi.com'
        : 'https://app.lihidev.com';
}

/**
 * Return the Lihi redirect domain for the current environment.
 *
 * @return string "redirect.lihi.com" in production, "redirect.lihidev.com" otherwise.
 */
function lihi_redirect_domain(): string {
    return is_production()
        ? 'redirect.lihi.com'
        : 'redirect.lihidev.com';
}

/**
 * Return the email address used to authenticate with the Lihi API.
 *
 * Reads the value stored in the plugin settings (Options API).
 *
 * @return string Email address.
 */
function lihi_email(): string {
    return (string) get_option( 'lihi_email', '' );
}

/**
 * Return the Lihi API key for the current environment.
 *
 * @return string Shared dev key in non-production; empty string in production.
 */
function lihi_api_key(): string {
    return is_production()
        ? ''
        : '2f294400a5d37c1578df3d1c923171d51e09e9e259e2ec64f781e0b3893ed0c5';
}

/**
 * Internal singleton store. Keyed by class/interface name.
 *
 * @param string      $key     Store key.
 * @param object|null $replace If provided (even as null), replaces the stored value.
 * @param bool        $has_arg Whether a replacement was provided (to distinguish "set to null" from "read").
 */
function _lihi_singleton( string $key, ?object $replace = null, bool $has_arg = false ): ?object {
    static $store = [];

    if ( $has_arg ) {
        $store[ $key ] = $replace;
    }

    return $store[ $key ] ?? null;
}

/**
 * Return the shared Lihi_Client_Interface singleton, creating it on first call.
 */
function lihi_client(): Lihi_Client_Interface {
    $instance = _lihi_singleton( Lihi_Client_Interface::class );

    if ( ! $instance instanceof Lihi_Client_Interface ) {
        $instance = new Lihi_Client();
        _lihi_singleton( Lihi_Client_Interface::class, $instance, true );
    }

    return $instance;
}

/**
 * Replace (or reset, by passing null) the Lihi_Client_Interface singleton. Test helper.
 */
function lihi_client_set( ?Lihi_Client_Interface $client ): void {
    _lihi_singleton( Lihi_Client_Interface::class, $client, true );
}

/**
 * Return the shared Lihi_Service singleton, creating it on first call.
 */
function lihi_service(): Lihi_Service {
    $instance = _lihi_singleton( Lihi_Service::class );

    if ( ! $instance instanceof Lihi_Service ) {
        $instance = new Lihi_Service( lihi_client() );
        _lihi_singleton( Lihi_Service::class, $instance, true );
    }

    return $instance;
}

/**
 * Replace (or reset, by passing null) the Lihi_Service singleton. Test helper.
 */
function lihi_service_set( ?Lihi_Service $service ): void {
    _lihi_singleton( Lihi_Service::class, $service, true );
}
