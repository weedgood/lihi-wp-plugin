<?php
namespace Lihi\ShortUrl;

/**
 * lihi auth API client contract.
 *
 * Base URL: lihi_config( 'auth_domain' ) (e.g. https://w.lihidev.com).
 * Response envelope: { result: bool, data: {...} } for both success and failure;
 * failure bodies carry { data: { message: string } }.
 *
 * The tenant domain is derived server-side from the HTTP Host header, so
 * implementations must send the current WP site's host (home_url()) as Host
 * — not the auth server's own host.
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
     * A `verified: true` response means the (domain, email) pair is already
     * verified and no new token was issued. A `verified: false` response means
     * a fresh verification JWT was just minted and delivered out-of-band
     * (e.g. emailed) — the token itself is never returned.
     *
     * @param string $email Email to verify.
     * @return array{verified: bool}
     *
     * @throws Lihi_Validation_Exception on HTTP 400 (invalid email, missing Host).
     * @throws Lihi_Server_Exception     on HTTP 500 or network failure.
     */
    public function update_email( string $email ): array;

    /**
     * Exchange a verified email for a fresh upstream lihi bearer token.
     *
     * POST /auth/login
     *
     * @param string $email Verified tenant email.
     * @return array{token: string}
     *
     * @throws Lihi_Validation_Exception on HTTP 400.
     * @throws Lihi_Auth_Exception       on HTTP 403 "email not verified"
     *                                   (also returned when the row does not exist).
     * @throws Lihi_Server_Exception     on HTTP 500 or network failure.
     */
    public function login( string $email ): array;
}
