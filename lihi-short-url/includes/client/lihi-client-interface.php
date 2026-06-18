<?php
namespace Lihi\ShortUrl;

/**
 * lihi Wordpress API client contract.
 *
 * Base URL: injected by the caller, typically lihi_config( 'api_domain' ).
 * Mirrors the endpoints wired in lihi-admin's `wordpress/v1` group
 * (`routes/api.php`). Auth now lives under the same API namespace and keeps
 * the `/auth` route prefix.
 *
 * `SiteController::update` / `destroy` exist in source but are not routed,
 * and `/posts` / `/site-urls` do not exist in this group — so they are
 * intentionally absent from this interface. `POST /mail` (legacy api_key mail
 * sender) has no plugin use and is omitted here.
 *
 * JWT methods require a bearer token obtained from `login()`. The service
 * layer is responsible for acquiring and refreshing the token. The site UUID
 * used by auth payloads is also injected by the caller.
 *
 * Plugin usage note: the current plugin only calls the two Site endpoints
 * (`get_sites` via `get_short_links`, and `create_site`). `get_profile` is
 * kept here to keep the contract aligned with the service surface.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Lihi_Client_Interface {

    // -------------------------------------------------------------------------
    // Auth (no bearer token)
    // -------------------------------------------------------------------------

    /**
     * Start or refresh email verification for the current tenant.
     *
     * POST /api/wordpress/v1/auth/update-email (AuthController@updateEmail)
     *
     * @param string $email Email to verify.
     * @return array{verified: bool}
     *
     * @throws Lihi_Validation_Exception on HTTP 400.
     * @throws Lihi_Rate_Limit_Exception on HTTP 429.
     * @throws Lihi_Server_Exception on HTTP 500 or network error.
     */
    public function update_email( string $email ): array;

    /**
     * Exchange a verified email for a fresh bearer token.
     *
     * POST /api/wordpress/v1/auth/login (AuthController@login)
     *
     * @param string $email Verified tenant email.
     * @return array{token: string}
     *
     * @throws Lihi_Validation_Exception on HTTP 400.
     * @throws Lihi_Auth_Exception on HTTP 403.
     * @throws Lihi_Server_Exception on HTTP 500, network error, or result:false.
     */
    public function login( string $email ): array;

    // -------------------------------------------------------------------------
    // Profile (jwt)
    // -------------------------------------------------------------------------

    /**
     * Current user's role, plan expiry, and accessible redirect domains.
     *
     * GET /api/wordpress/v1/profile (AuthController@profile)
     *
     * Tenant is resolved via the bearer token's `sub` claim; no query params.
     * Not used by the plugin; kept here for contract completeness.
     *
     * @param string $token JWT bearer token.
     *
     * @return array{
     *   result: bool,
     *   data: array{
     *     user_role: ?string,
     *     end_date:  ?string,
     *     domains:   list<string>,
     *   },
     * }
     *
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function get_profile( string $token ): array;

    // -------------------------------------------------------------------------
    // Sites (jwt)
    // -------------------------------------------------------------------------

    /**
     * Retrieve sites with optional filters.
     *
     * GET /api/wordpress/v1/sites (SiteController@index)
     *
     * @param string $token JWT bearer token.
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
     *     sites: array{
     *       current_page: int,
     *       total:        int,
     *       per_page:     int,
     *       data: list<array{
     *         id:             int,
     *         domain_name:    string,
     *         short_url:      string,
     *         wordpress_link: array{type: string, type_id: string},
     *         site_urls:      list<array{id: int, url: string}>,
     *         site_tags:      list<mixed>,
     *       }>,
     *     },
     *     domains:     list<array{id: mixed, name: string}>,
     *     total_sites: int,
     *     limit_sites: int,
     *   },
     * }
     *
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
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
     * POST /api/wordpress/v1/sites (SiteController@store)
     *
     * Server-side Validator requires `domain`, `urls`, `type`; `type_id` is
     * optional but must be a string when present.
     *
     * @param string $token JWT bearer token.
     * @param array{
     *   domain:   string,
     *   urls:     list<string>,
     *   type:     string,
     *   type_id?: string|int,
     *   tags?:    string,
     * } $body Request body.
     *
     * @return array{
     *   result: bool,
     *   data: array{
     *     id:             int,
     *     domain_name:    string,
     *     short_url:      string,
     *     site_urls:      list<array{id: int, url: string}>,
     *     wordpress_link: array{type: string, type_id: string},
     *   },
     * }
     *
     * @throws Lihi_Validation_Exception on HTTP 400 (missing required fields).
     * @throws Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function create_site( string $token, array $body ): array;
}
