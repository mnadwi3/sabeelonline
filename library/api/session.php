<?php
/**
 * Unified session status for Library / Hub frontends.
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = sabeel_peek_user();
if (!$user) {
    lib_json([
        'ok' => true,
        'authenticated' => false,
        'login' => '/pages/login.php',
    ]);
}

$modules = ($user['role_slug'] ?? '') === 'super_admin'
    ? sabeel_module_keys()
    : sabeel_parse_modules((string) ($user['modules'] ?? ''));

lib_json([
    'ok' => true,
    'authenticated' => true,
    'username' => (string) $user['username'],
    'name' => (string) ($user['full_name'] !== '' ? $user['full_name'] : $user['username']),
    'role' => (string) ($user['role_slug'] ?? ''),
    'modules' => $modules,
    'can_library' => in_array('library', $modules, true) || ($user['role_slug'] ?? '') === 'super_admin',
    'can_courses' => in_array('courses', $modules, true) || ($user['role_slug'] ?? '') === 'super_admin',
    'can_blog' => in_array('blog', $modules, true) || ($user['role_slug'] ?? '') === 'super_admin',
    'can_portal' => in_array('portal', $modules, true) || ($user['role_slug'] ?? '') === 'super_admin',
]);
