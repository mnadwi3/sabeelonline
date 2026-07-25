<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pdo = db();
$admin = current_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        flash('error', 'All password fields are required.');
        redirect('admin/password.php');
    }

    if (strlen($new) < 8) {
        flash('error', 'New password must be at least 8 characters.');
        redirect('admin/password.php');
    }

    if ($new !== $confirm) {
        flash('error', 'New password and confirmation do not match.');
        redirect('admin/password.php');
    }

    if ($new === $current) {
        flash('error', 'New password must be different from the current password.');
        redirect('admin/password.php');
    }

    $st = $pdo->prepare('SELECT id, password_hash FROM tbl_users WHERE id = ? LIMIT 1');
    $st->execute([(int) $admin['id']]);
    $user = $st->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        flash('error', 'Current password is incorrect.');
        redirect('admin/password.php');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE tbl_users SET password_hash = ? WHERE id = ?')
        ->execute([$hash, (int) $user['id']]);

    flash('success', 'Password changed successfully. Use the new password next time you sign in.');
    redirect('admin/password.php');
}

$pageTitle = 'Change Password';
$active = 'password';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="card" style="max-width:480px">
  <h2>Change admin password</h2>
  <p class="muted mb-2">Signed in as <strong><?= e($admin['login'] ?? 'admin') ?></strong></p>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Current password</label>
    <input type="password" name="current_password" required autocomplete="current-password">

    <label class="mt-2">New password</label>
    <input type="password" name="new_password" required minlength="8" autocomplete="new-password">

    <label class="mt-2">Confirm new password</label>
    <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">

    <p class="muted mt-2" style="font-size:0.85rem">Minimum 8 characters.</p>
    <button class="btn btn-primary mt-2" type="submit">Update password</button>
  </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
