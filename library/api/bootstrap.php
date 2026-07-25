<?php
/**
 * Shared helpers for Library API (server-side PDF storage).
 */
declare(strict_types=1);

session_start();

header('X-Content-Type-Options: nosniff');

const LIB_ADMIN_CODE = 'admin@sabeel';
const LIB_ADMIN_CODES = ['admin@sabeel', 'ADMIN-SABEEL'];
const LIB_MAX_PDF_BYTES = 40 * 1024 * 1024;   // 40 MB
const LIB_MAX_COVER_BYTES = 5 * 1024 * 1024;  // 5 MB

function lib_is_admin_code(string $code): bool
{
    $code = strtoupper(trim($code));
    foreach (LIB_ADMIN_CODES as $valid) {
        if ($code === strtoupper(trim($valid))) {
            return true;
        }
    }
    return $code === strtoupper(LIB_ADMIN_CODE);
}

function lib_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function lib_paths(): array
{
    $root = dirname(__DIR__);
    return [
        'root' => $root,
        'data' => $root . DIRECTORY_SEPARATOR . 'data',
        'resources' => $root . DIRECTORY_SEPARATOR . 'resources',
        'json' => $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'resources.json',
        'folders' => $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'folders.json',
        'structure' => $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'structure.json',
    ];
}

function lib_ensure_dirs(): void
{
    $p = lib_paths();
    if (!is_dir($p['data'])) {
        mkdir($p['data'], 0755, true);
    }
    if (!is_dir($p['resources'])) {
        mkdir($p['resources'], 0755, true);
    }
    if (!is_file($p['json'])) {
        file_put_contents($p['json'], "[]\n");
    }
    if (!is_file($p['folders'])) {
        file_put_contents($p['folders'], "[]\n");
    }
    if (!is_file($p['structure'])) {
        file_put_contents($p['structure'], json_encode(['courses' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }
}

function lib_read_resources(): array
{
    lib_ensure_dirs();
    $raw = file_get_contents(lib_paths()['json']);
    $data = json_decode($raw !== false ? $raw : '[]', true);
    return is_array($data) ? $data : [];
}

function lib_write_resources(array $list): bool
{
    lib_ensure_dirs();
    $json = json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(lib_paths()['json'], $json . "\n", LOCK_EX) !== false;
}

function lib_read_folders(): array
{
    lib_ensure_dirs();
    $raw = file_get_contents(lib_paths()['folders']);
    $data = json_decode($raw !== false ? $raw : '[]', true);
    return is_array($data) ? $data : [];
}

function lib_write_folders(array $list): bool
{
    lib_ensure_dirs();
    $json = json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(lib_paths()['folders'], $json . "\n", LOCK_EX) !== false;
}

function lib_slug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'folder';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'folder';
}

function lib_find_folder(string $id): ?array
{
    foreach (lib_read_folders() as $folder) {
        if (($folder['id'] ?? '') === $id) {
            return $folder;
        }
    }
    return null;
}

function lib_read_structure(): array
{
    lib_ensure_dirs();
    $raw = file_get_contents(lib_paths()['structure']);
    $data = json_decode($raw !== false ? $raw : '{"courses":[]}', true);
    if (!is_array($data)) {
        $data = ['courses' => []];
    }
    if (!isset($data['courses']) || !is_array($data['courses'])) {
        $data['courses'] = [];
    }
    return $data;
}

function lib_write_structure(array $structure): bool
{
    lib_ensure_dirs();
    if (!isset($structure['courses']) || !is_array($structure['courses'])) {
        $structure['courses'] = [];
    }
    $json = json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(lib_paths()['structure'], $json . "\n", LOCK_EX) !== false;
}

function lib_find_course(string $id): ?array
{
    foreach (lib_read_structure()['courses'] as $course) {
        if (($course['id'] ?? '') === $id) {
            return $course;
        }
    }
    return null;
}

function lib_require_admin(): void
{
    if (!empty($_SESSION['lib_admin']) && $_SESSION['lib_admin'] === true) {
        return;
    }

    $code = '';
    if (isset($_POST['admin_code'])) {
        $code = (string) $_POST['admin_code'];
    } elseif (isset($_SERVER['HTTP_X_ADMIN_CODE'])) {
        $code = (string) $_SERVER['HTTP_X_ADMIN_CODE'];
    }

    if (!lib_is_admin_code($code)) {
        lib_json(['ok' => false, 'error' => 'Admin authentication required.'], 401);
    }

    $_SESSION['lib_admin'] = true;
}

function lib_safe_filename(string $original, string $prefix, string $ext): string
{
    $base = pathinfo($original, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base) ?: 'file';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'file';
    }
    return $prefix . '-' . time() . '-' . substr($base, 0, 40) . '.' . $ext;
}

function lib_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

function lib_is_pdf(string $tmpPath): bool
{
    $fh = fopen($tmpPath, 'rb');
    if (!$fh) {
        return false;
    }
    $head = fread($fh, 5);
    fclose($fh);
    return $head === '%PDF-';
}
