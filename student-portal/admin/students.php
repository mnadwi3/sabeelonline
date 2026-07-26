<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/marksheet.php';
require_admin();
$pdo = db();

$semesters = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['admin_no'] ?? 0);
        $studentRoll = trim($_POST['student_roll_no'] ?? '');
        $roll = normalize_student_id($_POST['roll_no'] ?? '');
        $name = trim($_POST['s_name_e'] ?? '');
        $fname = trim($_POST['f_name_e'] ?? '');
        $dob = $_POST['dob'] !== '' ? $_POST['dob'] : null;
        $address = trim($_POST['address_e'] ?? '');
        $courseId = $_POST['course_id'] !== '' ? (int) $_POST['course_id'] : null;
        $semester = trim($_POST['semester'] ?? '');
        $semYear = trim($_POST['semester_year'] ?? '');

        if ($name === '') {
            flash('error', 'Name is required.');
            redirect('admin/students.php');
        }

        if ($studentRoll === '') {
            flash('error', 'Roll No is required.');
            redirect('admin/students.php' . ($id > 0 ? '?edit=' . $id : ''));
        }

        try {
            if ($id > 0) {
                // Edit: keep/allow changing portal ID (for migrating guessable IDs)
                if (!is_valid_student_id($roll)) {
                    flash(
                        'error',
                        'Student ID must be at least 8 characters, include letters (A–Z), and may use numbers/hyphen.'
                    );
                    redirect('admin/students.php?edit=' . $id);
                }
                if (student_id_exists($pdo, $roll, $id)) {
                    flash('error', 'This Student ID already exists. Use a unique ID.');
                    redirect('admin/students.php?edit=' . $id);
                }

                $old = $pdo->prepare('SELECT roll_no FROM tbl_students WHERE admin_no=?');
                $old->execute([$id]);
                $prevRoll = (string) ($old->fetchColumn() ?: '');

                $pdo->prepare(
                    'UPDATE tbl_students SET student_roll_no=?, roll_no=?, s_name_e=?, f_name_e=?, dob=?, address_e=?, course_id=?, semester=?, semester_year=? WHERE admin_no=?'
                )->execute([$studentRoll, $roll, $name, $fname, $dob, $address, $courseId, $semester, $semYear, $id]);

                // Keep results linked if Student ID changed
                if ($prevRoll !== '' && strcasecmp($prevRoll, $roll) !== 0) {
                    $pdo->prepare('UPDATE tbl_results SET roll_no=? WHERE roll_no=?')->execute([$roll, $prevRoll]);
                }
                flash('success', 'Student updated. Portal ID: ' . $roll . ' — give the student this ID if it changed.');
            } else {
                // Create: non-guessable ID only (accept form preview if secure+free, else regenerate)
                $posted = $roll;
                if (
                    is_secure_student_id_format($posted)
                    && is_valid_student_id($posted)
                    && !student_id_exists($pdo, $posted)
                ) {
                    $roll = $posted;
                } else {
                    $roll = generate_unique_student_id($pdo);
                }

                $saved = false;
                for ($attempt = 0; $attempt < 5 && !$saved; $attempt++) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO tbl_students (student_roll_no, roll_no, s_name_e, f_name_e, dob, address_e, course_id, semester, semester_year)
                             VALUES (?,?,?,?,?,?,?,?,?)'
                        )->execute([$studentRoll, $roll, $name, $fname, $dob, $address, $courseId, $semester, $semYear]);
                        $saved = true;
                    } catch (Throwable $e) {
                        if (stripos($e->getMessage(), 'Duplicate') === false || $attempt >= 4) {
                            throw $e;
                        }
                        $roll = generate_unique_student_id($pdo);
                    }
                }
                flash('success', 'Student added. Give them this Portal Student ID: ' . $roll);
            }
        } catch (Throwable $e) {
            $msg = stripos($e->getMessage(), 'Duplicate') !== false
                ? 'This Student ID already exists. Reload the form to get a new ID.'
                : 'Could not save student.';
            flash('error', $msg);
        }
        redirect('admin/students.php');
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM tbl_students WHERE admin_no=?')->execute([(int) $_POST['admin_no']]);
        flash('success', 'Student deleted.');
        redirect('admin/students.php');
    }

    if ($action === 'regenerate_id') {
        $id = (int) ($_POST['admin_no'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Student not found.');
            redirect('admin/students.php');
        }
        try {
            $old = $pdo->prepare('SELECT roll_no FROM tbl_students WHERE admin_no=?');
            $old->execute([$id]);
            $prevRoll = (string) ($old->fetchColumn() ?: '');
            if ($prevRoll === '') {
                flash('error', 'Student not found.');
                redirect('admin/students.php');
            }
            $roll = generate_unique_student_id($pdo);
            $pdo->prepare('UPDATE tbl_students SET roll_no=? WHERE admin_no=?')->execute([$roll, $id]);
            if (strcasecmp($prevRoll, $roll) !== 0) {
                $pdo->prepare('UPDATE tbl_results SET roll_no=? WHERE roll_no=?')->execute([$roll, $prevRoll]);
            }
            flash('success', 'New Portal Student ID: ' . $roll . ' (was ' . $prevRoll . '). Tell the student immediately.');
        } catch (Throwable $e) {
            flash('error', 'Could not regenerate Student ID.');
        }
        redirect('admin/students.php?edit=' . $id);
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM tbl_students WHERE admin_no=?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$previewStudentId = '';
if (!$edit) {
    try {
        $previewStudentId = generate_unique_student_id($pdo);
    } catch (Throwable $e) {
        $previewStudentId = '';
    }
}

$courses = $pdo->query('SELECT * FROM tbl_courses ORDER BY course_name')->fetchAll();
$students = $pdo->query(
    'SELECT st.*, c.course_name, c.month_year FROM tbl_students st
     LEFT JOIN tbl_courses c ON c.course_id = st.course_id
     ORDER BY st.admin_no DESC'
)->fetchAll();

$pageTitle = 'Students';
$active = 'students';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="card mb-2">
  <h2><?= $edit ? 'Edit Student' : 'Add Student' ?></h2>
  <p class="muted">
    <strong>Roll No</strong> is the class/batch number (e.g. SUS-001).
    <strong>Student ID</strong> is the private portal code for the Results page —
    new students get a random 10-character ID (hard to guess). Old IDs keep working until you change them.
  </p>
  <form method="post" class="mt-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="admin_no" value="<?= (int) ($edit['admin_no'] ?? 0) ?>">
    <div class="form-grid">
      <div>
        <label>Roll No *</label>
        <input name="student_roll_no" required maxlength="40"
               placeholder="e.g. SUS-001"
               value="<?= e($edit['student_roll_no'] ?? '') ?>">
      </div>
      <div>
        <label>Student ID (portal login) <?= $edit ? '*' : '' ?></label>
        <?php if ($edit): ?>
          <?php $editIdSecure = is_secure_student_id_format((string) ($edit['roll_no'] ?? '')); ?>
          <input name="roll_no" required minlength="8" maxlength="40"
                 pattern="(?=.*[A-Za-z])[A-Za-z0-9-]{8,40}"
                 title="At least 8 characters with letters"
                 value="<?= e($edit['roll_no'] ?? '') ?>"
                 style="text-transform:uppercase">
          <?php if (!$editIdSecure): ?>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.8rem;color:#b42318">
              This ID looks guessable (batch-style). Use <strong>Regenerate secure Student ID</strong> below, then tell the student the new code.
            </p>
          <?php else: ?>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.8rem">
              Secure format. Change only if needed — tell the student the new ID after saving.
            </p>
          <?php endif; ?>
        <?php else: ?>
          <input name="roll_no" readonly required minlength="8" maxlength="40"
                 value="<?= e($previewStudentId) ?>"
                 style="text-transform:uppercase;letter-spacing:0.04em;font-weight:600"
                 title="Auto-generated portal Student ID">
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.8rem">
            Auto-generated (not sequential). <a href="<?= e(base_url('admin/students.php')) ?>">Generate another</a>
            if needed. Copy this ID for the student after you save.
          </p>
        <?php endif; ?>
      </div>
      <div>
        <label>Name *</label>
        <input name="s_name_e" required value="<?= e($edit['s_name_e'] ?? '') ?>">
      </div>
      <div>
        <label>Father's Name</label>
        <input name="f_name_e" value="<?= e($edit['f_name_e'] ?? '') ?>">
      </div>
      <div>
        <label>Date of Birth</label>
        <input type="date" name="dob" value="<?= e($edit['dob'] ?? '') ?>">
      </div>
      <div>
        <label>Address</label>
        <input name="address_e" value="<?= e($edit['address_e'] ?? '') ?>">
      </div>
      <div>
        <label>Course</label>
        <select name="course_id">
          <option value="">— Select —</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int) $c['course_id'] ?>" <?= (int) ($edit['course_id'] ?? 0) === (int) $c['course_id'] ? 'selected' : '' ?>>
              <?= e($c['course_name'] . ' (' . $c['month_year'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Current Semester</label>
        <select name="semester">
          <option value="">— Select —</option>
          <?php foreach ($semesters as $sem): ?>
            <option value="<?= e($sem) ?>" <?= ($edit['semester'] ?? '') === $sem ? 'selected' : '' ?>><?= e($sem) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="muted" style="margin:0.25rem 0 0;font-size:0.8rem">Profile only. Older semester results stay in Marks Entry.</p>
      </div>
      <div>
        <label>Current Semester Year</label>
        <input name="semester_year" placeholder="e.g. 2024" value="<?= e($edit['semester_year'] ?? '') ?>">
      </div>
    </div>
    <div class="actions-bar">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Update Student' : 'Save Student' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(base_url('admin/students.php')) ?>">Cancel</a><?php endif; ?>
      <?php if ($edit): ?>
        <a class="btn btn-emerald" href="<?= e(base_url('admin/marks.php?roll_no=' . urlencode($edit['roll_no']))) ?>">Enter Marks →</a>
      <?php endif; ?>
    </div>
  </form>
  <?php if ($edit): ?>
    <form method="post" class="mt-2" onsubmit="return confirm('Generate a new random Portal Student ID? The old ID will stop working. You must tell the student the new ID.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="regenerate_id">
      <input type="hidden" name="admin_no" value="<?= (int) $edit['admin_no'] ?>">
      <button class="btn btn-outline btn-sm" type="submit">Regenerate secure Student ID</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>All Students</h2>
  <div class="table-wrap mt-2">
    <table class="data">
      <thead>
        <tr>
          <th>Roll No</th><th>Student ID</th><th>Portal format</th><th>Name</th><th>Father</th>
          <th>Course</th><th>Semester</th><th>Year</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $st): ?>
          <?php
            $sid = (string) $st['roll_no'];
            $ok = is_valid_student_id($sid);
            $secure = is_secure_student_id_format($sid);
            $portal = format_sabeel_student_id($st);
          ?>
          <tr>
            <td><?= e($st['student_roll_no'] ?? '—') ?></td>
            <td>
              <strong><?= e($sid) ?></strong>
              <?php if (!$ok): ?>
                <br><span class="badge badge-fail">Fix: need 8+ chars with letters</span>
              <?php elseif (!$secure): ?>
                <br><span class="badge badge-fail">Guessable — regenerate</span>
              <?php endif; ?>
            </td>
            <td><code><?= e($portal) ?></code></td>
            <td><?= e($st['s_name_e']) ?></td>
            <td><?= e($st['f_name_e']) ?></td>
            <td><?= e($st['course_name'] ?? '—') ?></td>
            <td><?= e($st['semester'] ?? '—') ?></td>
            <td><?= e($st['semester_year'] ?? '—') ?></td>
            <td class="flex gap-sm flex-wrap">
              <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $st['admin_no'] ?>">Edit</a>
              <a class="btn btn-emerald btn-sm" href="<?= e(base_url('admin/marks.php?roll_no=' . urlencode($st['roll_no']))) ?>">Marks</a>
              <form method="post" onsubmit="return confirm('Delete student?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="admin_no" value="<?= (int) $st['admin_no'] ?>">
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
