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

    add_action( 'admin_enqueue_scripts', function ( string $hook_suffix ) use ( $hook ): void {
        if ( $hook_suffix !== $hook ) {
            return;
        }

        enqueue_settings_assets();
    } );
} );

function enqueue_settings_assets(): void {
    $settings_css = plugin_dir_path( __FILE__ ) . '../assets/lihi-settings.css';
    $settings_js  = plugin_dir_path( __FILE__ ) . '../assets/lihi-settings.js';
    $css_version  = file_exists( $settings_css ) ? '1.0.3-' . filemtime( $settings_css ) : '1.0.3';
    $js_version   = file_exists( $settings_js ) ? '1.0.3-' . filemtime( $settings_js ) : '1.0.3';

    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style(
        'lihi-settings-style',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-settings.css',
        [],
        $css_version
    );

    wp_enqueue_script(
        'lihi-settings',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-settings.js',
        [],
        $js_version,
        true
    );

    wp_localize_script( 'lihi-settings', 'lihiSettings', [
        'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
        'emailAction'            => 'lihi_update_email',
        'emailNonce'             => wp_create_nonce( 'lihi_update_email' ),
        'dashboardAction'        => 'lihi_dashboard_passthrough',
        'dashboardNonce'         => wp_create_nonce( 'lihi_dashboard_passthrough' ),
        'dashboardUrl'           => lihi_dashboard_url(),
        'passthroughRedirectUrl' => lihi_passthrough_redirect_url(),
        'requestFailed'          => __( 'Request failed. Please try again later.', 'lihi-short-url' ),
        'emailConsentRequired'   => __( 'Please confirm that lihi may use this email to create an account if one does not already exist.', 'lihi-short-url' ),
        'dashboard'              => [
            'opening'          => __( 'Opening lihi dashboard...', 'lihi-short-url' ),
            'opened'           => __( 'lihi dashboard is opening in a new tab.', 'lihi-short-url' ),
            'direct'           => __( 'lihi dashboard opened. Sign in there if needed.', 'lihi-short-url' ),
            'popupBlocked'     => __( 'Your browser blocked the lihi dashboard tab. Please allow pop-ups and try again.', 'lihi-short-url' ),
            'proofUnavailable' => __( 'Your browser does not support secure lihi dashboard login.', 'lihi-short-url' ),
        ],
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
        } catch ( Lihi_User_Invalid_Exception $e ) {
            $profile_error = __( 'Your lihi account is unavailable. Please contact lihi support before creating short URLs.', 'lihi-short-url' );
        } catch ( Lihi_Token_Invalid_Exception $e ) {
            $profile_error = __( 'Your lihi login session has expired. Please try again.', 'lihi-short-url' );
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
    <div class="wrap lihi-settings-page">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <section class="lihi-services-panel" aria-labelledby="lihi-services-heading">
            <div class="lihi-services-panel__intro">
                <div class="lihi-services-panel__lead">
                    <h2 id="lihi-services-heading"><?php esc_html_e( 'Create short URLs in WordPress. Manage the rest in lihi.', 'lihi-short-url' ); ?></h2>
                    <p class="lihi-services-panel__copy">
                        <span class="lihi-services-panel__copy-line"><?php esc_html_e( 'This plugin provides a simple workflow for creating and using short URLs.', 'lihi-short-url' ); ?></span>
                        <span class="lihi-services-panel__copy-line"><?php esc_html_e( 'For short URL list management, link editing, personal domains, SMS, and growth tools, go to the lihi dashboard.', 'lihi-short-url' ); ?></span>
                    </p>
                    <div class="lihi-services-route" aria-hidden="true">
                        <span><?php esc_html_e( 'WordPress', 'lihi-short-url' ); ?></span>
                        <span class="lihi-services-route__line"></span>
                        <span><?php esc_html_e( 'lihi dashboard', 'lihi-short-url' ); ?></span>
                    </div>
                </div>

                <div class="lihi-account-panel">
                    <div class="lihi-account-panel__header">
                        <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                        <div>
                            <h3><?php esc_html_e( 'Connected lihi account', 'lihi-short-url' ); ?></h3>
                            <p><?php esc_html_e( 'Save and verify the email used by this WordPress site.', 'lihi-short-url' ); ?></p>
                        </div>
                    </div>
                    <div class="lihi-account-panel__form">
                        <label for="lihi_email"><?php esc_html_e( 'Email', 'lihi-short-url' ); ?></label>
                        <div class="lihi-account-panel__controls">
                            <input type="email" id="lihi_email" name="lihi_email" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                            <button type="button" id="lihi-save-email" class="button button-primary">
                                <?php esc_html_e( 'Save & Verify', 'lihi-short-url' ); ?>
                            </button>
                        </div>
                        <p class="lihi-account-panel__description">
                            <?php esc_html_e( 'Saving sends a verification email. The WordPress shortcut works after verification is complete.', 'lihi-short-url' ); ?>
                        </p>
                        <div class="lihi-account-panel__consent">
                            <input type="checkbox" id="lihi_email_consent" name="create_account_consent" value="1" />
                            <label class="lihi-account-panel__consent-label" for="lihi_email_consent"><?php esc_html_e( 'If this lihi account does not exist yet, I agree that lihi may use this email to create it.', 'lihi-short-url' ); ?></label>
                        </div>
                        <div id="lihi-email-status" role="status" aria-live="polite"></div>
                    </div>
                    <div id="lihi-account-section" class="lihi-account-panel__profile">
                        <?php if ( $profile !== null ) : ?>
                            <dl>
                                <div>
                                    <dt><?php esc_html_e( 'Email', 'lihi-short-url' ); ?></dt>
                                    <dd><?php echo esc_html( $value ); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e( 'Subscribe Plan', 'lihi-short-url' ); ?></dt>
                                    <dd><?php echo esc_html( $profile['user_role'] ?? __( '(none)', 'lihi-short-url' ) ); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e( 'Plan End Date', 'lihi-short-url' ); ?></dt>
                                    <dd><?php echo esc_html( $profile['end_date'] ?? __( '—', 'lihi-short-url' ) ); ?></dd>
                                </div>
                            </dl>
                        <?php elseif ( $profile_error !== '' ) : ?>
                            <div class="notice notice-error inline"><p><?php echo esc_html( $profile_error ); ?></p></div>
                        <?php else : ?>
                            <p><?php esc_html_e( 'No lihi account is connected yet.', 'lihi-short-url' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="lihi-services-panel__workspace">
                <div class="lihi-services-panel__workspace-header">
                    <p class="lihi-services-panel__workspace-heading"><?php esc_html_e( 'lihi dashboard', 'lihi-short-url' ); ?></p>
                    <div class="lihi-services-panel__actions">
                        <button type="button" id="lihi-open-dashboard" class="button button-primary lihi-dashboard-button">
                            <span class="dashicons dashicons-external" aria-hidden="true"></span>
                            <?php esc_html_e( 'Go to lihi dashboard', 'lihi-short-url' ); ?>
                        </button>
                        <div id="lihi-dashboard-status" class="lihi-services-panel__status" role="status" aria-live="polite"></div>
                    </div>
                </div>
                <div class="lihi-services-grid" aria-label="<?php esc_attr_e( 'lihi dashboard services', 'lihi-short-url' ); ?>">
                    <article class="lihi-service-card lihi-service-card--links">
                        <span class="dashicons dashicons-admin-links lihi-service-card__icon" aria-hidden="true"></span>
                        <h3><?php esc_html_e( 'Short URLs', 'lihi-short-url' ); ?></h3>
                        <p><?php esc_html_e( 'Manage destinations, traffic splitting, A/B tests, QR codes, tags, and click analytics for campaign links.', 'lihi-short-url' ); ?></p>
                    </article>
                    <article class="lihi-service-card lihi-service-card--domains">
                        <span class="dashicons dashicons-admin-site-alt3 lihi-service-card__icon" aria-hidden="true"></span>
                        <h3><?php esc_html_e( 'Domains', 'lihi-short-url' ); ?></h3>
                        <p><?php esc_html_e( 'Manage personal domains, DNS settings, and short-link domains connected to traffic splitting and performance tracking.', 'lihi-short-url' ); ?></p>
                    </article>
                    <article class="lihi-service-card lihi-service-card--sms">
                        <span class="dashicons dashicons-email-alt2 lihi-service-card__icon" aria-hidden="true"></span>
                        <h3><?php esc_html_e( 'SMS', 'lihi-short-url' ); ?></h3>
                        <p><?php esc_html_e( 'Send marketing SMS with NCC whitelist support, short-link tracking, delivery workflows, and SMS API access.', 'lihi-short-url' ); ?></p>
                    </article>
                    <article class="lihi-service-card lihi-service-card--tools">
                        <span class="dashicons dashicons-chart-line lihi-service-card__icon" aria-hidden="true"></span>
                        <h3><?php esc_html_e( 'Growth tools', 'lihi-short-url' ); ?></h3>
                        <p><?php esc_html_e( 'Use UTM builders, QR codes, bulk imports, and API keys for larger workflows.', 'lihi-short-url' ); ?></p>
                    </article>
                </div>
            </div>

            <div class="lihi-services-panel__legal">
                <a href="<?php echo esc_url( 'https://knowledge.lihi.io/terms/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Terms of Use', 'lihi-short-url' ); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url( 'https://knowledge.lihi.io/privacy-policy/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy Policy', 'lihi-short-url' ); ?></a>
            </div>
        </section>
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

    $has_create_account_consent = isset( $_POST['create_account_consent'] )
        && sanitize_text_field( wp_unslash( $_POST['create_account_consent'] ) ) === '1';
    if ( ! $has_create_account_consent ) {
        wp_send_json_error( __( 'Please confirm that lihi may use this email to create an account if one does not already exist.', 'lihi-short-url' ), 400 );
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

function parse_dashboard_challenge_field(): string {
    $challenge = isset( $_POST['challenge'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['challenge'] ) ) )
        : '';

    return preg_match( '/^[A-Za-z0-9_-]{43}$/', $challenge )
        ? $challenge
        : '';
}

function send_dashboard_passthrough_exception( \Exception $e ): void {
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

    if ( $e instanceof Lihi_Validation_Exception ) {
        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON payload is rendered with textContent in lihi-settings.js.
        /* translators: %s: validation error message returned by the lihi API. */
        wp_send_json_error( sprintf( __( 'lihi API rejected the request: %s', 'lihi-short-url' ), $e->getMessage() ), 400 );
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        return;
    }

    if ( $e instanceof Lihi_Rate_Limit_Exception ) {
        wp_send_json_error( __( 'Too many requests. Please wait a moment and try again.', 'lihi-short-url' ), 429 );
        return;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostics gated behind WP_DEBUG.
        error_log( '[lihi] dashboard passthrough failed: ' . $e->getMessage() );
    }

    if ( $e instanceof Lihi_Server_Exception ) {
        wp_send_json_error( __( 'The lihi service is unavailable. Please try again later.', 'lihi-short-url' ), 503 );
        return;
    }

    wp_send_json_error( __( 'Failed to open lihi dashboard. Please try again later.', 'lihi-short-url' ), 500 );
}

function ajax_dashboard_passthrough(): void {
    check_ajax_referer( 'lihi_dashboard_passthrough', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to open the lihi dashboard.', 'lihi-short-url' ), 403 );
        return;
    }

    if ( lihi_email() === '' ) {
        wp_send_json_error( __( 'lihi email is not configured. Please set it in Settings → lihi Short URL.', 'lihi-short-url' ), 409 );
        return;
    }

    try {
        $cached_token = Lihi_Singletons::lihi_token_store()->get();
        if ( ! is_string( $cached_token ) || $cached_token === '' ) {
            $dashboard_url = lihi_dashboard_url();
            if ( $dashboard_url === '' ) {
                throw new \RuntimeException( 'Could not resolve lihi dashboard URL.' );
            }

            wp_send_json_success( [
                'passthrough'   => false,
                'dashboard_url' => $dashboard_url,
            ] );
            return;
        }

        $challenge = parse_dashboard_challenge_field();
        if ( $challenge === '' ) {
            wp_send_json_error( __( 'Could not verify browser session. Please refresh the page and try again.', 'lihi-short-url' ), 400 );
            return;
        }

        $nonce        = Lihi_Singletons::lihi_service()->create_passthrough_nonce( '', $challenge );
        $redirect_url = lihi_passthrough_redirect_url();
        if ( $redirect_url === '' ) {
            throw new \RuntimeException( 'Could not resolve lihi passthrough redirect URL.' );
        }

        wp_send_json_success( [
            'passthrough'   => true,
            'nonce'        => $nonce,
            'redirect_url' => $redirect_url,
        ] );
    } catch ( \Exception $e ) {
        send_dashboard_passthrough_exception( $e );
    }
}

add_action( 'wp_ajax_lihi_dashboard_passthrough', __NAMESPACE__ . '\\ajax_dashboard_passthrough' );
