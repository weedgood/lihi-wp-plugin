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
    // wp_ajax_lihi_url_options / copy / create
    // -------------------------------------------------------------------------

    /** @test */
    public function url_options_returns_profile_domains_for_modal(): void
    {
        $_POST['item_id'] = '42';

        $service = $this->mockService();
        $service->shouldReceive('get_profile')
            ->once()
            ->andReturn(['domains' => ['go.example.com', 'go2.example.com']]);
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_url_options();

        $this->assertSame(['go.example.com', 'go2.example.com'], $sent['domains']);
    }

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
        $service->shouldNotReceive('get_existing_short_url');
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
        $service->shouldReceive('get_existing_short_url')
            ->with(42, 'post')
            ->once()
            ->andReturn('abc-slug');
        $service->shouldNotReceive('get_or_create_short_url');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '1');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('abc-slug', $sent['url']);
        $this->assertTrue($sent['lihi_already']);
    }

    /** @test */
    public function derives_type_from_item_id_instead_of_trusting_client_payload(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'forged';
        $_POST['domain']  = 'go.example.com';

        Functions\when('get_post_type')->justReturn('page');

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->with(42, 'page', ['domain' => 'go.example.com', 'tags' => [], 'utm' => []])
            ->once()
            ->andReturn('page-slug');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '1');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertSame('page-slug', $sent['url']);
        $this->assertTrue($sent['lihi_already']);
    }

    /** @test */
    public function passes_modal_options_to_service_on_create_request(): void
    {
        $_POST['item_id'] = '42';
        $_POST['domain']  = 'go.example.com';
        $_POST['tags']    = '[" launch ","post",""]';
        $_POST['utm']     = '{"source":"newsletter","medium":"email","ignored":"x"}';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->with(42, 'post', [
                'domain' => 'go.example.com',
                'tags'   => ['launch', 'post'],
                'utm'    => ['source' => 'newsletter', 'medium' => 'email'],
            ])
            ->once()
            ->andReturn('option-slug');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '1');
        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertSame('option-slug', $sent['url']);
        $this->assertTrue($sent['lihi_already']);
    }

    /** @test */
    public function create_passes_selected_domain_to_saas_without_extra_profile_validation(): void
    {
        $_POST['item_id'] = '42';
        $_POST['domain']  = 'other.example.com';

        $service = $this->mockService();
        $service->shouldNotReceive('get_profile');
        $service->shouldReceive('get_or_create_short_url')
            ->with(42, 'post', ['domain' => 'other.example.com', 'tags' => [], 'utm' => []])
            ->once()
            ->andReturn('other-slug');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '1');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertSame('other-slug', $sent['url']);
        $this->assertTrue($sent['lihi_already']);
    }

    /** @test */
    public function create_requires_a_selected_domain(): void
    {
        $_POST['item_id'] = '42';

        $service = $this->mockService();
        $service->shouldNotReceive('get_profile');
        $service->shouldNotReceive('get_or_create_short_url');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertStringContainsString('Please choose a redirect domain', $errorMsg);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function copy_returns_existing_short_url_without_creating(): void
    {
        $_POST['item_id'] = '42';

        $service = $this->mockService();
        $service->shouldReceive('get_existing_short_url')
            ->with(42, 'post')
            ->once()
            ->andReturn('existing-slug');
        $service->shouldNotReceive('get_or_create_short_url');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '1');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('existing-slug', $sent['url']);
        $this->assertTrue($sent['lihi_already']);
    }

    /** @test */
    public function copy_marks_item_unready_when_existing_short_url_is_missing(): void
    {
        $_POST['item_id'] = '42';

        $service = $this->mockService();
        $service->shouldReceive('get_existing_short_url')
            ->with(42, 'post')
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Not_Found_Exception('missing'));
        $service->shouldNotReceive('get_or_create_short_url');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '0');

        $captured   = null;
        $statusCode = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data, $status = null) use (&$captured, &$statusCode) {
                $captured   = $data;
                $statusCode = $status;
            });

        \Lihi\ShortUrl\ajax_copy_url();

        $this->assertSame('lihi_missing', $captured['code']);
        $this->assertStringContainsString('removed', $captured['message']);
        $this->assertSame(410, $statusCode);
    }

    /** @test */
    public function edit_returns_passthrough_nonce_for_existing_short_url(): void
    {
        $_POST['item_id'] = '42';
        $_POST['challenge'] = str_repeat('A', 43);

        $service = $this->mockService();
        $service->shouldReceive('get_existing_short_url')
            ->with(42, 'post')
            ->once()
            ->andReturn('https://lihi.io/existing');
        $service->shouldReceive('create_passthrough_nonce')
            ->with('https://lihi.io/existing', str_repeat('A', 43))
            ->once()
            ->andReturn('nonce-token');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\when('Lihi\ShortUrl\lihi_passthrough_form_action')->justReturn('https://app.lihidev.com/api/wordpress/v1/passthrough/redirect');
        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '1');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_edit_url();

        $this->assertSame('nonce-token', $sent['nonce']);
        $this->assertSame('https://app.lihidev.com/api/wordpress/v1/passthrough/redirect', $sent['form_action']);
        $this->assertSame('https://lihi.io/existing', $sent['target']);
    }

    /** @test */
    public function edit_requires_manage_options(): void
    {
        $_POST['item_id'] = '42';

        $service = $this->mockService();
        $service->shouldNotReceive('get_existing_short_url');
        $service->shouldNotReceive('create_passthrough_nonce');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $checkedCaps = [];
        Functions\when('current_user_can')->alias(function ($capability, $postId = null) use (&$checkedCaps) {
            $checkedCaps[] = [$capability, $postId];
            return $capability !== 'manage_options';
        });

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_edit_url();

        $this->assertContains(['manage_options', null], $checkedCaps);
        $this->assertNotContains(['read_post', 42], $checkedCaps);
        $this->assertStringContainsString('permission', $errorMsg);
        $this->assertSame(403, $statusCode);
    }

    /** @test */
    public function edit_requires_browser_challenge(): void
    {
        $_POST['item_id'] = '42';

        $service = $this->mockService();
        $service->shouldNotReceive('get_existing_short_url');
        $service->shouldNotReceive('create_passthrough_nonce');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_edit_url();

        $this->assertStringContainsString('verify browser session', $errorMsg);
        $this->assertStringNotContainsString('lihi API rejected', $errorMsg);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function edit_marks_item_unready_when_existing_short_url_is_missing(): void
    {
        $_POST['item_id'] = '42';
        $_POST['challenge'] = str_repeat('A', 43);

        $service = $this->mockService();
        $service->shouldReceive('get_existing_short_url')
            ->with(42, 'post')
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Not_Found_Exception('missing'));
        $service->shouldNotReceive('create_passthrough_nonce');
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'lihi_already', '0');

        $captured   = null;
        $statusCode = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data, $status = null) use (&$captured, &$statusCode) {
                $captured   = $data;
                $statusCode = $status;
            });

        \Lihi\ShortUrl\ajax_edit_url();

        $this->assertSame('lihi_missing', $captured['code']);
        $this->assertStringContainsString('removed', $captured['message']);
        $this->assertSame(410, $statusCode);
    }

    /** @test */
    public function returns_error_when_service_throws(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';
        $_POST['domain']  = 'go.example.com';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \RuntimeException('API error'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertSame('Failed to generate short URL. Please try again later.', $errorMsg);
        $this->assertSame(500, $statusCode);
    }

    /** @test */
    public function returns_friendly_message_on_auth_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';
        $_POST['domain']  = 'go.example.com';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Auth_Exception('API Key error'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        Functions\when('check_ajax_referer')->justReturn(true);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertStringContainsString('has not been verified', $errorMsg);
        $this->assertSame(403, $statusCode);
    }

    /** @test */
    public function returns_friendly_message_on_user_invalid_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';
        $_POST['domain']  = 'go.example.com';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_User_Invalid_Exception('User Invalid'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertStringContainsString('account is unavailable', $errorMsg);
        $this->assertSame(403, $statusCode);
    }

    /** @test */
    public function returns_login_expired_message_on_token_invalid_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';
        $_POST['domain']  = 'go.example.com';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: Token expired ,please login again'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertStringContainsString('login session has expired', $errorMsg);
        $this->assertSame(401, $statusCode);
    }

    /** @test */
    public function returns_user_invalid_message_on_user_not_found_response_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';
        $_POST['domain']  = 'go.example.com';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_User_Invalid_Exception('HTTP 404: user_not_found ,please login again'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertStringContainsString('account is unavailable', $errorMsg);
        $this->assertSame(403, $statusCode);
    }

    /** @test */
    public function returns_bad_request_when_service_throws_validation_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';
        $_POST['domain']  = 'go.example.com';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Validation_Exception('bad request'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertStringContainsString('lihi API rejected the request', $errorMsg);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function returns_service_unavailable_when_lihi_service_throws_server_exception(): void
    {
        $_POST['item_id'] = '42';
        $_POST['type']    = 'post';
        $_POST['domain']  = 'go.example.com';

        $service = $this->mockService();
        $service->shouldReceive('get_or_create_short_url')
            ->andThrow(new \Lihi\ShortUrl\Lihi_Server_Exception('upstream unavailable'));
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);

        $errorMsg   = null;
        $statusCode = null;
        $this->expectJsonError($errorMsg, $statusCode);

        \Lihi\ShortUrl\ajax_create_url();

        $this->assertStringContainsString('lihi service is unavailable', $errorMsg);
        $this->assertSame(503, $statusCode);
    }
}
