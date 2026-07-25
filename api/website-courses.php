<?php
/**
 * Public GET + admin POST for homepage marketing courses.
 * Reuses Library admin codes (library/api/bootstrap.php).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/library/api/bootstrap.php';

const WEB_COURSES_FILE = __DIR__ . '/../data/website-courses.json';
const WEB_ASSETS_DIR = __DIR__ . '/../assets/courses';
const WEB_WHATSAPP = '918979983149';
const WEB_MAX_IMAGE_BYTES = 5 * 1024 * 1024;

function web_courses_ensure(): void
{
    $dir = dirname(WEB_COURSES_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!is_file(WEB_COURSES_FILE)) {
        file_put_contents(
            WEB_COURSES_FILE,
            json_encode(['courses' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            LOCK_EX
        );
    }
}

function web_courses_read(): array
{
    web_courses_ensure();
    $raw = file_get_contents(WEB_COURSES_FILE);
    $data = json_decode($raw !== false ? $raw : '{"courses":[]}', true);
    if (!is_array($data)) {
        $data = ['courses' => []];
    }
    if (!isset($data['courses']) || !is_array($data['courses'])) {
        $data['courses'] = [];
    }
    return $data;
}

function web_courses_write(array $data): bool
{
    web_courses_ensure();
    if (!isset($data['courses']) || !is_array($data['courses'])) {
        $data['courses'] = [];
    }
    $data['courses'] = array_values($data['courses']);
    usort($data['courses'], static function ($a, $b) {
        return ((int) ($a['sortOrder'] ?? 0)) <=> ((int) ($b['sortOrder'] ?? 0));
    });
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(WEB_COURSES_FILE, $json . "\n", LOCK_EX) !== false;
}

function web_courses_normalize(array $input, ?string $existingId = null): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        lib_json(['ok' => false, 'error' => 'Course name is required.'], 400);
    }

    $registration = strtolower(trim((string) ($input['registration'] ?? 'closed')));
    if ($registration !== 'open' && $registration !== 'closed') {
        $registration = 'closed';
    }

    $id = $existingId ?: trim((string) ($input['id'] ?? ''));
    if ($id === '') {
        $id = 'web-' . lib_slug($name) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    // CTA + WhatsApp copy follow registration status automatically.
    $enroll = 'Assalamu Alaikum, I want to enroll in ' . $name . '.';
    $waitlist = 'Assalamu Alaikum, please notify me when ' . $name . ' registration opens.';

    $image = trim((string) ($input['image'] ?? 'assets/personal.png'));
    if ($image === '') {
        $image = 'assets/personal.png';
    }

    return [
        'id' => $id,
        'name' => $name,
        'description' => trim((string) ($input['description'] ?? '')),
        'image' => $image,
        'registration' => $registration,
        'duration' => trim((string) ($input['duration'] ?? '')),
        'classDays' => trim((string) ($input['classDays'] ?? '')),
        'fee' => trim((string) ($input['fee'] ?? '')),
        'sortOrder' => (int) ($input['sortOrder'] ?? 0),
        'whatsappEnrollText' => $enroll,
        'whatsappWaitlistText' => $waitlist,
        'whatsapp' => WEB_WHATSAPP,
    ];
}

function web_courses_upload_image(): array
{
    if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        lib_json(['ok' => false, 'error' => 'Please choose an image file.'], 400);
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        lib_json(['ok' => false, 'error' => 'Image upload failed. File may be too large.'], 400);
    }
    if (($file['size'] ?? 0) > WEB_MAX_IMAGE_BYTES) {
        lib_json(['ok' => false, 'error' => 'Image is too large. Maximum allowed size is 5 MB.'], 400);
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        lib_json(['ok' => false, 'error' => 'Upload a valid image (JPG, PNG, or WebP).'], 400);
    }

    $mime = (string) ($info['mime'] ?? '');
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extMap[$mime])) {
        lib_json(['ok' => false, 'error' => 'Image must be JPG, PNG, or WebP.'], 400);
    }

    if (!is_dir(WEB_ASSETS_DIR) && !mkdir(WEB_ASSETS_DIR, 0755, true) && !is_dir(WEB_ASSETS_DIR)) {
        lib_json(['ok' => false, 'error' => 'Could not create assets/courses folder.'], 500);
    }

    $filename = lib_safe_filename((string) ($file['name'] ?? 'course'), 'course', $extMap[$mime]);
    $dest = WEB_ASSETS_DIR . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        lib_json(['ok' => false, 'error' => 'Could not save image to assets.'], 500);
    }

    return [
        'ok' => true,
        'image' => 'assets/courses/' . $filename,
        'url' => 'assets/courses/' . $filename,
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $data = web_courses_read();
    lib_json(['ok' => true, 'courses' => $data['courses'], 'whatsapp' => WEB_WHATSAPP]);
}

if ($method !== 'POST') {
    lib_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

lib_require_admin();

$action = trim((string) ($_POST['action'] ?? ''));

if ($action === 'upload_image') {
    lib_json(web_courses_upload_image());
}

$payloadRaw = (string) ($_POST['course'] ?? '');
$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : null;
if (!is_array($payload)) {
    $payload = $_POST;
}

$data = web_courses_read();
$courses = $data['courses'];

if ($action === 'save') {
    $id = trim((string) ($payload['id'] ?? ''));
    $normalized = web_courses_normalize($payload, $id !== '' ? $id : null);
    $found = false;
    foreach ($courses as $i => $course) {
        if (($course['id'] ?? '') === $normalized['id']) {
            $courses[$i] = $normalized;
            $found = true;
            break;
        }
    }
    if (!$found) {
        if ($normalized['sortOrder'] <= 0) {
            $max = 0;
            foreach ($courses as $course) {
                $max = max($max, (int) ($course['sortOrder'] ?? 0));
            }
            $normalized['sortOrder'] = $max + 1;
        }
        $courses[] = $normalized;
    }
    $data['courses'] = $courses;
    if (!web_courses_write($data)) {
        lib_json(['ok' => false, 'error' => 'Could not save courses file. Check folder permissions.'], 500);
    }
    lib_json(['ok' => true, 'course' => $normalized, 'courses' => web_courses_read()['courses']]);
}

if ($action === 'delete') {
    $id = trim((string) ($payload['id'] ?? $_POST['id'] ?? ''));
    if ($id === '') {
        lib_json(['ok' => false, 'error' => 'Course id is required.'], 400);
    }
    $before = count($courses);
    $courses = array_values(array_filter($courses, static fn($c) => ($c['id'] ?? '') !== $id));
    if (count($courses) === $before) {
        lib_json(['ok' => false, 'error' => 'Course not found.'], 404);
    }
    $data['courses'] = $courses;
    if (!web_courses_write($data)) {
        lib_json(['ok' => false, 'error' => 'Could not save courses file. Check folder permissions.'], 500);
    }
    lib_json(['ok' => true, 'courses' => $courses]);
}

if ($action === 'reorder') {
    $orderRaw = (string) ($_POST['order'] ?? '');
    $order = json_decode($orderRaw, true);
    if (!is_array($order)) {
        lib_json(['ok' => false, 'error' => 'Invalid order payload.'], 400);
    }
    $map = [];
    foreach ($courses as $course) {
        $map[$course['id'] ?? ''] = $course;
    }
    $next = [];
    $n = 1;
    foreach ($order as $id) {
        $id = (string) $id;
        if (isset($map[$id])) {
            $map[$id]['sortOrder'] = $n++;
            $next[] = $map[$id];
            unset($map[$id]);
        }
    }
    foreach ($map as $leftover) {
        $leftover['sortOrder'] = $n++;
        $next[] = $leftover;
    }
    $data['courses'] = $next;
    if (!web_courses_write($data)) {
        lib_json(['ok' => false, 'error' => 'Could not save courses file. Check folder permissions.'], 500);
    }
    lib_json(['ok' => true, 'courses' => web_courses_read()['courses']]);
}

lib_json(['ok' => false, 'error' => 'Unknown action.'], 400);
