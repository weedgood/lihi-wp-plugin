<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Auth_Client;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('home_url')->justReturn('https://wp.example.com:8443');
        Functions\when('Lihi\\ShortUrl\\lihi_uuid')->justReturn('site-uuid');
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
    public function update_email_posts_email_and_site_host_in_json_body(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":{"verified":false}}');

        (new Lihi_Auth_Client())->update_email('admin@example.com');

        $request = $capture();
        $body    = json_decode($request['args']['body'] ?? '{}', true);

        $this->assertStringEndsWith('/auth/update-email', $request['url']);
        $this->assertSame('POST', $request['args']['method']);
        $this->assertSame('application/json', $request['args']['headers']['Content-Type']);
        $this->assertArrayNotHasKey('Host', $request['args']['headers']);
        $this->assertSame('admin@example.com', $body['email']);
        $this->assertSame('wp.example.com', $body['hostname']);
        $this->assertSame('site-uuid', $body['uuid']);
        $this->assertArrayNotHasKey('is_mobile', $body);
    }

    /** @test */
    public function login_posts_email_site_host_and_mobile_state_in_json_body(): void
    {
        Functions\when('wp_is_mobile')->justReturn(true);
        $capture = $this->mockRequest(200, '{"result":true,"data":{"token":"jwt-token"}}');

        (new Lihi_Auth_Client())->login('admin@example.com');

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
}
