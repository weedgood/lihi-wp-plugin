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
        return new Lihi_Client();
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
    public function get_posts_sends_get_to_posts_path(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":[]}');
        $this->makeClient()->get_posts('test-token');
        $this->assertStringContainsString('/api/wordpress/v1/posts', $capture()['url']);
        $this->assertSame('GET', $capture()['args']['method']);
    }

    /** @test */
    public function get_posts_passes_locale_as_query_param(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":[]}');
        $this->makeClient()->get_posts('test-token', 'en');
        $this->assertStringContainsString('locale=en', $capture()['url']);
    }

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
    public function update_site_sends_put_with_id_in_path(): void
    {
        $capture = $this->mockRequest(200, '{"result":true}');
        $this->makeClient()->update_site('test-token', 456, ['urls' => [['id' => 1, 'url' => 'https://example.com']]]);
        $c = $capture();
        $this->assertStringContainsString('/api/wordpress/v1/sites/456', $c['url']);
        $this->assertSame('PUT', $c['args']['method']);
    }

    /** @test */
    public function delete_site_sends_delete_with_id_in_path(): void
    {
        $capture = $this->mockRequest(200, '{"result":true}');
        $result  = $this->makeClient()->delete_site('test-token', 789);
        $c       = $capture();
        $this->assertStringContainsString('/api/wordpress/v1/sites/789', $c['url']);
        $this->assertSame('DELETE', $c['args']['method']);
        $this->assertTrue($result);
    }

    /** @test */
    public function create_site_url_sends_post_to_site_urls_path(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":{"id":1,"url":"https://example.com"}}');
        $this->makeClient()->create_site_url('test-token', ['site_id' => '456', 'url' => 'https://example.com']);
        $c = $capture();
        $this->assertStringContainsString('/api/wordpress/v1/site-urls', $c['url']);
        $this->assertSame('POST', $c['args']['method']);
        $body = json_decode($c['args']['body'], true);
        $this->assertSame('456', $body['site_id']);
    }

    /** @test */
    public function update_site_url_sends_put_with_id_in_path(): void
    {
        $capture = $this->mockRequest(200, '{"result":true,"data":{"id":99,"url":"https://new.com"}}');
        $this->makeClient()->update_site_url('test-token', 99, ['url' => 'https://new.com']);
        $c = $capture();
        $this->assertStringContainsString('/api/wordpress/v1/site-urls/99', $c['url']);
        $this->assertSame('PUT', $c['args']['method']);
    }

    /** @test */
    public function delete_site_url_sends_delete_with_id_in_path(): void
    {
        $capture = $this->mockRequest(200, '{"result":true}');
        $result  = $this->makeClient()->delete_site_url('test-token', 55);
        $c       = $capture();
        $this->assertStringContainsString('/api/wordpress/v1/site-urls/55', $c['url']);
        $this->assertSame('DELETE', $c['args']['method']);
        $this->assertTrue($result);
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
    public function request_omits_authorization_header_for_login(): void
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
    public function request_throws_on_invalid_json(): void
    {
        $this->mockRequest(200, 'not-json');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected JSON but got/');
        $this->makeClient()->get_sites('test-token');
    }

    /** @test */
    public function request_throws_on_http_4xx(): void
    {
        $this->mockRequest(404, '{"error":"not found"}');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/404/');
        $this->makeClient()->get_sites('test-token');
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
        $this->makeClient()->get_sites('test-token');
    }
}
