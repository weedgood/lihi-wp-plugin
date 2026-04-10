<?php
namespace Lihi\ShortUrl;

/**
 * Global helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        $client   = ( defined( 'APP_ENV' ) && APP_ENV === 'test' )
            ? new Lihi_Client_Mock()
            : new Lihi_Client();
        $instance = new Lihi_Service( $client );
    }

    return $instance;
}
