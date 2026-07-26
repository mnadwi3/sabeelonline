<?php
/**
 * Secure PHP session bootstrap.
 *
 * Location: /includes/session.php
 */

declare(strict_types=1);

/**
 * Start a hardened session (once).
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        enforce_session_timeout();
        return;
    }

    /** @var array $config */
    $config = $GLOBALS['app_config'];

    $secure = is_https_request();
    $lifetime = (int) $config['session_lifetime'];

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    // Lax so post-login redirects into Blog / Library / Portal keep the session cookie
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');

    session_name((string) $config['session_name']);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Bind session to a fingerprint to reduce hijacking risk
    $fingerprint = session_fingerprint();
    if (!isset($_SESSION['_fingerprint'])) {
        $_SESSION['_fingerprint'] = $fingerprint;
    } elseif (!hash_equals((string) $_SESSION['_fingerprint'], $fingerprint)) {
        destroy_secure_session();
        start_secure_session();
        return;
    }

    if (!isset($_SESSION['_created_at'])) {
        $_SESSION['_created_at'] = time();
    }

    enforce_session_timeout();
    maybe_regenerate_session();
}

/**
 * Soft fingerprint (UA + IP /24 or /64) — avoids logging out on every IP change
 * while still detecting obvious session theft across networks.
 */
function session_fingerprint(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = client_ip();
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $ip = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $ip = implode(':', array_slice($parts, 0, 4)) . '::';
    }
    return hash('sha256', $ua . '|' . $ip);
}

/**
 * Idle timeout (30 minutes by default).
 */
function enforce_session_timeout(): void
{
    /** @var array $config */
    $config = $GLOBALS['app_config'];
    $lifetime = (int) $config['session_lifetime'];
    $now = time();

    if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $lifetime) {
        destroy_secure_session();
        start_secure_session();
        return;
    }

    $_SESSION['_last_activity'] = $now;
}

/**
 * Periodically regenerate session ID (session fixation / fixation mitigation).
 */
function maybe_regenerate_session(): void
{
    /** @var array $config */
    $config = $GLOBALS['app_config'];
    $interval = (int) $config['session_regenerate_interval'];
    $last = (int) ($_SESSION['_last_regenerate'] ?? 0);

    if ($last === 0 || (time() - $last) >= $interval) {
        session_regenerate_id(true);
        $_SESSION['_last_regenerate'] = time();
    }
}

/**
 * Force new session id (call after privilege change / login).
 */
function regenerate_session_id(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['_last_regenerate'] = time();
    $_SESSION['_fingerprint'] = session_fingerprint();
}

/**
 * Fully destroy session + cookie.
 */
function destroy_secure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }

    session_destroy();
}
