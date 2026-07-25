<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lib_json(['ok' => false, 'error' => 'POST required.'], 405);
}

$code = isset($_POST['admin_code']) ? trim((string) $_POST['admin_code']) : '';
if (!lib_is_admin_code($code)) {
    lib_json(['ok' => false, 'error' => 'Invalid admin code.'], 401);
}

$_SESSION['lib_admin'] = true;
lib_json(['ok' => true]);
