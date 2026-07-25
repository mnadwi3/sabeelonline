<?php
/**
 * =========================================================
 * Authentication Helpers
 * =========================================================
 * This file handles:
 * - Starting the login session
 * - Checking if a user is logged in
 * - Checking Admin / Teacher roles
 * - Password hashing + verifying
 * - Logging users out
 *
 * Include it on protected pages like this:
 *
 *   require_once __DIR__ . '/db.php';
 *   require_once __DIR__ . '/auth.php';
 *   require_login();                 // any logged-in user
 *   require_role('admin');           // admin only
 * =========================================================
 */

// Start session only once (needed to remember login)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Make sure database connection helpers are available
require_once __DIR__ . '/db.php';

/**
 * Create a secure password hash before saving to database
 *
 * Example:
 *   $hash = hash_password('Admin@123');
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Check a plain password against the hash stored in database
 *
 * Example:
 *   if (verify_password($input, $user['password'])) { ... }
 */
function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Save logged-in user data into the session
 */
function login_user(array $user): void
{
    // Prevent session fixation attacks (simple safety step)
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role']; // 'admin' or 'teacher'
}

/**
 * Is anyone logged in right now?
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Get current logged-in user id (or 0 if guest)
 */
function current_user_id(): int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

/**
 * Get current logged-in user role ('admin', 'teacher', or '')
 */
function current_user_role(): string
{
    return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : '';
}

/**
 * Get current logged-in user name
 */
function current_user_name(): string
{
    return isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : '';
}

/**
 * Shortcut helpers for roles
 */
function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function is_teacher(): bool
{
    return current_user_role() === 'teacher';
}

/**
 * Reload user from DB so disabled / role-changed accounts lose access
 */
function refresh_session_user(): void
{
    global $pdo;

    if (!is_logged_in()) {
        return;
    }

    $user = db_one(
        $pdo,
        "SELECT id, name, email, role, is_active FROM teachers WHERE id = ? LIMIT 1",
        [current_user_id()]
    );

    if (!$user || (int) $user['is_active'] !== 1) {
        logout_user();
        header('Location: login.php');
        exit;
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

/**
 * Force login: if guest, send them to login.php
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    refresh_session_user();
}

/**
 * Force a specific role (admin or teacher)
 *
 * Example:
 *   require_role('admin');
 */
function require_role(string $role): void
{
    require_login();

    if (current_user_role() !== $role) {
        // Logged in, but wrong role
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Allow either admin OR teacher (any staff member)
 */
function require_staff(): void
{
    require_login();

    $role = current_user_role();
    if ($role !== 'admin' && $role !== 'teacher') {
        header('Location: login.php');
        exit;
    }
}

/**
 * Try to log in with email + password
 * Returns the user array on success, or null on failure
 */
function attempt_login(string $email, string $password): ?array
{
    global $pdo;

    $email = trim(strtolower($email));

    // Find active user by email (prepared statement = safer)
    $user = db_one(
        $pdo,
        "SELECT * FROM teachers
         WHERE email = ?
           AND is_active = 1
         LIMIT 1",
        [$email]
    );

    if (!$user) {
        return null;
    }

    // Check password hash
    if (!verify_password($password, $user['password'])) {
        return null;
    }

    // Success → store session
    login_user($user);
    return $user;
}

/**
 * Log the user out and clear session data
 */
function logout_user(): void
{
    $_SESSION = [];

    // Remove session cookie if it exists
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Simple text cleaner for forms (prevents basic XSS in HTML output)
 *
 * Example in HTML:
 *   echo e($post['title']);
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
