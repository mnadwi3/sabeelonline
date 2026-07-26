<?php
/**
 * Cross-portal auth gate — read SABEELAUTH without clashing with Blog/Portal helpers.
 *
 * Use from Blog, Library API, Student Portal, Hub JS (via session API).
 * Location: /includes/sabeel_gate.php
 */

declare(strict_types=1);

/** @return list<string> */
function sabeel_module_keys(): array
{
    return ['blog', 'library', 'portal', 'courses'];
}

/** @return array<string,string> */
function sabeel_module_labels(): array
{
    return [
        'blog' => 'Blog (Admin / Teacher)',
        'library' => 'Digital Library',
        'portal' => 'Student Portal (Results Admin)',
        'courses' => 'Courses & Admissions Admin',
    ];
}

/**
 * Ensure users.modules + users.blog_teacher_id exist (safe to call repeatedly).
 */
function sabeel_ensure_user_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'modules'")->fetch();
        if (!$cols) {
            $pdo->exec("ALTER TABLE users ADD COLUMN modules VARCHAR(120) NOT NULL DEFAULT '' AFTER role_id");
        }
        $cols2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'blog_teacher_id'")->fetch();
        if (!$cols2) {
            $pdo->exec('ALTER TABLE users ADD COLUMN blog_teacher_id INT UNSIGNED NULL DEFAULT NULL AFTER modules');
        }
    } catch (Throwable $e) {
        error_log('sabeel_ensure_user_columns: ' . $e->getMessage());
    }
}

/**
 * @return list<string>
 */
function sabeel_parse_modules(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $parts = preg_split('/[\s,|]+/', strtolower(trim($raw))) ?: [];
    $allowed = array_fill_keys(sabeel_module_keys(), true);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && isset($allowed[$p])) {
            $out[$p] = true;
        }
    }
    return array_keys($out);
}

/**
 * @param list<string> $modules
 */
function sabeel_encode_modules(array $modules): string
{
    $allowed = array_fill_keys(sabeel_module_keys(), true);
    $clean = [];
    foreach ($modules as $m) {
        $m = strtolower(trim((string) $m));
        if (isset($allowed[$m])) {
            $clean[$m] = true;
        }
    }
    return implode(',', array_keys($clean));
}

/**
 * Portal access check.
 *
 * Simple rule (what the site owner asked for):
 *   Admin / Super Admin  → every admin area (Blog, Library, Portal, Courses, Hub)
 *   Teacher              → Blog only (writers)
 *   Student              → Library only (optional coursebook login)
 */
function sabeel_user_has_module(array $user, string $module): bool
{
    $module = strtolower(trim($module));
    if ($module === '') {
        return false;
    }
    $role = (string) ($user['role_slug'] ?? '');

    // One Admin login opens every management page
    if (in_array($role, ['admin', 'super_admin'], true)) {
        return true;
    }

    if ($role === 'teacher' && $module === 'blog') {
        return true;
    }

    if ($role === 'student' && $module === 'library') {
        return true;
    }

    // Legacy checkbox column (ignored for admins; still honoured for odd cases)
    $mods = sabeel_parse_modules(isset($user['modules']) ? (string) $user['modules'] : '');
    return in_array($module, $mods, true);
}

/** True when this account is a site admin (manages all panels). */
function sabeel_is_site_admin(?array $user): bool
{
    if (!$user) {
        return false;
    }
    return in_array((string) ($user['role_slug'] ?? ''), ['admin', 'super_admin'], true);
}

function sabeel_login_url(string $redirectPath = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = $scheme . '://' . $host;
    $url = $base . '/pages/login.php';
    if ($redirectPath !== '') {
        if ($redirectPath[0] !== '/') {
            $redirectPath = '/' . $redirectPath;
        }
        $url .= '?redirect=' . rawurlencode($redirectPath);
    }
    return $url;
}

/**
 * PDO for the shared Hostinger database (auth + blog).
 */
function sabeel_gate_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require dirname(__DIR__) . '/config/config.php';
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    sabeel_ensure_user_columns($pdo);
    return $pdo;
}

/**
 * Peek the unified SABEELAUTH session without breaking another active session.
 *
 * @return array|null user row with role_slug / role_name / modules
 */
function sabeel_peek_user(): ?array
{
    $config = require dirname(__DIR__) . '/config/config.php';
    $authName = (string) ($config['session_name'] ?? 'SABEELAUTH');

    $wasActive = session_status() === PHP_SESSION_ACTIVE;
    $prevName = $wasActive ? session_name() : null;

    if ($wasActive) {
        if ($prevName === $authName) {
            // Already on unified session
            $userId = !empty($_SESSION['auth_user_id']) ? (int) $_SESSION['auth_user_id'] : 0;
            if ($userId <= 0) {
                return null;
            }
            return sabeel_load_user($userId);
        }
        session_write_close();
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name($authName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();

    $userId = !empty($_SESSION['auth_user_id']) ? (int) $_SESSION['auth_user_id'] : 0;
    $lastActivity = (int) ($_SESSION['_last_activity'] ?? 0);
    $lifetime = (int) ($config['session_lifetime'] ?? 1800);
    if ($userId > 0 && $lastActivity > 0 && (time() - $lastActivity) > $lifetime) {
        $userId = 0;
    }

    session_write_close();

    if ($wasActive && $prevName) {
        session_name($prevName);
        @session_start();
    }

    if ($userId <= 0) {
        return null;
    }

    return sabeel_load_user($userId);
}

function sabeel_load_user(int $userId): ?array
{
    try {
        $pdo = sabeel_gate_pdo();
        $stmt = $pdo->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('sabeel_load_user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Ensure a linked teachers row exists for blog authorship; returns teachers.id.
 */
function sabeel_ensure_blog_teacher(PDO $pdo, array $user): ?int
{
    $existing = !empty($user['blog_teacher_id']) ? (int) $user['blog_teacher_id'] : 0;
    if ($existing > 0) {
        $check = $pdo->prepare('SELECT id FROM teachers WHERE id = ? LIMIT 1');
        $check->execute([$existing]);
        if ($check->fetchColumn()) {
            return $existing;
        }
    }

    // teachers table may not exist on a fresh DB
    try {
        $pdo->query('SELECT 1 FROM teachers LIMIT 1');
    } catch (Throwable $e) {
        return null;
    }

    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $name = trim((string) ($user['full_name'] ?? ''));
    if ($name === '') {
        $name = (string) ($user['username'] ?? 'Staff');
    }
    $roleSlug = (string) ($user['role_slug'] ?? 'teacher');
    $teacherRole = in_array($roleSlug, ['admin', 'super_admin'], true) ? 'admin' : 'teacher';

    if ($email !== '') {
        $byEmail = $pdo->prepare('SELECT id FROM teachers WHERE email = ? LIMIT 1');
        $byEmail->execute([$email]);
        $tid = $byEmail->fetchColumn();
        if ($tid) {
            $link = $pdo->prepare('UPDATE users SET blog_teacher_id = ?, updated_at = NOW() WHERE id = ?');
            $link->execute([(int) $tid, (int) $user['id']]);
            return (int) $tid;
        }
    }

    // Placeholder password — login goes through unified users table only
    $placeholder = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $ins = $pdo->prepare(
        'INSERT INTO teachers (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $ins->execute([$name, $email !== '' ? $email : ('user' . (int) $user['id'] . '@blog.local'), $placeholder, $teacherRole]);
    $tid = (int) $pdo->lastInsertId();
    $link = $pdo->prepare('UPDATE users SET blog_teacher_id = ?, updated_at = NOW() WHERE id = ?');
    $link->execute([$tid, (int) $user['id']]);
    return $tid;
}

/**
 * Map unified role → blog role string.
 */
function sabeel_blog_role_for_user(array $user): string
{
    $role = (string) ($user['role_slug'] ?? '');
    return in_array($role, ['admin', 'super_admin'], true) ? 'admin' : 'teacher';
}

/**
 * Import legacy Blog teachers + Portal admins into users (no password changes).
 *
 * @return array{teachers:int,portal:int,skipped:int,errors:list<string>}
 */
function sabeel_import_legacy_accounts(PDO $pdo): array
{
    sabeel_ensure_user_columns($pdo);
    $result = ['teachers' => 0, 'portal' => 0, 'skipped' => 0, 'errors' => []];

    $roleIds = [];
    foreach ($pdo->query('SELECT id, slug FROM roles')->fetchAll() ?: [] as $r) {
        $roleIds[(string) $r['slug']] = (int) $r['id'];
    }

    // Grant all modules to existing Super Admins
    if (!empty($roleIds['super_admin'])) {
        $all = sabeel_encode_modules(sabeel_module_keys());
        $pdo->prepare('UPDATE users SET modules = ? WHERE role_id = ?')->execute([$all, $roleIds['super_admin']]);
    }

    // --- Blog teachers ---
    try {
        $teachers = $pdo->query('SELECT * FROM teachers')->fetchAll() ?: [];
    } catch (Throwable $e) {
        $teachers = [];
        $result['errors'][] = 'teachers table not available';
    }

    foreach ($teachers as $t) {
        $email = strtolower(trim((string) ($t['email'] ?? '')));
        if ($email === '') {
            $result['skipped']++;
            continue;
        }
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? OR blog_teacher_id = ? LIMIT 1');
        $exists->execute([$email, (int) $t['id']]);
        if ($exists->fetchColumn()) {
            $result['skipped']++;
            // Keep link fresh
            $pdo->prepare('UPDATE users SET blog_teacher_id = ? WHERE email = ? AND (blog_teacher_id IS NULL OR blog_teacher_id = 0)')
                ->execute([(int) $t['id'], $email]);
            continue;
        }

        $usernameBase = preg_replace('/[^A-Za-z0-9._-]/', '', explode('@', $email)[0] ?: 'teacher') ?: 'teacher';
        $username = substr($usernameBase, 0, 40);
        $n = 0;
        while (true) {
            $try = $n === 0 ? $username : substr($username, 0, 40) . $n;
            $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $chk->execute([$try]);
            if (!$chk->fetchColumn()) {
                $username = $try;
                break;
            }
            $n++;
        }

        $roleSlug = ((string) ($t['role'] ?? '')) === 'admin' ? 'admin' : 'teacher';
        $roleId = $roleIds[$roleSlug] ?? $roleIds['teacher'] ?? 3;
        $modules = sabeel_encode_modules(['blog']);
        $password = (string) $t['password'];
        if ($password === '') {
            $result['skipped']++;
            continue;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password, full_name, role_id, modules, blog_teacher_id, is_active, password_changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $username,
                $email,
                $password, // keep existing hash
                (string) ($t['name'] ?? $username),
                $roleId,
                $modules,
                (int) $t['id'],
                (int) ($t['is_active'] ?? 1) ? 1 : 0,
            ]);
            $result['teachers']++;
        } catch (Throwable $e) {
            $result['errors'][] = 'teacher ' . $email . ': ' . $e->getMessage();
        }
    }

    // --- Student Portal admins (tbl_users) ---
    try {
        $admins = $pdo->query('SELECT * FROM tbl_users')->fetchAll() ?: [];
    } catch (Throwable $e) {
        $admins = [];
        $result['errors'][] = 'tbl_users table not available';
    }

    foreach ($admins as $a) {
        $login = trim((string) ($a['login_name'] ?? ''));
        if ($login === '') {
            $result['skipped']++;
            continue;
        }
        $email = strtolower($login) . '@portal.sabeel.local';
        $exists = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $exists->execute([$login, $email]);
        if ($exists->fetchColumn()) {
            $result['skipped']++;
            continue;
        }

        $password = (string) ($a['password_hash'] ?? '');
        if ($password === '') {
            $result['skipped']++;
            continue;
        }

        $roleId = $roleIds['admin'] ?? 2;
        $modules = sabeel_encode_modules(['portal']);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password, full_name, role_id, modules, is_active, password_changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
            );
            $stmt->execute([
                $login,
                $email,
                $password,
                (string) ($a['t_name'] ?? $login),
                $roleId,
                $modules,
            ]);
            $result['portal']++;
        } catch (Throwable $e) {
            $result['errors'][] = 'portal ' . $login . ': ' . $e->getMessage();
        }
    }

    return $result;
}
