<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/marksheet.php';

$results = [];
$searched = false;
$rateLimited = false;
$query = trim($_GET['q'] ?? '');
$viewId = (int) ($_GET['id'] ?? 0);
$studentSession = portal_student_session_get();

// Reuse Student ID from Library / previous Results visit
if ($query === '' && $studentSession && $viewId <= 0) {
    header('Location: ' . base_url('public/index.php?q=' . rawurlencode($studentSession['student_id'])));
    exit;
}

// Lookups require Student ID (q). Bare ?id= is not allowed (prevents enumerating result rows).
if ($query !== '') {
    $searched = true;
    if (!portal_lookup_allowed(portal_client_ip())) {
        $rateLimited = true;
        $results = [];
    } else {
        try {
            $results = find_all_published_by_roll_or_id(db(), $query);
            // Remember Student ID for Library + next Results visit
            if ($results !== []) {
                $name = (string) ($results[0]['s_name_e'] ?? '');
                $roll = normalize_student_id(
                    preg_match('/^sabeel-\d{2}-(.+)$/i', $query, $m) ? $m[1] : $query
                );
                // Confirm they exist as a student record when possible
                try {
                    $st = db()->prepare('SELECT roll_no, s_name_e FROM tbl_students WHERE LOWER(roll_no)=LOWER(?) LIMIT 1');
                    $st->execute([$roll]);
                    $stu = $st->fetch();
                    if ($stu) {
                        portal_student_session_set((string) $stu['roll_no'], (string) ($stu['s_name_e'] ?? $name));
                    } else {
                        portal_student_session_set($roll, $name);
                    }
                } catch (Throwable $e) {
                    portal_student_session_set($roll, $name);
                }
            }
        } catch (Throwable $e) {
            $results = [];
        }
    }
} elseif ($viewId > 0) {
    // id without matching Student ID → treat as not found (same message)
    $searched = true;
    $results = [];
}

$view = null;
if ($viewId > 0 && $results !== []) {
    foreach ($results as $row) {
        if ((int) ($row['id'] ?? 0) === $viewId) {
            $view = $row;
            break;
        }
    }
} elseif (count($results) === 1) {
    $view = $results[0];
}

$displayQuery = $query !== '' ? $query : (string) ($studentSession['student_id'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Portal — <?= e(app_config('app_name')) ?></title>
  <link rel="stylesheet" href="/style.css?v=20260726navMobile1">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20260726sharedNav1">
</head>
<body class="portal-page has-site-nav">
  <header class="site-header" id="siteHeader">
    <div class="container header-inner">
      <a href="/#home" class="logo" aria-label="Sabeel Us-Salam Online Home">
        <img src="/assets/logo-white.png" alt="Sabeel Us-Salam" class="logo-img" width="64" height="64">
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mainNav">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>
  <?php
    $navActive = 'results';
    require dirname(__DIR__, 2) . '/includes/site-nav.php';
  ?>
  <div class="nav-backdrop" id="navBackdrop" hidden></div>
<?php if (!$searched || !$results): ?>
  <div class="portal-hero">
    <div class="portal-card">
      <div class="portal-brand">
        <img class="logo logo-header" src="<?= e(asset('images/logo_header.png')) ?>" alt="Sabeel Us Salaam Online" onerror="this.style.display='none'">
        <p class="portal-kicker">Sabeel Us Salaam Online</p>
      </div>
      <h1>Student Results</h1>
      <p class="muted">Enter your <strong>Student ID</strong> (same ID opens the Library / coursebooks).</p>
      <form method="get" class="portal-search-form">
        <label for="studentId">Student ID</label>
        <input id="studentId" name="q" required minlength="8" maxlength="40"
               placeholder="e.g. K7M2NP9QXH"
               value="<?= e($displayQuery) ?>" autocomplete="off"
               style="text-transform:uppercase">
        <button class="btn btn-primary btn-block" type="submit">View Result</button>
      </form>
      <?php if ($rateLimited): ?>
        <div class="portal-empty">
          <h2>Please try again later</h2>
          <p class="muted">Too many lookups from this connection. Wait a few minutes, then try again with your Student ID.</p>
        </div>
      <?php elseif ($searched && !$results): ?>
        <div class="portal-empty">
          <h2>Result not found</h2>
          <p class="muted">
            No published result matches that Student ID.
            Check the ID carefully, or ask your admin if your result has been published.
            You can still open the <a href="/library/">Library</a> with this Student ID if you are enrolled.
          </p>
        </div>
      <?php endif; ?>
      <div class="portal-links">
        <a href="/">← Website</a>
        <a href="/library/">Library (same Student ID)</a>
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
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn btn-outline" href="/library/">Open Library</a>
        <a class="btn btn-outline" href="<?= e(base_url('public/index.php')) ?>">New search</a>
      </div>
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
    <?php render_marksheet($view, false); ?>
    <div class="actions-bar marksheet-actions no-print">
      <a class="btn btn-outline" href="/library/">Open Library</a>
      <a class="btn btn-outline" href="<?= e(base_url('public/index.php')) ?>">New search</a>
      <?php if (count($results) > 1): ?>
        <a class="btn btn-outline" href="<?= e(base_url('public/index.php?q=' . urlencode($query))) ?>">All semesters</a>
      <?php endif; ?>
      <button class="btn btn-primary" type="button" id="btnDownloadPdf">Download PDF</button>
      <button class="btn btn-emerald" type="button" id="btnDownloadImage">Download Image</button>
    </div>
    <p class="download-hint marksheet-download-hint no-print muted">Use <strong>Download PDF</strong> or <strong>Download Image</strong> for a clean copy (no blank page space). Browser Print always uses full A4.</p>
  </div>
<?php endif; ?>
<script src="/script.js?v=20260726sharedNav1" defer></script>
<script src="<?= e(asset('js/app.js')) ?>?v=20260726sharedNav1"></script>
</body>
</html>
