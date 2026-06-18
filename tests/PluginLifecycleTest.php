<?php

namespace Lihi\ShortUrl\Tests;

class PluginLifecycleTest extends \WP_UnitTestCase
{
    protected function tearDown(): void
    {
        delete_option( 'lihi_uuid' );
        delete_option( 'lihi_uuid_lock' );
        update_option( 'lihi_email', 'test@example.com' );
        delete_option( 'lihi_domain' );
        delete_transient( 'lihi_token' );
        parent::tearDown();
    }

    public function test_deactivation_hook_is_registered(): void
    {
        $hook = 'deactivate_' . plugin_basename( dirname( __DIR__ ) . '/lihi-short-url/lihi-short-url.php' );

        $this->assertNotFalse( has_action( $hook, 'Lihi\\ShortUrl\\deactivate' ) );
    }

    public function test_deactivation_clears_plugin_owned_site_data(): void
    {
        update_option( 'lihi_email', 'admin@example.com' );
        update_option( 'lihi_domain', 'redirect.lihidev.com' );
        update_option( 'lihi_uuid', '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e' );
        update_option( 'lihi_uuid_lock', '1' );
        set_transient( 'lihi_token', 'stale-jwt', HOUR_IN_SECONDS );

        do_action( 'deactivate_' . plugin_basename( dirname( __DIR__ ) . '/lihi-short-url/lihi-short-url.php' ) );

        $this->assertFalse( get_option( 'lihi_email' ) );
        $this->assertFalse( get_option( 'lihi_domain' ) );
        $this->assertFalse( get_option( 'lihi_uuid' ) );
        $this->assertFalse( get_option( 'lihi_uuid_lock' ) );
        $this->assertFalse( get_transient( 'lihi_token' ) );
    }
}
