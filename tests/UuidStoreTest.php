<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Lihi\ShortUrl\Lihi_Server_Exception;
use Lihi\ShortUrl\Lihi_Uuid_Store;
use PHPUnit\Framework\TestCase;

class UuidStoreTest extends TestCase
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
    public function get_returns_existing_uuid_option(): void
    {
        $uuid = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';

        Functions\expect('get_option')->once()->with('lihi_uuid', '')->andReturn($uuid);

        $this->assertSame($uuid, (new Lihi_Uuid_Store())->get());
    }

    /** @test */
    public function get_creates_uuid_option_when_missing(): void
    {
        $uuid       = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';
        $uuid_reads = 0;
        $lock_value = '';
        $lock_adds  = 0;
        $uuid_adds  = 0;
        $lock_dels  = 0;

        Functions\when('get_option')->alias(function ($key, $default = false) use (&$uuid_reads, &$lock_value, $uuid) {
            if ($key === 'lihi_uuid_lock') {
                return $lock_value;
            }

            $this->assertSame('lihi_uuid', $key);
            $uuid_reads++;
            return $uuid_reads < 3 ? '' : $uuid;
        });
        Functions\when('add_option')->alias(function ($key, $value, $deprecated = '', $autoload = 'yes') use (&$lock_value, &$lock_adds, &$uuid_adds, $uuid) {
            if ($key === 'lihi_uuid_lock') {
                $lock_adds++;
                $lock_value = $value;
                $this->assertSame('no', $autoload);
                return true;
            }

            $uuid_adds++;
            $this->assertSame('lihi_uuid', $key);
            $this->assertSame($uuid, $value);
            $this->assertSame('no', $autoload);
            return true;
        });
        Functions\when('delete_option')->alias(function ($key) use (&$lock_dels) {
            $this->assertSame('lihi_uuid_lock', $key);
            $lock_dels++;
            return true;
        });
        Functions\expect('wp_generate_uuid4')->once()->andReturn($uuid);

        $this->assertSame($uuid, (new Lihi_Uuid_Store())->get());
        $this->assertSame(1, $lock_adds);
        $this->assertSame(1, $uuid_adds);
        $this->assertSame(1, $lock_dels);
    }

    /** @test */
    public function get_replaces_invalid_uuid_option(): void
    {
        $uuid       = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';
        $uuid_reads = 0;
        $lock_value = '';
        $updates    = 0;

        Functions\when('get_option')->alias(function ($key, $default = false) use (&$uuid_reads, &$lock_value, $uuid) {
            if ($key === 'lihi_uuid_lock') {
                return $lock_value;
            }

            $this->assertSame('lihi_uuid', $key);
            $uuid_reads++;
            return $uuid_reads < 3 ? 'not-a-uuid' : $uuid;
        });
        Functions\when('add_option')->alias(function ($key, $value, $deprecated = '', $autoload = 'yes') use (&$lock_value) {
            $this->assertSame('lihi_uuid_lock', $key);
            $lock_value = $value;
            $this->assertSame('no', $autoload);
            return true;
        });
        Functions\when('update_option')->alias(function ($key, $value, $autoload = null) use (&$updates, $uuid) {
            $updates++;
            $this->assertSame('lihi_uuid', $key);
            $this->assertSame($uuid, $value);
            $this->assertFalse($autoload);
            return true;
        });
        Functions\when('delete_option')->justReturn(true);
        Functions\expect('wp_generate_uuid4')->once()->andReturn($uuid);

        $this->assertSame($uuid, (new Lihi_Uuid_Store())->get());
        $this->assertSame(1, $updates);
    }

    /** @test */
    public function get_returns_persisted_uuid_when_add_option_loses_race(): void
    {
        $generated  = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';
        $persisted  = '7c7a984b-c772-479c-957b-5a25bb2b9d18';
        $uuid_reads = 0;
        $lock_value = '';

        Functions\when('get_option')->alias(function ($key, $default = false) use (&$uuid_reads, &$lock_value, $persisted) {
            if ($key === 'lihi_uuid_lock') {
                return $lock_value;
            }

            $this->assertSame('lihi_uuid', $key);
            $uuid_reads++;
            return $uuid_reads < 3 ? '' : $persisted;
        });
        Functions\when('add_option')->alias(function ($key, $value, $deprecated = '', $autoload = 'yes') use (&$lock_value, $generated) {
            if ($key === 'lihi_uuid_lock') {
                $lock_value = $value;
                return true;
            }

            $this->assertSame('lihi_uuid', $key);
            $this->assertSame($generated, $value);
            $this->assertSame('no', $autoload);
            return false;
        });
        Functions\when('delete_option')->justReturn(true);
        Functions\expect('wp_generate_uuid4')->once()->andReturn($generated);

        $this->assertSame($persisted, (new Lihi_Uuid_Store())->get());
    }

    /** @test */
    public function get_throws_when_add_option_fails_without_persisted_uuid(): void
    {
        $generated = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';

        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lihi_uuid_lock') {
                return '123:lock';
            }
            return '';
        });
        Functions\when('add_option')->alias(function ($key) {
            return $key === 'lihi_uuid_lock';
        });
        Functions\when('delete_option')->justReturn(true);
        Functions\when('esc_html')->returnArg(1);
        Functions\expect('wp_generate_uuid4')->once()->andReturn($generated);

        $this->expectException(Lihi_Server_Exception::class);

        (new Lihi_Uuid_Store())->get();
    }

    /** @test */
    public function get_throws_when_update_option_fails_without_persisted_uuid(): void
    {
        $generated = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';

        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lihi_uuid_lock') {
                return '123:lock';
            }
            return 'not-a-uuid';
        });
        Functions\when('add_option')->alias(function ($key) {
            return $key === 'lihi_uuid_lock';
        });
        Functions\expect('update_option')->once()->andReturn(false);
        Functions\when('delete_option')->justReturn(true);
        Functions\when('esc_html')->returnArg(1);
        Functions\expect('wp_generate_uuid4')->once()->andReturn($generated);

        $this->expectException(Lihi_Server_Exception::class);

        (new Lihi_Uuid_Store())->get();
    }

    /** @test */
    public function get_waits_for_existing_lock_then_returns_stored_uuid(): void
    {
        $uuid       = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';
        $uuid_reads = 0;

        Functions\when('get_option')->alias(function ($key, $default = false) use (&$uuid_reads, $uuid) {
            if ($key === 'lihi_uuid_lock') {
                return time() . ':held';
            }

            $this->assertSame('lihi_uuid', $key);
            $uuid_reads++;
            return $uuid_reads === 1 ? '' : $uuid;
        });
        Functions\expect('add_option')
            ->once()
            ->andReturn(false);
        Functions\expect('usleep')->once()->with(100000);
        Functions\expect('wp_generate_uuid4')->never();

        $this->assertSame($uuid, (new Lihi_Uuid_Store())->get());
    }

    /** @test */
    public function get_throws_when_lock_never_releases_and_uuid_stays_missing(): void
    {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lihi_uuid_lock') {
                return time() . ':held';
            }
            return '';
        });
        Functions\expect('add_option')
            ->twice()
            ->andReturn(false);
        Functions\expect('usleep')->once()->with(100000);
        Functions\when('esc_html')->returnArg(1);
        Functions\expect('wp_generate_uuid4')->never();

        $this->expectException(Lihi_Server_Exception::class);

        (new Lihi_Uuid_Store())->get(100000);
    }
}
