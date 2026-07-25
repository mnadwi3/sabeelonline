<?php
/**
 * Public admission form submissions (Enroll Now).
 */
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const ADM_JSON = __DIR__ . '/../data/admissions/applications.json';
const ADM_UPLOADS = __DIR__ . '/../data/admissions/uploads';
const ADM_MAX_BYTES = 5 * 1024 * 1024;

function adm_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

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

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    adm_json(['ok' => false, 'error' => 'POST required.'], 405);
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$fatherName = trim((string) ($_POST['father_name'] ?? ''));
$dob = trim((string) ($_POST['dob'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$idType = trim((string) ($_POST['id_type'] ?? ''));
$course = trim((string) ($_POST['course'] ?? ''));

$allowedIdTypes = ['Aadhaar', 'Voter ID', 'Driving Licence'];

if ($fullName === '' || mb_strlen($fullName) < 2) {
    adm_json(['ok' => false, 'error' => 'Full name is required.'], 400);
}
if ($fatherName === '') {
    adm_json(['ok' => false, 'error' => "Father's name is required."], 400);
}
if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    adm_json(['ok' => false, 'error' => 'Valid date of birth is required.'], 400);
}
if ($address === '' || mb_strlen($address) < 8) {
    adm_json(['ok' => false, 'error' => 'Address is required.'], 400);
}
if (!in_array($idType, $allowedIdTypes, true)) {
    adm_json(['ok' => false, 'error' => 'Select identity proof type (Aadhaar, Voter ID, or Driving Licence).'], 400);
}
if ($course === '') {
    adm_json(['ok' => false, 'error' => 'Course is required.'], 400);
}

if (empty($_FILES['id_proof']) || !is_uploaded_file($_FILES['id_proof']['tmp_name'])) {
    adm_json(['ok' => false, 'error' => 'Please upload your identity proof.'], 400);
}

$file = $_FILES['id_proof'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    adm_json(['ok' => false, 'error' => 'Identity proof upload failed. File may be too large.'], 400);
}
if (($file['size'] ?? 0) > ADM_MAX_BYTES) {
    adm_json(['ok' => false, 'error' => 'Identity proof must be 5 MB or smaller.'], 400);
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
    adm_json(['ok' => false, 'error' => 'Identity proof must be JPG, PNG, WebP, or PDF.'], 400);
}

adm_ensure();
$filename = adm_safe_name((string) ($file['name'] ?? 'id-proof'), $extMap[$mime]);
$dest = ADM_UPLOADS . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    adm_json(['ok' => false, 'error' => 'Could not save identity proof. Check folder permissions.'], 500);
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
    adm_json(['ok' => false, 'error' => 'Could not save admission form.'], 500);
}

adm_json([
    'ok' => true,
    'message' => 'Admission form submitted successfully. Our team will contact you soon.',
    'application' => [
        'id' => $entry['id'],
        'course' => $entry['course'],
        'full_name' => $entry['full_name'],
    ],
]);
