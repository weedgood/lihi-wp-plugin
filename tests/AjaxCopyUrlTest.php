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
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post_type')->justReturn('post');
    }

    protected function tearDown(): void
    {
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set(null);
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

    private function expectJsonError(?string &$message, ?int &$statusCode): void
    {
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg, $status = null) use (&$message, &$statusCode) {
                $message    = $msg;
                $statusCode = $status;
            });
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

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('lihi email is not configured', $errorMsg);
        $this->assertSame(409, $statusCode);
    }

    /** @test */
    public function returns_error_when_domain_is_not_configured(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('Lihi\\ShortUrl\\lihi_domain')->justReturn('');

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('lihi redirect domain is not configured', $errorMsg);
        $this->assertSame(409, $statusCode);
    }

    /** @test */
    public function returns_error_when_item_id_is_zero(): void
    {
        $_POST['item_id'] = '0';
        $_POST['type']    = 'post';

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('Invalid post ID or type.', $errorMsg);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function returns_error_when_post_type_cannot_be_resolved(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('get_post_type')->justReturn(false);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('Invalid post ID or type.', $errorMsg);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function returns_error_when_user_cannot_read_item(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldNotReceive('get_or_create_short_url');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $checkedCap    = null;
        $checkedPostId = null;
        Functions\when('current_user_can')->alias(function ($capability, $postId = null) use (&$checkedCap, &$checkedPostId) {
            $checkedCap    = $capability;
            $checkedPostId = $postId;
            return false;
        });

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('read_post', $checkedCap);
        $this->assertSame(42, $checkedPostId);
        $this->assertStringContainsString('permission', $errorMsg);
        $this->assertSame(403, $statusCode);
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
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

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
    public function derives_type_from_item_id_instead_of_trusting_client_payload(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'forged';

        Functions\when('get_post_type')->justReturn('page');

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->with(42, 'page')
            ->once()
            ->andReturn('page-slug');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame(['url' => 'page-slug'], $sent);
    }

    /** @test */
    public function returns_error_when_service_throws(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \RuntimeException('API error'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('Failed to generate short URL. Please try again later.', $errorMsg);
        $this->assertSame(500, $statusCode);
    }

    /** @test */
    public function returns_friendly_message_on_auth_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Auth_Exception('API Key error'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('has not been verified', $errorMsg);
        $this->assertSame(403, $statusCode);
    }

    /** @test */
    public function returns_bad_request_when_service_throws_validation_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Validation_Exception('bad request'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('lihi API rejected the request', $errorMsg);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function returns_service_unavailable_when_lihi_service_throws_server_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Server_Exception('upstream unavailable'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertStringContainsString('lihi service is unavailable', $errorMsg);
        $this->assertSame(503, $statusCode);
    }
}
