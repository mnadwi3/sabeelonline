<?php
/**
 * Admin: view login / logout / failed login / password change history.
 * Location: /pages/admin/login-history.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin', 'super_admin');

$rows = [];
$error = '';

try {
    $rows = auth()->recentLoginHistory(150);
} catch (Throwable $e) {
    log_security_error('login-history', $e);
    $error = 'Unable to load login history.';
}

render_auth_header('Login History', true);
?>
  <div class="topbar">
    <strong>Login history &amp; audit</strong>
    <nav>
      <a href="<?php echo e(app_url('/pages/dashboard.php')); ?>">Dashboard</a>
      <a href="<?php echo e(app_url('/pages/admin/users.php')); ?>">Manage users</a>
      <a href="<?php echo e(app_url('/pages/logout.php')); ?>">Logout</a>
    </nav>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="panel">
    <h2>Recent events</h2>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>When</th>
            <th>Event</th>
            <th>User</th>
            <th>IP</th>
            <th>Browser</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6">No events recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?php echo e((string) $row['created_at']); ?></td>
            <td><?php echo e((string) $row['event_type']); ?></td>
            <td>
              <?php
                $label = trim((string) (($row['username'] ?? '') ?: ($row['email'] ?? '')));
                echo e($label !== '' ? $label : ('#' . (string) ($row['user_id'] ?? '—')));
              ?>
            </td>
            <td><?php echo e((string) ($row['ip_address'] ?? '—')); ?></td>
            <td style="max-width:220px;word-break:break-word;"><?php echo e((string) ($row['user_agent'] ?? '—')); ?></td>
            <td><?php echo e((string) ($row['details'] ?? '')); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php render_auth_footer(); ?>
