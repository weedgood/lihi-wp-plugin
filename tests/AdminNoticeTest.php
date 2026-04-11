<?php

namespace Lihi\ShortUrl\Tests;

class AdminNoticeTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option( 'lihi_email' );
        // Re-run bootstrap with empty email so the admin_notices guard fires.
        // `include` (not require_once) lets the file execute again.
        include dirname( __DIR__ ) . '/lihi-shorturl/bootstrap.php';
    }

    protected function tearDown(): void
    {
        remove_all_actions( 'admin_notices' );
        update_option( 'lihi_email', 'test@example.com' );
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Hook registration
    // -------------------------------------------------------------------------

    public function test_admin_notices_hook_registered_when_email_empty(): void
    {
        $this->assertNotFalse( has_action( 'admin_notices' ) );
    }

    // -------------------------------------------------------------------------
    // Notice output
    // -------------------------------------------------------------------------

    public function test_notice_shown_to_admin_with_link_to_settings(): void
    {
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'notice-warning', $output );
        $this->assertStringContainsString( 'lihi-settings', $output );
    }

    public function test_notice_hidden_from_subscriber(): void
    {
        $subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $subscriber );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertSame( '', $output );
    }
}
