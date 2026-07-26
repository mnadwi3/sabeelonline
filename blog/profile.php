<?php
/**
 * Edit own profile (name, bio). Password: /pages/change-password.php
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_staff();

$page_title = 'Profile';
$page_mode = 'admin';

$user = db_one($pdo, 'SELECT * FROM teachers WHERE id = ? LIMIT 1', [current_user_id()]);
if (!$user) {
    logout_user();
    header('Location: ' . sabeel_login_url('/blog/dashboard.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        db_run(
            $pdo,
            'UPDATE teachers SET name = ?, bio = ? WHERE id = ?',
            [$name, $bio, current_user_id()]
        );

        // Keep unified users.full_name in sync when present
        if (!empty($_SESSION['auth_user_id'])) {
            try {
                $pdo->prepare('UPDATE users SET full_name = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$name, (int) $_SESSION['auth_user_id']]);
            } catch (Throwable $e) {
                // ignore if users table unavailable
            }
        }

        $_SESSION['user_name'] = $name;
        $success = 'Profile updated successfully.';
        $user = db_one($pdo, 'SELECT * FROM teachers WHERE id = ? LIMIT 1', [current_user_id()]);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<form class="form-card" method="post" action="profile.php" style="max-width:640px;">
  <div class="form-group">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" required value="<?php echo e($user['name']); ?>">
  </div>

  <div class="form-group">
    <label for="email">Email (cannot change here)</label>
    <input type="email" id="email" value="<?php echo e($user['email']); ?>" disabled>
  </div>

  <div class="form-group">
    <label for="bio">Bio</label>
    <textarea id="bio" name="bio"><?php echo e($user['bio']); ?></textarea>
  </div>

  <p class="muted" style="margin:0 0 1rem;">
    To change your sign-in password, use
    <a href="/pages/change-password.php">Admin Change Password</a>.
  </p>

  <button type="submit" class="btn btn-primary">Save Profile</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
