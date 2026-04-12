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
    }

    protected function tearDown(): void
    {
        \Lihi\ShortUrl\lihi_service(null);
        $_POST = [];
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getAjaxHandler(): \Closure
    {
        global $wp_filter;

        if (!isset($wp_filter['wp_ajax_lihi_copy_url'])) {
            $this->fail("Hook 'wp_ajax_lihi_copy_url' is not registered");
        }

        foreach ($wp_filter['wp_ajax_lihi_copy_url']->callbacks as $callbacks) {
            foreach ($callbacks as $cb) {
                if ($cb['function'] instanceof \Closure) {
                    return $cb['function'];
                }
            }
        }

        $this->fail("No closure found for 'wp_ajax_lihi_copy_url'");
    }

    private function mockService(): \Mockery\MockInterface&Lihi_Service
    {
        return Mockery::mock(Lihi_Service::class);
    }

    // -------------------------------------------------------------------------
    // wp_ajax_lihi_copy_url
    // -------------------------------------------------------------------------

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

        ($this->getAjaxHandler())();

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

        ($this->getAjaxHandler())();

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
        \Lihi\ShortUrl\lihi_service($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        ($this->getAjaxHandler())();

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
        \Lihi\ShortUrl\lihi_service($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        ($this->getAjaxHandler())();

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
        \Lihi\ShortUrl\lihi_service($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$errorMsg) {
                $errorMsg = $msg;
            });

        ($this->getAjaxHandler())();

        $this->assertStringContainsString('Lihi login failed', $errorMsg);
    }
}
