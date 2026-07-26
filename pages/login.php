<?php
/**
 * Site Admin login — one ID + password for all management pages.
 * Location: /pages/login.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in() && is_admin()) {
    $redirect = (string) ($_GET['redirect'] ?? '');
    if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
        redirect($redirect);
    }
    redirect((string) $GLOBALS['app_config']['dashboard_path']);
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        try {
            $result = auth()->attemptLogin($identifier, $password, $remember);
            if ($result['ok']) {
                $role = (string) (($result['user']['role_slug'] ?? current_user_role()) ?: '');
                // This page is for Site Admin only (Admin / Super Admin)
                if (!in_array($role, ['admin', 'super_admin'], true)) {
                    auth()->logout();
                    $error = 'This login is for Admin only. Use the correct portal for teacher or student access.';
                } else {
                    $redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? '');
                    if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
                        redirect($redirect);
                    }
                    redirect((string) $GLOBALS['app_config']['dashboard_path']);
                }
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            log_security_error('login', $e);
            $error = 'Unable to sign in right now. Please try again later.';
        }
    }
}

$redirectValue = (string) ($_GET['redirect'] ?? '');

render_auth_header('Admin Login');
?>
<div class="auth-card">
  <h1>Admin Login</h1>
  <p class="subtitle">One Admin ID and password for Blog, Library, Courses, Admissions, Results Admin, and Admin Hub.</p>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
  <?php endif; ?>
  <?php render_flash(); ?>

  <form method="post" action="" autocomplete="on" novalidate>
    <?php echo csrf_field(); ?>
    <?php if ($redirectValue !== ''): ?>
      <input type="hidden" name="redirect" value="<?php echo e($redirectValue); ?>">
    <?php endif; ?>

    <div class="form-group">
      <label for="identifier">Admin ID</label>
      <input type="text" id="identifier" name="identifier" required maxlength="190"
             value="<?php echo e($identifier); ?>" autocomplete="username"
             placeholder="Username or email">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>

    <label class="form-check">
      <input type="checkbox" name="remember" value="1">
      <span>Remember me for 30 days</span>
    </label>

    <button type="submit" class="btn btn-primary btn-block">Sign in as Admin</button>
  </form>

  <div class="auth-links">
    <a href="<?php echo e(app_url('/pages/forgot-password.php')); ?>">Forgot password?</a>
    <a href="<?php echo e(app_url('/')); ?>">← Back to website</a>
  </div>
</div>
<?php render_auth_footer(); ?>
