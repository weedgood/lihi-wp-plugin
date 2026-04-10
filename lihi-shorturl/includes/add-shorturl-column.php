<?php
namespace Lihi\ShortUrl;

/**
 * Adds a "Shout URL" column to all public post type list tables.
 *
 * Dynamically registers column hooks for every public post type so that
 * custom post types are supported without extra configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue post-button.js on any post-type list screen (edit.php).
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'edit.php' ) {
        return;
    }

    wp_enqueue_style(
        'lihi-admin',
        plugin_dir_url( __FILE__ ) . '../assets/post-button.css',
        [],
        '0.1.0'
    );

    wp_enqueue_script(
        'lihi-admin',
        plugin_dir_url( __FILE__ ) . '../assets/post-button.js',
        [],
        '0.1.0',
        true
    );

    wp_localize_script( 'lihi-admin', 'lihiAdmin', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'lihi_copy_url' ),
        'action'      => 'lihi_copy_url',
        'labelCopied' => __( 'Copied!', 'lihi-shorturl' ),
        'resetDelay'  => 2000,
    ] );
} );

// Register column header and button for every public post type.
add_action( 'init', function () {
    $post_types = get_post_types( [ 'public' => true ], 'names' );

    foreach ( $post_types as $post_type ) {
        add_filter( "manage_{$post_type}_posts_columns", function ( $columns ) {
            $columns['lihi'] = __( 'Shout URL', 'lihi-shorturl' );
            return $columns;
        } );

        add_action( "manage_{$post_type}_posts_custom_column", function ( $column, $post_id ) use ( $post_type ) {
            if ( $column === 'lihi' ) {
                echo '<button class="button button-secondary" data-id="' . esc_attr( $post_id ) . '" data-type="' . esc_attr( $post_type ) . '">Lihi</button>';
            }
        }, 10, 2 );
    }
} );

// Fetch or create a Lihi short URL for a post, then return it for clipboard copy.
add_action( 'wp_ajax_lihi_copy_url', function () {
    check_ajax_referer( 'lihi_copy_url', 'nonce' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    $type    = sanitize_key( $_POST['type'] ?? '' );

    if ( ! $post_id || ! $type ) {
        wp_send_json_error( 'Invalid post ID or type.' );
    }

    try {
        $url = lihi_service()->get_or_create_short_url( $post_id, $type );
        wp_send_json_success( [ 'url' => $url ] );
    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
} );
