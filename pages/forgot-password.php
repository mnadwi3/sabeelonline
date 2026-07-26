<?php
/**
 * Request a password-reset email.
 * Location: /pages/forgot-password.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect((string) $GLOBALS['app_config']['dashboard_path']);
}

$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        try {
            $result = auth()->requestPasswordReset($email);
            $message = $result['message'];
        } catch (Throwable $e) {
            log_security_error('forgot-password', $e);
            $error = 'Unable to process that request right now.';
        }
    }
}

render_auth_header('Forgot Password');
?>
<div class="auth-card">
  <h1>Forgot password</h1>
  <p class="subtitle">Enter your account email. If it exists, we will send a secure reset link.</p>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
  <?php endif; ?>
  <?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo e($message); ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <?php echo csrf_field(); ?>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required maxlength="190"
             value="<?php echo e($email); ?>" autocomplete="email">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
  </form>

  <div class="auth-links">
    <a href="<?php echo e(app_url('/pages/login.php')); ?>">Back to login</a>
  </div>
</div>
<?php render_auth_footer(); ?>
