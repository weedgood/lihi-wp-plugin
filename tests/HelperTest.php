<?php

namespace Lihi\ShortUrl\Tests;

class HelperTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option( 'lihi_email' );
    }

    protected function tearDown(): void
    {
        update_option( 'lihi_email', 'test@example.com' );
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // lihi_email()
    // -------------------------------------------------------------------------

    public function test_lihi_email_returns_stored_option_value(): void
    {
        update_option( 'lihi_email', 'hello@example.com' );
        $this->assertSame( 'hello@example.com', \Lihi\ShortUrl\lihi_email() );
    }

    public function test_lihi_email_returns_empty_string_when_option_unset(): void
    {
        $this->assertSame( '', \Lihi\ShortUrl\lihi_email() );
    }
}
