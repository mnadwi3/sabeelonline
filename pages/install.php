<?php
/**
 * One-time installer for the auth schema + first Super Admin.
 *
 * SAFE: never drops tables; never modifies existing user rows.
 * Delete or protect this file after installation on Hostinger.
 *
 * Location: /pages/install.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$GLOBALS['app_config'] = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/functions.php';

send_security_headers();

$messages = [];
$errors = [];
$done = false;

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Unable to read SQL file.');
    }

    // Remove line comments carefully, then split on semicolons
    $lines = preg_split('/\R/', $sql) ?: [];
    $buffer = '';
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $buffer .= $line . "\n";
    }

    $statements = array_filter(array_map('trim', explode(';', $buffer)));
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? 'superadmin'));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $fullName = trim((string) ($_POST['full_name'] ?? 'Site Super Admin'));

    try {
        $pdo = db();
        run_sql_file($pdo, dirname(__DIR__) . '/sql/auth_schema.sql');
        $messages[] = 'Schema ensured (CREATE TABLE IF NOT EXISTS). Existing tables were not dropped.';

        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            $messages[] = 'Users already exist (' . $count . '). No accounts were changed.';
            $done = true;
        } else {
            if (!validate_username($username)) {
                throw new InvalidArgumentException('Invalid username.');
            }
            if (!validate_email($email)) {
                throw new InvalidArgumentException('Invalid email.');
            }
            if (!validate_password_strength($password)) {
                throw new InvalidArgumentException(password_rules_message());
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password, full_name, role_id, is_active, password_changed_at)
                 VALUES (?, ?, ?, ?, 1, 1, NOW())'
            );
            $stmt->execute([$username, $email, $hash, $fullName]);
            $messages[] = 'Super Admin account created. You can sign in now.';
            $done = true;
        }
    } catch (Throwable $e) {
        log_security_error('install', $e);
        $errors[] = 'Installation failed. Check database credentials in config/config.php and server error logs.';
        // Friendly detail only for clearly validation issues
        if ($e instanceof InvalidArgumentException) {
            $errors = [$e->getMessage()];
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Auth Installer — Sabeel</title>
  <link rel="stylesheet" href="<?php echo e(app_url('/assets/css/auth.css')); ?>">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <header class="auth-brand"><span style="color:#fff;font-weight:700;">Auth Installer</span></header>
    <div class="auth-card">
      <h1>Install authentication tables</h1>
      <p class="subtitle">
        Creates roles, users, login_attempts, password_resets, remember_tokens, and audit_logs
        using <strong>CREATE TABLE IF NOT EXISTS</strong> only. Existing accounts are never deleted or rewritten.
      </p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
      <?php endforeach; ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert alert-success"><?php echo e($msg); ?></div>
      <?php endforeach; ?>

      <?php if ($done): ?>
        <div class="auth-links">
          <a class="btn btn-primary btn-block" href="<?php echo e(app_url('/pages/login.php')); ?>">Go to login</a>
        </div>
        <p class="help">Delete <code>pages/install.php</code> from Hostinger after setup.</p>
      <?php else: ?>
        <form method="post" action="">
          <div class="form-group">
            <label for="full_name">Super Admin full name</label>
            <input id="full_name" name="full_name" value="Site Super Admin" required>
          </div>
          <div class="form-group">
            <label for="username">Username</label>
            <input id="username" name="username" value="superadmin" required maxlength="50">
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required maxlength="190">
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
            <p class="help"><?php echo e(password_rules_message()); ?></p>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Install &amp; create Super Admin</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
