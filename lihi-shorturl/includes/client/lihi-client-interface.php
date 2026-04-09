<?php
/**
 * Lihi API client contract.
 *
 * All client implementations (production and mock) must satisfy this interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Lihi_Client_Interface {

    /** Authenticate and return a response containing a JWT token. */
    public function login( string $email, string $api_key ): array;

    /** Retrieve all posts. */
    public function get_posts(): array;

    /** Retrieve sites, optionally filtered by $params. */
    public function get_sites( array $params = [] ): array;

    /** Create a new site. */
    public function create_site( array $body ): array;

    /** Create a new site URL. */
    public function create_site_url( array $body ): array;

    /** Update an existing site URL by ID. */
    public function update_site_url( int $id, array $body ): array;

    /** Delete a site URL by ID. */
    public function delete_site_url( int $id ): bool;
}
