<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Service {

    private Lihi_Client_Interface $client;

    public function __construct( Lihi_Client_Interface $client ) {
        $this->client = $client;
    }

    public function login( string $email, string $api_key ): bool {
        $result = $this->client->login( $email, $api_key );
        $token  = $result['token'] ?? '';

        if ( ! $token ) {
            return false;
        }

        setcookie( 'lihi_token', $token, [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ] );

        return true;
    }
}
