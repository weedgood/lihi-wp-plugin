<?php
namespace Lihi\ShortUrl;

/**
 * AJAX endpoints for the Short URL column buttons.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Browser admin-ajax request failed local validation before any lihi API call. */
class Lihi_Ajax_Bad_Request_Exception extends \RuntimeException {}

function parse_json_array_field( string $field ): array {
    $raw = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '[]';
    if ( ! is_string( $raw ) ) {
        return [];
    }

    $decoded = json_decode( $raw, true );
    if ( ! is_array( $decoded ) ) {
        return [];
    }

    $values = [];
    foreach ( $decoded as $value ) {
        if ( ! is_scalar( $value ) ) {
            continue;
        }

        $sanitized = trim( sanitize_text_field( (string) $value ) );
        if ( $sanitized !== '' ) {
            $values[] = $sanitized;
        }
    }

    return array_values( array_unique( $values ) );
}

function parse_utm_field(): array {
    $raw = isset( $_POST['utm'] ) ? wp_unslash( $_POST['utm'] ) : '{}';
    if ( ! is_string( $raw ) ) {
        return [];
    }

    $decoded = json_decode( $raw, true );
    if ( ! is_array( $decoded ) ) {
        return [];
    }

    $utm = [];
    foreach ( [ 'source', 'medium', 'campaign', 'term', 'content' ] as $key ) {
        if ( ! isset( $decoded[ $key ] ) || ! is_scalar( $decoded[ $key ] ) ) {
            continue;
        }

        $value = trim( sanitize_text_field( (string) $decoded[ $key ] ) );
        if ( $value !== '' ) {
            $utm[ $key ] = $value;
        }
    }

    return $utm;
}

function parse_domain_field(): string {
    $domain = isset( $_POST['domain'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['domain'] ) ) )
        : '';

    if ( $domain === '' ) {
        throw new Lihi_Validation_Exception( __( 'Please choose a redirect domain.', 'lihi-short-url' ) );
    }

    return $domain;
}

function parse_passthrough_challenge_field(): string {
    $challenge = isset( $_POST['challenge'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['challenge'] ) ) )
        : '';

    if ( ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $challenge ) ) {
        throw new Lihi_Ajax_Bad_Request_Exception( __( 'Could not verify browser session. Please try again.', 'lihi-short-url' ) );
    }

    return $challenge;
}

function available_profile_domains( array $profile ): array {
    $domains = [];
    foreach ( $profile['domains'] ?? [] as $domain ) {
        if ( ! is_scalar( $domain ) ) {
            continue;
        }

        $value = trim( (string) $domain );
        if ( $value !== '' ) {
            $domains[] = $value;
        }
    }

    return array_values( array_unique( $domains ) );
}

function validate_lihi_item_request(): array {
    $item_id = intval( wp_unslash( $_POST['item_id'] ?? 0 ) );

    if ( ! $item_id ) {
        wp_send_json_error( __( 'Invalid post ID or type.', 'lihi-short-url' ), 400 );
        return [ 0, '' ];
    }

    $type = get_post_type( $item_id );
    if ( ! is_string( $type ) || $type === '' ) {
        wp_send_json_error( __( 'Invalid post ID or type.', 'lihi-short-url' ), 400 );
        return [ 0, '' ];
    }

    if ( ! current_user_can( 'read_post', $item_id ) ) {
        wp_send_json_error( __( 'You do not have permission to generate a short URL for this item.', 'lihi-short-url' ), 403 );
        return [ 0, '' ];
    }

    if ( lihi_email() === '' ) {
        wp_send_json_error( __( 'lihi email is not configured. Please set it in Settings → lihi Short URL.', 'lihi-short-url' ), 409 );
        return [ 0, '' ];
    }

    return [ $item_id, $type ];
}

function validate_lihi_edit_permission(): bool {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to edit lihi short URLs.', 'lihi-short-url' ), 403 );
        return false;
    }

    return true;
}

function handle_lihi_ajax_exception( \Exception $e, array $context = [] ): void {
    if ( $e instanceof Lihi_User_Invalid_Exception ) {
        wp_send_json_error( __( 'Your lihi account is unavailable. Please contact lihi support before creating short URLs.', 'lihi-short-url' ), 403 );
        return;
    }

    if ( $e instanceof Lihi_Token_Invalid_Exception ) {
        wp_send_json_error( __( 'Your lihi login session has expired. Please try again.', 'lihi-short-url' ), 401 );
        return;
    }

    if ( $e instanceof Lihi_Auth_Exception ) {
        wp_send_json_error( __( 'Your lihi email has not been verified yet. Please open Settings → lihi Short URL and click Save & Verify to resend the verification email.', 'lihi-short-url' ), 403 );
        return;
    }

    if ( $e instanceof Lihi_Ajax_Bad_Request_Exception ) {
        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON payload is rendered with textContent in lihi-button.js.
        wp_send_json_error( $e->getMessage(), 400 );
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        return;
    }

    if ( $e instanceof Lihi_Validation_Exception ) {
        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON payload is rendered with textContent in lihi-button.js.
        /* translators: %s: validation error message returned by the lihi API. */
        wp_send_json_error( sprintf( __( 'lihi API rejected the request: %s', 'lihi-short-url' ), $e->getMessage() ), 400 );
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        return;
    }

    if ( $e instanceof Lihi_Not_Found_Exception && isset( $context['missing_item_id'] ) ) {
        update_post_meta( (int) $context['missing_item_id'], 'lihi_already', '0' );
        wp_send_json_error( [
            'code'    => 'lihi_missing',
            'message' => __( 'Short URL has been removed. Please create it again.', 'lihi-short-url' ),
        ], 410 );
        return;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $log_prefix = isset( $context['log_prefix'] ) && is_string( $context['log_prefix'] )
            ? trim( $context['log_prefix'] )
            : '';
        $log_message = $log_prefix !== ''
            ? $log_prefix . ': ' . $e->getMessage()
            : $e->getMessage();
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostics gated behind WP_DEBUG.
        error_log( '[lihi] ' . $log_message );
    }

    if ( $e instanceof Lihi_Server_Exception ) {
        wp_send_json_error( __( 'The lihi service is unavailable. Please try again later.', 'lihi-short-url' ), 503 );
        return;
    }

    $message = isset( $context['fallback_message'] ) && is_string( $context['fallback_message'] )
        ? $context['fallback_message']
        : __( 'Request failed. Please try again later.', 'lihi-short-url' );
    wp_send_json_error( $message, 500 );
}

function ajax_url_options(): void {
    check_ajax_referer( 'lihi_short_url', 'nonce' );

    list( $item_id ) = validate_lihi_item_request();
    if ( ! $item_id ) {
        return;
    }

    try {
        $profile = Lihi_Singletons::lihi_service()->get_profile();
        $domains = available_profile_domains( $profile );
        wp_send_json_success( [
            'domains' => $domains,
        ] );
    } catch ( \Exception $e ) {
        handle_lihi_ajax_exception( $e, [
            'fallback_message' => __( 'Could not load account information.', 'lihi-short-url' ),
            'log_prefix'       => 'profile fetch failed',
        ] );
    }
}

add_action( 'wp_ajax_lihi_url_options', __NAMESPACE__ . '\\ajax_url_options' );

/**
 * AJAX handler: fetch an existing lihi short URL for a post and return it to
 * the browser for clipboard copy.
 *
 * Exported as a named function (rather than an inline closure) so tests can
 * invoke it directly without walking $wp_filter.
 */
function ajax_copy_url(): void {
    check_ajax_referer( 'lihi_short_url', 'nonce' );

    list( $item_id, $type ) = validate_lihi_item_request();
    if ( ! $item_id ) {
        return;
    }

    try {
        $url = Lihi_Singletons::lihi_service()->get_existing_short_url( $item_id, $type );
        update_post_meta( $item_id, 'lihi_already', '1' );
        wp_send_json_success( [
            'url'          => $url,
            'lihi_already' => true,
        ] );
    } catch ( \Exception $e ) {
        handle_lihi_ajax_exception( $e, [
            'fallback_message' => __( 'Failed to copy short URL. Please try again later.', 'lihi-short-url' ),
            'missing_item_id'  => $item_id,
        ] );
    }
}

add_action( 'wp_ajax_lihi_copy_url', __NAMESPACE__ . '\\ajax_copy_url' );

/**
 * AJAX handler: verify an existing lihi short URL and create a passthrough
 * nonce so the browser can open the lihi dashboard edit flow.
 */
function ajax_edit_url(): void {
    check_ajax_referer( 'lihi_short_url', 'nonce' );

    if ( ! validate_lihi_edit_permission() ) {
        return;
    }

    list( $item_id, $type ) = validate_lihi_item_request();
    if ( ! $item_id ) {
        return;
    }

    try {
        $challenge    = parse_passthrough_challenge_field();
        $url          = Lihi_Singletons::lihi_service()->get_existing_short_url( $item_id, $type );
        $nonce        = Lihi_Singletons::lihi_service()->create_passthrough_nonce( $url, $challenge );
        $redirect_url = lihi_passthrough_redirect_url();
        if ( $redirect_url === '' ) {
            throw new \RuntimeException( 'Could not resolve lihi passthrough redirect URL.' );
        }

        update_post_meta( $item_id, 'lihi_already', '1' );
        wp_send_json_success( [
            'nonce'        => $nonce,
            'redirect_url' => $redirect_url,
            'target'       => $url,
        ] );
    } catch ( \Exception $e ) {
        handle_lihi_ajax_exception( $e, [
            'fallback_message' => __( 'Failed to open lihi dashboard. Please try again later.', 'lihi-short-url' ),
            'missing_item_id'  => $item_id,
        ] );
    }
}

add_action( 'wp_ajax_lihi_edit_url', __NAMESPACE__ . '\\ajax_edit_url' );

/**
 * AJAX handler: create (or fetch-and-create) a lihi short URL for a post using
 * modal options, then return it to the browser for clipboard copy.
 */
function ajax_create_url(): void {
    check_ajax_referer( 'lihi_short_url', 'nonce' );

    list( $item_id, $type ) = validate_lihi_item_request();
    if ( ! $item_id ) {
        return;
    }

    try {
        $domain = parse_domain_field();
        $url    = Lihi_Singletons::lihi_service()->get_or_create_short_url( $item_id, $type, [
            'domain' => $domain,
            'tags'   => parse_json_array_field( 'tags' ),
            'utm'    => parse_utm_field(),
        ] );
        update_post_meta( $item_id, 'lihi_already', '1' );
        wp_send_json_success( [
            'url'          => $url,
            'lihi_already' => true,
        ] );
    } catch ( \Exception $e ) {
        handle_lihi_ajax_exception( $e, [
            'fallback_message' => __( 'Failed to generate short URL. Please try again later.', 'lihi-short-url' ),
        ] );
    }
}

add_action( 'wp_ajax_lihi_create_url', __NAMESPACE__ . '\\ajax_create_url' );
