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

    private function makeClient(): Lihi_Client
    {
        return new Lihi_Client('https://app.lihidev.com', 'site-uuid');
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
    // Individual methods — path, HTTP method, and payload
    // -------------------------------------------------------------------------

    /** @test */
    public function get_short_links_sends_get_with_type_and_type_id(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":{"sites":{"data":[]}}}');
        $this->makeClient()->get_short_links('test-token', 'post', 42);
        $url = $capture()['url'];
        $this->assertStringContainsString('/api/wordpress/v1/sites', $url);
        $this->assertStringContainsString('type=post', $url);
        $this->assertStringContainsString('type_id=42', $url);
        $this->assertStringContainsString('per_page=20', $url);
    }

    /** @test */
    public function get_profile_sends_get_to_profile_path_with_bearer_token(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":{"user_role":"admin","end_date":null,"domains":["redirect.lihidev.com"]}}');
        $this->makeClient()->get_profile('my-token');
        $c = $capture();
        $this->assertStringContainsString('/api/wordpress/v1/profile', $c['url']);
        $this->assertSame('GET', $c['args']['method']);
        $this->assertSame('Bearer my-token', $c['args']['headers']['Authorization']);
    }

    // -------------------------------------------------------------------------
    // request()
    // -------------------------------------------------------------------------

    /** @test */
    public function get_request_appends_data_as_query_string(): void
    {
        $capture = $this->mockRequest();
        $this->makeClient()->get_sites('test-token', ['type' => 'products', 'per_page' => 10]);
        $c = $capture();
        $this->assertStringContainsString('type=products', $c['url']);
        $this->assertStringContainsString('per_page=10', $c['url']);
        $this->assertArrayNotHasKey('body', $c['args']);
    }

    /** @test */
    public function post_request_encodes_data_as_json_body(): void
    {
        $capture = $this->mockRequest();
        $this->makeClient()->create_site('test-token', [
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
    public function request_includes_authorization_header_when_token_provided(): void
    {
        $capture = $this->mockRequest();
        $this->makeClient()->get_sites('my-token');
        $c = $capture();
        $this->assertSame('Bearer my-token', $c['args']['headers']['Authorization']);
    }

    /** @test */
    public function request_returns_empty_array_on_204(): void
    {
        $this->mockRequest(204, '');
        $result = $this->makeClient()->get_sites('test-token');
        $this->assertSame([], $result);
    }

    /** @test */
    public function request_returns_empty_array_on_empty_body(): void
    {
        $this->mockRequest(200, '');
        $result = $this->makeClient()->get_sites('test-token');
        $this->assertSame([], $result);
    }

    /** @test */
    public function request_throws_not_found_exception_on_404_html(): void
    {
        $html = '<html><head><title>Page Not Found</title></head><body></body></html>';
        $this->mockRequest(404, $html);
        $this->expectException(\Lihi\ShortUrl\Lihi_Not_Found_Exception::class);
        $this->expectExceptionMessageMatches('/404.*Page Not Found/');
        $this->makeClient()->get_sites('test-token');
    }

    /** @test */
    public function request_throws_token_invalid_exception_on_upgrade_title(): void
    {
        $html = '<html><head><title>網站升級中...</title></head><body></body></html>';
        $this->mockRequest(500, $html);
        $this->expectException(\Lihi\ShortUrl\Lihi_Token_Invalid_Exception::class);
        $this->expectExceptionMessageMatches('/500.*網站升級中/');
        $this->makeClient()->get_sites('test-token');
    }

    /** @test */
    public function token_invalid_exception_is_a_server_exception(): void
    {
        $html = '<html><head><title>網站升級中...</title></head><body></body></html>';
        $this->mockRequest(500, $html);
        $this->expectException(\Lihi\ShortUrl\Lihi_Server_Exception::class);
        $this->makeClient()->get_sites('test-token');
    }

    /** @test */
    public function request_throws_server_exception_on_5xx_html_unknown_title(): void
    {
        $html = '<html><head><title>Internal Server Error</title></head><body></body></html>';
        $this->mockRequest(500, $html);
        $this->expectException(\Lihi\ShortUrl\Lihi_Server_Exception::class);
        $this->expectExceptionMessageMatches('/500.*Internal Server Error/');
        $this->makeClient()->get_sites('test-token');
    }

    /** @test */
    public function request_throws_server_exception_on_non_json_without_title(): void
    {
        $this->mockRequest(500, 'not-json');
        $this->expectException(\Lihi\ShortUrl\Lihi_Server_Exception::class);
        $this->makeClient()->get_sites('test-token');
    }

    /** @test */
    public function create_site_throws_validation_exception_on_400(): void
    {
        $this->mockRequest(400, '{"result":false,"msg":{"domain":["The domain field is required."]}}');
        $this->expectException(\Lihi\ShortUrl\Lihi_Validation_Exception::class);
        $this->makeClient()->create_site('token', []);
    }

    /** @test */
    public function request_throws_server_exception_on_wp_error(): void
    {
        $wpError = Mockery::mock('WP_Error');
        $wpError->shouldReceive('get_error_message')->andReturn('cURL error: connection timed out');

        Functions\expect('wp_remote_request')->once()->andReturn($wpError);
        Functions\when('is_wp_error')->justReturn(true);

        $this->expectException(\Lihi\ShortUrl\Lihi_Server_Exception::class);
        $this->expectExceptionMessage('cURL error: connection timed out');
        $this->makeClient()->get_sites('test-token');
    }
}
