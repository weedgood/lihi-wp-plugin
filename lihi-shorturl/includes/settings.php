<?php
namespace Lihi\ShortUrl;

/**
 * Plugin settings page: allows administrators to configure the Lihi email address.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register settings, section, and field via the Settings API.
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

    add_settings_section(
        'lihi_settings_section',
        '',
        '__return_false',
        'lihi-settings'
    );

    add_settings_field(
        'lihi_email_field',
        __( 'Email', 'lihi-shorturl' ),
        function () {
            $value = get_option( 'lihi_email', '' );
            echo '<input type="email" id="lihi_email" name="lihi_email" value="'
                . esc_attr( $value )
                . '" class="regular-text" />';
            echo '<p class="description">'
                . esc_html__( 'Required. The email address used to log in to the Lihi API.', 'lihi-shorturl' )
                . '</p>';
        },
        'lihi-settings',
        'lihi_settings_section'
    );
} );

// Invalidate cached token when the email changes — the old JWT belongs to a
// different Lihi account. Covers add / update / delete of the option.
$lihi_flush_token = function () {
    lihi_token_store()->flush();
};
add_action( 'add_option_lihi_email',    $lihi_flush_token );
add_action( 'update_option_lihi_email', $lihi_flush_token );
add_action( 'delete_option_lihi_email', $lihi_flush_token );

// Add the settings page under the Settings menu.
add_action( 'admin_menu', function () {
    add_options_page(
        __( 'Lihi Short URL Settings', 'lihi-shorturl' ),
        __( 'Lihi Short URL', 'lihi-shorturl' ),
        'manage_options',
        'lihi-settings',
        function () {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'lihi_settings_group' );
                    do_settings_sections( 'lihi-settings' );
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }
    );
} );
