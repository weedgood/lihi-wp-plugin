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

// Enqueue lihi-button.js on post-type list screens and the media grid.
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'edit.php' && $hook !== 'upload.php' ) {
        return;
    }

    wp_enqueue_style(
        'lihi-button',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-button.css',
        [],
        '0.1.0'
    );

    wp_enqueue_script(
        'lihi-button',
        plugin_dir_url( __FILE__ ) . '../assets/lihi-button.js',
        [],
        '0.1.0',
        true
    );

    wp_localize_script( 'lihi-button', 'lihiButton', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'lihi_copy_url' ),
        'action'      => 'lihi_copy_url',
        'labelCopied' => __( 'Copied!', 'lihi-shorturl' ),
        'resetDelay'  => 300,
    ] );
} );

// Register column header and button for the current post type only.
add_action( 'init', function () {
    $post_type = sanitize_key( $_GET['post_type'] ?? 'post' );

    add_filter( "manage_{$post_type}_posts_columns", function ( $columns ) {
        $columns['lihi'] = __( 'Shout URL', 'lihi-shorturl' );
        return $columns;
    } );

    add_action( "manage_{$post_type}_posts_custom_column", function ( $column, $post_id ) use ( $post_type ) {
        if ( $column === 'lihi' ) {
            echo '<button class="button button-secondary" data-lihi data-id="' . esc_attr( $post_id ) . '" data-type="' . esc_attr( $post_type ) . '">Lihi</button>';
        }
    }, 10, 2 );
} );

// Add a Lihi button to the attachment detail panel in the media grid view.
add_filter( 'attachment_fields_to_edit', function ( $form_fields, $post ) {
    $form_fields['lihi'] = [
        'label' => __( 'Shout URL', 'lihi-shorturl' ),
        'input' => 'html',
        'html'  => '<button class="button button-secondary" data-lihi data-id="' . esc_attr( $post->ID ) . '" data-type="' . esc_attr( $post->post_type ) . '">Lihi</button>',
    ];
    return $form_fields;
}, 10, 2 );

// Fetch or create a Lihi short URL for a post, then return it for clipboard copy.
add_action( 'wp_ajax_lihi_copy_url', function () {
    check_ajax_referer( 'lihi_copy_url', 'nonce' );

    $item_id = intval( $_POST['item_id'] ?? 0 );
    $type    = sanitize_key( $_POST['type'] ?? '' );

    if ( ! $item_id || ! $type ) {
        wp_send_json_error( 'Invalid post ID or type.' );
        return;
    }

    try {
        $url = lihi_service()->get_or_create_short_url( $item_id, $type );
        wp_send_json_success( [ 'url' => $url ] );
    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
} );
