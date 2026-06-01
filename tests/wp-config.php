<?php

define('DB_NAME', 'wordpress_test');
define('DB_USER', 'wordpress');
define('DB_PASSWORD', 'wordpress');
define('DB_HOST', 'db_test');

define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_DEBUG', true);
define('WP_ADMIN', true);
define('LIHI_ENV', 'test');

define('WP_TESTS_DOMAIN', 'localhost');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', PHP_BINARY);

$wordpress_dir = getenv('LIHI_WORDPRESS_DIR') ?: dirname(__DIR__) . '/wordpress';
define('ABSPATH', rtrim($wordpress_dir, '/\\') . '/');
