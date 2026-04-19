<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Auth_Client_Interface;
use Lihi\ShortUrl\Lihi_Server_Exception;
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
        \Lihi\ShortUrl\lihi_auth_client_set(null);
        $_POST = [];
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    private function mockAuthClient(): \Mockery\MockInterface&Lihi_Auth_Client_Interface
    {
        $client = Mockery::mock(Lihi_Auth_Client_Interface::class);
        \Lihi\ShortUrl\lihi_auth_client_set($client);
        return $client;
    }

    /** @test */
    public function returns_error_when_user_lacks_manage_options(): void
    {
        $_POST['email'] = 'alice@example.com';
        Functions\when('current_user_can')->justReturn(false);

        Functions\expect('update_option')->never();

        $captured = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$captured) {
                $captured = $msg;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('permission', $captured);
    }

    /** @test */
    public function empty_email_clears_option_and_returns_success(): void
    {
        $_POST['email'] = '';

        $authClient = $this->mockAuthClient();
        $authClient->shouldNotReceive('update_email');

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

        $authClient = $this->mockAuthClient();
        $authClient->shouldNotReceive('update_email');

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

        $captured = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$captured) {
                $captured = $msg;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('valid email', $captured);
    }

    /** @test */
    public function returns_error_when_sanitized_email_fails_is_email_check(): void
    {
        // sanitize_email() strips the space → "alice@example" (no TLD), which
        // is_email() rejects. The handler must not fall through to update_email().
        $_POST['email'] = 'alice @example';

        $authClient = $this->mockAuthClient();
        $authClient->shouldNotReceive('update_email');

        Functions\expect('update_option')->never();

        $captured = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$captured) {
                $captured = $msg;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('valid email', $captured);
    }

    /** @test */
    public function update_option_not_called_when_auth_client_throws_validation(): void
    {
        $_POST['email'] = 'alice@example.com';

        $this->mockAuthClient()
            ->shouldReceive('update_email')
            ->with('alice@example.com')
            ->once()
            ->andThrow(new Lihi_Validation_Exception('bad email'));

        Functions\expect('update_option')->never();

        $captured = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$captured) {
                $captured = $msg;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('rejected the email', $captured);
    }

    /** @test */
    public function update_option_not_called_when_auth_client_throws_server_error(): void
    {
        $_POST['email'] = 'alice@example.com';

        $this->mockAuthClient()
            ->shouldReceive('update_email')
            ->once()
            ->andThrow(new Lihi_Server_Exception('500 upstream'));

        Functions\expect('update_option')->never();

        $captured = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($msg) use (&$captured) {
                $captured = $msg;
            });

        \Lihi\ShortUrl\ajax_update_email();

        $this->assertStringContainsString('unavailable', $captured);
    }

    /** @test */
    public function persists_option_and_returns_verified_true_on_success(): void
    {
        $_POST['email'] = 'alice@example.com';

        $this->mockAuthClient()
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

        $this->mockAuthClient()
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
}
