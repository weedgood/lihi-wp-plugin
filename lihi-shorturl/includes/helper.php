<?php
namespace Lihi\ShortUrl;

/**
 * Global helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return a configuration value loaded from includes/config.php.
 *
 * The config file is required once and cached for the request lifetime.
 *
 * @param string $key Configuration key (e.g. "api_domain", "auth_domain").
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
 *
 * Reads the value stored in the plugin settings (Options API).
 *
 * @return string Email address.
 */
function lihi_email(): string {
    return (string) get_option( 'lihi_email', '' );
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
 * Return the shared Lihi_Auth_Client_Interface singleton, creating it on first call.
 */
function lihi_auth_client(): Lihi_Auth_Client_Interface {
    $instance = _lihi_singleton( Lihi_Auth_Client_Interface::class );

    if ( ! $instance instanceof Lihi_Auth_Client_Interface ) {
        $instance = new Lihi_Auth_Client();
        _lihi_singleton( Lihi_Auth_Client_Interface::class, $instance, true );
    }

    return $instance;
}

/**
 * Replace (or reset, by passing null) the Lihi_Auth_Client_Interface singleton. Test helper.
 */
function lihi_auth_client_set( ?Lihi_Auth_Client_Interface $client ): void {
    _lihi_singleton( Lihi_Auth_Client_Interface::class, $client, true );
}

/**
 * Return the shared Lihi_Token_Store singleton, creating it on first call.
 */
function lihi_token_store(): Lihi_Token_Store {
    $instance = _lihi_singleton( Lihi_Token_Store::class );

    if ( ! $instance instanceof Lihi_Token_Store ) {
        $instance = new Lihi_Token_Store();
        _lihi_singleton( Lihi_Token_Store::class, $instance, true );
    }

    return $instance;
}

/**
 * Replace (or reset, by passing null) the Lihi_Token_Store singleton. Test helper.
 */
function lihi_token_store_set( ?Lihi_Token_Store $store ): void {
    _lihi_singleton( Lihi_Token_Store::class, $store, true );
}

/**
 * Return the shared Lihi_Service singleton, creating it on first call.
 */
function lihi_service(): Lihi_Service {
    $instance = _lihi_singleton( Lihi_Service::class );

    if ( ! $instance instanceof Lihi_Service ) {
        $instance = new Lihi_Service( lihi_client(), lihi_auth_client(), lihi_token_store() );
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
