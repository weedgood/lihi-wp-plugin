<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use Lihi\ShortUrl\Lihi_Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
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

    // request() ---------------------------------------------------------------

    /** @test */
    public function get_request_appends_data_as_query_string(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function post_request_encodes_data_as_json_body(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function request_includes_authorization_header_when_auth_true(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function request_omits_authorization_header_when_auth_false(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function request_returns_empty_array_on_204(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function request_returns_empty_array_on_empty_body(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function request_throws_on_invalid_json(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function request_throws_on_http_4xx(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function request_throws_on_wp_error(): void
    {
        // TODO
        $this->markTestIncomplete();
    }
}
