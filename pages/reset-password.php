<?php
/**
 * Reset password using a secure one-time token.
 * Location: /pages/reset-password.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect((string) $GLOBALS['app_config']['dashboard_path']);
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $result = auth()->resetPasswordWithToken($token, $password);
                if ($result['ok']) {
                    flash_set('success', $result['message']);
                    redirect('/pages/login.php');
                }
                $error = $result['message'];
            } catch (Throwable $e) {
                log_security_error('reset-password', $e);
                $error = 'Unable to reset password right now.';
            }
        }
    }
}

render_auth_header('Reset Password');
?>
<div class="auth-card">
  <h1>Reset password</h1>
  <p class="subtitle"><?php echo e(password_rules_message()); ?></p>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
  <?php endif; ?>

  <?php if ($token === ''): ?>
    <div class="alert alert-error">Missing reset token. Use the link from your email.</div>
    <div class="auth-links">
      <a href="<?php echo e(app_url('/pages/forgot-password.php')); ?>">Request a new link</a>
    </div>
  <?php else: ?>
    <form method="post" action="">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="token" value="<?php echo e($token); ?>">

      <div class="form-group">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
      </div>

      <div class="form-group">
        <label for="password_confirm">Confirm new password</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-primary btn-block">Update password</button>
    </form>
  <?php endif; ?>

  <div class="auth-links">
    <a href="<?php echo e(app_url('/pages/login.php')); ?>">Back to login</a>
  </div>
</div>
<?php render_auth_footer(); ?>
