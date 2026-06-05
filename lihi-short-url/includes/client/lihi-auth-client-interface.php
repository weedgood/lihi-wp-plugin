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
 * The tenant domain is derived server-side from the HTTP Host header
 * (lowercased, `:port` stripped), so implementations must send the current
 * WP site's host (from home_url()) as Host — not the auth server's own host.
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
     *     `bad request` (Host header missing — intentionally generic
     *     anti-forgery gate).
     * @throws Lihi_Rate_Limit_Exception on HTTP 429. Per-host rate limit is
     *     10 requests / minute, keyed on the normalized Host. Shared fallback
     *     bucket for empty-Host requests.
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
     * Succeeds only when email_verifications has a row for (Host domain, email)
     * with verified = true. The token comes from the upstream lihi cloud API
     * and is not persisted by the auth service; upstream TTL is ~168 days.
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
