<?php

namespace Lihi\ShortUrl\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class AjaxCopyUrlTest extends TestCase
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
    public function returns_error_when_post_id_is_zero(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function returns_error_when_type_is_empty(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function returns_success_with_url_on_valid_request(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /** @test */
    public function returns_error_when_service_throws(): void
    {
        // TODO
        $this->markTestIncomplete();
    }
}
