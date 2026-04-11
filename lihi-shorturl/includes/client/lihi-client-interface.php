<?php
namespace Lihi\ShortUrl;

/**
 * Lihi API client contract.
 *
 * All client implementations (production and mock) must satisfy this interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Lihi_Client_Interface {

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    /**
     * Authenticate and return a JWT token.
     *
     * POST /api/shopify/v1/login
     *
     * @param string $email   Shopify shop email (typically `{shop_id}@shopify.com`).
     * @param string $api_key Lihi API key.
     * @return array{token: string}
     */
    public function login( string $email, string $api_key ): array;

    // -------------------------------------------------------------------------
    // Posts
    // -------------------------------------------------------------------------

    /**
     * Retrieve all posts.
     *
     * GET /api/shopify/v1/posts
     *
     * @return array{data: list<array{id: int, title: string}>}
     */
    public function get_posts(): array;

    // -------------------------------------------------------------------------
    // Sites
    // -------------------------------------------------------------------------

    /**
     * Retrieve sites with optional filters.
     *
     * GET /api/shopify/v1/sites
     *
     * @param array{
     *   type?:     string,
     *   type_id?:  string,
     *   per_page?: int,
     *   keyword?:  string,
     * } $params Query parameters.
     *
     * @return array{
     *   data: array{
     *     domains: list<string>,
     *     sites: array{
     *       data:          list<array{
     *         id:           int,
     *         site_name:    string,
     *         short_url:    string,
     *         repeat_click: int,
     *         site_urls:    list<array{id: int, url: string, count: int}>,
     *         shopify_link: array{type: string, type_id: int},
     *       }>,
     *       prev_page_url: string|null,
     *       next_page_url: string|null,
     *     },
     *   },
     * }
     */
    public function get_sites( array $params = [] ): array;

    /**
     * Retrieve short links filtered by Shopify type and one or more type IDs.
     *
     * Convenience wrapper around GET /api/shopify/v1/sites with per_page=20,
     * type, and type_id pre-filled.
     *
     * @param string          $type     Resource type: `products`, `collections`, `pages`, or `customizations`.
     * @param int|string|list<int> $type_ids Single ID or comma-separated / array of IDs.
     *
     * @return array{
     *   data: array{
     *     domains: list<string>,
     *     sites: array{
     *       data:          list<array{
     *         id:           int,
     *         site_name:    string,
     *         short_url:    string,
     *         repeat_click: int,
     *         site_urls:    list<array{id: int, url: string, count: int}>,
     *         shopify_link: array{type: string, type_id: int},
     *       }>,
     *       prev_page_url: string|null,
     *       next_page_url: string|null,
     *     },
     *   },
     * }
     */
    public function get_short_links( string $type, $type_ids ): array;

    /**
     * Create a new site (short link).
     *
     * POST /api/shopify/v1/sites
     *
     * @param array{
     *   tags:     string,
     *   urls:     list<string>,
     *   alias:    string,
     *   domain:   string,
     *   type:     string,
     *   type_id?: int,
     * } $body Request body.
     *
     * @return array{
     *   data: array{
     *     id:           int,
     *     site_name:    string,
     *     short_url:    string,
     *     shopify_link: array{type: string, type_id: int},
     *   },
     * }
     */
    public function create_site( array $body ): array;

    /**
     * Delete a site by ID.
     *
     * DELETE /api/shopify/v1/sites/{id}
     *
     * @param int $id Site ID.
     * @return bool Always true; throws on failure.
     */
    public function delete_site( int $id ): bool;

    // -------------------------------------------------------------------------
    // Site URLs
    // -------------------------------------------------------------------------

    /**
     * Create a new site URL.
     *
     * POST /api/shopify/v1/site-urls
     *
     * @param array{site_id: string, url: string} $body Request body.
     *
     * @return array{data: array{id: int, url: string}}
     */
    public function create_site_url( array $body ): array;

    /**
     * Update an existing site URL.
     *
     * PUT /api/shopify/v1/site-urls/{id}
     *
     * @param int            $id   Site URL ID.
     * @param array{url: string} $body Request body.
     *
     * @return array<string, mixed> Updated site URL object (response not used by frontend).
     */
    public function update_site_url( int $id, array $body ): array;

    /**
     * Delete a site URL by ID.
     *
     * DELETE /api/shopify/v1/site-urls/{id}
     *
     * @param int $id Site URL ID.
     * @return bool Always true; throws on failure.
     */
    public function delete_site_url( int $id ): bool;
}
