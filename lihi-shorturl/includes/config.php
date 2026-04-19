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
    'api_key'         => '2f294400a5d37c1578df3d1c923171d51e09e9e259e2ec64f781e0b3893ed0c5',
    'auth_domain'     => 'https://w.lihidev.com',
];
