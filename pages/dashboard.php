<?php
/**
 * Simple Admin home — every management panel in one place.
 * Location: /pages/dashboard.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
requireSiteAdmin();

$user = auth()->users()->findById((int) current_user_id());
if (!$user) {
    auth()->logout();
    flash_set('error', 'Your session is no longer valid. Please log in again.');
    redirect((string) $GLOBALS['app_config']['login_path']);
}

$panels = [
    ['href' => '/admin-hub.html', 'title' => 'Admin Hub', 'note' => 'Shortcut to every admin tool'],
    ['href' => '/blog/dashboard.php', 'title' => 'Blog Admin', 'note' => 'Posts, categories, teachers'],
    ['href' => '/library/admin.html', 'title' => 'Library Admin', 'note' => 'Upload and organise coursebooks'],
    ['href' => '/courses-admin.html', 'title' => 'Courses Admin', 'note' => 'Homepage course cards'],
    ['href' => '/admissions-admin.html', 'title' => 'Admissions Admin', 'note' => 'Enrolment applications'],
    ['href' => '/student-portal/admin/', 'title' => 'Results Admin', 'note' => 'Students, marks, marksheets'],
];

render_auth_header('Admin Dashboard', true);
?>
  <div class="topbar">
    <div>
      <strong>Admin: <?php echo e(current_user_display_name()); ?></strong>
      <div style="opacity:.85;font-size:.88rem;">Signed in — manage the whole site</div>
    </div>
    <nav>
      <a href="<?php echo e(app_url('/pages/change-password.php')); ?>">Change password</a>
      <a href="<?php echo e(app_url('/pages/admin/users.php')); ?>">Admin accounts</a>
      <a href="<?php echo e(app_url('/')); ?>">Main website</a>
      <a href="<?php echo e(app_url('/pages/logout.php')); ?>">Logout</a>
    </nav>
  </div>

  <?php render_flash(); ?>

  <div class="panel">
    <h2>Management pages</h2>
    <p class="help" style="margin-top:0;">You are logged in as Admin. Open any panel below — no extra passwords.</p>
    <div class="grid-2">
      <?php foreach ($panels as $link): ?>
        <a class="btn btn-secondary" style="display:block;text-align:left;padding:1rem 1.1rem;margin-bottom:.65rem;"
           href="<?php echo e(app_url($link['href'])); ?>">
          <strong style="display:block;"><?php echo e($link['title']); ?></strong>
          <span style="font-size:.88rem;opacity:.8;"><?php echo e($link['note']); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2>Your Admin account</h2>
    <table class="data">
      <tr><th>Admin ID</th><td><?php echo e((string) $user['username']); ?></td></tr>
      <tr><th>Email</th><td><?php echo e((string) $user['email']); ?></td></tr>
      <tr><th>Last login</th><td><?php echo e((string) ($user['last_login_at'] ?: '—')); ?></td></tr>
    </table>
    <p class="help" style="margin-top:1rem;">
      Students do <strong>not</strong> use Admin Login.
      Give each student one <strong>Student ID</strong> from Results Admin — that same ID opens
      <a href="<?php echo e(app_url('/student-portal/public/')); ?>">Download Results</a>
      and the <a href="<?php echo e(app_url('/library/')); ?>">Library</a>.
    </p>
  </div>
<?php render_auth_footer(); ?>
