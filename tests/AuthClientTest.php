<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Client;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('home_url')->justReturn('https://wp.example.com:8443');
        Functions\when('wp_json_encode')->alias('json_encode');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Stub a single HTTP call. Returns a callable that yields ['url' => ..., 'args' => ...].
     */
    private function mockRequest(int $code = 200, string $body = '{"result":true,"data":[]}'): callable
    {
        $captured = ['url' => null, 'args' => null];
        $fake     = ['__mock__' => true];

        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured, $fake) {
                $captured['url']  = $url;
                $captured['args'] = $args;
                return $fake;
            });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn($code);
        Functions\when('wp_remote_retrieve_body')->justReturn($body);

        return function () use (&$captured) {
            return $captured;
        };
    }

    /** @test */
    public function update_email_posts_email_password_and_site_host_in_json_body(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":{"verified":false}}');

        (new Lihi_Client('https://app.lihidev.com', 'site-uuid'))->update_email('admin@example.com', 'account-password');

        $request = $capture();
        $body    = json_decode($request['args']['body'] ?? '{}', true);

        $this->assertStringEndsWith('/auth/update-email', $request['url']);
        $this->assertSame('POST', $request['args']['method']);
        $this->assertSame('application/json', $request['args']['headers']['Content-Type']);
        $this->assertArrayNotHasKey('Host', $request['args']['headers']);
        $this->assertSame('admin@example.com', $body['email']);
        $this->assertSame('account-password', $body['password']);
        $this->assertSame('wp.example.com', $body['hostname']);
        $this->assertSame('site-uuid', $body['uuid']);
        $this->assertArrayNotHasKey('is_mobile', $body);
    }

    /** @test */
    public function update_email_returns_verified_true_from_update_email_endpoint(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":{"verified":true}}');

        $result = (new Lihi_Client('https://app.lihidev.com', 'site-uuid'))->update_email('admin@example.com', 'secret-password');

        $request = $capture();
        $body    = json_decode($request['args']['body'] ?? '{}', true);

        $this->assertStringEndsWith('/auth/update-email', $request['url']);
        $this->assertSame('admin@example.com', $body['email']);
        $this->assertSame('secret-password', $body['password']);
        $this->assertSame('wp.example.com', $body['hostname']);
        $this->assertSame('site-uuid', $body['uuid']);
        $this->assertSame(['verified' => true], $result);
    }

    /** @test */
    public function update_email_throws_email_or_password_invalid_exception_when_password_is_invalid(): void
    {
        $this->mockRequest(403, '{"result":false,"msg":"password invalid"}');

        $this->expectException(\Lihi\ShortUrl\Lihi_Email_Or_Password_Invalid_Exception::class);

        (new Lihi_Client('https://app.lihidev.com', 'site-uuid'))->update_email('admin@example.com', 'wrong-password');
    }

    /** @test */
    public function update_email_throws_email_or_password_invalid_exception_for_password_invalid_message(): void
    {
        $this->mockRequest(403, '{"result":false,"msg":"email or password invalid"}');

        $this->expectException(\Lihi\ShortUrl\Lihi_Email_Or_Password_Invalid_Exception::class);

        (new Lihi_Client('https://app.lihidev.com', 'site-uuid'))->update_email('admin@example.com', 'wrong-password');
    }

    /** @test */
    public function login_posts_email_site_host_and_mobile_state_in_json_body(): void
    {
        Functions\when('wp_is_mobile')->justReturn(true);
        $capture = $this->mockRequest(200, '{"result":true,"data":{"token":"jwt-token"}}');

        (new Lihi_Client('https://app.lihidev.com', 'site-uuid'))->login('admin@example.com');

        $request = $capture();
        $body    = json_decode($request['args']['body'] ?? '{}', true);

        $this->assertStringEndsWith('/auth/login', $request['url']);
        $this->assertSame('POST', $request['args']['method']);
        $this->assertSame('application/json', $request['args']['headers']['Content-Type']);
        $this->assertArrayNotHasKey('Host', $request['args']['headers']);
        $this->assertSame('admin@example.com', $body['email']);
        $this->assertSame('wp.example.com', $body['hostname']);
        $this->assertSame('site-uuid', $body['uuid']);
        $this->assertTrue($body['is_mobile']);
    }

    /** @test */
    public function login_throws_user_invalid_exception_on_user_invalid_403(): void
    {
        Functions\when('wp_is_mobile')->justReturn(false);
        $this->mockRequest(403, '{"result":false,"msg":"User Invalid"}');

        $this->expectException(\Lihi\ShortUrl\Lihi_User_Invalid_Exception::class);

        (new Lihi_Client('https://app.lihidev.com', 'site-uuid'))->login('admin@example.com');
    }

    /** @test */
    public function login_throws_auth_exception_on_email_not_verified_403(): void
    {
        Functions\when('wp_is_mobile')->justReturn(false);
        $this->mockRequest(403, '{"result":false,"msg":"email not verified"}');

        try {
            (new Lihi_Client('https://app.lihidev.com', 'site-uuid'))->login('admin@example.com');
            $this->fail('Expected auth exception.');
        } catch (\Lihi\ShortUrl\Lihi_User_Invalid_Exception $e) {
            $this->fail('Email-not-verified responses must not be treated as unavailable accounts.');
        } catch (\Lihi\ShortUrl\Lihi_Auth_Exception $e) {
            $this->assertStringContainsString('email not verified', $e->getMessage());
        }
    }
}
