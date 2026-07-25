<?php
/** @var string $pageTitle */
/** @var string $active */
$admin = current_admin();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Admin') ?> — <?= e(app_config('app_name')) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20260725pad1">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar no-print">
    <div class="brand">
      <strong><?= e(app_config('app_name')) ?></strong>
      <small>Results — Simple Entry</small>
    </div>
    <nav>
      <?php
      $links = [
        'dashboard' => ['admin/index.php', 'Dashboard'],
        'courses'   => ['admin/courses.php', 'Courses'],
        'subjects'  => ['admin/subjects.php', 'Subjects'],
        'students'  => ['admin/students.php', 'Students'],
        'marks'     => ['admin/marks.php', 'Marks Entry'],
        'results'   => ['admin/results.php', 'Results'],
        'password'  => ['admin/password.php', 'Change Password'],
      ];
      foreach ($links as $key => [$href, $label]):
      ?>
        <a class="<?= ($active ?? '') === $key ? 'active' : '' ?>" href="<?= e(base_url($href)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(base_url('public/index.php')) ?>" target="_blank">Public Portal</a>
      <a href="<?= e(base_url('admin/logout.php')) ?>">Logout</a>
    </nav>
  </aside>
  <main class="main">
    <div class="topbar no-print">
      <div>
        <h1><?= e($pageTitle ?? '') ?></h1>
        <p class="muted" style="margin:0"><?= e($admin['name'] ?? '') ?></p>
      </div>
    </div>
    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>">
        <?= e($flash['message']) ?>
      </div>
    <?php endif; ?>
