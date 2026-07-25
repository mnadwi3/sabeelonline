<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login = trim($_POST['login_name'] ?? '');
    $pass = $_POST['password'] ?? '';
    try {
        $stmt = db()->prepare('SELECT * FROM tbl_users WHERE login_name = ? LIMIT 1');
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['admin_id'] = (int) $user['id'];
            $_SESSION['admin_name'] = $user['t_name'];
            $_SESSION['admin_login'] = $user['login_name'];
            redirect('admin/index.php');
        }
        $error = 'Invalid username or password.';
    } catch (Throwable $e) {
        $error = 'Database error. Run install.php first.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — <?= e(app_config('app_name')) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <div class="text-center mb-2">
        <img class="logo-header" src="<?= e(asset('images/logo_header.png')) ?>" alt="Logo">
        <h1 class="mt-2">Admin Login</h1>
        <p class="muted"><?= e(app_config('app_tagline')) ?></p>
      </div>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Username</label>
        <input name="login_name" required autocomplete="username" value="<?= e($_POST['login_name'] ?? '') ?>">
        <label class="mt-2">Password</label>
        <input type="password" name="password" required autocomplete="current-password">
        <button class="btn btn-primary mt-2" type="submit" style="width:100%">Sign In</button>
      </form>
      <p class="text-center mt-2"><a href="<?= e(base_url('public/index.php')) ?>">← Student Result Portal</a></p>
    </div>
  </div>
</body>
</html>
