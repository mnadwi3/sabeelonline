<?php
/**
 * Marksheet helpers — clean layout (no seal, no watermark)
 */
function load_result_bundle(PDO $pdo, int $id, bool $publicOnly = false): ?array
{
    $titleSelect = has_marksheet_title_column($pdo)
        ? 'c.marksheet_title'
        : 'NULL AS marksheet_title';
    $sql = "SELECT r.*, s.student_roll_no, s.s_name_e, s.f_name_e, s.dob, s.photo, s.address_e, s.admin_no AS student_id,
                   c.course_name, {$titleSelect}, c.month_year
            FROM tbl_results r
            LEFT JOIN tbl_students s ON s.admin_no = r.admin_no
            LEFT JOIN tbl_courses c ON c.course_id = r.course_id
            WHERE r.id = ?";
    if ($publicOnly) {
        $sql .= ' AND r.is_published = 1';
    }
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $result = $st->fetch();
    if (!$result) {
        return null;
    }

    $ms = $pdo->prepare('SELECT * FROM tbl_marks WHERE result_id = ? ORDER BY mark_id');
    $ms->execute([$id]);
    $result['marks'] = $ms->fetchAll();

    // Always derive grade from percentage (fixes old gap-bug rows like 79.25 → F)
    $fix = calculate_result((float) $result['grand_total'], (float) $result['max_total']);
    $result['percentage'] = $fix['percentage'];
    $result['grade'] = $fix['grade'];
    $result['result_status'] = $fix['result_status'];

    return $result;
}

/**
 * Student ID format: Sabeel-{YY}-{RollNo}  e.g. Sabeel-24-616
 */
/**
 * Professional course title for the printed marksheet.
 * Uses marksheet_title when set; otherwise strips a trailing "Batch N".
 */
function marksheet_course_title(array $r): string
{
    $title = trim((string) ($r['marksheet_title'] ?? ''));
    if ($title !== '') {
        return $title;
    }
    $name = trim((string) ($r['course_name'] ?? ''));
    if ($name === '') {
        return 'Result';
    }
    $cleaned = preg_replace('/\s*[-–—]?\s*Batch\s*\d+\s*$/iu', '', $name);
    $cleaned = trim((string) $cleaned);
    return $cleaned !== '' ? $cleaned : $name;
}

/**
 * Student ID rules (stored in roll_no):
 * - At least 8 characters
 * - Letters and numbers (hyphen allowed)
 * - Must include at least one letter (not only 1, 2, 3…)
 */
function is_valid_student_id(string $id): bool
{
    $id = trim($id);
    return (bool) preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9-]{8,40}$/', $id);
}

function normalize_student_id(string $id): string
{
    return strtoupper(trim($id));
}

function format_sabeel_student_id(array $r): string
{
    $yearRaw = trim((string) ($r['semester_year'] ?? ''));
    $yy = date('y');
    if (preg_match('/(\d{2,4})/', $yearRaw, $m)) {
        $y = $m[1];
        $yy = strlen($y) >= 4 ? substr($y, -2) : str_pad($y, 2, '0', STR_PAD_LEFT);
    }
    $roll = trim((string) ($r['roll_no'] ?? ''));
    if ($roll === '') {
        return 'Sabeel-' . $yy;
    }
    return 'Sabeel-' . $yy . '-' . $roll;
}

function find_published_by_roll_or_id(PDO $pdo, string $query): ?array
{
    $list = find_all_published_by_roll_or_id($pdo, $query);
    return $list[0] ?? null;
}

/**
 * Find published results by Student ID.
 * Accepts:
 *   - Sabeel-YY-STUDENTID  (e.g. Sabeel-26-SUS00001)
 *   - STUDENTID alone       (8+ chars with letters, e.g. SUS00001)
 */
function find_all_published_by_roll_or_id(PDO $pdo, string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $yy = null;
    $roll = '';

    if (preg_match('/^sabeel-(\d{2})-(.+)$/i', $query, $m)) {
        $yy = $m[1];
        $roll = trim($m[2]);
    } elseif (is_valid_student_id($query)) {
        $roll = $query;
    } else {
        return [];
    }

    if ($roll === '' || !is_valid_student_id($roll)) {
        return [];
    }

    $st = $pdo->prepare(
        "SELECT r.id, r.semester_year FROM tbl_results r
         WHERE r.is_published = 1 AND LOWER(r.roll_no) = LOWER(?)
         ORDER BY r.semester_year ASC, r.semester ASC"
    );
    $st->execute([$roll]);
    $rows = $st->fetchAll();

    $matched = [];
    if ($yy !== null) {
        foreach ($rows as $row) {
            $yearRaw = (string) ($row['semester_year'] ?? '');
            if (preg_match('/(\d{2,4})/', $yearRaw, $ym)) {
                $y = $ym[1];
                $rowYy = strlen($y) >= 4 ? substr($y, -2) : str_pad($y, 2, '0', STR_PAD_LEFT);
                if ($rowYy === $yy) {
                    $matched[] = $row;
                }
            }
        }
    }
    $use = $matched !== [] ? $matched : $rows;

    $bundles = [];
    foreach ($use as $row) {
        $bundle = load_result_bundle($pdo, (int) $row['id'], true);
        if ($bundle) {
            $bundles[] = $bundle;
        }
    }
    return $bundles;
}

function render_marksheet(array $r, bool $showPrintBar = true): void
{
    $photo = !empty($r['photo']) ? asset($r['photo']) : null;
    $studentId = trim((string) ($r['roll_no'] ?? ''));
    $courseTitle = marksheet_course_title($r);
    ?>
    <div class="marksheet marksheet-clean" id="marksheet">
      <div class="marksheet-inner">
        <div class="marksheet-header">
          <img src="<?= e(asset('images/logo_header.png')) ?>" alt="Madarsa Sabeel Us Salam Online">
        </div>

        <div class="ms-doc-label">Statement of Marks</div>
        <h2 class="marksheet-title"><?= e($courseTitle) ?></h2>
        <?php if (!empty($r['month_year'])): ?>
          <p class="ms-subtitle"><?= e($r['month_year']) ?><?php if (!empty($r['semester'])): ?> · <?= e($r['semester']) ?><?php endif; ?></p>
        <?php endif; ?>

        <div class="ms-meta">
          <div class="ms-meta-heading">Student Information</div>
          <div class="ms-info ms-info-bold">
            <div class="ms-row"><span class="ms-label">Admission No</span><span class="ms-value"><?= e((string) ($r['student_id'] ?? $r['admin_no'] ?? '—')) ?></span></div>
            <div class="ms-row"><span class="ms-label">Roll No</span><span class="ms-value"><?= e($r['student_roll_no'] ?? $r['roll_no']) ?></span></div>
            <div class="ms-row"><span class="ms-label">Student ID</span><span class="ms-value"><?= e($studentId ?: '—') ?></span></div>
            <div class="ms-row"><span class="ms-label">Student Name</span><span class="ms-value"><?= e($r['s_name_e'] ?? '—') ?></span></div>
            <div class="ms-row"><span class="ms-label">Father Name</span><span class="ms-value"><?= e($r['f_name_e'] ?? '—') ?></span></div>
            <div class="ms-row"><span class="ms-label">Date of Birth</span><span class="ms-value"><?= e(format_date($r['dob'] ?? null)) ?></span></div>
            <div class="ms-row"><span class="ms-label">Address</span><span class="ms-value"><?= e($r['address_e'] ?? '—') ?></span></div>
          </div>
          <?php if ($photo): ?>
            <img class="ms-photo" src="<?= e($photo) ?>" alt="Photo">
          <?php endif; ?>
        </div>

        <table class="ms-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Subject</th>
              <th>Max Marks</th>
              <th>Marks Obtained</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; foreach ($r['marks'] as $m): ?>
              <tr>
                <td><?= str_pad((string) $i++, 2, '0', STR_PAD_LEFT) ?></td>
                <td class="ms-subject"><?= e($m['subject_name']) ?></td>
                <td><?= (int) $m['max_marks'] ?></td>
                <td><?= e((string) $m['obtained']) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="ms-total-row">
              <td colspan="2"><strong>Grand Total</strong></td>
              <td><strong><?= e((string) $r['max_total']) ?></strong></td>
              <td><strong><?= e((string) $r['grand_total']) ?></strong></td>
            </tr>
          </tbody>
        </table>

        <div class="ms-summary">
          <div><span>Result</span><strong><?= e($r['result_status']) ?></strong></div>
          <div><span>Percentage</span><strong><?= e((string) $r['percentage']) ?>%</strong></div>
          <div><span>Grade</span><strong><?= e($r['grade']) ?></strong></div>
          <div><span>Session</span><strong><?= e($r['month_year'] ?? '—') ?></strong></div>
        </div>

        <?php if (!empty($r['remarks'])): ?>
          <p class="ms-remarks"><strong>Remarks:</strong> <?= e($r['remarks']) ?></p>
        <?php endif; ?>

        <div class="ms-signs ms-signs-2">
          <div class="ms-sign ms-sign-principal">
            <div class="sign-slot">
              <img class="sign-principal" src="<?= e(asset('images/signature_principal.png')) ?>" alt="Principal">
            </div>
            <div class="sign-line"></div>
            <div><strong>Principal</strong></div>
          </div>
          <div class="ms-sign ms-sign-exam">
            <div class="sign-slot">
              <img class="sign-exam" src="<?= e(asset('images/signature_exam.png')) ?>" alt="Exam Incharge">
            </div>
            <div class="sign-line"></div>
            <div><strong>Exam Incharge</strong></div>
          </div>
        </div>

        <div class="grade-legend">
          Grading: 90+ A1 · 80+ A2 · 70+ B1 · 60+ B2 · 50+ C1 · 40+ C2 · Below 40 Fail
        </div>
      </div>
    </div>
    <?php if ($showPrintBar): ?>
    <div class="actions-bar marksheet-actions no-print">
      <button class="btn btn-primary" type="button" id="btnDownloadPdf">Download PDF</button>
      <button class="btn btn-emerald" type="button" id="btnDownloadImage">Download Image</button>
      <a class="btn btn-outline" href="javascript:history.back()">Back</a>
    </div>
    <p class="download-hint marksheet-download-hint no-print muted">Use Download PDF or Download Image for the full marksheet (signatures + grading). Browser Print may clip or add blank A4 space.</p>
    <?php endif; ?>
    <?php
}
