<?php
/**
 * Confirm Admin Login session for Library / Hub APIs.
 */
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lib_json(['ok' => false, 'error' => 'POST required.'], 405);
}

$user = lib_unified_admin_user();
if ($user) {
    $_SESSION['lib_admin'] = true;
    lib_json(['ok' => true, 'via' => 'admin_login']);
}

lib_json([
    'ok' => false,
    'error' => 'Sign in at /pages/login.php with your Admin ID and password.',
    'login' => '/pages/login.php?redirect=/admin-hub.html',
], 401);
