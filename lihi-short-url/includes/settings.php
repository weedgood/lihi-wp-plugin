<?php
namespace Lihi\ShortUrl;

/**
 * Plugin settings page: allows administrators to configure the lihi email address.
 *
 * The form does not POST to options.php. Instead the "Save & Verify" button
 * fires an AJAX request that (1) calls the lihi API update_email endpoint
 * and (2) only persists the option on success, so an email the lihi service
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

// Reset per-account state when the email changes. Covers add / update /
// delete of the option.
$lihi_reset_account_state = function () {
    Lihi_Singletons::lihi_token_store()->flush();
};
add_action( 'add_option_lihi_email',    $lihi_reset_account_state );
add_action( 'update_option_lihi_email', $lihi_reset_account_state );
add_action( 'delete_option_lihi_email', $lihi_reset_account_state );

// Add the settings page under the Settings menu.
add_action( 'admin_menu', function () {
    $hook = add_options_page(
        __( 'lihi Short URL Settings', 'lihi-short-url' ),
        __( 'lihi Short URL', 'lihi-short-url' ),
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
        '1.0.3',
        true
    );

    wp_localize_script( 'lihi-settings', 'lihiSettings', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'emailAction' => 'lihi_update_email',
        'emailNonce'  => wp_create_nonce( 'lihi_update_email' ),
    ] );
}

function render_settings_page(): void {
    $value         = get_option( 'lihi_email', '' );
    $profile       = null;
    $profile_error = '';

    // Same login-then-call pattern as the lihi button: Lihi_Service::get_profile()
    // wraps get_token() (which calls Lihi_Client::login() once per cache miss)
    // and retries once on Lihi_Token_Invalid_Exception.
    if ( $value !== '' ) {
        try {
            $profile = Lihi_Singletons::lihi_service()->get_profile();
        } catch ( Lihi_Auth_Exception $e ) {
            $profile_error = __( 'Email not verified yet. Click Save & Verify to resend the verification email.', 'lihi-short-url' );
        } catch ( Lihi_Server_Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostics gated behind WP_DEBUG.
                error_log( '[lihi] profile fetch failed: ' . $e->getMessage() );
            }
            $profile_error = __( 'The lihi service is temporarily unavailable. Please try again later.', 'lihi-short-url' );
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostics gated behind WP_DEBUG.
                error_log( '[lihi] profile fetch failed: ' . $e->getMessage() );
            }
            $profile_error = __( 'Could not load account information.', 'lihi-short-url' );
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="lihi_email"><?php esc_html_e( 'Email', 'lihi-short-url' ); ?></label></th>
                    <td>
                        <input type="email" id="lihi_email" name="lihi_email" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                        <button type="button" id="lihi-save-email" class="button button-primary">
                            <?php esc_html_e( 'Save & Verify', 'lihi-short-url' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'Email used to authenticate with the lihi service. Saving triggers email verification; leave blank to disable the plugin.', 'lihi-short-url' ); ?>
                        </p>
                        <div id="lihi-email-status" role="status" aria-live="polite"></div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div id="lihi-account-section">
            <?php if ( $profile !== null ) : ?>
                <h2><?php esc_html_e( 'Account', 'lihi-short-url' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Subscribe Plan', 'lihi-short-url' ); ?></th>
                            <td><?php echo esc_html( $profile['user_role'] ?? __( '(none)', 'lihi-short-url' ) ); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Plan End Date', 'lihi-short-url' ); ?></th>
                            <td><?php echo esc_html( $profile['end_date'] ?? __( '—', 'lihi-short-url' ) ); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php elseif ( $profile_error !== '' ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( $profile_error ); ?></p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler: verify and persist the lihi email.
 *
 * Empty input clears the option (effectively disabling the plugin).
 * Otherwise the lihi API is called first; only on success is the
 * option updated, so an address the service rejects never becomes active.
 */
function ajax_update_email(): void {
    check_ajax_referer( 'lihi_update_email', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to update this setting.', 'lihi-short-url' ), 403 );
        return;
    }

    $raw = isset( $_POST['email'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['email'] ) ) )
        : '';
    if ( $raw === '' ) {
        delete_option( 'lihi_email' );
        wp_send_json_success( [
            'verified' => false,
            'message'  => __( 'lihi email cleared. Short URL generation is disabled until a new email is verified.', 'lihi-short-url' ),
        ] );
        return;
    }

    $email = sanitize_email( $raw );
    if ( $email === '' || ! is_email( $email ) ) {
        wp_send_json_error( __( 'Please enter a valid email address.', 'lihi-short-url' ), 400 );
        return;
    }

    try {
        $result = Lihi_Singletons::lihi_client()->update_email( $email );
    } catch ( Lihi_Validation_Exception $e ) {
        wp_send_json_error( __( 'lihi rejected the email address. Please check the format and try again.', 'lihi-short-url' ), 400 );
        return;
    } catch ( Lihi_Rate_Limit_Exception $e ) {
        wp_send_json_error( __( 'Too many verification attempts. Please wait a moment and try again.', 'lihi-short-url' ), 429 );
        return;
    } catch ( Lihi_Server_Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostics gated behind WP_DEBUG.
            error_log( '[lihi] update_email failed: ' . $e->getMessage() );
        }
        wp_send_json_error( __( 'The lihi service is unavailable. Please try again later.', 'lihi-short-url' ), 503 );
        return;
    }

    update_option( 'lihi_email', $email );

    $verified = (bool) ( $result['verified'] ?? false );
    $message  = $verified
        ? __( '✓ Email verified.', 'lihi-short-url' )
        : __( 'Verification email sent. Please click the link in that email to finish verification.', 'lihi-short-url' );

    wp_send_json_success( [
        'verified' => $verified,
        'message'  => $message,
    ] );
}

add_action( 'wp_ajax_lihi_update_email', __NAMESPACE__ . '\\ajax_update_email' );
