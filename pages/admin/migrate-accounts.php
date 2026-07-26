<?php
/**
 * One-time import: Blog teachers + Student Portal admins → unified users.
 * Location: /pages/admin/migrate-accounts.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('super_admin');

$error = '';
$success = '';
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $report = sabeel_import_legacy_accounts(db());
            auth()->audit((int) current_user_id(), 'admin_migrate_accounts', json_encode([
                'teachers' => $report['teachers'],
                'portal' => $report['portal'],
                'skipped' => $report['skipped'],
            ]));
            $success = 'Import finished. Existing passwords were kept — users can sign in at /pages/login.php with the same credentials.';
        } catch (Throwable $e) {
            log_security_error('migrate-accounts', $e);
            $error = 'Import failed. Check the server error log.';
        }
    }
}

render_auth_header('Import legacy accounts', true);
?>
  <div class="topbar">
    <strong>Import legacy accounts</strong>
    <nav>
      <a href="<?php echo e(app_url('/pages/admin/users.php')); ?>">Manage users</a>
      <a href="<?php echo e(app_url('/pages/dashboard.php')); ?>">Dashboard</a>
      <a href="<?php echo e(app_url('/pages/logout.php')); ?>">Logout</a>
    </nav>
  </div>

  <?php if ($error !== ''): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

  <div class="panel">
    <h2>What this does</h2>
    <ul style="margin:0;padding-left:1.1rem;color:#445;line-height:1.55;">
      <li>Copies <strong>Blog</strong> accounts from <code>teachers</code> into <code>users</code> (Blog access enabled). Passwords stay the same.</li>
      <li>Copies <strong>Student Portal</strong> admins from <code>tbl_users</code> into <code>users</code> (Portal access enabled). Passwords stay the same.</li>
      <li>Gives your Super Admin account access to every portal.</li>
      <li>Does <em>not</em> delete or change rows in <code>teachers</code> or <code>tbl_users</code>.</li>
      <li>Safe to run more than once — already-imported emails/usernames are skipped.</li>
    </ul>
    <p class="help" style="margin-top:1rem;">
      After import, everyone signs in at
      <a href="<?php echo e(app_url('/pages/login.php')); ?>">Admin Login</a>
      with their existing password. One Admin account opens every panel via Admin Hub.
    </p>
    <form method="post" action="" style="margin-top:1.25rem;">
      <?php echo csrf_field(); ?>
      <button type="submit" class="btn btn-primary">Import Blog + Portal accounts now</button>
    </form>
  </div>

  <?php if (is_array($report)): ?>
  <div class="panel">
    <h2>Last import result</h2>
    <table class="data">
      <tr><th>Blog teachers imported</th><td><?php echo (int) $report['teachers']; ?></td></tr>
      <tr><th>Portal admins imported</th><td><?php echo (int) $report['portal']; ?></td></tr>
      <tr><th>Skipped (already exist / empty)</th><td><?php echo (int) $report['skipped']; ?></td></tr>
    </table>
    <?php if (!empty($report['errors'])): ?>
      <ul style="color:#b42318;">
        <?php foreach ($report['errors'] as $err): ?>
          <li><?php echo e((string) $err); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>
<?php render_auth_footer(); ?>
