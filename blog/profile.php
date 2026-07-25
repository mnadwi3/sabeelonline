<?php
/**
 * Edit own profile (name, bio, password)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_staff();

$page_title = 'Profile';
$page_mode = 'admin';

$user = db_one($pdo, "SELECT * FROM teachers WHERE id = ? LIMIT 1", [current_user_id()]);
if (!$user) {
    logout_user();
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if ($name === '') {
        $error = 'Name is required.';
    } elseif ($password !== '' && $password !== $password2) {
        $error = 'Passwords do not match.';
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        if ($password !== '') {
            db_run(
                $pdo,
                "UPDATE teachers SET name = ?, bio = ?, password = ? WHERE id = ?",
                [$name, $bio, hash_password($password), current_user_id()]
            );
        } else {
            db_run(
                $pdo,
                "UPDATE teachers SET name = ?, bio = ? WHERE id = ?",
                [$name, $bio, current_user_id()]
            );
        }

        // Update session name
        $_SESSION['user_name'] = $name;
        $success = 'Profile updated successfully.';
        $user = db_one($pdo, "SELECT * FROM teachers WHERE id = ? LIMIT 1", [current_user_id()]);
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

  <div class="form-row">
    <div class="form-group">
      <label for="password">New Password (optional)</label>
      <input type="password" id="password" name="password" placeholder="Leave blank to keep current">
    </div>
    <div class="form-group">
      <label for="password_confirm">Confirm Password</label>
      <input type="password" id="password_confirm" name="password_confirm">
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Save Profile</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
