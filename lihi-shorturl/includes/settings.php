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

// Invalidate cached token when the email changes — the old JWT belongs to a
// different lihi account. Covers add / update / delete of the option.
$lihi_flush_token = function () {
    lihi_token_store()->flush();
};
add_action( 'add_option_lihi_email',    $lihi_flush_token );
add_action( 'update_option_lihi_email', $lihi_flush_token );
add_action( 'delete_option_lihi_email', $lihi_flush_token );

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
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'lihi_update_email' ),
        'action'  => 'lihi_update_email',
    ] );
}

function render_settings_page(): void {
    $value = get_option( 'lihi_email', '' );
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
                            <?php esc_html_e( 'Required. The email used to authenticate with the lihi service. Saving triggers email verification.', 'lihi-shorturl' ); ?>
                        </p>
                        <div id="lihi-email-status" role="status" aria-live="polite"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * AJAX handler: verify and persist the lihi email.
 *
 * Calls the auth service first; only updates the option if the service
 * accepts the email. Returns { verified: bool } on success.
 */
function ajax_update_email(): void {
    check_ajax_referer( 'lihi_update_email', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to update this setting.', 'lihi-shorturl' ), 403 );
        return;
    }

    $email = sanitize_email( (string) ( $_POST['email'] ?? '' ) );
    if ( $email === '' || ! is_email( $email ) ) {
        wp_send_json_error( __( 'Please enter a valid email address.', 'lihi-shorturl' ) );
        return;
    }

    try {
        $result = lihi_auth_client()->update_email( $email );
    } catch ( Lihi_Validation_Exception $e ) {
        wp_send_json_error( __( 'lihi rejected the email address. Please check the format and try again.', 'lihi-shorturl' ) );
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
