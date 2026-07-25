<?php
require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string) ($_POST['action'] ?? ($_GET['action'] ?? '')));

if ($method === 'GET') {
    $structure = lib_read_structure();
    lib_json(['ok' => true, 'courses' => array_values($structure['courses'])]);
}

if ($method === 'POST') {
    lib_require_admin();
    lib_ensure_dirs();

    /* -------- Create course -------- */
    if ($action === 'create_course') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? '#0B5ED7'));
        if ($name === '') {
            lib_json(['ok' => false, 'error' => 'Course name is required.'], 400);
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#0B5ED7';
        }

        $id = 'crs-' . time() . '-' . substr(lib_slug($name), 0, 28);
        $course = [
            'id' => $id,
            'name' => $name,
            'short' => $name,
            'color' => $color,
            'description' => '',
            'subjects' => [],
        ];

        $structure = lib_read_structure();
        $structure['courses'][] = $course;
        if (!lib_write_structure($structure)) {
            lib_json(['ok' => false, 'error' => 'Could not save course.'], 500);
        }
        lib_json(['ok' => true, 'course' => $course]);
    }

    /* -------- Delete course -------- */
    if ($action === 'delete_course' || ($action === '' && ($_POST['_method'] ?? '') === 'DELETE' && isset($_POST['courseId']))) {
        $courseId = trim((string) ($_POST['courseId'] ?? $_POST['id'] ?? ''));
        if ($courseId === '') {
            lib_json(['ok' => false, 'error' => 'Course id is required.'], 400);
        }

        foreach (lib_read_resources() as $res) {
            if (($res['courseId'] ?? '') === $courseId) {
                lib_json(['ok' => false, 'error' => 'Course has PDFs. Delete those books first.'], 400);
            }
        }
        foreach (lib_read_folders() as $folder) {
            if (($folder['courseId'] ?? '') === $courseId) {
                lib_json(['ok' => false, 'error' => 'Course has book folders. Delete those first.'], 400);
            }
        }

        $structure = lib_read_structure();
        $kept = [];
        $found = false;
        foreach ($structure['courses'] as $course) {
            if (($course['id'] ?? '') === $courseId) {
                $found = true;
                continue;
            }
            $kept[] = $course;
        }
        if (!$found) {
            lib_json(['ok' => false, 'error' => 'Course not found.'], 404);
        }
        $structure['courses'] = $kept;
        lib_write_structure($structure);
        lib_json(['ok' => true]);
    }

    /* -------- Create subject -------- */
    if ($action === 'create_subject') {
        $courseId = trim((string) ($_POST['courseId'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($courseId === '' || $name === '') {
            lib_json(['ok' => false, 'error' => 'Course and subject name are required.'], 400);
        }

        $structure = lib_read_structure();
        $found = false;
        $subject = null;
        foreach ($structure['courses'] as &$course) {
            if (($course['id'] ?? '') !== $courseId) {
                continue;
            }
            $found = true;
            if (!isset($course['subjects']) || !is_array($course['subjects'])) {
                $course['subjects'] = [];
            }
            $subject = [
                'id' => 'sub-' . time() . '-' . substr(lib_slug($name), 0, 28),
                'name' => $name,
            ];
            $course['subjects'][] = $subject;
            break;
        }
        unset($course);

        if (!$found) {
            lib_json(['ok' => false, 'error' => 'Course not found.'], 404);
        }
        if (!lib_write_structure($structure)) {
            lib_json(['ok' => false, 'error' => 'Could not save subject.'], 500);
        }
        lib_json(['ok' => true, 'subject' => $subject, 'courseId' => $courseId]);
    }

    /* -------- Delete subject -------- */
    if ($action === 'delete_subject') {
        $courseId = trim((string) ($_POST['courseId'] ?? ''));
        $subjectId = trim((string) ($_POST['subjectId'] ?? ''));
        if ($courseId === '' || $subjectId === '') {
            lib_json(['ok' => false, 'error' => 'Course and subject id are required.'], 400);
        }

        foreach (lib_read_resources() as $res) {
            if (($res['courseId'] ?? '') === $courseId && ($res['subjectId'] ?? '') === $subjectId) {
                lib_json(['ok' => false, 'error' => 'Subject has PDFs. Delete those books first.'], 400);
            }
        }
        foreach (lib_read_folders() as $folder) {
            if (($folder['courseId'] ?? '') === $courseId && ($folder['subjectId'] ?? '') === $subjectId) {
                lib_json(['ok' => false, 'error' => 'Subject has book folders. Delete those first.'], 400);
            }
        }

        $structure = lib_read_structure();
        $foundCourse = false;
        $foundSubject = false;
        foreach ($structure['courses'] as &$course) {
            if (($course['id'] ?? '') !== $courseId) {
                continue;
            }
            $foundCourse = true;
            $kept = [];
            foreach (($course['subjects'] ?? []) as $subject) {
                if (($subject['id'] ?? '') === $subjectId) {
                    $foundSubject = true;
                    continue;
                }
                $kept[] = $subject;
            }
            $course['subjects'] = $kept;
            break;
        }
        unset($course);

        if (!$foundCourse) {
            lib_json(['ok' => false, 'error' => 'Course not found.'], 404);
        }
        if (!$foundSubject) {
            lib_json(['ok' => false, 'error' => 'Subject not found.'], 404);
        }
        lib_write_structure($structure);
        lib_json(['ok' => true]);
    }

    lib_json(['ok' => false, 'error' => 'Unknown action.'], 400);
}

lib_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
