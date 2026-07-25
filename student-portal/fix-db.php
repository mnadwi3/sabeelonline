<?php
/**
 * One-time DB fix — open once, then DELETE this file.
 * Adds marksheet_title column if missing.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

try {
    require_once __DIR__ . '/includes/bootstrap.php';
    $pdo = db();
    echo "DB connected OK\n";

    $cols = $pdo->query('SHOW COLUMNS FROM tbl_courses LIKE "marksheet_title"')->fetch();
    if ($cols) {
        echo "OK: marksheet_title column already exists\n";
    } else {
        $pdo->exec(
            'ALTER TABLE tbl_courses
             ADD COLUMN marksheet_title VARCHAR(200) NULL DEFAULT NULL
             AFTER course_name'
        );
        echo "OK: marksheet_title column added\n";
    }

    $n = (int) $pdo->query('SELECT COUNT(*) FROM tbl_users')->fetchColumn();
    echo "OK: tbl_users rows = {$n}\n";
    echo "\nDelete fix-db.php from the server now.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "FAIL: " . $e->getMessage() . "\n";
    echo "Check config/config.php db_pass and delete config/config.local.php on Hostinger if present.\n";
}
