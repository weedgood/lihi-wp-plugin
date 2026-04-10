<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
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
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // has_valid_token() -------------------------------------------------------

    /** @test */
    public function has_valid_token_returns_false_when_cookie_missing(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function has_valid_token_returns_false_when_cookie_empty(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function has_valid_token_returns_false_when_token_malformed(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function has_valid_token_returns_false_when_exp_missing(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function has_valid_token_returns_false_when_token_expired(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function has_valid_token_returns_true_when_token_valid(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    // login() -----------------------------------------------------------------

    /** @test */
    public function login_returns_token_on_success(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function login_throws_when_token_empty(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function login_propagates_client_exception(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    // get_or_create_short_url() -----------------------------------------------

    /** @test */
    public function get_or_create_returns_existing_site_name_without_creating(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function get_or_create_calls_create_when_no_match_found(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function get_or_create_throws_when_create_returns_empty_site_name(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function get_or_create_returns_first_matching_site_name(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function get_or_create_passes_correct_body_to_create_site(): void
    {
        // TODO
        $this->markTestIncomplete();
    }
}
