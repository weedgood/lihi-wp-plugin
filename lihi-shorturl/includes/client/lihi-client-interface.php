<?php
namespace Lihi\ShortUrl;

/**
 * Lihi API client contract.
 *
 * All client implementations (production and mock) must satisfy this interface.
 * Base URL: https://app.lihi.com/api/wordpress/v1 (production)
 *           https://app.lihidev.com/api/wordpress/v1 (non-production)
 *
 * Every method except login() requires a JWT $token obtained via login().
 * The service layer is responsible for acquiring and refreshing the token.
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
     * POST /api/wordpress/v1/login
     *
     * Email not found is auto-registered. Country only affects locale on first
     * registration (TW/HK → zh-TW, others → en).
     *
     * @param string $email   WordPress admin email.
     * @param string $api_key Lihi API key (server-side WORDPRESS_API_KEY).
     * @param string $country Optional. ISO country code, default 'TW'.
     * @return array{result: bool, token: string}
     */
    public function login( string $email, string $api_key, string $country = 'TW' ): array;

    // -------------------------------------------------------------------------
    // Posts（系統公告）
    // -------------------------------------------------------------------------

    /**
     * Retrieve system announcements visible to the current user.
     *
     * GET /api/wordpress/v1/posts
     *
     * @param string $token  JWT bearer token.
     * @param string $locale Optional. 'zh-TW' (default) or 'en'.
     * @return array{result: bool, data: list<array{id: int, title: string, body: string}>}
     */
    public function get_posts( string $token, string $locale = 'zh-TW' ): array;

    // -------------------------------------------------------------------------
    // Sites（短連結主體）
    // -------------------------------------------------------------------------

    /**
     * Retrieve sites with optional filters.
     *
     * GET /api/wordpress/v1/sites
     *
     * @param string $token  JWT bearer token.
     * @param array{
     *   type?:     string,
     *   type_id?:  string,
     *   per_page?: int,
     *   page?:     int,
     *   keyword?:  string,
     * } $params Query parameters.
     *
     * @return array{
     *   result: bool,
     *   data: array{
     *     domains: list<string>,
     *     total_sites: int,
     *     limit_sites: int,
     *     sites: array{
     *       current_page: int,
     *       total:        int,
     *       per_page:     int,
     *       data: list<array{
     *         id:             int,
     *         domain:         string,
     *         short_url:      string,
     *         wordpress_link: array{type: string, type_id: string},
     *         site_urls:      list<array{id: int, url: string}>,
     *         site_tags:      list<mixed>,
     *       }>,
     *     },
     *   },
     * }
     */
    public function get_sites( string $token, array $params = [] ): array;

    /**
     * Retrieve short links filtered by WordPress type and one or more type IDs.
     *
     * Convenience wrapper around GET /api/wordpress/v1/sites with per_page=20,
     * type, and type_id pre-filled.
     *
     * @param string               $token    JWT bearer token.
     * @param string               $type     Resource type (e.g. 'post', 'page', 'attachment').
     * @param int|string|list<int> $type_ids Single ID or comma-separated / array of IDs.
     *
     * @return array Same shape as get_sites().
     */
    public function get_short_links( string $token, string $type, $type_ids ): array;

    /**
     * Create a new site (short link).
     *
     * POST /api/wordpress/v1/sites
     *
     * @param string $token JWT bearer token.
     * @param array{
     *   urls:     list<string>,
     *   type:     string,
     *   type_id?: string|int,
     *   domain?:  string,
     *   tags?:    string,
     * } $body Request body. `tags` is a comma-separated string (e.g. "wordpress,blog").
     *
     * @return array{
     *   result: bool,
     *   data: array{
     *     id:             int,
     *     domain:         string,
     *     short_url:      string,
     *     site_urls:      list<array{id: int, url: string}>,
     *     wordpress_link: array{type: string, type_id: string},
     *   },
     * }
     */
    public function create_site( string $token, array $body ): array;

    /**
     * Batch-update the target URLs of all site_urls under a site.
     *
     * PUT /api/wordpress/v1/sites/{id}
     *
     * @param string $token JWT bearer token.
     * @param int    $id    Site ID.
     * @param array{
     *   urls: list<array{id: int, url: string}>,
     * } $body Request body.
     * @return array{result: bool}
     */
    public function update_site( string $token, int $id, array $body ): array;

    /**
     * Delete a site by ID (cascades to wordpress_link and site_urls).
     *
     * DELETE /api/wordpress/v1/sites/{id}
     *
     * @param string $token JWT bearer token.
     * @param int    $id    Site ID.
     * @return bool Always true; throws on failure.
     */
    public function delete_site( string $token, int $id ): bool;

    // -------------------------------------------------------------------------
    // Site URLs（個別分流連結）
    // -------------------------------------------------------------------------

    /**
     * Add a new target URL to an existing site.
     *
     * POST /api/wordpress/v1/site-urls
     *
     * @param string $token JWT bearer token.
     * @param array{site_id: string|int, url: string} $body Request body.
     * @return array{result: bool, data: array{id: int, site_id: int, url: string}}
     */
    public function create_site_url( string $token, array $body ): array;

    /**
     * Update a single site URL's target.
     *
     * PUT /api/wordpress/v1/site-urls/{id}
     *
     * @param string             $token JWT bearer token.
     * @param int                $id    Site URL ID.
     * @param array{url: string} $body  Request body.
     * @return array{result: bool, data: array{id: int, url: string}}
     */
    public function update_site_url( string $token, int $id, array $body ): array;

    /**
     * Delete a single site URL by ID.
     *
     * DELETE /api/wordpress/v1/site-urls/{id}
     *
     * @param string $token JWT bearer token.
     * @param int    $id    Site URL ID.
     * @return bool Always true; throws on failure.
     */
    public function delete_site_url( string $token, int $id ): bool;
}
