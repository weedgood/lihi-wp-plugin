<?php
namespace Lihi\ShortUrl;

/**
 * lihi Wordpress API client contract.
 *
 * Base URL: injected by the caller, typically lihi_api_host().
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
 * Plugin usage note: this contract exposes the server-to-server passthrough
 * nonce endpoint, while the browser-facing redirect remains outside this
 * client because it creates a SaaS web session.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Lihi_Client_Interface {

    // -------------------------------------------------------------------------
    // Auth (no bearer token)
    // -------------------------------------------------------------------------

    /**
     * Start or complete email verification for the current tenant.
     *
     * POST /api/wordpress/v1/auth/update-email (AuthController@updateEmail)
     *
     * @param string $email    Email to verify.
     * @param string $password lihi account password.
     * @return array{verified?: bool}
     *
     * @throws Lihi_Validation_Exception on HTTP 400.
     * @throws Lihi_Email_Or_Password_Invalid_Exception when the password is invalid.
     * @throws Lihi_Auth_Exception on other HTTP 403 auth rejection.
     * @throws Lihi_User_Invalid_Exception when lihi marks the user invalid.
     * @throws Lihi_Rate_Limit_Exception on HTTP 429.
     * @throws Lihi_Server_Exception on HTTP 500 or network error.
     */
    public function update_email( string $email, string $password ): array;

    /**
     * Exchange a verified email for a fresh bearer token.
     *
     * POST /api/wordpress/v1/auth/login (AuthController@login)
     *
     * @param string $email Verified tenant email.
     * @return array{token: string}
     *
     * @throws Lihi_Validation_Exception on HTTP 400.
     * @throws Lihi_Auth_Exception on HTTP 403 email / tenant rejection.
     * @throws Lihi_User_Invalid_Exception on "User Invalid" / "user_not_found".
     * @throws Lihi_Server_Exception on HTTP 500, network error, or result:false.
     */
    public function login( string $email ): array;

    // -------------------------------------------------------------------------
    // Profile (jwt)
    // -------------------------------------------------------------------------

    /**
     * Current user's role and plan expiry.
     *
     * GET /api/wordpress/v1/user/profile (UserController@profile)
     *
     * Tenant is resolved via the bearer token's `sub` claim; no query params.
     *
     * @param string $token JWT bearer token.
     *
     * @return array{
     *   result: bool,
     *   data: array{
     *     user_role: ?string,
     *     end_date:  ?string,
     *   },
     * }
     *
     * @throws Lihi_Auth_Exception | Lihi_User_Invalid_Exception | Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function get_profile( string $token ): array;

    /**
     * Retrieve create-modal options for the authenticated user.
     *
     * GET /api/wordpress/v1/user/options (UserController@options)
     *
     * @param string $token JWT bearer token.
     *
     * @return array{
     *   result: bool,
     *   data: array{
     *     domains:     list<array{id: mixed, name: string}>,
     *     utm_sources: list<string>,
     *     utm_mediums: list<string>,
     *   },
     * }
     *
     * @throws Lihi_Validation_Exception on HTTP 400.
     * @throws Lihi_Auth_Exception | Lihi_User_Invalid_Exception | Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function get_options( string $token ): array;

    // -------------------------------------------------------------------------
    // Passthrough (jwt)
    // -------------------------------------------------------------------------

    /**
     * Create a short-lived nonce that the browser can send to the SaaS GET
     * passthrough redirect endpoint.
     *
     * POST /api/wordpress/v1/passthrough/nonce (PassthroughController@nonce)
     *
     * @param string $token     JWT bearer token.
     * @param string $target    Optional admin-relative path or absolute URL search target.
     * @param string $challenge Browser-generated base64url(SHA-256(verifier)) challenge.
     *
     * @return array{
     *   result: bool,
     *   msg: string,
     *   data: array{nonce: string},
     * }
     *
     * @throws Lihi_Validation_Exception on HTTP 400 (invalid target).
     * @throws Lihi_Rate_Limit_Exception on HTTP 429.
     * @throws Lihi_Auth_Exception | Lihi_User_Invalid_Exception | Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function create_passthrough_nonce( string $token, string $target, string $challenge ): array;

    // -------------------------------------------------------------------------
    // Sites (jwt)
    // -------------------------------------------------------------------------

    /**
     * Find a short-link URL with WordPress filters.
     *
     * GET /api/wordpress/v1/site/find (SiteController@find)
     *
     * @param string $token JWT bearer token.
     * @param array{
     *   type:    string,
     *   type_id: string,
     * } $params Query parameters.
     *
     * @return array{
     *   result: bool,
     *   msg?: string,
     *   data: array{
     *     site: string,
     *   },
     * }
     *
     * @throws Lihi_Validation_Exception on HTTP 400 (missing required filters).
     * @throws Lihi_Auth_Exception | Lihi_User_Invalid_Exception | Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function get_sites( string $token, array $params = [] ): array;

    /**
     * Retrieve short links filtered by WordPress type and one or more type IDs.
     *
     * Convenience wrapper around GET /api/wordpress/v1/site/find with type and type_id pre-filled.
     *
     * @param string               $token    JWT bearer token.
     * @param string               $type     Resource type (e.g. 'post', 'page', 'attachment').
     * @param int|string|list<int> $type_ids Single ID or comma-separated / array of IDs; arrays are sent as comma-separated strings.
     *
     * @return array Same shape as get_sites().
     *
     * @throws Lihi_Validation_Exception on HTTP 400 (missing required filters).
     * @throws Lihi_Auth_Exception | Lihi_User_Invalid_Exception | Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function get_short_links( string $token, string $type, $type_ids ): array;

    /**
     * Create a new site (short link).
     *
     * POST /api/wordpress/v1/site/store (SiteController@store)
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
     * @throws Lihi_Auth_Exception | Lihi_User_Invalid_Exception | Lihi_Token_Invalid_Exception | Lihi_Server_Exception
     */
    public function create_site( string $token, array $body ): array;
}
