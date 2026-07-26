<?php
/**
 * Blog authentication — bridged to the unified SABEELAUTH module.
 *
 * Staff sign in at /pages/login.php. This file maps that session onto
 * Blog's existing helpers (current_user_id = teachers.id for posts).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/sabeel_gate.php';

if (session_status() === PHP_SESSION_NONE) {
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name((string) ($config['session_name'] ?? 'SABEELAUTH'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Apply unified user onto Blog session keys (teachers.id as user_id).
 */
function blog_sync_unified_session(?array $user = null): bool
{
    global $pdo;

    if ($user === null) {
        $user = sabeel_peek_user();
    }
    if (!$user || !sabeel_user_has_module($user, 'blog')) {
        return false;
    }

    $teacherId = sabeel_ensure_blog_teacher($pdo, $user);
    if (!$teacherId) {
        return false;
    }

    $_SESSION['user_id'] = $teacherId;
    $_SESSION['user_name'] = (string) ($user['full_name'] !== '' ? $user['full_name'] : $user['username']);
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role'] = sabeel_blog_role_for_user($user);
    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_blog_teacher_id'] = $teacherId;
    $_SESSION['_last_activity'] = time();

    return true;
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function is_logged_in(): bool
{
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['auth_user_id'])) {
        return true;
    }
    return blog_sync_unified_session();
}

function current_user_id(): int
{
    if (empty($_SESSION['user_id'])) {
        blog_sync_unified_session();
    }
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

function current_user_role(): string
{
    return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : '';
}

function current_user_name(): string
{
    return isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : '';
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function is_teacher(): bool
{
    return current_user_role() === 'teacher';
}

function refresh_session_user(): void
{
    global $pdo;

    if (!blog_sync_unified_session()) {
        logout_user();
        header('Location: ' . sabeel_login_url('/blog/dashboard.php'));
        exit;
    }

    $user = db_one(
        $pdo,
        'SELECT id, name, email, role, is_active FROM teachers WHERE id = ? LIMIT 1',
        [current_user_id()]
    );

    if (!$user || (int) $user['is_active'] !== 1) {
        logout_user();
        header('Location: ' . sabeel_login_url('/blog/dashboard.php'));
        exit;
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    // Keep admin elevation from unified role (super_admin / admin)
    if (!empty($_SESSION['auth_user_id'])) {
        $unified = sabeel_load_user((int) $_SESSION['auth_user_id']);
        if ($unified) {
            $_SESSION['user_role'] = sabeel_blog_role_for_user($unified);
            return;
        }
    }
    $_SESSION['user_role'] = $user['role'];
}

function require_login(): void
{
    if (!is_logged_in()) {
        $return = (string) ($_SERVER['REQUEST_URI'] ?? '/blog/dashboard.php');
        header('Location: ' . sabeel_login_url($return));
        exit;
    }
    refresh_session_user();
}

function require_role(string $role): void
{
    require_login();
    if (current_user_role() !== $role) {
        header('Location: dashboard.php');
        exit;
    }
}

function require_staff(): void
{
    require_login();
    $role = current_user_role();
    if ($role !== 'admin' && $role !== 'teacher') {
        header('Location: ' . sabeel_login_url('/blog/dashboard.php'));
        exit;
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
