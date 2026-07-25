<?php
/**
 * Public GET + admin POST for homepage marketing courses.
 * Reuses Library admin codes (library/api/bootstrap.php).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/library/api/bootstrap.php';

const WEB_COURSES_FILE = __DIR__ . '/../data/website-courses.json';
const WEB_WHATSAPP = '918979983149';

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

    $enroll = trim((string) ($input['whatsappEnrollText'] ?? ''));
    $waitlist = trim((string) ($input['whatsappWaitlistText'] ?? ''));
    if ($enroll === '') {
        $enroll = 'Assalamu Alaikum, I want to enroll in ' . $name . '.';
    }
    if ($waitlist === '') {
        $waitlist = 'Assalamu Alaikum, please notify me when ' . $name . ' registration opens.';
    }

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
