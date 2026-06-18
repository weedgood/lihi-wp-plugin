<?php
namespace Lihi\ShortUrl;

/**
 * Adds a "Short URL" column to all public post type list tables.
 *
 * Dynamically registers column hooks for every public post type so that
 * custom post types are supported without extra configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function render_lihi_button_container( int $post_id, string $post_type ): string {
    $already = get_post_meta( $post_id, 'lihi_already', true ) === '1';

    return '<div class="lihi-button-container" data-lihi-container data-id="' . esc_attr( $post_id ) . '" data-type="' . esc_attr( $post_type ) . '" data-lihi-already="' . esc_attr( $already ? '1' : '0' ) . '"></div>';
}

// UI hooks (column, container, enqueue) only register when the auth email is set.
// AJAX handlers are registered unconditionally in shorturl-column-ajax.php.
if ( lihi_email() !== '' ) {

// Enqueue lihi-button.js on list screens, media library, and post edit
// (so the button works inside the media modal opened from the editor).
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    $allowed = [ 'edit.php', 'upload.php', 'post.php', 'post-new.php' ];
    if ( ! in_array( $hook, $allowed, true ) ) {
        return;
    }

    wp_enqueue_style(
        'lihi-button',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-button.css',
        [],
        '1.0.3'
    );

    wp_enqueue_script(
        'lihi-button-api',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-button-api.js',
        [],
        '1.0.3',
        true
    );

    wp_enqueue_script(
        'lihi-button-modal',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-button-modal.js',
        [ 'lihi-button-api' ],
        '1.0.3',
        true
    );

    wp_enqueue_script(
        'lihi-button',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-button.js',
        [ 'lihi-button-api', 'lihi-button-modal' ],
        '1.0.3',
        true
    );

    wp_localize_script( 'lihi-button-api', 'lihiButton', [
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'lihi_short_url' ),
        'copyAction'    => 'lihi_copy_url',
        'createAction'  => 'lihi_create_url',
        'optionsAction' => 'lihi_url_options',
        'siteHost'      => lihi_site_host(),
        'labelOriginal' => 'lihi',
        'labelReady'    => __( 'Copy', 'lihi-short-url' ),
        'labelCopied'   => __( 'Copied!', 'lihi-short-url' ),
        'copyFallback'  => __( 'Clipboard access was blocked. Copy this short URL:', 'lihi-short-url' ),
        'resetDelay'    => 300,
        'labelDelay'    => 1200,
        'modal'         => [
            'title'         => __( 'Create lihi Short URL', 'lihi-short-url' ),
            'domain'        => __( 'Domain', 'lihi-short-url' ),
            'noDomains'     => __( 'No redirect domains are available for this lihi account.', 'lihi-short-url' ),
            'tags'          => __( 'Tags', 'lihi-short-url' ),
            'tagsAdd'       => __( 'Add', 'lihi-short-url' ),
            'removeTag'     => __( 'Remove tag', 'lihi-short-url' ),
            'utmSource'     => __( 'UTM source', 'lihi-short-url' ),
            'utmMedium'     => __( 'UTM medium', 'lihi-short-url' ),
            'utmCampaign'   => __( 'UTM campaign', 'lihi-short-url' ),
            'utmTerm'       => __( 'UTM term', 'lihi-short-url' ),
            'utmContent'    => __( 'UTM content', 'lihi-short-url' ),
            'cancel'        => __( 'Cancel', 'lihi-short-url' ),
            'submit'        => __( 'Create & Copy', 'lihi-short-url' ),
            'loading'       => __( 'Loading...', 'lihi-short-url' ),
        ],
        'notice'        => [
            'title'   => __( 'lihi Short URL', 'lihi-short-url' ),
            'confirm' => __( 'OK', 'lihi-short-url' ),
        ],
    ] );
} );

// Register column header and a frontend button container for every public post type.
// Media Library list mode (upload.php?mode=list) uses a different filter
// pair (manage_media_columns / manage_media_custom_column), handled separately.
add_action( 'admin_init', function () {
    $render_container = function ( $post_id, $post_type ) {
        echo render_lihi_button_container( (int) $post_id, (string) $post_type );
    };

    foreach ( get_post_types( [ 'public' => true ], 'names' ) as $post_type ) {
        if ( $post_type === 'attachment' ) {
            continue;
        }

        add_filter( "manage_{$post_type}_posts_columns", function ( $columns ) {
            $columns['lihi'] = __( 'Short URL', 'lihi-short-url' );
            return $columns;
        } );

        add_action( "manage_{$post_type}_posts_custom_column", function ( $column, $post_id ) use ( $post_type, $render_container ) {
            if ( $column === 'lihi' ) {
                $render_container( $post_id, $post_type );
            }
        }, 10, 2 );
    }

    // Media Library list mode.
    add_filter( 'manage_media_columns', function ( $columns ) {
        $columns['lihi'] = __( 'Short URL', 'lihi-short-url' );
        return $columns;
    } );

    add_action( 'manage_media_custom_column', function ( $column, $post_id ) use ( $render_container ) {
        if ( $column === 'lihi' ) {
            $render_container( $post_id, 'attachment' );
        }
    }, 10, 2 );
} );

// Add a frontend button container to the attachment detail panel in the media grid view.
add_filter( 'attachment_fields_to_edit', function ( $form_fields, $post ) {
    $form_fields['lihi'] = [
        'label' => __( 'Short URL', 'lihi-short-url' ),
        'input' => 'html',
        'html'  => render_lihi_button_container( (int) $post->ID, (string) $post->post_type ),
    ];
    return $form_fields;
}, 10, 2 );

} // end lihi_email() guard
