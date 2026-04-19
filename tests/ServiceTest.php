<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Auth_Client_Interface;
use Lihi\ShortUrl\Lihi_Client_Interface;
use Lihi\ShortUrl\Lihi_Service;
use Mockery;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    private array $configDefaults = [
        'redirect_domain' => 'redirect.lihidev.com',
        'api_domain'      => 'https://app.lihidev.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        // fetch_or_create() always resolves the WP host for the namespaced
        // API type; default both so tests don't have to repeat themselves.
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');
        $this->mockConfig();
    }

    /**
     * Stub lihi_config() to return values merged over $configDefaults.
     */
    private function mockConfig(array $overrides = []): void
    {
        $cfg = array_merge($this->configDefaults, $overrides);
        Functions\when('Lihi\ShortUrl\lihi_config')->alias(fn($k) => $cfg[$k] ?? null);
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

    private function makeClient(): Lihi_Client_Interface
    {
        return Mockery::mock(Lihi_Client_Interface::class);
    }

    private function makeAuthClient(): Lihi_Auth_Client_Interface
    {
        return Mockery::mock(Lihi_Auth_Client_Interface::class);
    }

    private function makeService(
        Lihi_Client_Interface $client,
        ?Lihi_Auth_Client_Interface $authClient = null
    ): Lihi_Service {
        return new Lihi_Service($client, $authClient ?? $this->makeAuthClient());
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
    // login()
    // -------------------------------------------------------------------------

    /** @test */
    public function login_returns_token_on_success(): void
    {
        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')
            ->with('user@example.com')
            ->once()
            ->andReturn(['token' => 'jwt-token']);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');

        $this->assertSame('jwt-token', $this->makeService($this->makeClient(), $authClient)->login());
    }

    /** @test */
    public function login_throws_when_token_empty(): void
    {
        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')->andReturn(['token' => '']);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');
        Functions\when('__')->returnArg(1);

        $this->expectException(\RuntimeException::class);
        $this->makeService($this->makeClient(), $authClient)->login();
    }

    /** @test */
    public function login_propagates_client_exception(): void
    {
        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')->andThrow(new \RuntimeException('Connection failed'));

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection failed');
        $this->makeService($this->makeClient(), $authClient)->login();
    }

    // -------------------------------------------------------------------------
    // get_token() — tested via get_or_create_short_url()
    // -------------------------------------------------------------------------

    /** @test */
    public function get_token_reuses_transient_without_login(): void
    {
        $cachedToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($cachedToken, 'post:example.com', 3)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(3, 'https://lihi.io/cached')]));

        $authClient = $this->makeAuthClient();
        $authClient->shouldNotReceive('login');

        Functions\when('get_transient')->justReturn($cachedToken);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=3');

        $result = $this->makeService($client, $authClient)->get_or_create_short_url(3, 'post');
        $this->assertSame('https://lihi.io/cached', $result);
    }

    /** @test */
    public function get_token_reuses_transient_on_double_check_after_acquiring_lock(): void
    {
        $cachedToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($cachedToken, 'post:example.com', 13)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(13, 'https://lihi.io/double')]));

        $authClient = $this->makeAuthClient();
        $authClient->shouldNotReceive('login');

        // First call (initial read) misses; second (double-check inside lock) hits.
        Functions\expect('get_transient')
            ->twice()
            ->andReturn(false, $cachedToken);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=13');

        $result = $this->makeService($client, $authClient)->get_or_create_short_url(13, 'post');
        $this->assertSame('https://lihi.io/double', $result);
    }

    /** @test */
    public function get_token_calls_login_when_gets_lock_and_no_transient(): void
    {
        $newToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($newToken, 'post:example.com', 5)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(5, 'https://lihi.io/xyz')]));

        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')
            ->with('user@example.com')
            ->once()
            ->andReturn(['token' => $newToken]);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=5');

        $result = $this->makeService($client, $authClient)->get_or_create_short_url(5, 'post');
        $this->assertSame('https://lihi.io/xyz', $result);
    }

    /** @test */
    public function get_token_waits_for_transient_when_lock_held_by_another_request(): void
    {
        $newToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($newToken, 'post:example.com', 9)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(9, 'https://lihi.io/waited')]));

        $authClient = $this->makeAuthClient();
        $authClient->shouldNotReceive('login');

        // First call returns false (cache miss), second returns token (winner stored it)
        Functions\expect('get_transient')
            ->twice()
            ->andReturn(false, $newToken);
        Functions\when('wp_cache_add')->justReturn(false); // lock already held
        Functions\when('usleep')->justReturn(null);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=9');

        $result = $this->makeService($client, $authClient)->get_or_create_short_url(9, 'post');
        $this->assertSame('https://lihi.io/waited', $result);
    }

    /** @test */
    public function get_token_falls_back_to_login_after_wait_timeout_and_deletes_stale_lock(): void
    {
        $newToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($newToken, 'post:example.com', 11)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(11, 'https://lihi.io/fallback')]));

        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $newToken]);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');
        Functions\when('get_transient')->justReturn(false); // never appears
        Functions\when('wp_cache_add')->justReturn(false);  // lock always held
        Functions\when('usleep')->justReturn(null);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=11');

        // Stale lock must be cleared after fallback login.
        Functions\expect('wp_cache_delete')
            ->once()
            ->with('lihi_token_lock', 'transient')
            ->andReturn(true);

        $result = $this->makeService($client, $authClient)->get_or_create_short_url(11, 'post');
        $this->assertSame('https://lihi.io/fallback', $result);
    }

    /** @test */
    public function get_token_propagates_login_exception(): void
    {
        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')->andThrow(new \RuntimeException('Auth failed'));

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Auth failed');
        $this->makeService($this->makeClient(), $authClient)->get_or_create_short_url(1, 'post');
    }

    // -------------------------------------------------------------------------
    // get_or_create_short_url()
    // -------------------------------------------------------------------------

    /** @test */
    public function get_or_create_returns_existing_short_url_without_creating(): void
    {
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with(Mockery::type('string'), 'post:example.com', 42)
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
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(99, 'other-slug')]));
        $client->shouldReceive('create_site')
            ->once()
            ->andReturn(['data' => ['short_url' => 'https://lihi.io/new']]);

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');

        $result = $this->makeService($client)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/new', $result);
    }

    /** @test */
    public function get_or_create_throws_when_create_returns_empty_site_name(): void
    {
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->andReturn($this->makeSitesResponse([]));
        $client->shouldReceive('create_site')
            ->andReturn(['data' => ['short_url' => '']]);

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');
        Functions\when('__')->returnArg(1);

        $this->expectException(\RuntimeException::class);
        $this->makeService($client)->get_or_create_short_url(42, 'post');
    }

    /** @test */
    public function get_or_create_returns_first_matching_short_url(): void
    {
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

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
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->andReturn($this->makeSitesResponse([]));

        $capturedBody = null;
        $client->shouldReceive('create_site')
            ->once()
            ->andReturnUsing(function ($token, $body) use (&$capturedBody) {
                $capturedBody = $body;
                return ['data' => ['short_url' => 'https://lihi.io/new']];
            });

        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');

        $this->makeService($client)->get_or_create_short_url(42, 'post');

        $this->assertSame(['https://example.com/?p=42'], $capturedBody['urls']);
        $this->assertSame('post:example.com', $capturedBody['type']);
        $this->assertSame('42', $capturedBody['type_id']);
        $this->assertSame('redirect.lihidev.com', $capturedBody['domain']);
        // tags keeps the bare $type, not the host-namespaced form
        $this->assertSame('wordpress,example.com,post', $capturedBody['tags']);
    }

    /** @test */
    public function get_or_create_uses_attachment_url_for_attachment_type(): void
    {
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->andReturn($this->makeSitesResponse([]));

        $capturedBody = null;
        $client->shouldReceive('create_site')
            ->once()
            ->andReturnUsing(function ($token, $body) use (&$capturedBody) {
                $capturedBody = $body;
                return ['data' => ['short_url' => 'https://lihi.io/img']];
            });

        Functions\when('wp_get_attachment_url')->justReturn('https://example.com/wp-content/uploads/photo.jpg');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');

        $this->makeService($client)->get_or_create_short_url(7, 'attachment');

        $this->assertSame(['https://example.com/wp-content/uploads/photo.jpg'], $capturedBody['urls']);
    }

    /** @test */
    public function get_or_create_namespaces_api_type_with_wp_host_for_get_short_links(): void
    {
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

        // Override the setUp default so we can verify the host propagates into $api_type.
        Functions\when('home_url')->justReturn('https://shop.example.org');
        Functions\when('wp_parse_url')->justReturn('shop.example.org');

        $capturedType = null;
        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->once()
            ->andReturnUsing(function ($token, $type, $itemId) use (&$capturedType) {
                $capturedType = $type;
                return $this->makeSitesResponse([]);
            });
        $client->shouldReceive('create_site')
            ->andReturn(['data' => ['short_url' => 'https://lihi.io/new']]);

        Functions\when('get_permalink')->justReturn('https://shop.example.org/?p=42');

        $this->makeService($client)->get_or_create_short_url(42, 'post');

        $this->assertSame('post:shop.example.org', $capturedType);
    }

    /** @test */
    public function get_or_create_sends_namespaced_type_but_bare_type_in_tags(): void
    {
        Functions\when('get_transient')->justReturn($this->makeJwt(time() + 3600));

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->andReturn($this->makeSitesResponse([]));

        $capturedBody = null;
        $client->shouldReceive('create_site')
            ->once()
            ->andReturnUsing(function ($token, $body) use (&$capturedBody) {
                $capturedBody = $body;
                return ['data' => ['short_url' => 'https://lihi.io/img']];
            });

        Functions\when('wp_get_attachment_url')->justReturn('https://example.com/uploads/a.jpg');

        $this->makeService($client)->get_or_create_short_url(7, 'attachment');

        $this->assertSame('attachment:example.com', $capturedBody['type']);
        $this->assertSame('wordpress,example.com,attachment', $capturedBody['tags']);
    }

    // -------------------------------------------------------------------------
    // get_or_create_short_url() — token-invalid retry
    // -------------------------------------------------------------------------

    /** @test */
    public function get_or_create_retries_once_when_get_short_links_throws_token_invalid(): void
    {
        $staleToken = $this->makeJwt(time() + 3600);
        $freshToken = $this->makeJwt(time() + 7200);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($staleToken, 'post:example.com', 42)
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));
        $client->shouldReceive('get_short_links')
            ->with($freshToken, 'post:example.com', 42)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(42, 'https://lihi.io/retried')]));

        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $freshToken]);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');
        Functions\when('delete_transient')->justReturn(true);
        // First read returns stale token; after invalidate_token, next reads return false
        // so the lock path takes over and login() is called.
        Functions\expect('get_transient')
            ->andReturn($staleToken, false, false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');

        $result = $this->makeService($client, $authClient)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/retried', $result);
    }

    /** @test */
    public function get_or_create_retries_once_when_create_site_throws_token_invalid(): void
    {
        $staleToken = $this->makeJwt(time() + 3600);
        $freshToken = $this->makeJwt(time() + 7200);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($staleToken, 'post:example.com', 42)
            ->once()
            ->andReturn($this->makeSitesResponse([]));
        $client->shouldReceive('create_site')
            ->with($staleToken, Mockery::any())
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));
        $client->shouldReceive('get_short_links')
            ->with($freshToken, 'post:example.com', 42)
            ->once()
            ->andReturn($this->makeSitesResponse([]));
        $client->shouldReceive('create_site')
            ->with($freshToken, Mockery::any())
            ->once()
            ->andReturn(['data' => ['short_url' => 'https://lihi.io/created']]);

        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $freshToken]);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');
        Functions\when('delete_transient')->justReturn(true);
        Functions\expect('get_transient')
            ->andReturn($staleToken, false, false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');

        $result = $this->makeService($client, $authClient)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/created', $result);
    }

    /** @test */
    public function get_or_create_propagates_token_invalid_exception_on_second_failure(): void
    {
        $staleToken = $this->makeJwt(time() + 3600);
        $freshToken = $this->makeJwt(time() + 7200);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($staleToken, 'post:example.com', 42)
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));
        $client->shouldReceive('get_short_links')
            ->with($freshToken, 'post:example.com', 42)
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));

        $authClient = $this->makeAuthClient();
        $authClient->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $freshToken]);

        Functions\when('Lihi\ShortUrl\lihi_email')->justReturn('user@example.com');
        Functions\when('delete_transient')->justReturn(true);
        Functions\expect('get_transient')
            ->andReturn($staleToken, false, false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);

        $this->expectException(\Lihi\ShortUrl\Lihi_Token_Invalid_Exception::class);
        $this->makeService($client, $authClient)->get_or_create_short_url(42, 'post');
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

    /** @test */
    public function resolve_url_throws_when_attachment_url_is_false(): void
    {
        Functions\when('wp_get_attachment_url')->justReturn(false);
        Functions\when('__')->returnArg(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/attachment 9/');
        $this->makeService($this->makeClient())->resolve_url(9, 'attachment');
    }

    /** @test */
    public function resolve_url_throws_when_permalink_is_false(): void
    {
        Functions\when('get_permalink')->justReturn(false);
        Functions\when('__')->returnArg(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/post 404/');
        $this->makeService($this->makeClient())->resolve_url(404, 'post');
    }
}
