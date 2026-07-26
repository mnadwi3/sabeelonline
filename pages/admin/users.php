<?php
/**
 * Optional: create extra Admin accounts / reset passwords.
 * Location: /pages/admin/users.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireSiteAdmin();

$usersApi = auth()->users();
$roles = $usersApi->listRoles();
$error = '';
$success = '';

// Only Admin / Super Admin roles in the simple workflow
$adminRoles = array_values(array_filter(
    $roles,
    static fn ($r) => in_array((string) ($r['slug'] ?? ''), ['admin', 'super_admin'], true)
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $username = trim((string) ($_POST['username'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $fullName = trim((string) ($_POST['full_name'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $roleId = (int) ($_POST['role_id'] ?? 0);

                $roleSlug = '';
                foreach ($adminRoles as $r) {
                    if ((int) $r['id'] === $roleId) {
                        $roleSlug = (string) $r['slug'];
                        break;
                    }
                }

                if (!validate_username($username)) {
                    $error = 'Admin ID must be 3–50 characters (letters, numbers, . _ -).';
                } elseif (!validate_email($email)) {
                    $error = 'Please enter a valid email address.';
                } elseif (!validate_password_strength($password)) {
                    $error = password_rules_message();
                } elseif ($usersApi->usernameExists($username) || $usersApi->emailExists($email)) {
                    $error = 'Admin ID or email is already in use.';
                } elseif ($roleSlug === '') {
                    $error = 'Invalid role.';
                } elseif ($roleSlug === 'super_admin' && !is_super_admin()) {
                    $error = 'Only a Super Admin can create another Super Admin.';
                } else {
                    $newId = $usersApi->create([
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'full_name' => $fullName,
                        'role_id' => $roleId,
                        'modules' => sabeel_module_keys(),
                        'is_active' => 1,
                    ]);
                    auth()->audit((int) current_user_id(), 'admin_create_user', 'Created admin #' . $newId);
                    $success = 'Admin account created. They can sign in at Admin Login.';
                }
            } elseif ($action === 'toggle') {
                $id = (int) ($_POST['user_id'] ?? 0);
                $target = $usersApi->findById($id);
                if (!$target) {
                    $error = 'User not found.';
                } elseif ((int) $target['id'] === (int) current_user_id()) {
                    $error = 'You cannot disable your own account.';
                } elseif (($target['role_slug'] ?? '') === 'super_admin' && !is_super_admin()) {
                    $error = 'Only a Super Admin can disable a Super Admin.';
                } else {
                    $newState = !((int) $target['is_active']);
                    $usersApi->setActive($id, $newState);
                    $success = $newState ? 'Account enabled.' : 'Account disabled.';
                }
            } elseif ($action === 'reset_password') {
                $id = (int) ($_POST['user_id'] ?? 0);
                $password = (string) ($_POST['password'] ?? '');
                $target = $usersApi->findById($id);
                if (!$target) {
                    $error = 'User not found.';
                } elseif (!validate_password_strength($password)) {
                    $error = password_rules_message();
                } elseif (($target['role_slug'] ?? '') === 'super_admin' && !is_super_admin()) {
                    $error = 'Only a Super Admin can reset that password.';
                } else {
                    $usersApi->setPassword($id, $password);
                    $success = 'Password reset for ' . $target['username'] . '.';
                }
            }
        } catch (Throwable $e) {
            log_security_error('admin-users', $e);
            $error = 'Unable to complete that action right now.';
        }
    }
}

$list = array_values(array_filter(
    $usersApi->listUsers(),
    static fn ($row) => in_array((string) ($row['role_slug'] ?? ''), ['admin', 'super_admin'], true)
));

render_auth_header('Admin accounts', true);
?>
  <div class="topbar">
    <strong>Admin accounts</strong>
    <nav>
      <a href="<?php echo e(app_url('/pages/dashboard.php')); ?>">Dashboard</a>
      <a href="<?php echo e(app_url('/pages/logout.php')); ?>">Logout</a>
    </nav>
  </div>

  <?php if ($error !== ''): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

  <div class="panel">
    <h2>Create another Admin (optional)</h2>
    <p class="help" style="margin-top:0;">Most sites only need one Admin. Extra admins can also open every management page.</p>
    <form method="post" action="">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="create">
      <div class="grid-2">
        <div class="form-group">
          <label for="username">Admin ID</label>
          <input id="username" name="username" required maxlength="50">
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required maxlength="190">
        </div>
        <div class="form-group">
          <label for="full_name">Full name</label>
          <input id="full_name" name="full_name" maxlength="120">
        </div>
        <div class="form-group">
          <label for="role_id">Type</label>
          <select id="role_id" name="role_id" required>
            <?php foreach ($adminRoles as $role): ?>
              <?php if (($role['slug'] ?? '') === 'super_admin' && !is_super_admin()) continue; ?>
              <option value="<?php echo (int) $role['id']; ?>"><?php echo e((string) $role['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="new-password">
          <p class="help"><?php echo e(password_rules_message()); ?></p>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Create Admin</button>
    </form>
  </div>

  <div class="panel">
    <h2>Admin list</h2>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Admin ID</th>
            <th>Type</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $row): ?>
          <tr>
            <td>
              <strong><?php echo e((string) $row['username']); ?></strong><br>
              <span style="color:#667;"><?php echo e((string) $row['email']); ?></span>
            </td>
            <td><?php echo e((string) $row['role_name']); ?></td>
            <td>
              <?php if ((int) $row['is_active']): ?>
                <span class="badge badge-ok">Active</span>
              <?php else: ?>
                <span class="badge badge-off">Disabled</span>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" action="" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                <button class="btn btn-secondary btn-sm" type="submit">
                  <?php echo (int) $row['is_active'] ? 'Disable' : 'Enable'; ?>
                </button>
              </form>
              <form method="post" action="" style="margin-top:.55rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                <input type="password" name="password" placeholder="New strong password" required>
                <button class="btn btn-danger btn-sm" type="submit" style="margin-top:.4rem;">Reset password</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php render_auth_footer(); ?>
