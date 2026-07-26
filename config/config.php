<?php
/**
 * Application configuration for the secure auth module.
 *
 * Location: /config/config.php
 * Hostinger: override secrets in config.local.php (same folder) if needed.
 */

declare(strict_types=1);

$config = [
    'app_name' => 'Sabeel Us Salaam Online',
    'app_url' => '', // auto-detected when empty
    'timezone' => 'Asia/Kolkata',

    // Session
    'session_name' => 'SABEELAUTH',
    'session_lifetime' => 1800, // 30 minutes inactivity
    'session_regenerate_interval' => 300, // re-issue session id every 5 minutes

    // Remember me
    'remember_cookie' => 'sabeel_remember',
    'remember_days' => 30,

    // Login protection
    'max_login_attempts' => 5,
    'lockout_minutes' => 15,
    'ip_rate_limit_attempts' => 20,
    'ip_rate_limit_minutes' => 15,

    // Password reset
    'reset_token_minutes' => 60,
    'mail_from' => 'noreply@sabeelussalaamonline.com',
    'mail_from_name' => 'Sabeel Us Salaam Online',

    // Paths (web-relative from site root)
    'login_path' => '/pages/login.php',
    'dashboard_path' => '/pages/dashboard.php',

    // Database — Hostinger MySQL (same DB as blog app; new tables only)
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u917534606_u123sabeel',
        'user' => 'u917534606_adminpanel',
        'pass' => 'Madarsa123*',
        'charset' => 'utf8mb4',
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

date_default_timezone_set($config['timezone']);

return $config;
