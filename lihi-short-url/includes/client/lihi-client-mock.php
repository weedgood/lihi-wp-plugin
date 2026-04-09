<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Client_Mock implements Lihi_Client_Interface {

    public function login( string $email, string $api_key ): array {
        $b64 = fn( $data ) => rtrim( strtr( base64_encode( json_encode( $data ) ), '+/', '-_' ), '=' );

        $header  = $b64( [ 'alg' => 'HS256', 'typ' => 'JWT' ] );
        $payload = $b64( [ 'sub' => 'mock-user', 'email' => $email, 'exp' => time() + 60 ] );

        return [ 'token' => "{$header}.{$payload}.mock-signature" ];
    }

    public function get_posts(): array {
        return [
            [ 'id' => 1, 'title' => 'Mock Post 1' ],
            [ 'id' => 2, 'title' => 'Mock Post 2' ],
        ];
    }

    public function get_sites( array $params = [] ): array {
        return [
            [ 'id' => 1, 'name' => 'Mock Site 1', 'url' => 'https://mock-site-1.example.com' ],
            [ 'id' => 2, 'name' => 'Mock Site 2', 'url' => 'https://mock-site-2.example.com' ],
        ];
    }

    public function create_site( array $body ): array {
        return array_merge( [ 'id' => 99 ], $body );
    }

    public function create_site_url( array $body ): array {
        return array_merge( [ 'id' => 99 ], $body );
    }

    public function update_site_url( int $id, array $body ): array {
        return array_merge( [ 'id' => $id ], $body );
    }

    public function delete_site_url( int $id ): bool {
        return true;
    }
}
