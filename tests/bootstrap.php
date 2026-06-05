<?php

$vendor_dir = getenv('LIHI_VENDOR_DIR') ?: dirname(__DIR__) . '/vendor';
$vendor_dir = rtrim($vendor_dir, '/\\');

require_once $vendor_dir . '/antecedent/patchwork/Patchwork.php';
require_once $vendor_dir . '/autoload.php';

$_tests_dir = getenv('WP_TESTS_DIR') ?: getenv('WP_PHPUNIT__DIR');

if (!$_tests_dir) {
    $_tests_dir = $vendor_dir . '/wp-phpunit/wp-phpunit';
}

$_tests_dir = rtrim($_tests_dir, '/\\');

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    exit("Error: functions.php not found in {$_tests_dir}/includes/functions.php\n");
}

if (!file_exists($_tests_dir . '/includes/bootstrap.php')) {
    exit("Error: bootstrap.php not found in {$_tests_dir}/includes/bootstrap.php\n");
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin(): void
{
    // Both options must be set before plugin load so the conditional UI-hook
    // registration in add-shorturl-column.php fires; PluginHooksTest depends
    // on this. AdminNoticeTest re-runs bootstrap.php with the options
    // selectively cleared to exercise the empty-state branches.
    update_option( 'lihi_email', 'test@example.com' );
    update_option( 'lihi_domain', 'redirect.lihidev.com' );
    require_once dirname(__DIR__) . '/lihi-short-url/lihi-short-url.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';
