<?php
require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    lib_json(['ok' => true, 'resources' => lib_read_resources()]);
}

if ($method === 'DELETE' || ($method === 'POST' && ($_POST['_method'] ?? '') === 'DELETE')) {
    lib_require_admin();

    $id = '';
    if ($method === 'DELETE') {
        parse_str(file_get_contents('php://input') ?: '', $body);
        $id = trim((string) ($body['id'] ?? ($_GET['id'] ?? '')));
    } else {
        $id = trim((string) ($_POST['id'] ?? ''));
    }

    if ($id === '') {
        lib_json(['ok' => false, 'error' => 'Resource id is required.'], 400);
    }

    $list = lib_read_resources();
    $kept = [];
    $removed = null;
    foreach ($list as $item) {
        if (($item['id'] ?? '') === $id) {
            $removed = $item;
            continue;
        }
        $kept[] = $item;
    }

    if ($removed === null) {
        lib_json(['ok' => false, 'error' => 'Resource not found.'], 404);
    }

    $paths = lib_paths();
    foreach (['fileUrl', 'cover'] as $key) {
        $rel = (string) ($removed[$key] ?? '');
        if ($rel !== '' && strpos($rel, 'resources/') === 0) {
            $full = $paths['root'] . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    lib_write_resources($kept);
    lib_json(['ok' => true]);
}

lib_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
