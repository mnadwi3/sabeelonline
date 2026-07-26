<?php
/**
 * Bootstrap: session, PDO, helpers
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/../config/config.php';
/* Local XAMPP overrides only — never use on Hostinger/live */
$localConfig = __DIR__ . '/../config/config.local.php';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$isLocalHost = $host === 'localhost'
    || str_starts_with($host, 'localhost:')
    || $host === '127.0.0.1'
    || str_starts_with($host, '127.0.0.1:');
if ($isLocalHost && is_file($localConfig)) {
    $config = array_merge($config, require $localConfig);
}

function app_config(?string $key = null, $default = null)
{
    global $config;
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = app_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $c['db_host'],
        $c['db_port'],
        $c['db_name'],
        $c['db_charset']
    );
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    ensure_schema($pdo);
    return $pdo;
}

/** Whether tbl_courses.marksheet_title exists (cached per request). */
function has_marksheet_title_column(?PDO $pdo = null): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    try {
        $db = $pdo ?? db();
        $cols = $db->query('SHOW COLUMNS FROM tbl_courses LIKE "marksheet_title"')->fetch();
        $has = (bool) $cols;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/** Lightweight migrations for live DBs (safe to run repeatedly). */
function ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM tbl_courses LIKE "marksheet_title"')->fetch();
        if (!$cols) {
            $pdo->exec(
                'ALTER TABLE tbl_courses
                 ADD COLUMN marksheet_title VARCHAR(200) NULL DEFAULT NULL
                 AFTER course_name'
            );
        }

        $studentRollColumn = $pdo->query('SHOW COLUMNS FROM tbl_students LIKE "student_roll_no"')->fetch();
        if (!$studentRollColumn) {
            $pdo->exec(
                'ALTER TABLE tbl_students
                 ADD COLUMN student_roll_no VARCHAR(40) NULL DEFAULT NULL
                 AFTER admin_no'
            );
            $pdo->exec(
                'UPDATE tbl_students
                 SET student_roll_no = roll_no
                 WHERE student_roll_no IS NULL OR student_roll_no = ""'
            );
        }
    } catch (Throwable $e) {
        // Table may not exist yet, or DB user lacks ALTER privilege
    }
}

function detect_base_path(): string
{
    $configured = trim((string) app_config('base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    // Auto-detect project folder from this file: /includes/bootstrap.php
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRoot = realpath(dirname(__DIR__)) ?: '';
    if ($docRoot && $projectRoot && strpos($projectRoot, $docRoot) === 0) {
        $rel = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
        return rtrim($rel, '/') ?: '';
    }
    return '';
}

function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $base = detect_base_path();
    }
    $path = ltrim($path, '/');
    if ($base === '') {
        return $path === '' ? '/' : '/' . $path;
    }
    return $path === '' ? $base : $base . '/' . $path;
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function require_admin(): void
{
    // One Admin Login session (SABEELAUTH)
    $gate = dirname(__DIR__, 2) . '/includes/sabeel_gate.php';
    if (is_file($gate)) {
        require_once $gate;
        $user = sabeel_peek_user();
        if ($user && sabeel_is_site_admin($user)) {
            $_SESSION['admin_id'] = (int) $user['id'];
            $_SESSION['admin_name'] = (string) ($user['full_name'] !== '' ? $user['full_name'] : $user['username']);
            $_SESSION['admin_login'] = (string) $user['username'];
            return;
        }
    }

    // Clear stale portal-only session leftovers
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_login']);
    $return = '/student-portal/admin/';
    header('Location: /pages/login.php?redirect=' . rawurlencode($return));
    exit;
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'] ?? 'Admin',
        'login' => $_SESSION['admin_login'] ?? '',
    ];
}

/**
 * Calculate percentage, grade, and Pass/Fail from totals.
 * Uses threshold bands (no gaps): e.g. 79.25 → B1, not F.
 */
function calculate_result(float $obtained, float $maximum): array
{
    $pct = $maximum > 0 ? round(($obtained / $maximum) * 100, 2) : 0.0;
    $passPct = (float) app_config('pass_percentage', 40);

    // Highest threshold first
    $thresholds = [
        ['min' => 90, 'grade' => 'A1'],
        ['min' => 80, 'grade' => 'A2'],
        ['min' => 70, 'grade' => 'B1'],
        ['min' => 60, 'grade' => 'B2'],
        ['min' => 50, 'grade' => 'C1'],
        ['min' => 40, 'grade' => 'C2'],
    ];
    $grade = 'F';
    foreach ($thresholds as $t) {
        if ($pct >= $t['min']) {
            $grade = $t['grade'];
            break;
        }
    }
    if ($pct < $passPct) {
        $grade = 'F';
    }
    $status = $pct >= $passPct ? 'Pass' : 'Fail';
    return [
        'percentage' => $pct,
        'grade' => $grade,
        'result_status' => $status,
        'final_result' => $status,
    ];
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $t = strtotime($date);
    return $t ? date('d M Y', $t) : e($date);
}
