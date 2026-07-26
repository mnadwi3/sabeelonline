<?php
/**
 * Authentication: login, logout, remember-me, password reset, audit.
 *
 * Location: /classes/Auth.php
 */

declare(strict_types=1);

final class Auth
{
    private User $users;

    public function __construct(private PDO $pdo, private array $config)
    {
        $this->users = new User($pdo);
    }

    public function users(): User
    {
        return $this->users;
    }

    /**
     * Attempt login with username/email + password.
     *
     * @return array{ok:bool,message:string,user?:array}
     */
    public function attemptLogin(string $identifier, string $password, bool $remember = false): array
    {
        $identifier = trim($identifier);
        $ip = client_ip();
        $ua = client_user_agent();

        if ($identifier === '' || $password === '') {
            return ['ok' => false, 'message' => 'Please enter your username/email and password.'];
        }

        if ($this->isIpRateLimited($ip)) {
            $this->audit(null, 'failed_login', 'Rate limited IP');
            return ['ok' => false, 'message' => 'Too many login attempts from this network. Please try again later.'];
        }

        $user = $this->users->findByLoginIdentifier($identifier);

        if (!$user) {
            $this->recordAttempt(null, $identifier, false);
            $this->audit(null, 'failed_login', 'Unknown user: ' . $identifier);
            return ['ok' => false, 'message' => 'Invalid credentials.'];
        }

        $userId = (int) $user['id'];

        if (!(int) $user['is_active']) {
            $this->recordAttempt($userId, $identifier, false);
            $this->audit($userId, 'failed_login', 'Inactive account');
            return ['ok' => false, 'message' => 'This account has been disabled. Contact an administrator.'];
        }

        if ($this->users->isLocked($user)) {
            $this->recordAttempt($userId, $identifier, false);
            $this->audit($userId, 'failed_login', 'Account locked');
            return ['ok' => false, 'message' => 'Account temporarily locked due to too many failed attempts. Try again in 15 minutes.'];
        }

        $valid = verify_password_with_migration(
            $this->pdo,
            $userId,
            $password,
            (string) $user['password']
        );

        if (!$valid) {
            $this->users->recordFailedLogin(
                $userId,
                (int) $this->config['max_login_attempts'],
                (int) $this->config['lockout_minutes']
            );
            $this->recordAttempt($userId, $identifier, false);
            $this->audit($userId, 'failed_login', 'Bad password');
            return ['ok' => false, 'message' => 'Invalid credentials.'];
        }

        // Success
        $this->users->clearLockout($userId);
        $this->recordAttempt($userId, $identifier, true);
        $this->establishSession($user);

        if ($remember) {
            $this->issueRememberToken($userId);
        }

        $this->audit($userId, 'login', 'Successful login');
        regenerate_csrf_token();

        $fresh = $this->users->findById($userId);
        return ['ok' => true, 'message' => 'Logged in.', 'user' => $fresh ?: $user];
    }

    /**
     * Create authenticated session (prevents fixation via regenerate).
     */
    public function establishSession(array $user): void
    {
        regenerate_session_id();

        $_SESSION['auth_user_id'] = (int) $user['id'];
        $_SESSION['auth_username'] = (string) $user['username'];
        $_SESSION['auth_email'] = (string) $user['email'];
        $_SESSION['auth_full_name'] = (string) ($user['full_name'] ?? '');
        $_SESSION['auth_role'] = (string) ($user['role_slug'] ?? '');
        $_SESSION['auth_role_name'] = (string) ($user['role_name'] ?? '');
        $mods = function_exists('sabeel_parse_modules')
            ? sabeel_parse_modules((string) ($user['modules'] ?? ''))
            : [];
        if (($user['role_slug'] ?? '') === 'super_admin' && function_exists('sabeel_module_keys')) {
            $mods = sabeel_module_keys();
        }
        $_SESSION['auth_modules'] = $mods;
        $blogTeacherId = !empty($user['blog_teacher_id']) ? (int) $user['blog_teacher_id'] : 0;
        $wantsBlog = in_array('blog', $mods, true);
        if ($blogTeacherId <= 0 && $wantsBlog && function_exists('sabeel_ensure_blog_teacher')) {
            $ensured = sabeel_ensure_blog_teacher($this->pdo, $user);
            if ($ensured) {
                $blogTeacherId = $ensured;
            }
        }
        $_SESSION['auth_blog_teacher_id'] = $blogTeacherId;
        // Blog compatibility keys (same SABEELAUTH cookie)
        if ($blogTeacherId > 0 && in_array('blog', $mods, true)) {
            $_SESSION['user_id'] = $blogTeacherId;
            $_SESSION['user_name'] = (string) ($user['full_name'] !== '' ? $user['full_name'] : $user['username']);
            $_SESSION['user_email'] = (string) $user['email'];
            $_SESSION['user_role'] = in_array((string) ($user['role_slug'] ?? ''), ['admin', 'super_admin'], true)
                ? 'admin'
                : 'teacher';
        }
        $_SESSION['_last_activity'] = time();
    }

    public function logout(): void
    {
        $userId = current_user_id();
        if ($userId) {
            $this->audit($userId, 'logout', 'User logged out');
            $this->revokeRememberTokensForUser($userId);
        }

        $this->clearRememberCookie();
        destroy_secure_session();
        start_secure_session();
        regenerate_csrf_token();
    }

    /**
     * Restore session from remember-me cookie when applicable.
     */
    public function attemptRememberLogin(): void
    {
        if (is_logged_in()) {
            return;
        }

        $cookieName = (string) $this->config['remember_cookie'];
        $raw = (string) ($_COOKIE[$cookieName] ?? '');
        if ($raw === '' || !str_contains($raw, ':')) {
            return;
        }

        [$selector, $validator] = explode(':', $raw, 2);
        if ($selector === '' || $validator === '' || !ctype_alnum($selector)) {
            $this->clearRememberCookie();
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if (!$token) {
            $this->clearRememberCookie();
            return;
        }

        $hash = hash('sha256', $validator);
        if (!hash_equals((string) $token['token_hash'], $hash)) {
            // Possible theft — revoke all tokens for this user
            $this->revokeRememberTokensForUser((int) $token['user_id']);
            $this->clearRememberCookie();
            $this->audit((int) $token['user_id'], 'failed_login', 'Remember-me token mismatch');
            return;
        }

        $user = $this->users->findById((int) $token['user_id']);
        if (!$user || !(int) $user['is_active']) {
            $this->revokeRememberTokensForUser((int) $token['user_id']);
            $this->clearRememberCookie();
            return;
        }

        // Rotate remember token
        $this->pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([(int) $token['id']]);
        $this->establishSession($user);
        $this->issueRememberToken((int) $user['id']);
        $this->users->clearLockout((int) $user['id']);
        $this->audit((int) $user['id'], 'login', 'Remember-me login');
    }

    private function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(12)); // 24 hex chars
        $validator = bin2hex(random_bytes(32));
        $hash = hash('sha256', $validator);
        $days = (int) $this->config['remember_days'];

        $stmt = $this->pdo->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, user_agent, ip_address)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?)'
        );
        $stmt->execute([$userId, $selector, $hash, $days, client_user_agent(), client_ip()]);

        $cookieName = (string) $this->config['remember_cookie'];
        setcookie($cookieName, $selector . ':' . $validator, [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'secure' => is_https_request(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function revokeRememberTokensForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    private function clearRememberCookie(): void
    {
        $cookieName = (string) $this->config['remember_cookie'];
        $raw = (string) ($_COOKIE[$cookieName] ?? '');

        if ($raw !== '' && str_contains($raw, ':')) {
            $selector = explode(':', $raw, 2)[0];
            if ($selector !== '') {
                $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
                $stmt->execute([$selector]);
            }
        }

        if ($raw !== '' || isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => is_https_request(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            unset($_COOKIE[$cookieName]);
        }
    }

    private function isIpRateLimited(string $ip): bool
    {
        $limit = (int) $this->config['ip_rate_limit_attempts'];
        $minutes = (int) $this->config['ip_rate_limit_minutes'];

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND was_successful = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$ip, $minutes]);
        return (int) $stmt->fetchColumn() >= $limit;
    }

    private function recordAttempt(?int $userId, string $identifier, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (user_id, identifier, ip_address, user_agent, was_successful)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            function_exists('mb_substr') ? mb_substr($identifier, 0, 190) : substr($identifier, 0, 190),
            client_ip(),
            client_user_agent(),
            $success ? 1 : 0,
        ]);
    }

    public function audit(?int $userId, string $eventType, ?string $details = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_logs (user_id, event_type, ip_address, user_agent, details)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $detailsSafe = $details;
            if ($detailsSafe !== null && function_exists('mb_substr')) {
                $detailsSafe = mb_substr($detailsSafe, 0, 500);
            } elseif ($detailsSafe !== null) {
                $detailsSafe = substr($detailsSafe, 0, 500);
            }
            $stmt->execute([$userId, $eventType, client_ip(), client_user_agent(), $detailsSafe]);
        } catch (Throwable $e) {
            log_security_error('audit', $e);
        }
    }

    /**
     * Create a password-reset token and email the link.
     *
     * @return array{ok:bool,message:string}
     */
    public function requestPasswordReset(string $email): array
    {
        $generic = 'If that email is registered, a reset link has been sent.';

        if (!validate_email($email)) {
            return ['ok' => true, 'message' => $generic];
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !(int) $user['is_active']) {
            return ['ok' => true, 'message' => $generic];
        }

        $userId = (int) $user['id'];

        // Invalidate previous unused tokens
        $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')
            ->execute([$userId]);

        $token = secure_random_hex(32);
        $hash = hash('sha256', $token);
        $minutes = (int) $this->config['reset_token_minutes'];

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)'
        );
        $stmt->execute([$userId, $hash, $minutes, client_ip()]);

        $link = app_url('/pages/reset-password.php?token=' . urlencode($token));
        $subject = 'Password reset — ' . $this->config['app_name'];
        $body = "Hello,\n\nWe received a request to reset your password.\n\n"
            . "Reset link (valid for {$minutes} minutes):\n{$link}\n\n"
            . "If you did not request this, ignore this email.\n";

        $this->sendMail((string) $user['email'], $subject, $body);
        $this->audit($userId, 'password_reset_request', 'Reset email requested');

        return ['ok' => true, 'message' => $generic];
    }

    /**
     * Consume reset token and set a new strong password.
     *
     * @return array{ok:bool,message:string}
     */
    public function resetPasswordWithToken(string $token, string $newPassword): array
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return ['ok' => false, 'message' => 'Invalid or expired reset link.'];
        }
        if (!validate_password_strength($newPassword)) {
            return ['ok' => false, 'message' => password_rules_message()];
        }

        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['ok' => false, 'message' => 'Invalid or expired reset link.'];
        }

        $userId = (int) $row['user_id'];

        $this->users->setPassword($userId, $newPassword);
        $this->pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
            ->execute([(int) $row['id']]);
        $this->revokeRememberTokensForUser($userId);
        $this->audit($userId, 'password_change', 'Password reset via token');

        return ['ok' => true, 'message' => 'Password updated. You can log in now.'];
    }

    /**
     * Change password for the currently authenticated user.
     *
     * @return array{ok:bool,message:string}
     */
    public function changePassword(int $userId, string $current, string $newPassword): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        $ok = verify_password_with_migration(
            $this->pdo,
            $userId,
            $current,
            (string) $user['password']
        );
        if (!$ok) {
            return ['ok' => false, 'message' => 'Current password is incorrect.'];
        }
        if (!validate_password_strength($newPassword)) {
            return ['ok' => false, 'message' => password_rules_message()];
        }
        if (hash_equals($current, $newPassword)) {
            return ['ok' => false, 'message' => 'New password must be different from the current password.'];
        }

        $this->users->setPassword($userId, $newPassword);
        $this->revokeRememberTokensForUser($userId);
        $this->audit($userId, 'password_change', 'Password changed by user');

        return ['ok' => true, 'message' => 'Password changed successfully.'];
    }

    /**
     * @return list<array>
     */
    public function recentLoginHistory(int $limit = 100, ?int $userId = null): array
    {
        $limit = max(1, min(500, $limit));
        if ($userId) {
            $stmt = $this->pdo->prepare(
                'SELECT a.*, u.username, u.email
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.user_id = ? AND a.event_type IN (\'login\',\'logout\',\'failed_login\',\'password_change\')
                 ORDER BY a.created_at DESC
                 LIMIT ' . $limit
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->pdo->query(
                'SELECT a.*, u.username, u.email
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.event_type IN (\'login\',\'logout\',\'failed_login\',\'password_change\')
                 ORDER BY a.created_at DESC
                 LIMIT ' . $limit
            );
        }
        return $stmt->fetchAll() ?: [];
    }

    private function sendMail(string $to, string $subject, string $body): void
    {
        $from = (string) $this->config['mail_from'];
        $fromName = (string) $this->config['mail_from_name'];
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: ' . sprintf('%s <%s>', $this->encodeMailHeader($fromName), $from),
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$ok) {
            error_log('[SabeelAuth] mail() failed for password reset to ' . $to);
            // On Hostinger local/dev, log the link so admins can still reset
            error_log('[SabeelAuth] Reset body: ' . str_replace("\n", ' | ', $body));
        }
    }

    private function encodeMailHeader(string $value): string
    {
        return preg_replace('/[\r\n]+/', '', $value) ?? $value;
    }
}
