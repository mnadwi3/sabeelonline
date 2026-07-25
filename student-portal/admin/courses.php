<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();
$hasTitle = has_marksheet_title_column($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['course_id'] ?? 0);
        $name = trim($_POST['course_name'] ?? '');
        $title = trim($_POST['marksheet_title'] ?? '');
        $my = trim($_POST['month_year'] ?? '');
        if ($name === '' || $my === '') {
            flash('error', 'Course / Batch name and Month/Year are required.');
            redirect('admin/courses.php');
        }
        try {
            if ($id > 0) {
                if ($hasTitle) {
                    $pdo->prepare(
                        'UPDATE tbl_courses SET course_name=?, marksheet_title=?, month_year=? WHERE course_id=?'
                    )->execute([$name, $title !== '' ? $title : null, $my, $id]);
                } else {
                    $pdo->prepare(
                        'UPDATE tbl_courses SET course_name=?, month_year=? WHERE course_id=?'
                    )->execute([$name, $my, $id]);
                }
                flash('success', 'Course updated.');
            } else {
                if ($hasTitle) {
                    $pdo->prepare(
                        'INSERT INTO tbl_courses (course_name, marksheet_title, month_year) VALUES (?,?,?)'
                    )->execute([$name, $title !== '' ? $title : null, $my]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO tbl_courses (course_name, month_year) VALUES (?,?)'
                    )->execute([$name, $my]);
                }
                flash('success', 'Course added.');
            }
        } catch (Throwable $e) {
            flash('error', 'Could not save course. Check database permissions.');
        }
        redirect('admin/courses.php');
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM tbl_courses WHERE course_id=?')->execute([(int) $_POST['course_id']]);
        flash('success', 'Course deleted.');
        redirect('admin/courses.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM tbl_courses WHERE course_id=?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$courses = $pdo->query('SELECT * FROM tbl_courses ORDER BY course_id DESC')->fetchAll();

$pageTitle = 'Courses';
$active = 'courses';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="card mb-2">
  <h2><?= $edit ? 'Edit Course' : 'Add Course' ?></h2>
  <p class="muted">Use a batch name for admin lists. Set a professional marksheet title for the printed result.</p>
  <?php if (!$hasTitle): ?>
    <div class="alert alert-error">
      Marksheet title column is missing. In Hostinger phpMyAdmin run:
      <code>ALTER TABLE tbl_courses ADD COLUMN marksheet_title VARCHAR(200) NULL AFTER course_name;</code>
    </div>
  <?php endif; ?>
  <form method="post" class="mt-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="course_id" value="<?= (int) ($edit['course_id'] ?? 0) ?>">
    <div class="form-grid">
      <div>
        <label>Course / Batch name (admin) *</label>
        <input name="course_name" required placeholder="e.g. Short Term Alimiyyat Batch 4"
               value="<?= e($edit['course_name'] ?? '') ?>">
      </div>
      <div>
        <label>Marksheet title (printed)</label>
        <input name="marksheet_title" placeholder="e.g. Diploma In Short Term Alimiyyat"
               value="<?= e($edit['marksheet_title'] ?? '') ?>" <?= $hasTitle ? '' : 'disabled' ?>>
      </div>
      <div>
        <label>Month / Year *</label>
        <input name="month_year" required placeholder="e.g. March 2024"
               value="<?= e($edit['month_year'] ?? '') ?>">
      </div>
    </div>
    <div class="actions-bar">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Update' : 'Save Course' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(base_url('admin/courses.php')) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2>All Courses</h2>
  <div class="table-wrap mt-2">
    <table class="data">
      <thead>
        <tr>
          <th>#</th>
          <th>Course / Batch</th>
          <th>Marksheet title</th>
          <th>Month / Year</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td><?= (int) $c['course_id'] ?></td>
            <td><?= e($c['course_name']) ?></td>
            <td><?= e(($c['marksheet_title'] ?? '') !== '' ? $c['marksheet_title'] : '—') ?></td>
            <td><?= e($c['month_year']) ?></td>
            <td class="flex gap-sm">
              <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $c['course_id'] ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Delete this course?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="course_id" value="<?= (int) $c['course_id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
