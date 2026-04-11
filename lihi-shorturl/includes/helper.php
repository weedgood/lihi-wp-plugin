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
 * Return whether the plugin is running in test mode.
 *
 * @return bool True when LIHI_ENV is "test".
 */
function is_test(): bool {
    return defined( 'LIHI_ENV' ) && LIHI_ENV === 'test';
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
 * Return the shared Lihi_Client_Interface singleton.
 *
 * Passing an instance replaces the singleton (useful in tests).
 * Passing null resets it so it is recreated on the next call.
 *
 * @param Lihi_Client_Interface|null $inject Optional client to inject or null to reset.
 */
function lihi_client( ?Lihi_Client_Interface $inject = null ): Lihi_Client_Interface {
    static $instance = null;

    if ( func_num_args() > 0 ) {
        $instance = $inject;
    }

    if ( $instance === null ) {
        $instance = is_test()
            ? new Lihi_Client_Mock()
            : new Lihi_Client();
    }

    return $instance;
}

/**
 * Return the shared Lihi_Service singleton.
 *
 * Passing a Lihi_Service instance replaces the singleton (useful in tests).
 * Passing null resets the singleton so it is recreated on the next call.
 * Calling with no arguments returns the existing or newly created singleton.
 *
 * @param Lihi_Service|null $inject Optional service to inject or null to reset.
 */
function lihi_service( ?Lihi_Service $inject = null ): Lihi_Service {
    static $instance = null;

    if ( func_num_args() > 0 ) {
        $instance = $inject;
    }

    if ( $instance === null ) {
        $instance = new Lihi_Service( lihi_client() );
    }

    return $instance;
}
