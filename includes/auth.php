<?php
/**
 * Authorization helpers for protected pages.
 *
 * Location: /includes/auth.php
 */

declare(strict_types=1);

/**
 * Global Auth service (created in bootstrap).
 */
function auth(): Auth
{
    if (!isset($GLOBALS['auth_service']) || !$GLOBALS['auth_service'] instanceof Auth) {
        throw new RuntimeException('Auth service is not initialized.');
    }
    return $GLOBALS['auth_service'];
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth_user_id']);
}

function current_user_id(): ?int
{
    return !empty($_SESSION['auth_user_id']) ? (int) $_SESSION['auth_user_id'] : null;
}

function current_user_role(): ?string
{
    return !empty($_SESSION['auth_role']) ? (string) $_SESSION['auth_role'] : null;
}

function current_username(): string
{
    return (string) ($_SESSION['auth_username'] ?? '');
}

function current_user_display_name(): string
{
    $name = trim((string) ($_SESSION['auth_full_name'] ?? ''));
    return $name !== '' ? $name : current_username();
}

/**
 * Role hierarchy for privilege checks.
 *
 * @return array<string,int>
 */
function role_levels(): array
{
    return [
        'student' => 1,
        'teacher' => 2,
        'admin' => 3,
        'super_admin' => 4,
    ];
}

function user_has_role(string ...$roles): bool
{
    $current = current_user_role();
    if ($current === null) {
        return false;
    }
    foreach ($roles as $role) {
        if (hash_equals($current, $role)) {
            return true;
        }
    }
    return false;
}

function user_at_least_role(string $minimumRole): bool
{
    $levels = role_levels();
    $current = current_user_role();
    if ($current === null || !isset($levels[$current], $levels[$minimumRole])) {
        return false;
    }
    return $levels[$current] >= $levels[$minimumRole];
}

function is_student(): bool
{
    return user_has_role('student');
}

function is_teacher(): bool
{
    return user_has_role('teacher', 'admin', 'super_admin');
}

function is_admin(): bool
{
    return user_has_role('admin', 'super_admin');
}

function is_super_admin(): bool
{
    return user_has_role('super_admin');
}

/** CamelCase aliases (API names from security spec). */
function isStudent(): bool
{
    return is_student();
}

function isTeacher(): bool
{
    return is_teacher();
}

function isAdmin(): bool
{
    return is_admin();
}

function isSuperAdmin(): bool
{
    return is_super_admin();
}

/**
 * Require an authenticated session; redirect to login otherwise.
 */
function requireLogin(): void
{
    if (!is_logged_in()) {
        $return = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $login = (string) ($GLOBALS['app_config']['login_path'] ?? '/pages/login.php');
        if ($return !== '' && str_starts_with($return, '/') && !str_starts_with($return, '//')) {
            redirect($login . '?redirect=' . urlencode($return));
        }
        redirect($login);
    }

    // Re-validate account still active
    $user = auth()->users()->findById((int) current_user_id());
    if (!$user || !(int) $user['is_active']) {
        auth()->logout();
        flash_set('error', 'Your session is no longer valid. Please log in again.');
        redirect((string) ($GLOBALS['app_config']['login_path'] ?? '/pages/login.php'));
    }

    // Refresh role / modules in case admin changed them
    $_SESSION['auth_role'] = (string) $user['role_slug'];
    $_SESSION['auth_role_name'] = (string) $user['role_name'];
    $_SESSION['auth_full_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['auth_username'] = (string) $user['username'];
    $_SESSION['auth_email'] = (string) $user['email'];
    $mods = sabeel_parse_modules((string) ($user['modules'] ?? ''));
    if (($user['role_slug'] ?? '') === 'super_admin') {
        $mods = sabeel_module_keys();
        // Persist full access if the column was added after install
        if (trim((string) ($user['modules'] ?? '')) === '') {
            try {
                auth()->users()->setModules((int) $user['id'], $mods);
            } catch (Throwable $e) {
                // non-fatal
            }
        }
    }
    $_SESSION['auth_modules'] = $mods;
    $_SESSION['auth_blog_teacher_id'] = !empty($user['blog_teacher_id'])
        ? (int) $user['blog_teacher_id']
        : 0;
}

/**
 * Current user's portal module keys.
 *
 * @return list<string>
 */
function current_user_modules(): array
{
    if (!empty($_SESSION['auth_modules']) && is_array($_SESSION['auth_modules'])) {
        return array_values($_SESSION['auth_modules']);
    }
    return [];
}

function user_has_module(string $module): bool
{
    if (is_super_admin()) {
        return true;
    }
    return in_array(strtolower(trim($module)), current_user_modules(), true);
}

/**
 * Require login + access to a portal module (blog, library, portal, courses).
 */
function requireModule(string $module): void
{
    requireLogin();
    if (!user_has_module($module)) {
        http_response_code(403);
        exit('Access denied. Your account is not enabled for this section. Ask a Super Admin to grant access.');
    }
}

/**
 * Require login + one of the given roles (exact match).
 */
function requireRole(string ...$roles): void
{
    requireLogin();
    if (!user_has_role(...$roles)) {
        http_response_code(403);
        exit('Access denied. You do not have permission to view this page.');
    }
}

/**
 * Require login + at least the given role level.
 */
function requireMinRole(string $minimumRole): void
{
    requireLogin();
    if (!user_at_least_role($minimumRole)) {
        http_response_code(403);
        exit('Access denied. You do not have permission to view this page.');
    }
}
