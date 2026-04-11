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
 * @return bool True when APP_ENV is "production".
 */
function is_production(): bool {
    return defined( 'APP_ENV' ) && APP_ENV === 'production';
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
 * Return the configured Lihi account email.
 *
 * @return string Email address, empty string if not set.
 */
function option_email(): string {
    return (string) get_option( 'lihi_email', '' );
}

/**
 * Return the configured Lihi API key.
 *
 * @return string API key, empty string if not set.
 */
function option_api_key(): string {
    return (string) get_option( 'lihi_api_key', '' );
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
        $client   = is_production()
            ? new Lihi_Client()
            : new Lihi_Client_Mock();
        $instance = new Lihi_Service( $client );
    }

    return $instance;
}
