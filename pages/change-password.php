<?php
/**
 * Change password (authenticated users).
 * Location: /pages/change-password.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        if ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $result = auth()->changePassword((int) current_user_id(), $current, $new);
                if ($result['ok']) {
                    $success = $result['message'];
                    regenerate_session_id();
                } else {
                    $error = $result['message'];
                }
            } catch (Throwable $e) {
                log_security_error('change-password', $e);
                $error = 'Unable to change password right now.';
            }
        }
    }
}

render_auth_header('Change Password');
?>
<div class="auth-card">
  <h1>Change password</h1>
  <p class="subtitle"><?php echo e(password_rules_message()); ?></p>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
  <?php endif; ?>
  <?php if ($success !== ''): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <?php echo csrf_field(); ?>

    <div class="form-group">
      <label for="current_password">Current password</label>
      <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>

    <div class="form-group">
      <label for="new_password">New password</label>
      <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
    </div>

    <div class="form-group">
      <label for="new_password_confirm">Confirm new password</label>
      <input type="password" id="new_password_confirm" name="new_password_confirm" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save new password</button>
  </form>

  <div class="auth-links">
    <a href="<?php echo e(app_url('/pages/dashboard.php')); ?>">Back to dashboard</a>
  </div>
</div>
<?php render_auth_footer(); ?>
