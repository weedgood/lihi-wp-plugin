<?php
namespace Lihi\ShortUrl;

/**
 * Admin settings page for Lihi Short URL.
 *
 * Registers a Settings page under the WordPress Settings menu.
 * Stores lihi_api_domain, lihi_email, and lihi_api_key via the Settings API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register settings, sections, and fields.
add_action( 'admin_init', function () {
    register_setting( 'lihi_settings', 'lihi_email', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_email',
        'default'           => '',
    ] );

    register_setting( 'lihi_settings', 'lihi_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );

    add_settings_section( 'lihi_main', '', '__return_null', 'lihi-settings' );

    add_settings_field( 'lihi_email', __( 'Email', 'lihi-shorturl' ), function () {
        $value = get_option( 'lihi_email', '' );
        echo '<input type="email" name="lihi_email" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }, 'lihi-settings', 'lihi_main' );

    add_settings_field( 'lihi_api_key', __( 'API Key', 'lihi-shorturl' ), function () {
        $value = get_option( 'lihi_api_key', '' );
        echo '<input type="password" name="lihi_api_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }, 'lihi-settings', 'lihi_main' );
} );

// Add settings page under Settings menu.
add_action( 'admin_menu', function () {
    add_options_page(
        __( 'Lihi Short URL', 'lihi-shorturl' ),
        __( 'Lihi Short URL', 'lihi-shorturl' ),
        'manage_options',
        'lihi-settings',
        function () {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p>
                    <?php
                    printf(
                        /* translators: %s: registration URL */
                        esc_html__( 'No account yet? Register at %s', 'lihi-shorturl' ),
                        '<a href="' . esc_url( lihi_api_domain() . '/admin/register' ) . '" target="_blank">' . esc_html( lihi_api_domain() . '/admin/register' ) . '</a>'
                    );
                    ?>
                </p>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'lihi_settings' );
                    do_settings_sections( 'lihi-settings' );
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }
    );
} );
