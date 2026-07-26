<?php
/**
 * Library admin login — prefers unified SABEELAUTH session.
 * Legacy admin codes still accepted as emergency fallback.
 */
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lib_json(['ok' => false, 'error' => 'POST required.'], 405);
}

$user = lib_unified_admin_user();
if ($user) {
    $_SESSION['lib_admin'] = true;
    lib_json(['ok' => true, 'via' => 'unified']);
}

$code = isset($_POST['admin_code']) ? trim((string) $_POST['admin_code']) : '';
if ($code !== '' && lib_is_admin_code($code)) {
    $_SESSION['lib_admin'] = true;
    lib_json(['ok' => true, 'via' => 'legacy_code']);
}

lib_json([
    'ok' => false,
    'error' => 'Sign in at /pages/login.php with an account that has Library or Courses access.',
    'login' => '/pages/login.php?redirect=/admin-hub.html',
], 401);
