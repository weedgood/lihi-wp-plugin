<?php

class PluginLoadedTest extends WP_UnitTestCase
{
    public function test_plugin_file_loaded(): void
    {
        $this->assertTrue(class_exists(\Lihi\ShortUrl\Lihi_Client::class));
    }
}