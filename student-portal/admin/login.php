<?php
/**
 * Results Admin — always the one Admin Login (no second password form).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$gate = dirname(__DIR__, 2) . '/includes/sabeel_gate.php';
if (is_file($gate)) {
    require_once $gate;
    $user = sabeel_peek_user();
    if ($user && sabeel_is_site_admin($user)) {
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_name'] = (string) ($user['full_name'] !== '' ? $user['full_name'] : $user['username']);
        $_SESSION['admin_login'] = (string) $user['username'];
        $_SESSION['auth_user_id'] = (int) $user['id'];
        $_SESSION['_last_activity'] = time();
        redirect('admin/index.php');
    }
    header('Location: ' . sabeel_login_url('/student-portal/admin/'));
    exit;
}

header('Location: /pages/login.php?redirect=' . rawurlencode('/student-portal/admin/'));
exit;
