<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'edit.php' ) {
        return;
    }
    wp_enqueue_script(
        'lihi-admin',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/post-button.js',
        [],
        '0.1.0',
        true
    );
} );

add_filter( 'manage_posts_columns', function ( $columns ) {
    $columns['lihi'] = __( 'Shout URL', 'lihi-wp-plugin' );
    return $columns;
} );

add_action( 'manage_posts_custom_column', function ( $column, $post_id ) {
    if ( $column === 'lihi' ) {
        echo '<button class="button button-secondary" data-id="' . esc_attr( $post_id ) . '">Lihi</button>';
    }
}, 10, 2 );
