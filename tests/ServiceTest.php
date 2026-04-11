<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Client_Interface;
use Lihi\ShortUrl\Lihi_Service;
use Mockery;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeClient(): Lihi_Client_Interface
    {
        return Mockery::mock(Lihi_Client_Interface::class);
    }

    private function makeService(Lihi_Client_Interface $client): Lihi_Service
    {
        return new Lihi_Service($client);
    }

    /** Build a structurally-valid JWT with the given exp timestamp. */
    private function makeJwt(int $exp): string
    {
        $b64 = fn($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
        return $b64(['alg' => 'HS256', 'typ' => 'JWT'])
            . '.' . $b64(['sub' => 'test', 'exp' => $exp])
            . '.sig';
    }

    private function makeSitesResponse(array $sites): array
    {
        return [
            'result' => true,
            'data'   => [
                'domains'     => [],
                'total_sites' => count( $sites ),
                'limit_sites' => 500,
                'sites'       => [
                    'current_page' => 1,
                    'total'        => count( $sites ),
                    'per_page'     => 20,
                    'data'         => $sites,
                ],
            ],
        ];
    }

    private function makeSite(int $typeId, string $lihiUrl): array
    {
        return [
            'id'             => $typeId,
            'domain'         => 'lihi.io',
            'short_url'       => $lihiUrl,
            'site_urls'      => [],
            'site_tags'      => [],
            'wordpress_link' => ['type' => 'post', 'type_id' => (string) $typeId],
        ];
    }

    // -------------------------------------------------------------------------
    // has_valid_token()
    // -------------------------------------------------------------------------

    /** @test */
    public function has_valid_token_returns_false_when_cookie_missing(): void
    {
        $this->assertFalse($this->makeService($this->makeClient())->has_valid_token());
    }

    /** @test */
    public function has_valid_token_returns_false_when_cookie_empty(): void
    {
        $_COOKIE['lihi_token'] = '';
        $this->assertFalse($this->makeService($this->makeClient())->has_valid_token());
    }

    /** @test */
    public function has_valid_token_returns_false_when_token_malformed(): void
    {
        $_COOKIE['lihi_token'] = 'not-a-valid-jwt';
        $this->assertFalse($this->makeService($this->makeClient())->has_valid_token());
    }

    /** @test */
    public function has_valid_token_returns_false_when_exp_missing(): void
    {
        $b64 = fn($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
        $_COOKIE['lihi_token'] = $b64(['alg' => 'HS256']) . '.' . $b64(['sub' => 'test']) . '.sig';
        $this->assertFalse($this->makeService($this->makeClient())->has_valid_token());
    }

    /** @test */
    public function has_valid_token_returns_false_when_token_expired(): void
    {
        $_COOKIE['lihi_token'] = $this->makeJwt(time() - 1);
        $this->assertFalse($this->makeService($this->makeClient())->has_valid_token());
    }

    /** @test */
    public function has_valid_token_returns_true_when_token_valid(): void
    {
        $_COOKIE['lihi_token'] = $this->makeJwt(time() + 3600);
        $this->assertTrue($this->makeService($this->makeClient())->has_valid_token());
    }

    // -------------------------------------------------------------------------
    // login()
    // -------------------------------------------------------------------------

    /** @test */
    public function login_returns_token_on_success(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('login')
            ->with('user@example.com', 'key123')
            ->once()
            ->andReturn(['token' => 'jwt-token']);

        Functions\when('wp_get_current_user')->justReturn((object)['user_email' => 'user@example.com']);
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key123');

        $this->assertSame('jwt-token', $this->makeService($client)->login());
    }

    /** @test */
    public function login_throws_when_token_empty(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('login')->andReturn(['token' => '']);

        Functions\when('wp_get_current_user')->justReturn((object)['user_email' => 'user@example.com']);
        Functions\when('__')->returnArg(1);

        $this->expectException(\RuntimeException::class);
        $this->makeService($client)->login();
    }

    /** @test */
    public function login_propagates_client_exception(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('login')->andThrow(new \RuntimeException('Connection failed'));

        Functions\when('wp_get_current_user')->justReturn((object)['user_email' => 'user@example.com']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection failed');
        $this->makeService($client)->login();
    }

    // -------------------------------------------------------------------------
    // get_or_create_short_url()
    // -------------------------------------------------------------------------

    /** @test */
    public function get_or_create_returns_existing_short_url_without_creating(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with('post', 42)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(42, 'https://lihi.io/existing')]));
        $client->shouldNotReceive('create_site');

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');

        $result = $this->makeService($client)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/existing', $result);
    }

    /** @test */
    public function get_or_create_calls_create_when_no_match_found(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(99, 'other-slug')]));
        $client->shouldReceive('create_site')
            ->once()
            ->andReturn(['data' => ['short_url' => 'https://lihi.io/new']]);

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');

        $result = $this->makeService($client)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/new', $result);
    }

    /** @test */
    public function get_or_create_throws_when_create_returns_empty_site_name(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->andReturn($this->makeSitesResponse([]));
        $client->shouldReceive('create_site')
            ->andReturn(['data' => ['short_url' => '']]);

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');
        Functions\when('__')->returnArg(1);

        $this->expectException(\RuntimeException::class);
        $this->makeService($client)->get_or_create_short_url(42, 'post');
    }

    /** @test */
    public function get_or_create_returns_first_matching_short_url(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->andReturn($this->makeSitesResponse([
                $this->makeSite(42, 'https://lihi.io/first'),
                $this->makeSite(42, 'https://lihi.io/second'),
            ]));
        $client->shouldNotReceive('create_site');

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');

        $result = $this->makeService($client)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/first', $result);
    }

    /** @test */
    public function get_or_create_passes_correct_body_to_create_site(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->andReturn($this->makeSitesResponse([]));

        $capturedBody = null;
        $client->shouldReceive('create_site')
            ->once()
            ->andReturnUsing(function ($body) use (&$capturedBody) {
                $capturedBody = $body;
                return ['data' => ['short_url' => 'https://lihi.io/new']];
            });

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');
        Functions\when('Lihi\ShortUrl\lihi_redirect_domain')->justReturn('redirect.lihidev.com');

        $this->makeService($client)->get_or_create_short_url(42, 'post');

        $this->assertSame(['https://example.com/?p=42'], $capturedBody['urls']);
        $this->assertSame('post', $capturedBody['type']);
        $this->assertSame('42', $capturedBody['type_id']);
        $this->assertSame('redirect.lihidev.com', $capturedBody['domain']);
        $this->assertSame('wordpress,example.com,post', $capturedBody['tags']);
    }

    // -------------------------------------------------------------------------
    // resolve_url()
    // -------------------------------------------------------------------------

    /** @test */
    public function resolve_url_uses_get_permalink_for_post(): void
    {
        Functions\when('get_permalink')->justReturn('https://example.com/?p=5');

        $result = $this->makeService($this->makeClient())->resolve_url(5, 'post');

        $this->assertSame('https://example.com/?p=5', $result);
    }

    /** @test */
    public function resolve_url_uses_get_permalink_for_page(): void
    {
        Functions\when('get_permalink')->justReturn('https://example.com/about/');

        $result = $this->makeService($this->makeClient())->resolve_url(10, 'page');

        $this->assertSame('https://example.com/about/', $result);
    }

    /** @test */
    public function resolve_url_uses_wp_get_attachment_url_for_attachment(): void
    {
        Functions\when('wp_get_attachment_url')->justReturn('https://example.com/wp-content/uploads/photo.jpg');

        $result = $this->makeService($this->makeClient())->resolve_url(7, 'attachment');

        $this->assertSame('https://example.com/wp-content/uploads/photo.jpg', $result);
    }
}
