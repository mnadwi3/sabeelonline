<?php
/**
 * Marks Entry — multi-semester safe
 * Old semester results are never deleted when entering a new semester.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();

$semesters = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6'];
$roll = trim((string) ($_GET['roll_no'] ?? $_POST['roll_no'] ?? ''));
$selectedSem = trim((string) ($_GET['semester'] ?? $_POST['semester'] ?? ''));
$selectedYear = trim((string) ($_GET['semester_year'] ?? $_POST['semester_year'] ?? ''));

$student = null;
$subjects = [];
$existing = null;
$existingMarks = [];
$history = [];

if ($roll !== '') {
    $st = $pdo->prepare(
        'SELECT st.*, c.course_name, c.month_year FROM tbl_students st
         LEFT JOIN tbl_courses c ON c.course_id = st.course_id
         WHERE st.roll_no = ? LIMIT 1'
    );
    $st->execute([$roll]);
    $student = $st->fetch() ?: null;

    if ($student) {
        // Default semester/year from student "current" only if not chosen yet
        if ($selectedSem === '') {
            $selectedSem = (string) ($student['semester'] ?? '');
        }
        if ($selectedYear === '') {
            $selectedYear = (string) ($student['semester_year'] ?? '');
        }

        if ($student['course_id']) {
            $ss = $pdo->prepare('SELECT * FROM tbl_subjects WHERE course_id=? ORDER BY sort_order, subject_id');
            $ss->execute([(int) $student['course_id']]);
            $subjects = $ss->fetchAll();
        }

        // Load marks only for the selected semester (does not touch other semesters)
        if ($selectedSem !== '' && $selectedYear !== '' && $student['course_id']) {
            $rs = $pdo->prepare(
                'SELECT * FROM tbl_results WHERE roll_no=? AND course_id=? AND semester=? AND semester_year=? LIMIT 1'
            );
            $rs->execute([$student['roll_no'], $student['course_id'], $selectedSem, $selectedYear]);
            $existing = $rs->fetch() ?: null;
            if ($existing) {
                $ms = $pdo->prepare('SELECT * FROM tbl_marks WHERE result_id=?');
                $ms->execute([(int) $existing['id']]);
                foreach ($ms->fetchAll() as $m) {
                    $existingMarks[$m['subject_name']] = $m;
                }
            }
        }

        // All saved semester results for this student (history — never auto-deleted)
        $hs = $pdo->prepare(
            "SELECT r.id, r.semester, r.semester_year, r.percentage, r.grade, r.result_status, r.is_published, c.course_name
             FROM tbl_results r
             LEFT JOIN tbl_courses c ON c.course_id = r.course_id
             WHERE r.roll_no = ?
             ORDER BY r.semester_year DESC, r.semester ASC"
        );
        $hs->execute([$student['roll_no']]);
        $history = $hs->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_marks') {
    verify_csrf();
    $roll = trim($_POST['roll_no'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $semYear = trim($_POST['semester_year'] ?? '');
    $setCurrent = isset($_POST['set_as_current']);

    $st = $pdo->prepare('SELECT * FROM tbl_students WHERE roll_no=? LIMIT 1');
    $st->execute([$roll]);
    $student = $st->fetch();
    if (!$student) {
        flash('error', 'Student not found for this Roll No.');
        redirect('admin/marks.php');
    }
    if ($semester === '' || $semYear === '') {
        flash('error', 'Select Semester and Semester Year for this result.');
        redirect('admin/marks.php?roll_no=' . urlencode($roll));
    }

    $names = $_POST['subject_name'] ?? [];
    $maxes = $_POST['max_marks'] ?? [];
    $obts = $_POST['obtained'] ?? [];
    $remarks = trim($_POST['remarks'] ?? '');
    $publish = isset($_POST['is_published']) ? 1 : 0;

    $totalObt = 0.0;
    $totalMax = 0.0;
    $rows = [];
    foreach ($names as $i => $subName) {
        $subName = trim((string) $subName);
        if ($subName === '') {
            continue;
        }
        $max = (float) ($maxes[$i] ?? 100);
        $obt = (float) ($obts[$i] ?? 0);
        if ($obt < 0) {
            $obt = 0;
        }
        if ($obt > $max) {
            $obt = $max;
        }
        $totalObt += $obt;
        $totalMax += $max;
        $rows[] = ['subject_name' => $subName, 'max_marks' => (int) $max, 'obtained' => $obt];
    }

    if (!$rows) {
        flash('error', 'Enter at least one subject mark.');
        redirect('admin/marks.php?roll_no=' . urlencode($roll) . '&semester=' . urlencode($semester) . '&semester_year=' . urlencode($semYear));
    }

    $calc = calculate_result($totalObt, $totalMax);

    $pdo->beginTransaction();
    try {
        // Upsert ONLY this semester's result — other semesters stay untouched
        $find = $pdo->prepare(
            'SELECT id FROM tbl_results WHERE roll_no=? AND course_id=? AND semester=? AND semester_year=? LIMIT 1'
        );
        $find->execute([$student['roll_no'], $student['course_id'], $semester, $semYear]);
        $rid = $find->fetchColumn();

        if ($rid) {
            $resultId = (int) $rid;
            $pdo->prepare(
                'UPDATE tbl_results SET admin_no=?, grand_total=?, max_total=?, percentage=?, grade=?,
                 result_status=?, remarks=?, is_published=? WHERE id=?'
            )->execute([
                $student['admin_no'], $totalObt, $totalMax, $calc['percentage'], $calc['grade'],
                $calc['result_status'], $remarks, $publish, $resultId,
            ]);
            $pdo->prepare('DELETE FROM tbl_marks WHERE result_id=?')->execute([$resultId]);
        } else {
            $pdo->prepare(
                'INSERT INTO tbl_results
                 (admin_no, roll_no, course_id, semester, semester_year, grand_total, max_total,
                  percentage, grade, result_status, remarks, is_published)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $student['admin_no'], $student['roll_no'], $student['course_id'],
                $semester, $semYear,
                $totalObt, $totalMax, $calc['percentage'], $calc['grade'],
                $calc['result_status'], $remarks, $publish,
            ]);
            $resultId = (int) $pdo->lastInsertId();
        }

        $ins = $pdo->prepare(
            'INSERT INTO tbl_marks (result_id, subject_name, max_marks, obtained) VALUES (?,?,?,?)'
        );
        foreach ($rows as $r) {
            $ins->execute([$resultId, $r['subject_name'], $r['max_marks'], $r['obtained']]);
        }

        // Optionally mark this as student's current semester (does NOT delete old results)
        if ($setCurrent) {
            $pdo->prepare('UPDATE tbl_students SET semester=?, semester_year=? WHERE admin_no=?')
                ->execute([$semester, $semYear, $student['admin_no']]);
        }

        $pdo->commit();
        flash('success', $semester . ' (' . $semYear . ') saved. Other semester results are safe. ' . $calc['percentage'] . '% — ' . $calc['grade']);
        redirect('admin/marksheet.php?id=' . $resultId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Save failed: ' . $e->getMessage());
        redirect('admin/marks.php?roll_no=' . urlencode($roll) . '&semester=' . urlencode($semester) . '&semester_year=' . urlencode($semYear));
    }
}

$allRolls = $pdo->query('SELECT roll_no, s_name_e FROM tbl_students ORDER BY roll_no')->fetchAll();

$pageTitle = 'Marks Entry';
$active = 'marks';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="alert alert-info">
  <strong>Multi-semester:</strong> Naya semester add karne se pehle semester ka result delete nahi hota.
  Har semester ka marksheet alag save / download hota hai.
</div>

<div class="marks-overview">
  <div class="card marks-select-card">
    <h2>1. Select Student</h2>
    <form method="get" class="form-grid mt-2">
      <div>
        <label>Student ID *</label>
        <select name="roll_no" required onchange="this.form.submit()">
          <option value="">— Select Student ID —</option>
          <?php foreach ($allRolls as $r): ?>
            <option value="<?= e($r['roll_no']) ?>" <?= $roll === $r['roll_no'] ? 'selected' : '' ?>>
              <?= e($r['roll_no'] . ' — ' . $r['s_name_e']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($student): ?>
        <div>
          <label>Semester for this result *</label>
          <select name="semester" required onchange="this.form.submit()">
            <option value="">— Select —</option>
            <?php foreach ($semesters as $sem): ?>
              <option value="<?= e($sem) ?>" <?= $selectedSem === $sem ? 'selected' : '' ?>><?= e($sem) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Semester Year *</label>
          <input name="semester_year" required placeholder="e.g. 2026" value="<?= e($selectedYear) ?>"
                 onchange="this.form.submit()">
        </div>
        <div style="display:flex;align-items:end">
          <button class="btn btn-outline" type="submit">Load</button>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($student): ?>
  <div class="card marks-student-card">
    <h2>2. Student</h2>
    <div class="ms-info mt-2" style="grid-template-columns:1fr 1fr 1fr">
      <div><strong>Name:</strong> <?= e($student['s_name_e']) ?></div>
      <div><strong>Roll No:</strong> <?= e($student['student_roll_no'] ?? '—') ?></div>
      <div><strong>Student ID:</strong> <?= e($student['roll_no']) ?></div>
      <div><strong>Course:</strong> <?= e($student['course_name'] ?? '—') ?></div>
      <div><strong>Current semester (profile):</strong> <?= e($student['semester'] ?? '—') ?> / <?= e($student['semester_year'] ?? '—') ?></div>
      <div><strong>Entering marks for:</strong> <?= e($selectedSem ?: '—') ?> / <?= e($selectedYear ?: '—') ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($student): ?>
<?php if ($history): ?>
<div class="card mb-2">
  <h2>Saved semester results (all kept)</h2>
  <div class="table-wrap mt-2">
    <table class="data">
      <thead>
        <tr><th>Semester</th><th>Year</th><th>Course</th><th>%</th><th>Grade</th><th>Result</th><th>Portal</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td><?= e($h['semester']) ?></td>
            <td><?= e($h['semester_year']) ?></td>
            <td><?= e($h['course_name'] ?? '—') ?></td>
            <td><?= e((string) $h['percentage']) ?></td>
            <td><?= e($h['grade']) ?></td>
            <td><span class="badge <?= $h['result_status'] === 'Pass' ? 'badge-pass' : 'badge-fail' ?>"><?= e($h['result_status']) ?></span></td>
            <td><span class="badge <?= $h['is_published'] ? 'badge-pub' : 'badge-hide' ?>"><?= $h['is_published'] ? 'Published' : 'Hidden' ?></span></td>
            <td class="flex gap-sm">
              <a class="btn btn-outline btn-sm" href="<?= e(base_url('admin/marksheet.php?id=' . (int) $h['id'])) ?>">Download</a>
              <a class="btn btn-emerald btn-sm" href="<?= e(base_url('admin/marks.php?roll_no=' . urlencode($roll) . '&semester=' . urlencode((string) $h['semester']) . '&semester_year=' . urlencode((string) $h['semester_year']))) ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($subjects && $selectedSem !== '' && $selectedYear !== ''): ?>
<div class="card marks-entry-card">
  <h2>3. Enter Marks — <?= e($selectedSem) ?> (<?= e($selectedYear) ?>)</h2>
  <p class="muted">
    <?= $existing ? 'Editing existing result for this semester only.' : 'Creating a NEW semester result. Previous semesters stay saved.' ?>
  </p>
  <form method="post" id="marks-entry-form" class="marks-entry-form mt-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_marks">
    <input type="hidden" name="roll_no" value="<?= e($student['roll_no']) ?>">
    <input type="hidden" name="semester" value="<?= e($selectedSem) ?>">
    <input type="hidden" name="semester_year" value="<?= e($selectedYear) ?>">

    <div class="table-wrap marks-entry-table-wrap">
      <table class="data marks-entry-table">
        <thead>
          <tr><th>Subject</th><th>Max Marks</th><th>Obtained Marks</th></tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $sub): ?>
            <?php $ex = $existingMarks[$sub['subject_name']] ?? null; ?>
            <tr class="mark-row">
              <td>
                <?= e($sub['subject_name']) ?>
                <input type="hidden" name="subject_name[]" value="<?= e($sub['subject_name']) ?>">
              </td>
              <td>
                <input class="max-marks" type="number" name="max_marks[]" min="1"
                       value="<?= e((string) ($ex['max_marks'] ?? $sub['max_marks'])) ?>">
              </td>
              <td>
                <input class="obt-marks" type="number" step="0.01" min="0" name="obtained[]"
                       value="<?= e((string) ($ex['obtained'] ?? '')) ?>" placeholder="0" required>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="ms-summary mt-2">
      <div><span>Total</span><strong id="live-total">0</strong></div>
      <div><span>Percentage</span><strong id="live-pct">0%</strong></div>
      <div><span>Grade</span><strong id="live-grade">—</strong></div>
      <div><span>Result</span><strong id="live-status">—</strong></div>
    </div>

    <div class="form-grid mt-2">
      <div><label>Remarks (optional)</label><input name="remarks" value="<?= e($existing['remarks'] ?? '') ?>"></div>
      <div style="display:flex;flex-direction:column;justify-content:end;gap:0.5rem">
        <label style="display:flex;gap:0.5rem;align-items:center">
          <input type="checkbox" name="is_published" value="1" style="width:auto"
            <?= !empty($existing['is_published']) ? 'checked' : '' ?>>
          Publish on public portal
        </label>
        <label style="display:flex;gap:0.5rem;align-items:center">
          <input type="checkbox" name="set_as_current" value="1" style="width:auto" checked>
          Set as student's current semester (keeps old results)
        </label>
      </div>
    </div>

    <div class="actions-bar">
      <button class="btn btn-primary" type="submit">Save <?= e($selectedSem) ?> Marks</button>
    </div>
  </form>
</div>
<?php elseif ($student && (!$selectedSem || !$selectedYear)): ?>
  <div class="alert alert-info">Semester aur Year select karein — jaise Semester 2 / 2026 — phir marks enter karein.</div>
<?php elseif ($student && !$subjects): ?>
  <div class="alert alert-info">
    Subjects missing.
    <a href="<?= e(base_url('admin/subjects.php')) ?>">Add subjects</a> for this course.
  </div>
<?php endif; ?>
<?php elseif ($roll !== ''): ?>
  <div class="alert alert-error">Roll No not found.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
