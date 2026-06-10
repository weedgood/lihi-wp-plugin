<?php
/**
 * Uninstall handler for the lihi Short URL plugin.
 *
 * Runs once when the user deletes the plugin from the WordPress admin and
 * removes every option / transient the plugin writes, so no data is left
 * behind in the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'lihi_email' );
delete_option( 'lihi_domain' );
delete_option( 'lihi_uuid' );
delete_option( 'lihi_uuid_lock' );
delete_transient( 'lihi_token' );
