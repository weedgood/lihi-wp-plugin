<?php
namespace Lihi\ShortUrl;

/**
 * Plugin configuration. Read via lihi_config( $key ).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'api_domain'      => 'https://app.lihidev.com',
    'redirect_domain' => 'redirect.lihidev.com',
    'auth_domain'     => 'https://w.lihidev.com',
];
