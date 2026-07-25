<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lib_json(['ok' => false, 'error' => 'POST required.'], 405);
}

lib_require_admin();
lib_ensure_dirs();

$title = trim((string) ($_POST['title'] ?? ''));
$courseId = trim((string) ($_POST['courseId'] ?? ''));
$subjectId = trim((string) ($_POST['subjectId'] ?? ($_POST['category'] ?? '')));
$description = trim((string) ($_POST['description'] ?? ''));
$folderId = trim((string) ($_POST['folderId'] ?? ''));

if ($title === '' || $courseId === '' || $subjectId === '') {
    lib_json(['ok' => false, 'error' => 'Title, course folder and subject folder are required.'], 400);
}

if ($folderId !== '') {
    $folder = lib_find_folder($folderId);
    if ($folder === null) {
        lib_json(['ok' => false, 'error' => 'Selected book folder was not found.'], 400);
    }
    if (($folder['courseId'] ?? '') !== $courseId || ($folder['subjectId'] ?? '') !== $subjectId) {
        lib_json(['ok' => false, 'error' => 'Book folder does not match the selected course/subject.'], 400);
    }
}

if (empty($_FILES['pdf']) || !is_uploaded_file($_FILES['pdf']['tmp_name'])) {
    lib_json(['ok' => false, 'error' => 'Please choose a PDF file.'], 400);
}

$pdf = $_FILES['pdf'];
if (($pdf['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    lib_json(['ok' => false, 'error' => 'PDF upload failed. File may be too large for the server limit.'], 400);
}

if (($pdf['size'] ?? 0) > LIB_MAX_PDF_BYTES) {
    lib_json(['ok' => false, 'error' => 'PDF is too large. Maximum allowed size is 40 MB.'], 400);
}

if (!lib_is_pdf($pdf['tmp_name'])) {
    lib_json(['ok' => false, 'error' => 'Only valid PDF files are allowed.'], 400);
}

$paths = lib_paths();
$pdfName = lib_safe_filename((string) $pdf['name'], 'book', 'pdf');

if ($folderId !== '') {
    $folderDir = $paths['resources'] . DIRECTORY_SEPARATOR . 'folders' . DIRECTORY_SEPARATOR . $folderId;
    if (!is_dir($folderDir)) {
        mkdir($folderDir, 0755, true);
    }
    $pdfDest = $folderDir . DIRECTORY_SEPARATOR . $pdfName;
    $pdfUrl = 'resources/folders/' . $folderId . '/' . $pdfName;
} else {
    $pdfDest = $paths['resources'] . DIRECTORY_SEPARATOR . $pdfName;
    $pdfUrl = 'resources/' . $pdfName;
}

if (!move_uploaded_file($pdf['tmp_name'], $pdfDest)) {
    lib_json(['ok' => false, 'error' => 'Could not save PDF on the server.'], 500);
}

$coverUrl = '';
if (!empty($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
    $cover = $_FILES['cover'];
    if (($cover['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        if (($cover['size'] ?? 0) > LIB_MAX_COVER_BYTES) {
            @unlink($pdfDest);
            lib_json(['ok' => false, 'error' => 'Cover image is too large. Maximum 5 MB.'], 400);
        }

        $info = @getimagesize($cover['tmp_name']);
        if ($info === false) {
            @unlink($pdfDest);
            lib_json(['ok' => false, 'error' => 'Cover must be a valid image (JPG/PNG/WebP).'], 400);
        }

        $mime = $info['mime'] ?? '';
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extMap[$mime])) {
            @unlink($pdfDest);
            lib_json(['ok' => false, 'error' => 'Cover must be JPG, PNG or WebP.'], 400);
        }

        $coverName = lib_safe_filename((string) $cover['name'], 'cover', $extMap[$mime]);
        $coverDest = $paths['resources'] . DIRECTORY_SEPARATOR . $coverName;
        if (!move_uploaded_file($cover['tmp_name'], $coverDest)) {
            @unlink($pdfDest);
            lib_json(['ok' => false, 'error' => 'Could not save cover image.'], 500);
        }
        $coverUrl = 'resources/' . $coverName;
    }
}

$resource = [
    'id' => 'srv-' . time() . '-' . bin2hex(random_bytes(3)),
    'title' => $title,
    'courseId' => $courseId,
    'subjectId' => $subjectId,
    'folderId' => $folderId !== '' ? $folderId : null,
    'description' => $description,
    'fileSize' => lib_format_bytes((int) $pdf['size']),
    'updatedAt' => date('Y-m-d'),
    'cover' => $coverUrl,
    'fileUrl' => $pdfUrl,
    'type' => 'pdf',
];

$list = lib_read_resources();
$list[] = $resource;

if (!lib_write_resources($list)) {
    @unlink($pdfDest);
    if ($coverUrl !== '') {
        @unlink($paths['resources'] . DIRECTORY_SEPARATOR . basename($coverUrl));
    }
    lib_json(['ok' => false, 'error' => 'Could not update resources catalogue.'], 500);
}

lib_json(['ok' => true, 'resource' => $resource]);
