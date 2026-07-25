<?php
/**
 * Public admission submissions + admin list/view of applications.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/library/api/bootstrap.php';

const ADM_JSON = __DIR__ . '/../data/admissions/applications.json';
const ADM_UPLOADS = __DIR__ . '/../data/admissions/uploads';
const ADM_MAX_BYTES = 5 * 1024 * 1024;
const ADM_ROOT = __DIR__ . '/..';

function adm_ensure(): void
{
    $dir = dirname(ADM_JSON);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!is_dir(ADM_UPLOADS)) {
        mkdir(ADM_UPLOADS, 0755, true);
    }
    if (!is_file(ADM_JSON)) {
        file_put_contents(ADM_JSON, "[]\n", LOCK_EX);
    }
    $deny = ADM_UPLOADS . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($deny)) {
        file_put_contents($deny, "Require all denied\n");
    }
}

function adm_read(): array
{
    adm_ensure();
    $raw = file_get_contents(ADM_JSON);
    $data = json_decode($raw !== false ? $raw : '[]', true);
    return is_array($data) ? $data : [];
}

function adm_write(array $list): bool
{
    adm_ensure();
    $json = json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(ADM_JSON, $json . "\n", LOCK_EX) !== false;
}

function adm_find(string $id): ?array
{
    foreach (adm_read() as $row) {
        if (($row['id'] ?? '') === $id) {
            return $row;
        }
    }
    return null;
}

function adm_safe_name(string $original, string $ext): string
{
    $base = pathinfo($original, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base) ?: 'id-proof';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'id-proof';
    }
    return 'id-' . time() . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '-' . substr($base, 0, 30) . '.' . $ext;
}

function adm_proof_path(array $row): ?string
{
    $rel = str_replace('\\', '/', (string) ($row['id_proof'] ?? ''));
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    if (!str_starts_with($rel, 'data/admissions/uploads/')) {
        return null;
    }
    $abs = realpath(ADM_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    $uploadsRoot = realpath(ADM_UPLOADS);
    if (!$abs || !$uploadsRoot || !str_starts_with($abs, $uploadsRoot) || !is_file($abs)) {
        return null;
    }
    return $abs;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

/* ---------- Admin: list ---------- */
if ($method === 'GET' && ($action === '' || $action === 'list')) {
    lib_require_admin();
    lib_json(['ok' => true, 'applications' => adm_read()]);
}

/* ---------- Admin: download identity proof ---------- */
if ($method === 'GET' && $action === 'file') {
    lib_require_admin();
    $id = trim((string) ($_GET['id'] ?? ''));
    $row = $id !== '' ? adm_find($id) : null;
    if (!$row) {
        lib_json(['ok' => false, 'error' => 'Application not found.'], 404);
    }
    $path = adm_proof_path($row);
    if (!$path) {
        lib_json(['ok' => false, 'error' => 'Identity proof file missing.'], 404);
    }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    $downloadName = basename($path);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

/* ---------- Admin: update status / delete ---------- */
if ($method === 'POST' && in_array($action, ['set_status', 'delete'], true)) {
    lib_require_admin();
    $id = trim((string) ($_POST['id'] ?? ''));
    if ($id === '') {
        lib_json(['ok' => false, 'error' => 'Application id is required.'], 400);
    }
    $list = adm_read();
    $found = false;
    foreach ($list as $i => $row) {
        if (($row['id'] ?? '') !== $id) {
            continue;
        }
        $found = true;
        if ($action === 'delete') {
            $path = adm_proof_path($row);
            if ($path) {
                @unlink($path);
            }
            unset($list[$i]);
        } else {
            $status = strtolower(trim((string) ($_POST['status'] ?? '')));
            if (!in_array($status, ['new', 'reviewed', 'accepted', 'rejected'], true)) {
                lib_json(['ok' => false, 'error' => 'Invalid status.'], 400);
            }
            $list[$i]['status'] = $status;
            $list[$i]['updated_at'] = gmdate('c');
        }
        break;
    }
    if (!$found) {
        lib_json(['ok' => false, 'error' => 'Application not found.'], 404);
    }
    if (!adm_write(array_values($list))) {
        lib_json(['ok' => false, 'error' => 'Could not update applications.'], 500);
    }
    lib_json(['ok' => true, 'applications' => adm_read()]);
}

/* ---------- Public submit ---------- */
if ($method !== 'POST' || $action !== '') {
    lib_json(['ok' => false, 'error' => 'Method or action not allowed.'], 405);
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$fatherName = trim((string) ($_POST['father_name'] ?? ''));
$dob = trim((string) ($_POST['dob'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$idType = trim((string) ($_POST['id_type'] ?? ''));
$course = trim((string) ($_POST['course'] ?? ''));

$allowedIdTypes = ['Aadhaar', 'Voter ID', 'Driving Licence'];

$strLen = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
};

if ($fullName === '' || $strLen($fullName) < 2) {
    lib_json(['ok' => false, 'error' => 'Full name is required.'], 400);
}
if ($fatherName === '') {
    lib_json(['ok' => false, 'error' => "Father's name is required."], 400);
}
if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    lib_json(['ok' => false, 'error' => 'Valid date of birth is required.'], 400);
}
if ($address === '' || $strLen($address) < 8) {
    lib_json(['ok' => false, 'error' => 'Address is required.'], 400);
}
if (!in_array($idType, $allowedIdTypes, true)) {
    lib_json(['ok' => false, 'error' => 'Select identity proof type (Aadhaar, Voter ID, or Driving Licence).'], 400);
}
if ($course === '') {
    lib_json(['ok' => false, 'error' => 'Course is required.'], 400);
}

if (empty($_FILES['id_proof']) || !is_uploaded_file($_FILES['id_proof']['tmp_name'])) {
    lib_json(['ok' => false, 'error' => 'Please upload your identity proof.'], 400);
}

$file = $_FILES['id_proof'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    lib_json(['ok' => false, 'error' => 'Identity proof upload failed. File may be too large.'], 400);
}
if (($file['size'] ?? 0) > ADM_MAX_BYTES) {
    lib_json(['ok' => false, 'error' => 'Identity proof must be 5 MB or smaller.'], 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($file['tmp_name']);
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];
if (!isset($extMap[$mime])) {
    lib_json(['ok' => false, 'error' => 'Identity proof must be JPG, PNG, WebP, or PDF.'], 400);
}

adm_ensure();
$filename = adm_safe_name((string) ($file['name'] ?? 'id-proof'), $extMap[$mime]);
$dest = ADM_UPLOADS . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    lib_json(['ok' => false, 'error' => 'Could not save identity proof. Check folder permissions.'], 500);
}

$entry = [
    'id' => 'adm-' . time() . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
    'course' => $course,
    'full_name' => $fullName,
    'father_name' => $fatherName,
    'dob' => $dob,
    'address' => $address,
    'id_type' => $idType,
    'id_proof' => 'data/admissions/uploads/' . $filename,
    'created_at' => gmdate('c'),
    'status' => 'new',
];

$list = adm_read();
array_unshift($list, $entry);
if (!adm_write($list)) {
    @unlink($dest);
    lib_json(['ok' => false, 'error' => 'Could not save admission form.'], 500);
}

lib_json([
    'ok' => true,
    'message' => 'Admission form submitted successfully. Our team will contact you soon.',
    'application' => [
        'id' => $entry['id'],
        'course' => $entry['course'],
        'full_name' => $entry['full_name'],
    ],
]);
