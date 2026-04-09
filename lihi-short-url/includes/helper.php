<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function lihi_service(): Lihi_Service {
    static $instance = null;

    if ( $instance === null ) {
        $instance = new Lihi_Service( new Lihi_Client_Mock() );
    }

    return $instance;
}
