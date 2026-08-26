<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'portfolio-test-salt-' . $scheme;
    }
}

require_once dirname(__DIR__) . '/includes/class-lacvo-core-crypto.php';
