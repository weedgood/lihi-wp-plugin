<?php

namespace Lihi\ShortUrl\Tests;

class AdminNoticeTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option( 'lihi_email' );
        delete_option( 'lihi_domain' );
        remove_all_actions( 'admin_notices' );
    }

    protected function tearDown(): void
    {
        remove_all_actions( 'admin_notices' );
        update_option( 'lihi_email', 'test@example.com' );
        update_option( 'lihi_domain', 'redirect.lihidev.com' );
        parent::tearDown();
    }

    /**
     * Re-execute bootstrap.php so the email/domain guards run against the
     * option state set up in the test. `include` (not require_once) lets the
     * file run again on each call.
     */
    private function loadBootstrap(): void
    {
        include dirname( __DIR__ ) . '/lihi-short-url/bootstrap.php';
    }

    public function test_no_global_admin_notice_when_email_empty(): void
    {
        $this->loadBootstrap();
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertFalse( has_action( 'admin_notices' ) );
        $this->assertSame( '', $output );
    }

    public function test_no_global_admin_notice_when_domain_empty(): void
    {
        update_option( 'lihi_email', 'admin@example.com' );
        $this->loadBootstrap();

        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertFalse( has_action( 'admin_notices' ) );
        $this->assertSame( '', $output );
    }

    public function test_no_global_admin_notice_when_fully_configured(): void
    {
        update_option( 'lihi_email', 'admin@example.com' );
        update_option( 'lihi_domain', 'redirect.lihidev.com' );
        $this->loadBootstrap();

        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertFalse( has_action( 'admin_notices' ) );
        $this->assertSame( '', $output );
    }
}
