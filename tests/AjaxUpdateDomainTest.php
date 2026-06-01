<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

class AjaxUpdateDomainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $_POST = [];

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    private function expectJsonError(?string &$message, ?int &$statusCode): void
    {
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg, $status = null) use (&$message, &$statusCode) {
                $message    = $msg;
                $statusCode = $status;
            });
    }

    /** @test */
    public function returns_error_when_user_lacks_manage_options(): void
    {
        $_POST['domain'] = 'redirect.lihidev.com';
        Functions\when('current_user_can')->justReturn(false);

        Functions\expect('update_option')->never();
        Functions\expect('delete_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_domain();

        $this->assertStringContainsString('permission', $captured);
        $this->assertSame(403, $statusCode);
    }

    /** @test */
    public function empty_domain_clears_option_and_returns_success(): void
    {
        $_POST['domain'] = '';

        Functions\expect('update_option')->never();
        Functions\expect('delete_option')
            ->once()
            ->with('lihi_domain');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_update_domain();

        $this->assertStringContainsString('cleared', $sent['message']);
    }

    /** @test */
    public function whitespace_only_domain_clears_option(): void
    {
        $_POST['domain'] = "   \t\n";

        Functions\expect('delete_option')
            ->once()
            ->with('lihi_domain');
        Functions\expect('update_option')->never();
        Functions\expect('wp_send_json_success')->once();

        \Lihi\ShortUrl\ajax_update_domain();
        $this->assertTrue(true, 'whitespace input cleared option via delete_option');
    }

    /** @test */
    public function invalid_domain_is_rejected_without_saving(): void
    {
        $_POST['domain'] = 'not a domain!';

        Functions\expect('update_option')->never();
        Functions\expect('delete_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_domain();

        $this->assertStringContainsString('Invalid', $captured);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function valid_domain_is_saved_and_returns_success(): void
    {
        $_POST['domain'] = 'redirect.lihidev.com';

        Functions\expect('update_option')
            ->once()
            ->with('lihi_domain', 'redirect.lihidev.com');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_update_domain();

        $this->assertStringContainsString('saved', $sent['message']);
    }
}
