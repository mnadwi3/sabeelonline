<?php
/**
 * Admin only — manage teachers
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('admin');

$page_title = 'Teachers';
$page_mode = 'admin';

$error = '';
$success = '';

// Create / update / delete teacher (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        exit('Invalid request. Please go back and try again.');
    }

    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === current_user_id()) {
            $error = 'You cannot delete your own admin account.';
        } else {
            db_run($pdo, "DELETE FROM teachers WHERE id = ? AND role = 'teacher'", [$id]);
            header('Location: teachers.php?msg=deleted');
            exit;
        }
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $bio = trim($_POST['bio'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $email === '') {
            $error = 'Name and email are required.';
        } elseif ($id === 0 && $password === '') {
            $error = 'Password is required for a new teacher.';
        } else {
            if ($id > 0) {
                $exists = db_one(
                    $pdo,
                    "SELECT id FROM teachers WHERE email = ? AND id != ? LIMIT 1",
                    [$email, $id]
                );
                if ($exists) {
                    $error = 'This email is already registered.';
                } elseif ($password !== '') {
                    db_run(
                        $pdo,
                        "UPDATE teachers
                         SET name = ?, email = ?, password = ?, bio = ?, is_active = ?
                         WHERE id = ? AND role = 'teacher'",
                        [$name, $email, hash_password($password), $bio, $isActive, $id]
                    );
                    header('Location: teachers.php?msg=updated');
                    exit;
                } else {
                    db_run(
                        $pdo,
                        "UPDATE teachers
                         SET name = ?, email = ?, bio = ?, is_active = ?
                         WHERE id = ? AND role = 'teacher'",
                        [$name, $email, $bio, $isActive, $id]
                    );
                    header('Location: teachers.php?msg=updated');
                    exit;
                }
            } else {
                $exists = db_one($pdo, "SELECT id FROM teachers WHERE email = ? LIMIT 1", [$email]);
                if ($exists) {
                    $error = 'This email is already registered.';
                } else {
                    db_run(
                        $pdo,
                        "INSERT INTO teachers (name, email, password, role, bio, is_active)
                         VALUES (?, ?, ?, 'teacher', ?, 1)",
                        [$name, $email, hash_password($password), $bio]
                    );
                    header('Location: teachers.php?msg=created');
                    exit;
                }
            }
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editUser = $editId ? db_one($pdo, "SELECT * FROM teachers WHERE id = ? AND role = 'teacher'", [$editId]) : null;

$teachers = db_all(
    $pdo,
    "SELECT * FROM teachers WHERE role = 'teacher' ORDER BY created_at DESC"
);

$messages = [
    'created' => 'Teacher created.',
    'updated' => 'Teacher updated.',
    'deleted' => 'Teacher deleted.',
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>
<?php if (isset($messages[$msg])): ?>
  <div class="alert alert-success"><?php echo e($messages[$msg]); ?></div>
<?php endif; ?>

<form class="form-card" method="post" action="teachers.php" style="margin-bottom:1.25rem;">
  <h2 style="margin-bottom:1rem;"><?php echo $editUser ? 'Edit Teacher' : 'Create Teacher'; ?></h2>
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo $editUser ? (int) $editUser['id'] : 0; ?>">

  <div class="form-row">
    <div class="form-group">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required
             value="<?php echo e($editUser['name'] ?? ''); ?>">
    </div>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required
             value="<?php echo e($editUser['email'] ?? ''); ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="password">Password <?php echo $editUser ? '(optional)' : ''; ?></label>
      <input type="password" id="password" name="password" <?php echo $editUser ? '' : 'required'; ?>>
    </div>
    <div class="form-group">
      <label for="bio">Bio</label>
      <input type="text" id="bio" name="bio" value="<?php echo e($editUser['bio'] ?? ''); ?>">
    </div>
  </div>

  <?php if ($editUser): ?>
    <div class="form-group">
      <label>
        <input type="checkbox" name="is_active" value="1"
          <?php echo !empty($editUser['is_active']) ? 'checked' : ''; ?>>
        Active (can login)
      </label>
    </div>
  <?php endif; ?>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary"><?php echo $editUser ? 'Update Teacher' : 'Create Teacher'; ?></button>
    <?php if ($editUser): ?>
      <a class="btn" href="teachers.php">Cancel</a>
    <?php endif; ?>
  </div>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$teachers): ?>
        <tr><td colspan="4" class="empty-state">No teachers yet.</td></tr>
      <?php else: ?>
        <?php foreach ($teachers as $teacher): ?>
          <tr>
            <td><?php echo e($teacher['name']); ?></td>
            <td><?php echo e($teacher['email']); ?></td>
            <td><?php echo !empty($teacher['is_active']) ? 'Active' : 'Disabled'; ?></td>
            <td class="btn-group">
              <a class="btn btn-sm btn-primary" href="teachers.php?edit=<?php echo (int) $teacher['id']; ?>">Edit</a>
              <form method="post" action="teachers.php" style="display:inline;" data-confirm="Delete this teacher and their posts?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo (int) $teacher['id']; ?>">
                <button class="btn btn-sm btn-danger" type="submit" name="action" value="delete">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
