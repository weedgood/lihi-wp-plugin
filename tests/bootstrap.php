<?php

require_once dirname(__DIR__) . '/vendor/antecedent/patchwork/Patchwork.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$_tests_dir = getenv('WP_TESTS_DIR') ?: getenv('WP_PHPUNIT__DIR');

if (!$_tests_dir) {
    $_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
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
    require_once dirname(__DIR__) . '/lihi-shorturl/lihi-shorturl.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';