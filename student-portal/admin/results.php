<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'publish') {
        $pdo->prepare('UPDATE tbl_results SET is_published=1 WHERE id=?')->execute([$id]);
        flash('success', 'Published.');
    } elseif ($action === 'hide') {
        $pdo->prepare('UPDATE tbl_results SET is_published=0 WHERE id=?')->execute([$id]);
        flash('success', 'Hidden.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM tbl_results WHERE id=?')->execute([$id]);
        flash('success', 'Deleted.');
    }
    redirect('admin/results.php');
}

$results = $pdo->query(
    "SELECT r.*, s.s_name_e, c.course_name, c.month_year
     FROM tbl_results r
     LEFT JOIN tbl_students s ON s.admin_no = r.admin_no
     LEFT JOIN tbl_courses c ON c.course_id = r.course_id
     ORDER BY r.updated_at DESC"
)->fetchAll();

$pageTitle = 'Results';
$active = 'results';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="card">
  <div class="flex between align-center flex-wrap mb-2">
    <h2>All Results</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('admin/marks.php')) ?>">Enter Marks</a>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Roll</th><th>Name</th><th>Course</th><th>Semester</th><th>Year</th>
          <th>Total</th><th>%</th><th>Grade</th><th>Result</th><th>Portal</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><?= e($r['roll_no']) ?></td>
            <td><?= e($r['s_name_e'] ?? '—') ?></td>
            <td><?= e($r['course_name'] ?? '—') ?></td>
            <td><?= e($r['semester'] ?? '—') ?></td>
            <td><?= e($r['semester_year'] ?? '—') ?></td>
            <td><?= e($r['grand_total'] . '/' . $r['max_total']) ?></td>
            <td><?= e((string) $r['percentage']) ?></td>
            <td><?= e($r['grade']) ?></td>
            <td><span class="badge <?= $r['result_status'] === 'Pass' ? 'badge-pass' : 'badge-fail' ?>"><?= e($r['result_status']) ?></span></td>
            <td><span class="badge <?= $r['is_published'] ? 'badge-pub' : 'badge-hide' ?>"><?= $r['is_published'] ? 'Published' : 'Hidden' ?></span></td>
            <td class="flex gap-sm flex-wrap">
              <a class="btn btn-outline btn-sm" href="<?= e(base_url('admin/marksheet.php?id=' . $r['id'])) ?>">Marksheet</a>
              <a class="btn btn-outline btn-sm" href="<?= e(base_url('admin/marks.php?roll_no=' . urlencode($r['roll_no']))) ?>">Edit</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <?php if ($r['is_published']): ?>
                  <input type="hidden" name="action" value="hide">
                  <button class="btn btn-outline btn-sm" type="submit">Hide</button>
                <?php else: ?>
                  <input type="hidden" name="action" value="publish">
                  <button class="btn btn-emerald btn-sm" type="submit">Publish</button>
                <?php endif; ?>
              </form>
              <form method="post" onsubmit="return confirm('Delete?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
