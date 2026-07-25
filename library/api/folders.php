<?php
require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    lib_json(['ok' => true, 'folders' => lib_read_folders()]);
}

if ($method === 'POST' && ($_POST['_method'] ?? '') !== 'DELETE') {
    lib_require_admin();
    lib_ensure_dirs();

    $name = trim((string) ($_POST['name'] ?? ''));
    $courseId = trim((string) ($_POST['courseId'] ?? ''));
    $subjectId = trim((string) ($_POST['subjectId'] ?? ''));

    if ($name === '' || $courseId === '' || $subjectId === '') {
        lib_json(['ok' => false, 'error' => 'Folder name, course and subject are required.'], 400);
    }

    $id = 'fld-' . time() . '-' . substr(lib_slug($name), 0, 24);
    $folder = [
        'id' => $id,
        'name' => $name,
        'courseId' => $courseId,
        'subjectId' => $subjectId,
        'createdAt' => date('Y-m-d'),
    ];

    // Physical folder for volumes
    $dir = lib_paths()['resources'] . DIRECTORY_SEPARATOR . 'folders' . DIRECTORY_SEPARATOR . $id;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $list = lib_read_folders();
    $list[] = $folder;
    if (!lib_write_folders($list)) {
        lib_json(['ok' => false, 'error' => 'Could not save folder.'], 500);
    }

    lib_json(['ok' => true, 'folder' => $folder]);
}

if ($method === 'DELETE' || ($method === 'POST' && ($_POST['_method'] ?? '') === 'DELETE')) {
    lib_require_admin();

    $id = trim((string) ($_POST['id'] ?? ($_GET['id'] ?? '')));
    if ($id === '') {
        lib_json(['ok' => false, 'error' => 'Folder id is required.'], 400);
    }

    // Block delete if resources still use this folder
    foreach (lib_read_resources() as $res) {
        if (($res['folderId'] ?? '') === $id) {
            lib_json(['ok' => false, 'error' => 'Folder has books inside. Delete those PDFs first.'], 400);
        }
    }

    $kept = [];
    $found = false;
    foreach (lib_read_folders() as $folder) {
        if (($folder['id'] ?? '') === $id) {
            $found = true;
            continue;
        }
        $kept[] = $folder;
    }
    if (!$found) {
        lib_json(['ok' => false, 'error' => 'Folder not found.'], 404);
    }

    lib_write_folders($kept);
    lib_json(['ok' => true]);
}

lib_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
