<?php
namespace Lihi\ShortUrl;

/**
 * lihi auth API client contract.
 *
 * Base URL: lihi_config( 'auth_domain' ) (e.g. https://w.lihidev.com).
 * See /home/wayne/lihi-wp-auth/docs/api.md for the upstream spec.
 *
 * Response envelope: every response — success or failure — is
 * { result: bool, data: {...} }; failure bodies carry { data: { message: string } }.
 *
 * The tenant identity is sent in each JSON request body as `hostname` (derived
 * from the current WP site's home_url() host) and `uuid` (the site-scoped
 * lihi_uuid option generated on first auth use). This avoids depending on
 * the HTTP Host header, which may be rewritten by proxies or load balancers.
 * Login requests also include `is_mobile` from wp_is_mobile().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Lihi_Auth_Client_Interface {

    /**
     * Start or refresh email verification for the current tenant.
     *
     * POST /auth/update-email
     *
     * Server behaviour:
     * - Row exists and verified = true → early-return { verified: true }, no
     *   DB write, no token issued.
     * - Row missing or verified = false → always mint a fresh JWT and upsert,
     *   invalidating any prior token. The JWT is delivered out-of-band (e.g.
     *   via verification email) and is never returned in the response.
     *
     * @param string $email Email to verify.
     * @return array{verified: bool}
     *
     * @throws Lihi_Validation_Exception on HTTP 400. Triggers: `invalid json body`,
     *     `email is required`, `invalid email` (RFC 5322 parse failure),
     *     `bad request` (hostname / uuid missing — intentionally generic
     *     anti-forgery gate).
     * @throws Lihi_Rate_Limit_Exception on HTTP 429. Per-host rate limit is
     *     10 requests / minute, keyed on the normalized payload `hostname`. Shared
     *     fallback bucket for empty-hostname requests.
     * @throws Lihi_Server_Exception on HTTP 500 (DB / signing failure) or
     *     network error. `data.message` ∈ `load verification`, `issue token`,
     *     `persist verification` — the underlying error is logged server-side.
     */
    public function update_email( string $email ): array;

    /**
     * Exchange a verified email for a fresh upstream lihi bearer token.
     *
     * POST /auth/login
     *
     * Succeeds only when email_verifications has a row for the payload tenant
     * identity and email with verified = true. The request also sends
     * `is_mobile` for request-context logging / policy decisions. The token
     * comes from the upstream lihi cloud API and is not persisted by the auth
     * service; upstream TTL is ~168 days.
     *
     * @param string $email Verified tenant email.
     * @return array{token: string}
     *
     * @throws Lihi_Validation_Exception on HTTP 400 (same triggers as update_email).
     * @throws Lihi_Auth_Exception on HTTP 403 `email not verified`. Missing rows
     *     and unverified rows deliberately share this response so callers
     *     cannot probe which emails exist under a tenant.
     * @throws Lihi_Server_Exception on HTTP 500 (`load verification`,
     *     `issue token` — collapses every upstream failure mode so callers
     *     can't fingerprint upstream state) or network error.
     */
    public function login( string $email ): array;
}
