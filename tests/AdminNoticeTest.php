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
        include dirname( __DIR__ ) . '/lihi-shorturl/bootstrap.php';
    }

    // -------------------------------------------------------------------------
    // Hook registration
    // -------------------------------------------------------------------------

    public function test_admin_notices_hook_registered_when_email_empty(): void
    {
        $this->loadBootstrap();
        $this->assertNotFalse( has_action( 'admin_notices' ) );
    }

    public function test_admin_notices_hook_registered_when_domain_empty(): void
    {
        update_option( 'lihi_email', 'admin@example.com' );
        $this->loadBootstrap();
        $this->assertNotFalse( has_action( 'admin_notices' ) );
    }

    public function test_admin_notices_hook_not_registered_when_fully_configured(): void
    {
        update_option( 'lihi_email', 'admin@example.com' );
        update_option( 'lihi_domain', 'redirect.lihidev.com' );
        $this->loadBootstrap();
        $this->assertFalse( has_action( 'admin_notices' ) );
    }

    // -------------------------------------------------------------------------
    // Notice output: email empty
    // -------------------------------------------------------------------------

    public function test_email_notice_shown_to_admin_with_link_to_settings(): void
    {
        $this->loadBootstrap();
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'notice-warning', $output );
        $this->assertStringContainsString( 'lihi-settings', $output );
        $this->assertStringContainsString( 'configure your email address', $output );
    }

    public function test_email_notice_hidden_from_subscriber(): void
    {
        $this->loadBootstrap();
        $subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $subscriber );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertSame( '', $output );
    }

    // -------------------------------------------------------------------------
    // Notice output: email set, domain empty
    // -------------------------------------------------------------------------

    public function test_domain_notice_shown_to_admin_with_link_to_settings(): void
    {
        update_option( 'lihi_email', 'admin@example.com' );
        $this->loadBootstrap();

        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'notice-warning', $output );
        $this->assertStringContainsString( 'lihi-settings', $output );
        $this->assertStringContainsString( 'choose a redirect domain', $output );
    }

    public function test_domain_notice_hidden_from_subscriber(): void
    {
        update_option( 'lihi_email', 'admin@example.com' );
        $this->loadBootstrap();

        $subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $subscriber );

        ob_start();
        do_action( 'admin_notices' );
        $output = ob_get_clean();

        $this->assertSame( '', $output );
    }
}
