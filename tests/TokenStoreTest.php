<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Token_Store;
use PHPUnit\Framework\TestCase;

class TokenStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** @test */
    public function get_returns_transient_value(): void
    {
        Functions\expect('get_transient')->once()->with('lihi_token')->andReturn('jwt');
        $this->assertSame('jwt', (new Lihi_Token_Store())->get());
    }

    /** @test */
    public function get_returns_false_on_miss(): void
    {
        Functions\when('get_transient')->justReturn(false);
        $this->assertFalse((new Lihi_Token_Store())->get());
    }

    /** @test */
    public function set_stores_transient_with_day_ttl(): void
    {
        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
        }
        Functions\expect('set_transient')
            ->once()
            ->with('lihi_token', 'jwt', DAY_IN_SECONDS);

        (new Lihi_Token_Store())->set('jwt');
        $this->assertTrue(true); // expectation counts as verification at teardown
    }

    /** @test */
    public function delete_removes_transient(): void
    {
        Functions\expect('delete_transient')->once()->with('lihi_token');
        (new Lihi_Token_Store())->delete();
        $this->assertTrue(true);
    }

    /** @test */
    public function acquire_lock_delegates_to_wp_cache_add(): void
    {
        Functions\expect('wp_cache_add')
            ->once()
            ->with('lihi_token_lock', 1, 'transient', 30)
            ->andReturn(true);

        $this->assertTrue((new Lihi_Token_Store())->acquire_lock());
    }

    /** @test */
    public function release_lock_delegates_to_wp_cache_delete(): void
    {
        Functions\expect('wp_cache_delete')
            ->once()
            ->with('lihi_token_lock', 'transient');

        (new Lihi_Token_Store())->release_lock();
        $this->assertTrue(true);
    }

    /** @test */
    public function flush_acquires_lock_then_deletes_then_releases(): void
    {
        $calls = [];

        Functions\when('wp_cache_add')->alias(function () use (&$calls) {
            $calls[] = 'acquire';
            return true;
        });
        Functions\when('delete_transient')->alias(function () use (&$calls) {
            $calls[] = 'delete';
        });
        Functions\when('wp_cache_delete')->alias(function () use (&$calls) {
            $calls[] = 'release';
        });

        (new Lihi_Token_Store())->flush();

        $this->assertSame(['acquire', 'delete', 'release'], $calls);
    }

    /** @test */
    public function flush_waits_for_lock_when_held_then_flushes(): void
    {
        $attempts = 0;
        // Lock held for first two attempts, then available.
        Functions\when('wp_cache_add')->alias(function () use (&$attempts) {
            $attempts++;
            return $attempts >= 3;
        });
        Functions\when('usleep')->justReturn(null);

        $deleted = false;
        Functions\when('delete_transient')->alias(function () use (&$deleted) {
            $deleted = true;
        });
        Functions\when('wp_cache_delete')->justReturn(null);

        (new Lihi_Token_Store())->flush();

        $this->assertSame(3, $attempts);
        $this->assertTrue($deleted);
    }

    /** @test */
    public function flush_gives_up_after_max_wait_and_still_deletes(): void
    {
        Functions\when('wp_cache_add')->justReturn(false); // never free
        Functions\when('usleep')->justReturn(null);

        $deleted = false;
        Functions\when('delete_transient')->alias(function () use (&$deleted) {
            $deleted = true;
        });
        Functions\when('wp_cache_delete')->justReturn(null);

        (new Lihi_Token_Store())->flush(300_000); // 0.3 s cap

        $this->assertTrue($deleted);
    }
}
