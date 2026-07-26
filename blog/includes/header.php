<?php
/**
 * Shared HTML header + optional admin sidebar
 *
 * Before including this file, you can set:
 *   $page_title = 'Dashboard';
 *   $page_mode  = 'admin'; // 'admin' or 'public'
 */

if (!isset($page_title)) {
    $page_title = 'Sabeel Blog';
}

if (!isset($page_mode)) {
    $page_mode = 'public';
}

// auth.php may already be included by the page
if (!function_exists('is_logged_in')) {
    require_once __DIR__ . '/auth.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($page_title); ?> | Sabeel Us Salaam Online</title>
  <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?php echo e($meta_description); ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <?php if ($page_mode === 'public'): ?>
    <!-- Same fonts + styles as main website -->
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=20260726navGap1">
    <link rel="stylesheet" href="assets/css/blog.css?v=20260726rte2">
  <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/blog.css?v=20260726rte2">
  <?php endif; ?>
</head>
<body class="page-<?php echo e($page_mode); ?>">

<?php if ($page_mode === 'admin'): ?>
  <!-- ================= ADMIN LAYOUT ================= -->
  <?php
  $currentAdminPage = basename($_SERVER['PHP_SELF'] ?? '');
  $adminNavItems = [
      ['dashboard.php', 'Dashboard'],
      ['new-post.php', 'New Blog'],
      ['posts.php', is_admin() ? 'All Blogs' : 'My Blogs'],
  ];

  if (is_admin()) {
      $adminNavItems[] = ['teachers.php', 'Teachers'];
      $adminNavItems[] = ['categories.php', 'Categories'];
  }

  $adminNavItems = array_merge($adminNavItems, [
      ['profile.php', 'Profile'],
      ['/pages/change-password.php', 'Change Password'],
      ['/admin-hub.html', 'Admin Hub'],
      ['index.php', 'View Blog'],
      ['/', 'Main Website'],
      ['logout.php', 'Logout'],
  ]);
  ?>
  <div class="admin-layout">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <span>Sabeel Blog</span>
        <small><?php echo e(ucfirst(current_user_role())); ?></small>
      </div>

      <nav class="sidebar-nav">
        <?php foreach ($adminNavItems as [$href, $label]): ?>
          <?php $isCurrentPage = $href === $currentAdminPage; ?>
          <a
            href="<?php echo e($href); ?>"
            class="<?php echo $isCurrentPage ? 'is-active' : ''; ?><?php echo $href === 'logout.php' ? ' logout-link' : ''; ?>"
            <?php echo $isCurrentPage ? 'aria-current="page"' : ''; ?>
          ><?php echo e($label); ?></a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <h1><?php echo e($page_title); ?></h1>
        <div class="topbar-user">Hello, <?php echo e(current_user_name()); ?></div>
      </header>
      <main class="admin-content">
<?php else: ?>
  <!-- ================= PUBLIC LAYOUT (same as main website) ================= -->
  <header class="site-header" id="siteHeader">
    <div class="container header-inner">
      <a href="/#home" class="logo" aria-label="Sabeel Us-Salam Online Home">
        <img src="../assets/logo-white.png" alt="Sabeel Us-Salam" class="logo-img" width="64" height="64">
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mainNav">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>
  <?php
    $navActive = 'blog';
    require dirname(__DIR__, 2) . '/includes/site-nav.php';
  ?>
  <div class="nav-backdrop" id="navBackdrop" hidden></div>

  <main class="blog-main">
<?php endif; ?>
