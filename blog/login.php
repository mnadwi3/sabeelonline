<?php
/**
 * Login page — Admin and Teacher
 */

require_once __DIR__ . '/includes/auth.php';

// If already logged in, go to dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// When form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $user = attempt_login($email, $password);
        if ($user) {
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Sabeel Blog</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/blog.css">
</head>
<body>
  <div class="login-page">
    <div class="card login-box">
      <h1>Staff Login</h1>
      <p class="subtitle">Admin and Teacher access for the blog system.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required
                 value="<?php echo e($_POST['email'] ?? ''); ?>"
                 placeholder="admin@sabeel.com">
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required
                 placeholder="Your password">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
      </form>

      <p style="margin-top:1rem; text-align:center;">
        <a href="index.php">← Back to Blog</a><br>
        <a href="/">Main Website</a>
      </p>
    </div>
  </div>
</body>
</html>
