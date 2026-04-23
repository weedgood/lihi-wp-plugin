<?php
namespace Lihi\ShortUrl;

/**
 * Plugin settings page: allows administrators to configure the lihi email address.
 *
 * The form does not POST to options.php. Instead the "Save & Verify" button
 * fires an AJAX request that (1) calls the auth service's update_email endpoint
 * and (2) only persists the option on success, so an email the auth service
 * rejects never becomes the active configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register the option so WordPress knows about its schema. The sanitize_callback
// is only invoked by Settings API form processing (which we no longer use); the
// AJAX handler below runs sanitize_email() explicitly.
add_action( 'admin_init', function () {
    register_setting(
        'lihi_settings_group',
        'lihi_email',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ]
    );
} );

// Reset per-account state when the email changes — both the JWT and the
// saved redirect domain belong to the previous lihi account and must not
// leak into the next one. Covers add / update / delete of the option.
$lihi_reset_account_state = function () {
    lihi_token_store()->flush();
    delete_option( 'lihi_domain' );
};
add_action( 'add_option_lihi_email',    $lihi_reset_account_state );
add_action( 'update_option_lihi_email', $lihi_reset_account_state );
add_action( 'delete_option_lihi_email', $lihi_reset_account_state );

// Add the settings page under the Settings menu.
add_action( 'admin_menu', function () {
    $hook = add_options_page(
        __( 'lihi Short URL Settings', 'lihi-shorturl' ),
        __( 'lihi Short URL', 'lihi-shorturl' ),
        'manage_options',
        'lihi-settings',
        __NAMESPACE__ . '\\render_settings_page'
    );

    add_action( "admin_print_scripts-{$hook}", __NAMESPACE__ . '\\enqueue_settings_assets' );
} );

function enqueue_settings_assets(): void {
    wp_enqueue_script(
        'lihi-settings',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-settings.js',
        [],
        '0.1.0',
        true
    );

    wp_localize_script( 'lihi-settings', 'lihiSettings', [
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'emailAction'   => 'lihi_update_email',
        'emailNonce'    => wp_create_nonce( 'lihi_update_email' ),
        'domainAction'  => 'lihi_update_domain',
        'domainNonce'   => wp_create_nonce( 'lihi_update_domain' ),
    ] );
}

function render_settings_page(): void {
    $value         = get_option( 'lihi_email', '' );
    $profile       = null;
    $profile_error = '';

    // Same login-then-call pattern as the lihi button: Lihi_Service::get_profile()
    // wraps get_token() (which calls Lihi_Auth_Client::login() once per cache miss)
    // and retries once on Lihi_Token_Invalid_Exception.
    if ( $value !== '' ) {
        try {
            $profile = lihi_service()->get_profile();
        } catch ( Lihi_Auth_Exception $e ) {
            $profile_error = __( 'Email not verified yet. Click Save & Verify to resend the verification email.', 'lihi-shorturl' );
        } catch ( Lihi_Server_Exception $e ) {
            error_log( '[lihi] profile fetch failed: ' . $e->getMessage() );
            $profile_error = __( 'The lihi service is temporarily unavailable. Please try again later.', 'lihi-shorturl' );
        } catch ( \Exception $e ) {
            error_log( '[lihi] profile fetch failed: ' . $e->getMessage() );
            $profile_error = __( 'Could not load account information.', 'lihi-shorturl' );
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="lihi_email"><?php esc_html_e( 'Email', 'lihi-shorturl' ); ?></label></th>
                    <td>
                        <input type="email" id="lihi_email" name="lihi_email" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                        <button type="button" id="lihi-save-email" class="button button-primary">
                            <?php esc_html_e( 'Save & Verify', 'lihi-shorturl' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'Email used to authenticate with the lihi service. Saving triggers email verification; leave blank to disable the plugin.', 'lihi-shorturl' ); ?>
                        </p>
                        <div id="lihi-email-status" role="status" aria-live="polite"></div>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if ( $profile !== null ) : ?>
            <?php
            $domains        = $profile['domains'] ?? [];
            $current_domain = get_option( 'lihi_domain', '' );
            ?>
            <h2><?php esc_html_e( 'Account', 'lihi-shorturl' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Subscribe Plan', 'lihi-shorturl' ); ?></th>
                        <td><?php echo esc_html( $profile['user_role'] ?? __( '(none)', 'lihi-shorturl' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Plan End Date', 'lihi-shorturl' ); ?></th>
                        <td><?php echo esc_html( $profile['end_date'] ?? __( '—', 'lihi-shorturl' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lihi_domain"><?php esc_html_e( 'Redirect domain', 'lihi-shorturl' ); ?></label></th>
                        <td>
                            <?php if ( empty( $domains ) ) : ?>
                                <?php esc_html_e( '(none available)', 'lihi-shorturl' ); ?>
                            <?php else : ?>
                                <select id="lihi_domain" name="lihi_domain">
                                    <option value="" <?php selected( $current_domain, '' ); ?>>
                                        <?php esc_html_e( '— Please Choose —', 'lihi-shorturl' ); ?>
                                    </option>
                                    <?php foreach ( $domains as $domain ) : ?>
                                        <option value="<?php echo esc_attr( $domain ); ?>" <?php selected( $current_domain, $domain ); ?>>
                                            <?php echo esc_html( $domain ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="lihi-save-domain" class="button button-primary">
                                    <?php esc_html_e( 'Save', 'lihi-shorturl' ); ?>
                                </button>
                                <p class="description">
                                    <?php esc_html_e( 'Redirect domain used when creating new lihi short URLs.', 'lihi-shorturl' ); ?>
                                </p>
                                <div id="lihi-domain-status" role="status" aria-live="polite"></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php elseif ( $profile_error !== '' ) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html( $profile_error ); ?></p></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * AJAX handler: verify and persist the lihi email.
 *
 * Empty input clears the option (effectively disabling the plugin).
 * Otherwise the auth service is called first; only on success is the
 * option updated, so an address the service rejects never becomes active.
 */
function ajax_update_email(): void {
    check_ajax_referer( 'lihi_update_email', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to update this setting.', 'lihi-shorturl' ), 403 );
        return;
    }

    $raw = trim( (string) ( $_POST['email'] ?? '' ) );
    if ( $raw === '' ) {
        delete_option( 'lihi_email' );
        wp_send_json_success( [
            'verified' => false,
            'message'  => __( 'lihi email cleared. Short URL generation is disabled until a new email is verified.', 'lihi-shorturl' ),
        ] );
        return;
    }

    $email = sanitize_email( $raw );
    if ( $email === '' || ! is_email( $email ) ) {
        wp_send_json_error( __( 'Please enter a valid email address.', 'lihi-shorturl' ) );
        return;
    }

    try {
        $result = lihi_auth_client()->update_email( $email );
    } catch ( Lihi_Validation_Exception $e ) {
        wp_send_json_error( __( 'lihi rejected the email address. Please check the format and try again.', 'lihi-shorturl' ) );
        return;
    } catch ( Lihi_Rate_Limit_Exception $e ) {
        wp_send_json_error( __( 'Too many verification attempts. Please wait a moment and try again.', 'lihi-shorturl' ) );
        return;
    } catch ( Lihi_Server_Exception $e ) {
        error_log( '[lihi] update_email failed: ' . $e->getMessage() );
        wp_send_json_error( __( 'The lihi auth service is unavailable. Please try again later.', 'lihi-shorturl' ) );
        return;
    }

    update_option( 'lihi_email', $email );

    $verified = (bool) ( $result['verified'] ?? false );
    $message  = $verified
        ? __( '✓ Email verified.', 'lihi-shorturl' )
        : __( 'Verification email sent. Please click the link in that email to finish verification.', 'lihi-shorturl' );

    wp_send_json_success( [
        'verified' => $verified,
        'message'  => $message,
    ] );
}

add_action( 'wp_ajax_lihi_update_email', __NAMESPACE__ . '\\ajax_update_email' );

/**
 * AJAX handler: persist the selected lihi redirect domain.
 *
 * Trusts the current_user_can + nonce gate for CSRF; basic hostname format
 * check rejects obviously-malformed input. Empty input clears the option.
 * Membership in the account's actual domain list is not re-verified here —
 * the UI only offers valid domains, and lihi-admin silently substitutes an
 * invalid domain with an account-valid one on create_site, so a stale
 * selection degrades gracefully.
 */
function ajax_update_domain(): void {
    check_ajax_referer( 'lihi_update_domain', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to update this setting.', 'lihi-shorturl' ), 403 );
        return;
    }

    $domain = trim( (string) ( $_POST['domain'] ?? '' ) );

    if ( $domain === '' ) {
        delete_option( 'lihi_domain' );
        wp_send_json_success( [
            'message' => __( 'Redirect domain cleared.', 'lihi-shorturl' ),
        ] );
        return;
    }

    // Reject anything that isn't a plausible hostname (letters, digits, dots, hyphens).
    if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $domain ) ) {
        wp_send_json_error( __( 'Invalid redirect domain.', 'lihi-shorturl' ) );
        return;
    }

    update_option( 'lihi_domain', $domain );

    wp_send_json_success( [
        'message' => __( 'Redirect domain saved.', 'lihi-shorturl' ),
    ] );
}

add_action( 'wp_ajax_lihi_update_domain', __NAMESPACE__ . '\\ajax_update_domain' );
