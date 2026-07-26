<?php
/**
 * Session status for Library / Hub frontends.
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

$isAdmin = sabeel_is_site_admin($user);
$modules = $isAdmin
    ? sabeel_module_keys()
    : sabeel_parse_modules((string) ($user['modules'] ?? ''));

lib_json([
    'ok' => true,
    'authenticated' => true,
    'username' => (string) $user['username'],
    'name' => (string) ($user['full_name'] !== '' ? $user['full_name'] : $user['username']),
    'role' => (string) ($user['role_slug'] ?? ''),
    'is_admin' => $isAdmin,
    'modules' => $modules,
    'can_library' => $isAdmin || in_array('library', $modules, true) || sabeel_user_has_module($user, 'library'),
    'can_courses' => $isAdmin || sabeel_user_has_module($user, 'courses'),
    'can_blog' => $isAdmin || sabeel_user_has_module($user, 'blog'),
    'can_portal' => $isAdmin || sabeel_user_has_module($user, 'portal'),
]);
