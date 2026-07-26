<?php
/**
 * Shared Student ID login for Library + Download Results.
 * Validates against student-portal tbl_students.roll_no (portal Student ID).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function student_access_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function student_access_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip !== '' ? $ip : 'unknown';
}

function student_access_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('SABEELSTUDENT');
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

function student_access_rate_ok(string $ip): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sabeel_student_access';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $now = time();
    $window = 600;
    $max = 30;
    $hits = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $data = json_decode($raw !== false ? $raw : '[]', true);
        if (is_array($data)) {
            foreach ($data as $t) {
                if (is_int($t) && ($now - $t) < $window) {
                    $hits[] = $t;
                }
            }
        }
    }
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}

function student_access_normalize_input(string $input): string
{
    $input = trim($input);
    if (preg_match('/^sabeel-(\d{2})-(.+)$/i', $input, $m)) {
        return strtoupper(trim($m[2]));
    }
    return strtoupper($input);
}

function student_access_pdo(): PDO
{
    $config = require dirname(__DIR__) . '/student-portal/config/config.php';
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = $host === 'localhost' || str_starts_with($host, 'localhost:')
        || $host === '127.0.0.1' || str_starts_with($host, '127.0.0.1:');
    $local = dirname(__DIR__) . '/student-portal/config/config.local.php';
    if ($isLocal && is_file($local)) {
        $config = array_merge($config, require $local);
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_port'],
        $config['db_name'],
        $config['db_charset'] ?? 'utf8mb4'
    );
    return new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function student_access_find(PDO $pdo, string $studentId): ?array
{
    if ($studentId === '' || !preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9-]{8,40}$/', $studentId)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT admin_no, roll_no, student_roll_no, s_name_e
         FROM tbl_students
         WHERE LOWER(roll_no) = LOWER(?)
         LIMIT 1'
    );
    $stmt->execute([$studentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

student_access_start_session();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    if (!empty($_SESSION['student_ok']) && !empty($_SESSION['student_id'])) {
        student_access_json([
            'ok' => true,
            'authenticated' => true,
            'student_id' => (string) $_SESSION['student_id'],
            'name' => (string) ($_SESSION['student_name'] ?? ''),
        ]);
    }
    student_access_json(['ok' => true, 'authenticated' => false]);
}

if ($method === 'POST') {
    $action = (string) ($_POST['action'] ?? 'login');
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        student_access_json(['ok' => true, 'authenticated' => false]);
    }

    if (!student_access_rate_ok(student_access_client_ip())) {
        student_access_json([
            'ok' => false,
            'error' => 'Too many attempts. Please wait a few minutes and try again.',
        ], 429);
    }

    $raw = (string) ($_POST['student_id'] ?? $_POST['q'] ?? '');
    $studentId = student_access_normalize_input($raw);
    if ($studentId === '') {
        student_access_json(['ok' => false, 'error' => 'Please enter your Student ID.'], 400);
    }

    try {
        $pdo = student_access_pdo();
        $student = student_access_find($pdo, $studentId);
    } catch (Throwable $e) {
        error_log('student-access: ' . $e->getMessage());
        student_access_json(['ok' => false, 'error' => 'Unable to verify Student ID right now.'], 503);
    }

    if (!$student) {
        student_access_json([
            'ok' => false,
            'error' => 'Student ID not found. Check the ID from your admin, or contact the office.',
        ], 401);
    }

    session_regenerate_id(true);
    $_SESSION['student_ok'] = true;
    $_SESSION['student_id'] = strtoupper(trim((string) $student['roll_no']));
    $_SESSION['student_name'] = (string) ($student['s_name_e'] ?? '');
    $_SESSION['student_admin_no'] = (int) ($student['admin_no'] ?? 0);
    $_SESSION['student_login_at'] = time();

    student_access_json([
        'ok' => true,
        'authenticated' => true,
        'student_id' => (string) $_SESSION['student_id'],
        'name' => (string) $_SESSION['student_name'],
        'results_url' => '/student-portal/public/?q=' . rawurlencode((string) $_SESSION['student_id']),
        'library_url' => '/library/',
    ]);
}

student_access_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
