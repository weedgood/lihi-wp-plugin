<?php

namespace Lihi\ShortUrl\Tests;

class HelperTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option( 'lihi_email' );
        delete_option( 'lihi_uuid' );
        \Lihi\ShortUrl\Lihi_Singletons::lihi_client_set( null );
    }

    protected function tearDown(): void
    {
        update_option( 'lihi_email', 'test@example.com' );
        delete_option( 'lihi_uuid' );
        \Lihi\ShortUrl\Lihi_Singletons::lihi_client_set( null );
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

    // -------------------------------------------------------------------------
    // lihi_uuid()
    // -------------------------------------------------------------------------

    public function test_lihi_uuid_returns_stored_option_value(): void
    {
        $stored = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';
        update_option( 'lihi_uuid', $stored );

        $this->assertSame( $stored, \Lihi\ShortUrl\lihi_uuid() );
    }

    public function test_lihi_uuid_creates_option_when_missing(): void
    {
        $uuid = \Lihi\ShortUrl\lihi_uuid();

        $this->assertNotSame( '', $uuid );
        $this->assertSame( $uuid, get_option( 'lihi_uuid' ) );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function test_lihi_uuid_replaces_invalid_stored_option_value(): void
    {
        update_option( 'lihi_uuid', 'not-a-uuid' );

        $uuid = \Lihi\ShortUrl\lihi_uuid();

        $this->assertNotSame( 'not-a-uuid', $uuid );
        $this->assertSame( $uuid, get_option( 'lihi_uuid' ) );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function test_lihi_client_receives_base_url_and_uuid_from_helper(): void
    {
        $stored = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';
        update_option( 'lihi_uuid', $stored );
        \Lihi\ShortUrl\Lihi_Singletons::lihi_client_set( null );

        $client = \Lihi\ShortUrl\Lihi_Singletons::lihi_client();
        $ref    = new \ReflectionClass( $client );

        $baseUrl = $ref->getProperty( 'base_url' );
        $baseUrl->setAccessible( true );
        $uuid = $ref->getProperty( 'uuid' );
        $uuid->setAccessible( true );

        $this->assertSame( 'https://app.lihidev.com', $baseUrl->getValue( $client ) );
        $this->assertSame( $stored, $uuid->getValue( $client ) );
    }

    public function test_lihi_singletons_exposes_static_client_factory(): void
    {
        $stored = '2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e';
        update_option( 'lihi_uuid', $stored );
        \Lihi\ShortUrl\Lihi_Singletons::lihi_client_set( null );

        $client = \Lihi\ShortUrl\Lihi_Singletons::lihi_client();

        $this->assertInstanceOf( \Lihi\ShortUrl\Lihi_Client_Interface::class, $client );
        $this->assertSame( $client, \Lihi\ShortUrl\Lihi_Singletons::lihi_client() );
    }

    public function test_client_service_and_store_global_wrappers_do_not_exist(): void
    {
        $this->assertFalse( function_exists( 'Lihi\\ShortUrl\\lihi_client' ) );
        $this->assertFalse( function_exists( 'Lihi\\ShortUrl\\lihi_service' ) );
        $this->assertFalse( function_exists( 'Lihi\\ShortUrl\\lihi_token_store' ) );
        $this->assertFalse( function_exists( 'Lihi\\ShortUrl\\lihi_uuid_store' ) );
    }
}
