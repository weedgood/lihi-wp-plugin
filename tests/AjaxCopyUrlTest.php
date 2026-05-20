<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Service;
use Mockery;
use PHPUnit\Framework\TestCase;

class AjaxCopyUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $_POST = [];

        Functions\when('check_ajax_referer')->justReturn(true);
    }

    protected function tearDown(): void
    {
        \Lihi\ShortUrl\lihi_service_set(null);
        $_POST = [];
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return \Mockery\MockInterface&Lihi_Service
     */
    private function mockService(): Lihi_Service
    {
        return Mockery::mock(Lihi_Service::class);
    }

    // -------------------------------------------------------------------------
    // wp_ajax_lihi_copy_url
    // -------------------------------------------------------------------------

    /** @test */
    public function returns_error_when_email_is_not_configured(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('Lihi\\ShortUrl\\lihi_email')->justReturn('');

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('lihi email is not configured', $errorMsg);
    }

    /** @test */
    public function returns_error_when_domain_is_not_configured(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('Lihi\\ShortUrl\\lihi_domain')->justReturn('');

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('lihi redirect domain is not configured', $errorMsg);
    }

    /** @test */
    public function returns_error_when_item_id_is_zero(): void
    {
        $_POST['item_id'] = '0';
        $_POST['type']    = 'post';

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('Invalid post ID or type.', $errorMsg);
    }

    /** @test */
    public function returns_error_when_type_is_empty(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = '';

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('Invalid post ID or type.', $errorMsg);
    }

    /** @test */
    public function returns_success_with_url_on_valid_request(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->with(42, 'post')
            ->once()
            ->andReturn('abc-slug');
        \Lihi\ShortUrl\lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame(['url' => 'abc-slug'], $sent);
    }

    /** @test */
    public function returns_error_when_service_throws(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \RuntimeException('API error'));
        \Lihi\ShortUrl\lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('Failed to generate short URL. Please try again later.', $errorMsg);
    }

    /** @test */
    public function returns_friendly_message_on_auth_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Auth_Exception('API Key error'));
        \Lihi\ShortUrl\lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('has not been verified', $errorMsg);
    }
}
