<?php
/**
 * Global helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return the shared Lihi_Service singleton.
 *
 * Uses a static variable so the same instance is reused across the request lifecycle.
 */
function lihi_service(): Lihi_Service {
    static $instance = null;

    if ( $instance === null ) {
        $instance = new Lihi_Service( new Lihi_Client_Mock() );
    }

    return $instance;
}
