<?php
/**
 * One-time installer: creates DB tables and default admin (admin / Admin@123)
 */
declare(strict_types=1);

$configFile = __DIR__ . '/config/config.php';
if (!is_file($configFile)) {
    exit('Missing config/config.php');
}
$config = require $configFile;
$localConfig = __DIR__ . '/config/config.local.php';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$isLocalHost = $host === 'localhost'
    || str_starts_with($host, 'localhost:')
    || $host === '127.0.0.1'
    || str_starts_with($host, '127.0.0.1:');
if ($isLocalHost && is_file($localConfig)) {
    $config = array_merge($config, require $localConfig);
}

$messages = [];
$ok = false;

/**
 * Split SQL file into executable statements (PDO MySQL is single-statement by default).
 */
function split_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $stmt = trim($part);
        if ($stmt === '' || strtoupper($stmt) === 'SET NAMES UTF8MB4') {
            // Still run SET NAMES if present as lone statement
            if (stripos($stmt, 'SET NAMES') === 0) {
                $out[] = $stmt;
            }
            continue;
        }
        $out[] = rtrim($stmt, "; \t\r\n");
    }
    return array_values(array_filter($out, static fn($s) => $s !== ''));
}

function run_sql_file(PDO $pdo, string $path, array &$messages): void
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing SQL file: ' . basename($path));
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Could not read ' . basename($path));
    }
    $statements = split_sql_statements($raw);
    $n = 0;
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
        $n++;
    }
    $messages[] = basename($path) . ': ran ' . $n . ' statement(s).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (trim((string) ($config['db_pass'] ?? '')) === '') {
            throw new RuntimeException('Set db_pass in config/config.php to your MySQL password first.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['db_host'],
            $config['db_port'],
            $config['db_name'],
            $config['db_charset']
        );
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        run_sql_file($pdo, __DIR__ . '/sql/schema.sql', $messages);
        run_sql_file($pdo, __DIR__ . '/sql/seed.sql', $messages);

        $hash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $pdo->prepare(
            'INSERT INTO tbl_users (t_name, login_name, password_hash) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), t_name = VALUES(t_name)'
        )->execute(['System Administrator', 'admin', $hash]);

        $messages[] = 'Admin ready: username admin / password Admin@123 — change after first login.';
        $messages[] = 'Delete install.php after setup.';
        $ok = true;
    } catch (Throwable $e) {
        $messages[] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install — Sabeel Results</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <h1>Install RMS</h1>
      <p class="muted">Creates MySQL tables for Sabeel Us Salaam Result Management.</p>
      <?php foreach ($messages as $m): ?>
        <div class="alert <?= $ok ? 'alert-success' : 'alert-error' ?>"><?= htmlspecialchars($m) ?></div>
      <?php endforeach; ?>
      <?php if (!$ok): ?>
        <form method="post">
          <p class="muted mb-2">Uses <code>config/config.php</code>. Set a real <code>db_pass</code> before running.</p>
          <button class="btn btn-primary" type="submit" style="width:100%">Run Installation</button>
        </form>
      <?php else: ?>
        <div class="portal-actions">
          <a class="btn btn-primary" href="admin/login.php">Admin Login</a>
          <a class="btn btn-outline" href="public/index.php">Result Portal</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
