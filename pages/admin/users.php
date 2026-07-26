<?php
/**
 * Admin: list / create / disable users, change roles, reset passwords.
 * Location: /pages/admin/users.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin', 'super_admin');

$usersApi = auth()->users();
$roles = $usersApi->listRoles();
$error = '';
$success = '';

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
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $password = (string) ($_POST['password'] ?? '');

                if (!validate_username($username)) {
                    $error = 'Username must be 3–50 characters (letters, numbers, . _ -).';
                } elseif (!validate_email($email)) {
                    $error = 'Please enter a valid email address.';
                } elseif (!validate_phone($phone)) {
                    $error = 'Please enter a valid phone number.';
                } elseif (!validate_password_strength($password)) {
                    $error = password_rules_message();
                } elseif ($usersApi->usernameExists($username) || $usersApi->emailExists($email)) {
                    $error = 'Username or email is already in use.';
                } elseif (!in_array($roleId, array_map(static fn ($r) => (int) $r['id'], $roles), true)) {
                    $error = 'Invalid role selected.';
                } else {
                    // Super Admin role only assignable by Super Admin
                    $roleSlug = '';
                    foreach ($roles as $r) {
                        if ((int) $r['id'] === $roleId) {
                            $roleSlug = (string) $r['slug'];
                            break;
                        }
                    }
                    if ($roleSlug === 'super_admin' && !is_super_admin()) {
                        $error = 'Only a Super Admin can create Super Admin accounts.';
                    } else {
                        $newId = $usersApi->create([
                            'username' => $username,
                            'email' => $email,
                            'password' => $password,
                            'phone' => $phone !== '' ? $phone : null,
                            'full_name' => $fullName,
                            'role_id' => $roleId,
                            'is_active' => 1,
                        ]);
                        auth()->audit((int) current_user_id(), 'admin_create_user', 'Created user #' . $newId);
                        $success = 'User created successfully.';
                    }
                }
            } elseif ($action === 'toggle') {
                $id = (int) ($_POST['user_id'] ?? 0);
                $target = $usersApi->findById($id);
                if (!$target) {
                    $error = 'User not found.';
                } elseif ((int) $target['id'] === (int) current_user_id()) {
                    $error = 'You cannot disable your own account.';
                } elseif (($target['role_slug'] ?? '') === 'super_admin' && !is_super_admin()) {
                    $error = 'Only a Super Admin can disable Super Admin accounts.';
                } else {
                    $newState = !((int) $target['is_active']);
                    $usersApi->setActive($id, $newState);
                    auth()->audit((int) current_user_id(), 'admin_toggle_user', ($newState ? 'Enabled' : 'Disabled') . ' user #' . $id);
                    $success = $newState ? 'User enabled.' : 'User disabled.';
                }
            } elseif ($action === 'role') {
                $id = (int) ($_POST['user_id'] ?? 0);
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $target = $usersApi->findById($id);
                $roleSlug = '';
                foreach ($roles as $r) {
                    if ((int) $r['id'] === $roleId) {
                        $roleSlug = (string) $r['slug'];
                        break;
                    }
                }
                if (!$target || $roleSlug === '') {
                    $error = 'Invalid user or role.';
                } elseif ((int) $target['id'] === (int) current_user_id()) {
                    $error = 'You cannot change your own role here.';
                } elseif (($roleSlug === 'super_admin' || ($target['role_slug'] ?? '') === 'super_admin') && !is_super_admin()) {
                    $error = 'Only a Super Admin can change Super Admin roles.';
                } else {
                    $usersApi->setRole($id, $roleId);
                    auth()->audit((int) current_user_id(), 'admin_change_role', 'User #' . $id . ' → ' . $roleSlug);
                    $success = 'Role updated.';
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
                    $error = 'Only a Super Admin can reset a Super Admin password.';
                } else {
                    $usersApi->setPassword($id, $password);
                    auth()->audit((int) current_user_id(), 'admin_reset_password', 'Reset password for user #' . $id);
                    $success = 'Password reset for ' . $target['username'] . '.';
                }
            }
        } catch (Throwable $e) {
            log_security_error('admin-users', $e);
            $error = 'Unable to complete that action right now.';
        }
    }
}

$list = $usersApi->listUsers();

render_auth_header('Manage Users', true);
?>
  <div class="topbar">
    <strong>User management</strong>
    <nav>
      <a href="<?php echo e(app_url('/pages/dashboard.php')); ?>">Dashboard</a>
      <a href="<?php echo e(app_url('/pages/admin/login-history.php')); ?>">Login history</a>
      <a href="<?php echo e(app_url('/pages/logout.php')); ?>">Logout</a>
    </nav>
  </div>

  <?php if ($error !== ''): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

  <div class="panel">
    <h2>Create user</h2>
    <form method="post" action="">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="create">
      <div class="grid-2">
        <div class="form-group">
          <label for="username">Username</label>
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
          <label for="phone">Phone</label>
          <input id="phone" name="phone" maxlength="20">
        </div>
        <div class="form-group">
          <label for="role_id">Role</label>
          <select id="role_id" name="role_id" required>
            <?php foreach ($roles as $role): ?>
              <?php if (($role['slug'] ?? '') === 'super_admin' && !is_super_admin()) continue; ?>
              <option value="<?php echo (int) $role['id']; ?>"><?php echo e((string) $role['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="password">Temporary password</label>
          <input type="password" id="password" name="password" required autocomplete="new-password">
          <p class="help"><?php echo e(password_rules_message()); ?></p>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Create user</button>
    </form>
  </div>

  <div class="panel">
    <h2>All users</h2>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $row): ?>
          <tr>
            <td><?php echo (int) $row['id']; ?></td>
            <td>
              <strong><?php echo e((string) $row['username']); ?></strong><br>
              <span style="color:#667;"><?php echo e((string) $row['email']); ?></span>
              <?php if (!empty($row['full_name'])): ?>
                <br><?php echo e((string) $row['full_name']); ?>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" action="" class="actions">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                <select name="role_id">
                  <?php foreach ($roles as $role): ?>
                    <?php if (($role['slug'] ?? '') === 'super_admin' && !is_super_admin()) continue; ?>
                    <option value="<?php echo (int) $role['id']; ?>"
                      <?php echo (int) $row['role_id'] === (int) $role['id'] ? 'selected' : ''; ?>>
                      <?php echo e((string) $role['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-secondary btn-sm" type="submit">Save role</button>
              </form>
            </td>
            <td>
              <?php if ((int) $row['is_active']): ?>
                <span class="badge badge-ok">Active</span>
              <?php else: ?>
                <span class="badge badge-off">Disabled</span>
              <?php endif; ?>
            </td>
            <td><?php echo e((string) ($row['last_login_at'] ?: '—')); ?></td>
            <td>
              <div class="actions">
                <form method="post" action="">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                  <button class="btn btn-secondary btn-sm" type="submit">
                    <?php echo (int) $row['is_active'] ? 'Disable' : 'Enable'; ?>
                  </button>
                </form>
              </div>
              <form method="post" action="" style="margin-top:.55rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                <div class="form-group" style="margin:0;">
                  <input type="password" name="password" placeholder="New strong password" required>
                </div>
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
