<?php

namespace Lihi\ShortUrl\Tests;

class PluginHooksTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_COOKIE['lihi_token']);
        $GLOBALS['wp_scripts'] = null;
        $GLOBALS['wp_styles']  = null;

        if (!function_exists('set_current_screen')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
            require_once ABSPATH . 'wp-admin/includes/screen.php';
        }
        set_current_screen('dashboard');
    }

    protected function tearDown(): void
    {
        $GLOBALS['current_screen'] = null;
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // AJAX actions registered
    // -------------------------------------------------------------------------

    public function test_wp_ajax_lihi_copy_url_handler_is_registered(): void
    {
        $this->assertNotFalse(has_action('wp_ajax_lihi_copy_url'));
    }

    // -------------------------------------------------------------------------
    // Script enqueue via admin_enqueue_scripts
    // -------------------------------------------------------------------------

    public function test_lihi_admin_script_enqueued_on_edit_screen(): void
    {
        do_action('admin_enqueue_scripts', 'edit.php');
        $this->assertTrue(wp_script_is('lihi-button', 'enqueued'));
    }

    public function test_lihi_admin_script_enqueued_on_upload_screen(): void
    {
        do_action('admin_enqueue_scripts', 'upload.php');
        $this->assertTrue(wp_script_is('lihi-button', 'enqueued'));
    }

    public function test_lihi_admin_script_enqueued_on_post_edit_screen(): void
    {
        do_action('admin_enqueue_scripts', 'post.php');
        $this->assertTrue(wp_script_is('lihi-button', 'enqueued'));
    }

    public function test_lihi_admin_script_enqueued_on_new_post_screen(): void
    {
        do_action('admin_enqueue_scripts', 'post-new.php');
        $this->assertTrue(wp_script_is('lihi-button', 'enqueued'));
    }

    public function test_lihi_admin_script_not_enqueued_on_unrelated_screens(): void
    {
        do_action('admin_enqueue_scripts', 'index.php');
        $this->assertFalse(wp_script_is('lihi-button', 'enqueued'));
    }
}
