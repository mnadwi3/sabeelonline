<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/marksheet.php';

$results = [];
$searched = false;
$query = trim($_GET['q'] ?? '');
$viewId = (int) ($_GET['id'] ?? 0);

if ($query !== '') {
    $searched = true;
    try {
        $results = find_all_published_by_roll_or_id(db(), $query);
    } catch (Throwable $e) {
        $results = [];
    }
}

$view = null;
if ($viewId > 0) {
    try {
        $view = load_result_bundle(db(), $viewId, true);
        if ($view && !$searched) {
            $results = [$view];
            $searched = true;
            $query = format_sabeel_student_id($view);
        }
    } catch (Throwable $e) {
        $view = null;
        $results = [];
        $searched = true;
    }
} elseif (count($results) === 1) {
    $view = $results[0];
} elseif ($viewId === 0 && count($results) > 1) {
    $view = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Portal — <?= e(app_config('app_name')) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20260725pad1">
</head>
<body class="portal-page">
<?php if (!$searched || !$results): ?>
  <div class="portal-hero">
    <div class="portal-card">
      <div class="portal-brand">
        <img class="logo logo-header" src="<?= e(asset('images/logo_header.png')) ?>" alt="Sabeel Us Salaam Online" onerror="this.style.display='none'">
        <p class="portal-kicker">Sabeel Us Salaam Online</p>
      </div>
      <h1>Student Results</h1>
      <p class="muted">Enter your Student ID (at least 8 characters with letters), e.g. <strong>SUS00001</strong>.</p>
      <form method="get" class="portal-search-form">
        <label for="studentId">Student ID</label>
        <input id="studentId" name="q" required minlength="8"
               placeholder="e.g. SUS00001 or Sabeel-26-SUS00001"
               value="<?= e($query) ?>" autocomplete="off"
               style="text-transform:uppercase">
        <button class="btn btn-primary btn-block" type="submit">View Result</button>
      </form>
      <?php if ($searched && !$results): ?>
        <div class="portal-empty">
          <h2>Result not found</h2>
          <p class="muted">
            Use your Student ID with letters (example <strong>SUS00001</strong>), not numbers like 1 or 2.
            Or try <strong>Sabeel-YY-STUDENTID</strong>. Ask admin if your result is published.
          </p>
        </div>
      <?php endif; ?>
      <div class="portal-links">
        <a href="../">← Website</a>
        <a href="../library/">Library</a>
        <a href="<?= e(base_url('admin/login.php')) ?>">Admin</a>
      </div>
    </div>
  </div>
<?php elseif (!$view && count($results) > 1): ?>
  <div class="portal-shell">
    <header class="portal-top">
      <div>
        <p class="portal-kicker">Student Portal</p>
        <h1>Select semester</h1>
        <p class="muted"><?= e($query) ?> · <?= e($results[0]['s_name_e'] ?? '') ?></p>
      </div>
      <a class="btn btn-outline" href="<?= e(base_url('public/index.php')) ?>">New search</a>
    </header>
    <div class="result-list">
      <?php foreach ($results as $row): ?>
        <article class="result-card">
          <div class="result-card-main">
            <h2><?= e($row['semester'] ?? 'Semester') ?></h2>
            <p><?= e(marksheet_course_title($row)) ?> · <?= e($row['semester_year'] ?? '') ?></p>
            <div class="result-meta">
              <span><?= e((string) ($row['percentage'] ?? '')) ?>%</span>
              <span><?= e($row['grade'] ?? '') ?></span>
              <span class="badge <?= ($row['result_status'] ?? '') === 'Pass' ? 'badge-pass' : 'badge-fail' ?>"><?= e($row['result_status'] ?? '') ?></span>
            </div>
          </div>
          <a class="btn btn-primary" href="<?= e(base_url('public/index.php?q=' . urlencode($query) . '&id=' . (int) $row['id'])) ?>">
            Open marksheet
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <div class="portal-shell portal-shell-wide">
    <div class="actions-bar no-print">
      <a class="btn btn-outline" href="<?= e(base_url('public/index.php')) ?>">New search</a>
      <?php if (count($results) > 1): ?>
        <a class="btn btn-outline" href="<?= e(base_url('public/index.php?q=' . urlencode($query))) ?>">All semesters</a>
      <?php endif; ?>
      <button class="btn btn-primary" type="button" id="btnDownloadPdf">Download PDF</button>
      <button class="btn btn-emerald" type="button" id="btnDownloadImage">Download Image</button>
    </div>
    <p class="download-hint no-print muted">Use <strong>Download PDF</strong> or <strong>Download Image</strong> for a clean copy (no blank page space). Browser Print always uses full A4.</p>
    <?php render_marksheet($view, false); ?>
  </div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>?v=20260725pad1"></script>
</body>
</html>
