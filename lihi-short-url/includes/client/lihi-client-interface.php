<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Lihi_Client_Interface {

    public function login( string $email, string $api_key ): array;

    public function get_posts(): array;

    public function get_sites( array $params = [] ): array;

    public function create_site( array $body ): array;

    public function create_site_url( array $body ): array;

    public function update_site_url( int $id, array $body ): array;

    public function delete_site_url( int $id ): bool;
}
