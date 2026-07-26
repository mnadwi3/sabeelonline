<?php
/**
 * Authenticated user dashboard.
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
        <a href="<?php echo e(app_url('/pages/admin/login-history.php')); ?>">Login history</a>
      <?php endif; ?>
      <a href="<?php echo e(app_url('/')); ?>">Main website</a>
      <a href="<?php echo e(app_url('/pages/logout.php')); ?>">Logout</a>
    </nav>
  </div>

  <?php render_flash(); ?>

  <div class="panel">
    <h2>Account</h2>
    <table class="data">
      <tr><th>Username</th><td><?php echo e((string) $user['username']); ?></td></tr>
      <tr><th>Email</th><td><?php echo e((string) $user['email']); ?></td></tr>
      <tr><th>Phone</th><td><?php echo e((string) ($user['phone'] ?: '—')); ?></td></tr>
      <tr><th>Role</th><td><?php echo e((string) $user['role_name']); ?></td></tr>
      <tr><th>Last login</th><td><?php echo e((string) ($user['last_login_at'] ?: '—')); ?></td></tr>
    </table>
  </div>

  <div class="panel">
    <h2>Security reminders</h2>
    <ul style="margin:0;padding-left:1.1rem;color:#445;">
      <li>Sessions expire after 30 minutes of inactivity.</li>
      <li>Use a unique, strong password for this account.</li>
      <li>Only admins can create users and change roles.</li>
    </ul>
  </div>
<?php render_auth_footer(); ?>
