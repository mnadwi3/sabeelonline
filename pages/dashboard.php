<?php
/**
 * Authenticated user dashboard with portal launchers.
 * Location: /pages/dashboard.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
requireLogin();

$user = auth()->users()->findById((int) current_user_id());
if (!$user) {
    auth()->logout();
    flash_set('error', 'Your session is no longer valid. Please log in again.');
    redirect((string) $GLOBALS['app_config']['login_path']);
}

$modules = current_user_modules();
$labels = sabeel_module_labels();

$links = [];
if (user_has_module('blog')) {
    $links[] = ['href' => '/blog/dashboard.php', 'title' => 'Blog Admin', 'note' => 'Posts, teachers, categories'];
}
if (user_has_module('library')) {
    $links[] = ['href' => '/library/', 'title' => 'Digital Library', 'note' => 'Coursebooks for students'];
    $links[] = ['href' => '/library/admin.html', 'title' => 'Library Admin', 'note' => 'Upload and organise PDFs'];
}
if (user_has_module('courses')) {
    $links[] = ['href' => '/courses-admin.html', 'title' => 'Courses Admin', 'note' => 'Homepage course cards'];
    $links[] = ['href' => '/admissions-admin.html', 'title' => 'Admissions Admin', 'note' => 'Enrolment applications'];
    $links[] = ['href' => '/admin-hub.html', 'title' => 'Admin Hub', 'note' => 'All website admin tools'];
}
if (user_has_module('portal')) {
    $links[] = ['href' => '/student-portal/admin/', 'title' => 'Results Admin', 'note' => 'Students, courses, marksheets'];
}

render_auth_header('Dashboard', true);
?>
  <div class="topbar">
    <div>
      <strong>Hello, <?php echo e(current_user_display_name()); ?></strong>
      <div style="opacity:.85;font-size:.88rem;"><?php echo e((string) ($user['role_name'] ?? '')); ?></div>
    </div>
    <nav>
      <a href="<?php echo e(app_url('/pages/change-password.php')); ?>">Change password</a>
      <?php if (is_admin()): ?>
        <a href="<?php echo e(app_url('/pages/admin/users.php')); ?>">Manage users</a>
        <?php if (is_super_admin()): ?>
          <a href="<?php echo e(app_url('/pages/admin/migrate-accounts.php')); ?>">Import legacy accounts</a>
        <?php endif; ?>
        <a href="<?php echo e(app_url('/pages/admin/login-history.php')); ?>">Login history</a>
      <?php endif; ?>
      <a href="<?php echo e(app_url('/')); ?>">Main website</a>
      <a href="<?php echo e(app_url('/pages/logout.php')); ?>">Logout</a>
    </nav>
  </div>

  <?php render_flash(); ?>

  <div class="panel">
    <h2>Your portals</h2>
    <?php if (!$links): ?>
      <p class="help">No portal access is assigned to this account yet. Ask a Super Admin to enable Blog, Library, Portal, or Courses under Manage users.</p>
    <?php else: ?>
      <div class="grid-2">
        <?php foreach ($links as $link): ?>
          <a class="btn btn-secondary" style="display:block;text-align:left;padding:1rem 1.1rem;margin-bottom:.65rem;"
             href="<?php echo e(app_url($link['href'])); ?>">
            <strong style="display:block;"><?php echo e($link['title']); ?></strong>
            <span style="font-size:.88rem;opacity:.8;"><?php echo e($link['note']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Account</h2>
    <table class="data">
      <tr><th>Username</th><td><?php echo e((string) $user['username']); ?></td></tr>
      <tr><th>Email</th><td><?php echo e((string) $user['email']); ?></td></tr>
      <tr><th>Phone</th><td><?php echo e((string) ($user['phone'] ?: '—')); ?></td></tr>
      <tr><th>Role</th><td><?php echo e((string) $user['role_name']); ?></td></tr>
      <tr>
        <th>Portal access</th>
        <td>
          <?php
            $bits = [];
            foreach ($labels as $key => $label) {
                if (user_has_module($key)) {
                    $bits[] = $label;
                }
            }
            echo $bits ? e(implode(' · ', $bits)) : '—';
          ?>
        </td>
      </tr>
      <tr><th>Last login</th><td><?php echo e((string) ($user['last_login_at'] ?: '—')); ?></td></tr>
    </table>
  </div>
<?php render_auth_footer(); ?>
