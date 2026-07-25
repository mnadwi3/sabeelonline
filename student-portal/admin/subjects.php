<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();

$courses = $pdo->query('SELECT * FROM tbl_courses ORDER BY course_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['subject_id'] ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $name = trim($_POST['subject_name'] ?? '');
        $max = (int) ($_POST['max_marks'] ?? 100);
        if ($id > 0) {
            $pdo->prepare('UPDATE tbl_subjects SET course_id=?, subject_name=?, max_marks=? WHERE subject_id=?')
                ->execute([$courseId, $name, $max, $id]);
            flash('success', 'Subject updated.');
        } else {
            $pdo->prepare('INSERT INTO tbl_subjects (course_id, subject_name, max_marks, sort_order) VALUES (?,?,?,?)')
                ->execute([$courseId, $name, $max, (int) ($_POST['sort_order'] ?? 1)]);
            flash('success', 'Subject added.');
        }
        redirect('admin/subjects.php');
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM tbl_subjects WHERE subject_id=?')->execute([(int) $_POST['subject_id']]);
        flash('success', 'Subject deleted.');
        redirect('admin/subjects.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM tbl_subjects WHERE subject_id=?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$subjects = $pdo->query(
    'SELECT sub.*, c.course_name, c.month_year FROM tbl_subjects sub
     JOIN tbl_courses c ON c.course_id = sub.course_id
     ORDER BY c.course_name, sub.sort_order, sub.subject_id'
)->fetchAll();

$pageTitle = 'Subjects';
$active = 'subjects';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="card mb-2">
  <h2><?= $edit ? 'Edit Subject' : 'Add Subject' ?></h2>
  <form method="post" class="mt-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="subject_id" value="<?= (int) ($edit['subject_id'] ?? 0) ?>">
    <div class="form-grid">
      <div>
        <label>Course *</label>
        <select name="course_id" required>
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int) $c['course_id'] ?>" <?= (int) ($edit['course_id'] ?? 0) === (int) $c['course_id'] ? 'selected' : '' ?>>
              <?= e($c['course_name'] . ' (' . $c['month_year'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Subject Name *</label>
        <input name="subject_name" required placeholder="e.g. Nahw" value="<?= e($edit['subject_name'] ?? '') ?>">
      </div>
      <div>
        <label>Max Marks</label>
        <input type="number" name="max_marks" value="<?= e((string) ($edit['max_marks'] ?? 100)) ?>">
      </div>
    </div>
    <div class="actions-bar">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Update' : 'Add Subject' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(base_url('admin/subjects.php')) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2>All Subjects</h2>
  <div class="table-wrap mt-2">
    <table class="data">
      <thead><tr><th>Course</th><th>Subject</th><th>Max</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($subjects as $s): ?>
          <tr>
            <td><?= e($s['course_name'] . ' (' . $s['month_year'] . ')') ?></td>
            <td><?= e($s['subject_name']) ?></td>
            <td><?= (int) $s['max_marks'] ?></td>
            <td class="flex gap-sm">
              <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $s['subject_id'] ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Delete?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="subject_id" value="<?= (int) $s['subject_id'] ?>">
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
