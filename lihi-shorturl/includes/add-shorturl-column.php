<?php
/**
 * Adds a "Shout URL" column to the Posts list table.
 *
 * Each row displays a Lihi button that triggers the short-URL flow via post-button.js.
 * The script is enqueued only on edit.php to avoid unnecessary loading on other admin pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue post-button.js only on the Posts list screen (edit.php).
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'edit.php' ) {
        return;
    }
    // Register post-button.js to be output in the admin footer.
    wp_enqueue_script(
        'lihi-admin',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/post-button.js',
        [],
        '0.1.0',
        true
    );
} );

// Register the "Shout URL" column header in the Posts list table.
add_filter( 'manage_posts_columns', function ( $columns ) {
    // __() returns the translated string for the given text domain.
    $columns['lihi'] = __( 'Shout URL', 'lihi-shorturl' );
    return $columns;
} );

// Render the Lihi button inside the "Shout URL" column for each post row.
add_action( 'manage_posts_custom_column', function ( $column, $post_id ) {
    if ( $column === 'lihi' ) {
        // esc_attr() sanitises the post ID before outputting it as an HTML attribute.
        echo '<button class="button button-secondary" data-id="' . esc_attr( $post_id ) . '">Lihi</button>';
    }
}, 10, 2 );
