<?php
/**
 * Shared helpers: escaping, validation, redirects, flash messages.
 *
 * Location: /includes/functions.php
 */

declare(strict_types=1);

/**
 * Escape for safe HTML output (XSS protection).
 */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Absolute app URL for a path.
 */
function app_url(string $path = ''): string
{
    /** @var array $config */
    $config = $GLOBALS['app_config'];
    $base = trim((string) ($config['app_url'] ?? ''));

    if ($base === '') {
        $scheme = is_https_request() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = $scheme . '://' . $host;
    }

    if ($path === '') {
        return rtrim($base, '/');
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return rtrim($base, '/') . $path;
}

/**
 * Redirect and exit.
 */
function redirect(string $path): void
{
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }
    header('Location: ' . app_url($path));
    exit;
}

/**
 * Flash message (one request).
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Consume flash message.
 *
 * @return array{type:string,message:string}|null
 */
function flash_get(): ?array
{
    if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return [
        'type' => (string) ($flash['type'] ?? 'info'),
        'message' => (string) ($flash['message'] ?? ''),
    ];
}

/**
 * Validate email.
 */
function validate_email(string $email): bool
{
    $email = trim($email);
    if ($email === '' || strlen($email) > 190) {
        return false;
    }
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate username: 3–50 chars, letters, numbers, underscore, dot, hyphen.
 */
function validate_username(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username);
}

/**
 * Strong password rules (min 10, upper, lower, digit, special).
 */
function validate_password_strength(string $password): bool
{
    if (strlen($password) < 10) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    return true;
}

/**
 * Human-readable password rule message.
 */
function password_rules_message(): string
{
    return 'Password must be at least 10 characters and include uppercase, lowercase, a number, and a special character.';
}

/**
 * Validate phone (optional international-ish format).
 */
function validate_phone(string $phone): bool
{
    $phone = trim($phone);
    if ($phone === '') {
        return true; // optional
    }
    return (bool) preg_match('/^\+?[0-9()\-\s]{7,20}$/', $phone);
}

/**
 * Detect whether a stored password looks like a password_hash() output.
 */
function is_password_hashed(string $stored): bool
{
    $info = password_get_info($stored);
    return isset($info['algo']) && (int) $info['algo'] !== 0;
}

/**
 * Verify password with safe plaintext → hash migration.
 * Existing plaintext passwords keep working; on success they are re-hashed in place.
 *
 * @return bool true when credentials match
 */
function verify_password_with_migration(PDO $pdo, int $userId, string $plain, string $stored): bool
{
    if (is_password_hashed($stored)) {
        if (!password_verify($plain, $stored)) {
            return false;
        }
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            $newHash = password_hash($plain, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ?, password_changed_at = COALESCE(password_changed_at, NOW()), updated_at = NOW() WHERE id = ?');
            $stmt->execute([$newHash, $userId]);
        }
        return true;
    }

    // Legacy plaintext (or other non-hash) storage — do not force reset
    if (!hash_equals($stored, $plain)) {
        return false;
    }

    $newHash = password_hash($plain, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ?, password_changed_at = NOW(), updated_at = NOW() WHERE id = ?');
    $stmt->execute([$newHash, $userId]);
    return true;
}

/**
 * Hash a new password.
 */
function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Simple HTML layout wrapper for auth pages.
 */
function render_auth_header(string $title, bool $wide = false): void
{
    /** @var array $config */
    $config = $GLOBALS['app_config'];
    $appName = (string) $config['app_name'];
    $wrapClass = $wide ? 'auth-wrap auth-wide' : 'auth-wrap';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' — ' . e($appName) . '</title>';
    echo '<link rel="stylesheet" href="' . e(app_url('/assets/css/auth.css')) . '">';
    echo '</head><body class="auth-body"><div class="' . e($wrapClass) . '">';
    echo '<header class="auth-brand"><a href="' . e(app_url('/')) . '">' . e($appName) . '</a></header>';
}

/**
 * Close auth layout.
 */
function render_auth_footer(): void
{
    echo '</div></body></html>';
}

/**
 * Show flash alert HTML if present.
 */
function render_flash(): void
{
    $flash = flash_get();
    if (!$flash || $flash['message'] === '') {
        return;
    }
    $type = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info';
    echo '<div class="alert alert-' . e($type) . '" role="alert">' . e($flash['message']) . '</div>';
}
