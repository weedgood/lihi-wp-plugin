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

// UI hooks (column, button, enqueue) only register when the plugin is configured.
// The AJAX handler below is registered unconditionally so any stale button
// clicks get a friendly error rather than WordPress's bare "0" response.
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

// Register column header and button for every public post type.
// Media Library list mode (upload.php?mode=list) uses a different filter
// pair (manage_media_columns / manage_media_custom_column), handled separately.
add_action( 'admin_init', function () {
    $render_button = function ( $post_id, $post_type ) {
        echo '<button class="button button-secondary" data-lihi data-id="' . esc_attr( $post_id ) . '" data-type="' . esc_attr( $post_type ) . '">lihi</button>';
    };

    foreach ( get_post_types( [ 'public' => true ], 'names' ) as $post_type ) {
        if ( $post_type === 'attachment' ) {
            continue;
        }

        add_filter( "manage_{$post_type}_posts_columns", function ( $columns ) {
            $columns['lihi'] = __( 'Short URL', 'lihi-shorturl' );
            return $columns;
        } );

        add_action( "manage_{$post_type}_posts_custom_column", function ( $column, $post_id ) use ( $post_type, $render_button ) {
            if ( $column === 'lihi' ) {
                $render_button( $post_id, $post_type );
            }
        }, 10, 2 );
    }

    // Media Library list mode.
    add_filter( 'manage_media_columns', function ( $columns ) {
        $columns['lihi'] = __( 'Short URL', 'lihi-shorturl' );
        return $columns;
    } );

    add_action( 'manage_media_custom_column', function ( $column, $post_id ) use ( $render_button ) {
        if ( $column === 'lihi' ) {
            $render_button( $post_id, 'attachment' );
        }
    }, 10, 2 );
} );

// Add a lihi button to the attachment detail panel in the media grid view.
add_filter( 'attachment_fields_to_edit', function ( $form_fields, $post ) {
    $form_fields['lihi'] = [
        'label' => __( 'Short URL', 'lihi-shorturl' ),
        'input' => 'html',
        'html'  => '<button class="button button-secondary" data-lihi data-id="' . esc_attr( $post->ID ) . '" data-type="' . esc_attr( $post->post_type ) . '">lihi</button>',
    ];
    return $form_fields;
}, 10, 2 );

} // end lihi_email() guard

/**
 * AJAX handler: fetch or create a lihi short URL for a post and return it
 * to the browser for clipboard copy.
 *
 * Exported as a named function (rather than an inline closure) so tests can
 * invoke it directly without walking $wp_filter.
 */
function ajax_copy_url(): void {
    check_ajax_referer( 'lihi_copy_url', 'nonce' );

    if ( lihi_email() === '' ) {
        wp_send_json_error( __( 'lihi email is not configured. Please set it in Settings → lihi Short URL.', 'lihi-shorturl' ) );
        return;
    }

    $item_id = intval( $_POST['item_id'] ?? 0 );
    $type    = sanitize_key( $_POST['type'] ?? '' );

    if ( ! $item_id || ! $type ) {
        wp_send_json_error( __( 'Invalid post ID or type.', 'lihi-shorturl' ) );
        return;
    }

    try {
        $url = lihi_service()->get_or_create_short_url( $item_id, $type );
        wp_send_json_success( [ 'url' => $url ] );
    } catch ( Lihi_Auth_Exception $e ) {
        wp_send_json_error( __( 'lihi API key was rejected — this plugin version is no longer supported. Please update the plugin.', 'lihi-shorturl' ) );
    } catch ( Lihi_Email_Exception $e ) {
        wp_send_json_error( __( 'lihi rejected the configured email. Please verify it in Settings → lihi Short URL.', 'lihi-shorturl' ) );
    } catch ( Lihi_Validation_Exception $e ) {
        /* translators: %s: validation error message returned by the lihi API. */
        wp_send_json_error( sprintf( __( 'lihi API rejected the request: %s', 'lihi-shorturl' ), $e->getMessage() ) );
    } catch ( \Exception $e ) {
        error_log( '[lihi] ' . $e->getMessage() );
        wp_send_json_error( __( 'Failed to generate short URL. Please try again later.', 'lihi-shorturl' ) );
    }
}

add_action( 'wp_ajax_lihi_copy_url', __NAMESPACE__ . '\\ajax_copy_url' );
