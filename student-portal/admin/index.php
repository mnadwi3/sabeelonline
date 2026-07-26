<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();

$stats = [
    'students' => (int) $pdo->query('SELECT COUNT(*) FROM tbl_students')->fetchColumn(),
    'courses'  => (int) $pdo->query('SELECT COUNT(*) FROM tbl_courses')->fetchColumn(),
    'results'  => (int) $pdo->query('SELECT COUNT(*) FROM tbl_results')->fetchColumn(),
    'subjects' => (int) $pdo->query('SELECT COUNT(*) FROM tbl_subjects')->fetchColumn(),
    'passed'   => (int) $pdo->query("SELECT COUNT(*) FROM tbl_results WHERE result_status = 'Pass'")->fetchColumn(),
    'failed'   => (int) $pdo->query("SELECT COUNT(*) FROM tbl_results WHERE result_status = 'Fail'")->fetchColumn(),
];

$latest = $pdo->query(
    "SELECT r.id, r.roll_no, r.percentage, r.grade, r.result_status, r.is_published,
            r.semester, s.s_name_e, s.student_roll_no, c.course_name
     FROM tbl_results r
     LEFT JOIN tbl_students s ON s.admin_no = r.admin_no
     LEFT JOIN tbl_courses c ON c.course_id = r.course_id
     ORDER BY r.updated_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="stat-grid">
  <div class="stat-card"><div class="label">Students</div><div class="value"><?= $stats['students'] ?></div></div>
  <div class="stat-card emerald"><div class="label">Courses</div><div class="value"><?= $stats['courses'] ?></div></div>
  <div class="stat-card"><div class="label">Results</div><div class="value"><?= $stats['results'] ?></div></div>
  <div class="stat-card gold"><div class="label">Subjects</div><div class="value"><?= $stats['subjects'] ?></div></div>
  <div class="stat-card emerald"><div class="label">Passed</div><div class="value"><?= $stats['passed'] ?></div></div>
  <div class="stat-card danger"><div class="label">Failed</div><div class="value"><?= $stats['failed'] ?></div></div>
</div>

<div class="card">
  <div class="flex between align-center flex-wrap mb-2">
    <h2>Recent Results</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('admin/marks.php')) ?>">Enter Marks</a>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Roll No</th><th>Student ID</th><th>Name</th><th>Course</th><th>Semester</th><th>%</th><th>Grade</th><th>Result</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$latest): ?>
          <tr><td colspan="9" class="muted">No results yet. Add a student, then enter marks.</td></tr>
        <?php endif; ?>
        <?php foreach ($latest as $row): ?>
          <tr>
            <td><?= e($row['student_roll_no'] ?? '—') ?></td>
            <td><strong><?= e($row['roll_no']) ?></strong></td>
            <td><?= e($row['s_name_e'] ?? '—') ?></td>
            <td><?= e($row['course_name'] ?? '—') ?></td>
            <td><?= e($row['semester'] ?? '—') ?></td>
            <td><?= e(format_percentage_display($row['percentage'])) ?></td>
            <td><?= e($row['grade']) ?></td>
            <td><span class="badge <?= $row['result_status'] === 'Pass' ? 'badge-pass' : 'badge-fail' ?>"><?= e($row['result_status']) ?></span></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(base_url('admin/marksheet.php?id=' . $row['id'])) ?>">Marksheet</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
