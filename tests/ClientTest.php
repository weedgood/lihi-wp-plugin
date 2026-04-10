<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Client;
use Mockery;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_json_encode')->alias('json_encode');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeClient(string $token = 'test-token'): Lihi_Client
    {
        return new Lihi_Client($token);
    }

    /**
     * Stub a single HTTP call. Returns a callable that yields ['url' => ..., 'args' => ...].
     */
    private function mockRequest(int $code = 200, string $body = '{}'): callable
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

    // -------------------------------------------------------------------------
    // request()
    // -------------------------------------------------------------------------

    /** @test */
    public function get_request_appends_data_as_query_string(): void
    {
        $capture = $this->mockRequest();
        $this->makeClient()->get_sites(['type' => 'products', 'per_page' => 10]);
        $c = $capture();
        $this->assertStringContainsString('type=products', $c['url']);
        $this->assertStringContainsString('per_page=10', $c['url']);
        $this->assertArrayNotHasKey('body', $c['args']);
    }

    /** @test */
    public function post_request_encodes_data_as_json_body(): void
    {
        $capture = $this->mockRequest();
        $this->makeClient()->create_site([
            'urls'    => ['https://example.com'],
            'alias'   => 'test',
            'domain'  => '',
            'tags'    => '',
            'type'    => 'post',
            'type_id' => 1,
        ]);
        $c    = $capture();
        $body = json_decode($c['args']['body'] ?? '{}', true);
        $this->assertSame(['https://example.com'], $body['urls']);
        $this->assertStringNotContainsString('?', $c['url']);
    }

    /** @test */
    public function request_includes_authorization_header_when_auth_true(): void
    {
        $capture = $this->mockRequest();
        $this->makeClient('my-token')->get_sites();
        $c = $capture();
        $this->assertSame('Bearer my-token', $c['args']['headers']['Authorization']);
    }

    /** @test */
    public function request_omits_authorization_header_when_auth_false(): void
    {
        $capture = $this->mockRequest();
        $this->makeClient()->login('user@example.com', 'api-key');
        $c = $capture();
        $this->assertArrayNotHasKey('Authorization', $c['args']['headers']);
    }

    /** @test */
    public function request_returns_empty_array_on_204(): void
    {
        $this->mockRequest(204, '');
        $result = $this->makeClient()->get_sites();
        $this->assertSame([], $result);
    }

    /** @test */
    public function request_returns_empty_array_on_empty_body(): void
    {
        $this->mockRequest(200, '');
        $result = $this->makeClient()->get_sites();
        $this->assertSame([], $result);
    }

    /** @test */
    public function request_throws_on_invalid_json(): void
    {
        $this->mockRequest(200, 'not-json');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected JSON but got/');
        $this->makeClient()->get_sites();
    }

    /** @test */
    public function request_throws_on_http_4xx(): void
    {
        $this->mockRequest(404, '{"error":"not found"}');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/404/');
        $this->makeClient()->get_sites();
    }

    /** @test */
    public function request_throws_on_wp_error(): void
    {
        $wpError = Mockery::mock('WP_Error');
        $wpError->shouldReceive('get_error_message')->andReturn('cURL error: connection timed out');

        Functions\expect('wp_remote_request')->once()->andReturn($wpError);
        Functions\when('is_wp_error')->justReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cURL error: connection timed out');
        $this->makeClient()->get_sites();
    }
}
