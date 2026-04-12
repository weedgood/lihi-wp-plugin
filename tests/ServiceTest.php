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

        Functions\when('get_option')->alias(function ( $key, $default = false ) {
            if ( $key === 'lihi_email' ) return 'user@example.com';
            return $default;
        });
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key123');

        $this->assertSame('jwt-token', $this->makeService($client)->login());
    }

    /** @test */
    public function login_throws_when_token_empty(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('login')->andReturn(['token' => '']);

        Functions\when('get_option')->alias(function ( $key, $default = false ) {
            if ( $key === 'lihi_email' ) return 'user@example.com';
            return $default;
        });
        Functions\when('__')->returnArg(1);

        $this->expectException(\RuntimeException::class);
        $this->makeService($client)->login();
    }

    /** @test */
    public function login_propagates_client_exception(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('login')->andThrow(new \RuntimeException('Connection failed'));

        Functions\when('get_option')->alias(function ( $key, $default = false ) {
            if ( $key === 'lihi_email' ) return 'user@example.com';
            return $default;
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection failed');
        $this->makeService($client)->login();
    }

    // -------------------------------------------------------------------------
    // get_token() — tested via get_or_create_short_url()
    // -------------------------------------------------------------------------

    /** @test */
    public function get_token_reuses_valid_cookie_without_login(): void
    {
        $validToken = $this->makeJwt(time() + 3600);
        $_COOKIE['lihi_token'] = $validToken;

        $client = $this->makeClient();
        $client->shouldNotReceive('login');
        $client->shouldReceive('get_short_links')
            ->with($validToken, 'post', 1)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(1, 'https://lihi.io/abc')]));

        Functions\when('get_permalink')->justReturn('https://example.com/?p=1');

        $result = $this->makeService($client)->get_or_create_short_url(1, 'post');
        $this->assertSame('https://lihi.io/abc', $result);
    }

    /** @test */
    public function get_token_reuses_transient_without_login_when_cookie_absent(): void
    {
        $cachedToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldNotReceive('login');
        $client->shouldReceive('get_short_links')
            ->with($cachedToken, 'post', 3)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(3, 'https://lihi.io/cached')]));

        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('get_transient')->justReturn($cachedToken);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=3');

        $result = $this->makeService($client)->get_or_create_short_url(3, 'post');
        $this->assertSame('https://lihi.io/cached', $result);
    }

    /** @test */
    public function get_token_reuses_transient_on_double_check_after_acquiring_lock(): void
    {
        $cachedToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldNotReceive('login');
        $client->shouldReceive('get_short_links')
            ->with($cachedToken, 'post', 13)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(13, 'https://lihi.io/double')]));

        Functions\when('get_current_user_id')->justReturn(7);
        // First call (step 2) misses; second call (double-check inside lock) hits.
        Functions\expect('get_transient')
            ->twice()
            ->andReturn(false, $cachedToken);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=13');

        $result = $this->makeService($client)->get_or_create_short_url(13, 'post');
        $this->assertSame('https://lihi.io/double', $result);
    }

    /** @test */
    public function get_token_calls_login_when_gets_lock_and_no_transient(): void
    {
        $newToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $newToken]);
        $client->shouldReceive('get_short_links')
            ->with($newToken, 'post', 5)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(5, 'https://lihi.io/xyz')]));

        Functions\when('get_option')->alias(function ( $key, $default = false ) {
            if ( $key === 'lihi_email' ) return 'user@example.com';
            return $default;
        });
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key');
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_cache_add')->justReturn(true);   // got lock
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=5');
        Functions\when('setcookie')->justReturn(true);
        Functions\when('is_ssl')->justReturn(false);

        $result = $this->makeService($client)->get_or_create_short_url(5, 'post');
        $this->assertSame('https://lihi.io/xyz', $result);
    }

    /** @test */
    public function get_token_reuses_transient_without_login_when_cookie_expired(): void
    {
        $_COOKIE['lihi_token'] = $this->makeJwt(time() - 1);
        $cachedToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldNotReceive('login');
        $client->shouldReceive('get_short_links')
            ->with($cachedToken, 'post', 7)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(7, 'https://lihi.io/def')]));

        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('get_transient')->justReturn($cachedToken);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=7');

        $result = $this->makeService($client)->get_or_create_short_url(7, 'post');
        $this->assertSame('https://lihi.io/def', $result);
    }

    /** @test */
    public function get_token_waits_for_transient_when_lock_held_by_another_request(): void
    {
        $newToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldNotReceive('login');
        $client->shouldReceive('get_short_links')
            ->with($newToken, 'post', 9)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(9, 'https://lihi.io/waited')]));

        Functions\when('get_current_user_id')->justReturn(7);
        // First call returns false (cache miss), second returns token (winner stored it)
        Functions\expect('get_transient')
            ->twice()
            ->andReturn(false, $newToken);
        Functions\when('wp_cache_add')->justReturn(false); // lock already held
        Functions\when('usleep')->justReturn(null);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=9');

        $result = $this->makeService($client)->get_or_create_short_url(9, 'post');
        $this->assertSame('https://lihi.io/waited', $result);
    }

    /** @test */
    public function get_token_falls_back_to_login_after_wait_timeout_and_deletes_stale_lock(): void
    {
        $newToken = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $newToken]);
        $client->shouldReceive('get_short_links')
            ->with($newToken, 'post', 11)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(11, 'https://lihi.io/fallback')]));

        Functions\when('get_option')->alias(function ( $key, $default = false ) {
            if ( $key === 'lihi_email' ) return 'user@example.com';
            return $default;
        });
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key');
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('get_transient')->justReturn(false); // never appears
        Functions\when('wp_cache_add')->justReturn(false);  // lock always held
        Functions\when('usleep')->justReturn(null);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=11');
        Functions\when('setcookie')->justReturn(true);
        Functions\when('is_ssl')->justReturn(false);

        // Stale lock must be cleared after fallback login.
        Functions\expect('wp_cache_delete')
            ->once()
            ->with('lihi_token_lock_7', 'transient')
            ->andReturn(true);

        $result = $this->makeService($client)->get_or_create_short_url(11, 'post');
        $this->assertSame('https://lihi.io/fallback', $result);
    }

    /** @test */
    public function get_token_propagates_login_exception(): void
    {
        $client = $this->makeClient();
        $client->shouldReceive('login')->andThrow(new \RuntimeException('Auth failed'));

        Functions\when('get_option')->alias(function ( $key, $default = false ) {
            if ( $key === 'lihi_email' ) return 'user@example.com';
            return $default;
        });
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key');
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Auth failed');
        $this->makeService($client)->get_or_create_short_url(1, 'post');
    }

    // -------------------------------------------------------------------------
    // get_or_create_short_url()
    // -------------------------------------------------------------------------

    /** @test */
    public function get_or_create_returns_existing_short_url_without_creating(): void
    {
        $_COOKIE['lihi_token'] = $this->makeJwt(time() + 3600);

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with(Mockery::type('string'), 'post', 42)
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
        $_COOKIE['lihi_token'] = $this->makeJwt(time() + 3600);

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
        $_COOKIE['lihi_token'] = $this->makeJwt(time() + 3600);

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
        $_COOKIE['lihi_token'] = $this->makeJwt(time() + 3600);

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
        $_COOKIE['lihi_token'] = $this->makeJwt(time() + 3600);

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
        Functions\when('Lihi\ShortUrl\lihi_redirect_domain')->justReturn('redirect.lihidev.com');

        $this->makeService($client)->get_or_create_short_url(42, 'post');

        $this->assertSame(['https://example.com/?p=42'], $capturedBody['urls']);
        $this->assertSame('post', $capturedBody['type']);
        $this->assertSame('42', $capturedBody['type_id']);
        $this->assertSame('redirect.lihidev.com', $capturedBody['domain']);
        $this->assertSame('wordpress,example.com,post', $capturedBody['tags']);
    }

    /** @test */
    public function get_or_create_uses_attachment_url_for_attachment_type(): void
    {
        $_COOKIE['lihi_token'] = $this->makeJwt(time() + 3600);

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
        Functions\when('Lihi\ShortUrl\lihi_redirect_domain')->justReturn('redirect.lihidev.com');

        $this->makeService($client)->get_or_create_short_url(7, 'attachment');

        $this->assertSame(['https://example.com/wp-content/uploads/photo.jpg'], $capturedBody['urls']);
    }

    // -------------------------------------------------------------------------
    // get_or_create_short_url() — token-invalid retry
    // -------------------------------------------------------------------------

    /** @test */
    public function get_or_create_retries_once_when_get_short_links_throws_token_invalid(): void
    {
        $staleToken = $this->makeJwt(time() + 3600);
        $freshToken = $this->makeJwt(time() + 7200);
        $_COOKIE['lihi_token'] = $staleToken;

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($staleToken, 'post', 42)
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));
        $client->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $freshToken]);
        $client->shouldReceive('get_short_links')
            ->with($freshToken, 'post', 42)
            ->once()
            ->andReturn($this->makeSitesResponse([$this->makeSite(42, 'https://lihi.io/retried')]));

        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lihi_email') return 'user@example.com';
            return $default;
        });
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key');
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('setcookie')->justReturn(true);
        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');

        $result = $this->makeService($client)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/retried', $result);
    }

    /** @test */
    public function get_or_create_retries_once_when_create_site_throws_token_invalid(): void
    {
        $staleToken = $this->makeJwt(time() + 3600);
        $freshToken = $this->makeJwt(time() + 7200);
        $_COOKIE['lihi_token'] = $staleToken;

        $client = $this->makeClient();
        // First pass: no matching short link found, create_site throws token invalid
        $client->shouldReceive('get_short_links')
            ->with($staleToken, 'post', 42)
            ->once()
            ->andReturn($this->makeSitesResponse([]));
        $client->shouldReceive('create_site')
            ->with($staleToken, Mockery::any())
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));
        $client->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $freshToken]);
        // Retry pass: no matching short link, create_site succeeds
        $client->shouldReceive('get_short_links')
            ->with($freshToken, 'post', 42)
            ->once()
            ->andReturn($this->makeSitesResponse([]));
        $client->shouldReceive('create_site')
            ->with($freshToken, Mockery::any())
            ->once()
            ->andReturn(['data' => ['short_url' => 'https://lihi.io/created']]);

        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lihi_email') return 'user@example.com';
            return $default;
        });
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key');
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('setcookie')->justReturn(true);
        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_permalink')->justReturn('https://example.com/?p=42');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('wp_parse_url')->justReturn('example.com');
        Functions\when('Lihi\ShortUrl\lihi_redirect_domain')->justReturn('redirect.lihidev.com');

        $result = $this->makeService($client)->get_or_create_short_url(42, 'post');
        $this->assertSame('https://lihi.io/created', $result);
    }

    /** @test */
    public function get_or_create_propagates_token_invalid_exception_on_second_failure(): void
    {
        $staleToken = $this->makeJwt(time() + 3600);
        $freshToken = $this->makeJwt(time() + 7200);
        $_COOKIE['lihi_token'] = $staleToken;

        $client = $this->makeClient();
        $client->shouldReceive('get_short_links')
            ->with($staleToken, 'post', 42)
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));
        $client->shouldReceive('login')
            ->once()
            ->andReturn(['token' => $freshToken]);
        $client->shouldReceive('get_short_links')
            ->with($freshToken, 'post', 42)
            ->once()
            ->andThrow(new \Lihi\ShortUrl\Lihi_Token_Invalid_Exception('HTTP 500: 網站升級中...'));

        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lihi_email') return 'user@example.com';
            return $default;
        });
        Functions\when('Lihi\ShortUrl\lihi_api_key')->justReturn('key');
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_cache_add')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('setcookie')->justReturn(true);
        Functions\when('is_ssl')->justReturn(false);

        $this->expectException(\Lihi\ShortUrl\Lihi_Token_Invalid_Exception::class);
        $this->makeService($client)->get_or_create_short_url(42, 'post');
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
