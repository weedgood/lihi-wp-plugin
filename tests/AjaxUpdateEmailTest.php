<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Client_Interface;
use Lihi\ShortUrl\Lihi_Rate_Limit_Exception;
use Lihi\ShortUrl\Lihi_Service;
use Lihi\ShortUrl\Lihi_Server_Exception;
use Lihi\ShortUrl\Lihi_Token_Store;
use Lihi\ShortUrl\Lihi_Validation_Exception;
use Mockery;
use PHPUnit\Framework\TestCase;

class AjaxUpdateEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $_POST = [];

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        // Mimic WordPress: sanitize_email() strips whitespace/control chars but
        // does not fully validate; is_email() is the RFC-style validator.
        Functions\when('sanitize_email')->alias(function ($email) {
            return preg_replace('/[\s\x00-\x1F\x7F]/', '', (string) $email);
        });
        Functions\when('is_email')->alias(function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
        });
    }

    protected function tearDown(): void
    {
        \Lihi\ShortUrl\Lihi_Singletons::lihi_client_set(null);
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set(null);
        \Lihi\ShortUrl\Lihi_Singletons::lihi_token_store_set(null);
        $_POST = [];
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return \Mockery\MockInterface&Lihi_Client_Interface
     */
    private function mockClient(): Lihi_Client_Interface
    {
        $client = Mockery::mock(Lihi_Client_Interface::class);
        \Lihi\ShortUrl\Lihi_Singletons::lihi_client_set($client);
        return $client;
    }

    /**
     * @return \Mockery\MockInterface&Lihi_Service
     */
    private function mockService(): Lihi_Service
    {
        $service = Mockery::mock(Lihi_Service::class);
        \Lihi\ShortUrl\Lihi_Singletons::lihi_service_set($service);
        return $service;
    }

    /**
     * @return \Mockery\MockInterface&Lihi_Token_Store
     */
    private function mockTokenStore()
    {
        $store = Mockery::mock(Lihi_Token_Store::class);
        \Lihi\ShortUrl\Lihi_Singletons::lihi_token_store_set($store);
        return $store;
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
        $_POST['email'] = 'alice@example.com';
        Functions\when('current_user_can')->justReturn(false);

        Functions\expect('update_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('permission', $captured);
        $this->assertSame(403, $statusCode);
    }

    /** @test */
    public function empty_email_clears_option_and_returns_success(): void
    {
        $_POST['email'] = '';

        $client = $this->mockClient();
        $client->shouldNotReceive('update_email');

        Functions\expect('update_option')->never();
        Functions\expect('delete_option')
            ->once()
            ->with('lihi_email');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertFalse($sent['verified']);
        $this->assertStringContainsString('cleared', $sent['message']);
    }

    /** @test */
    public function whitespace_only_email_clears_option(): void
    {
        $_POST['email'] = "   \t\n";

        $client = $this->mockClient();
        $client->shouldNotReceive('update_email');

        Functions\expect('update_option')->never();
        Functions\expect('delete_option')
            ->once()
            ->with('lihi_email');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertFalse($sent['verified']);
    }

    /** @test */
    public function returns_error_when_email_is_invalid(): void
    {
        $_POST['email'] = 'not-an-email';

        Functions\expect('update_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('valid email', $captured);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function returns_error_when_sanitized_email_fails_is_email_check(): void
    {
        // sanitize_email() strips the space → "alice@example" (no TLD), which
        // is_email() rejects. The handler must not fall through to update_email().
        $_POST['email'] = 'alice @example';

        $client = $this->mockClient();
        $client->shouldNotReceive('update_email');

        Functions\expect('update_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('valid email', $captured);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function update_option_not_called_when_auth_client_throws_validation(): void
    {
        $_POST['email'] = 'alice@example.com';
        $_POST['create_account_consent'] = '1';

        $this->mockClient()
            ->shouldReceive('update_email')
            ->with('alice@example.com')
            ->once()
            ->andThrow(new Lihi_Validation_Exception('bad email'));

        Functions\expect('update_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('rejected the email', $captured);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function update_option_not_called_when_auth_client_throws_rate_limit(): void
    {
        $_POST['email'] = 'alice@example.com';
        $_POST['create_account_consent'] = '1';

        $this->mockClient()
            ->shouldReceive('update_email')
            ->once()
            ->andThrow(new Lihi_Rate_Limit_Exception('too many requests'));

        Functions\expect('update_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('Too many', $captured);
        $this->assertSame(429, $statusCode);
    }

    /** @test */
    public function update_option_not_called_when_auth_client_throws_server_error(): void
    {
        $_POST['email'] = 'alice@example.com';
        $_POST['create_account_consent'] = '1';

        $this->mockClient()
            ->shouldReceive('update_email')
            ->once()
            ->andThrow(new Lihi_Server_Exception('500 upstream'));

        Functions\expect('update_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('unavailable', $captured);
        $this->assertSame(503, $statusCode);
    }

    /** @test */
    public function persists_option_and_returns_verified_true_on_success(): void
    {
        $_POST['email'] = 'alice@example.com';
        $_POST['create_account_consent'] = '1';

        $this->mockClient()
            ->shouldReceive('update_email')
            ->with('alice@example.com')
            ->once()
            ->andReturn(['verified' => true]);

        Functions\expect('update_option')
            ->once()
            ->with('lihi_email', 'alice@example.com');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertTrue($sent['verified']);
        $this->assertStringContainsString('verified', $sent['message']);
    }

    /** @test */
    public function persists_option_and_returns_verified_false_when_email_was_sent(): void
    {
        $_POST['email'] = 'alice@example.com';
        $_POST['create_account_consent'] = '1';

        $this->mockClient()
            ->shouldReceive('update_email')
            ->once()
            ->andReturn(['verified' => false]);

        Functions\expect('update_option')
            ->once()
            ->with('lihi_email', 'alice@example.com');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertFalse($sent['verified']);
        $this->assertStringContainsString('Verification email sent', $sent['message']);
    }

    /** @test */
    public function non_empty_email_requires_account_creation_consent(): void
    {
        $_POST['email'] = 'alice@example.com';

        $client = $this->mockClient();
        $client->shouldNotReceive('update_email');

        Functions\expect('update_option')->never();

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('confirm', $captured);
        $this->assertSame(400, $statusCode);
    }

    /** @test */
    public function dashboard_passthrough_returns_nonce_for_configured_email(): void
    {
        $_POST['challenge'] = str_repeat('A', 43);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('alice@example.com');
        Functions\when('Lihi\ShortUrl\lihi_passthrough_redirect_url')->justReturn('https://app.lihidev.com/api/wordpress/v1/passthrough/redirect');

        $this->mockTokenStore()
            ->shouldReceive('get')
            ->once()
            ->andReturn('cached-token');

        $this->mockService()
            ->shouldReceive('create_passthrough_nonce')
            ->with('', str_repeat('A', 43))
            ->once()
            ->andReturn('nonce-token');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_dashboard_passthrough();

        $this->assertTrue($sent['passthrough']);
        $this->assertSame('nonce-token', $sent['nonce']);
        $this->assertSame('https://app.lihidev.com/api/wordpress/v1/passthrough/redirect', $sent['redirect_url']);
    }

    /** @test */
    public function dashboard_passthrough_returns_dashboard_url_when_token_is_missing(): void
    {
        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('alice@example.com');
        Functions\when('Lihi\ShortUrl\lihi_dashboard_url')->justReturn('https://app.lihidev.com/admin');

        $this->mockTokenStore()
            ->shouldReceive('get')
            ->once()
            ->andReturn(false);

        $this->mockService()
            ->shouldNotReceive('create_passthrough_nonce');

        $sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$sent) {
                $sent = $data;
            });

        \Lihi\ShortUrl\ajax_dashboard_passthrough();

        $this->assertFalse($sent['passthrough']);
        $this->assertSame('https://app.lihidev.com/admin', $sent['dashboard_url']);
    }

    /** @test */
    public function dashboard_passthrough_requires_configured_email(): void
    {
        $_POST['challenge'] = str_repeat('A', 43);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('');

        $this->mockService()
            ->shouldNotReceive('create_passthrough_nonce');

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_dashboard_passthrough();

        $this->assertStringContainsString('lihi email is not configured', $captured);
        $this->assertSame(409, $statusCode);
    }

    /** @test */
    public function dashboard_passthrough_requires_browser_challenge(): void
    {
        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('alice@example.com');

        $this->mockTokenStore()
            ->shouldReceive('get')
            ->once()
            ->andReturn('cached-token');

        $this->mockService()
            ->shouldNotReceive('create_passthrough_nonce');

        $captured   = null;
        $statusCode = null;
        $this->expectJsonError($captured, $statusCode);

        \Lihi\ShortUrl\ajax_dashboard_passthrough();

        $this->assertStringContainsString('verify browser session', $captured);
        $this->assertSame(400, $statusCode);
    }
}
