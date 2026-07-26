<?php
/**
 * Security headers and request helpers.
 *
 * Location: /includes/security.php
 */

declare(strict_types=1);

/**
 * Send recommended security headers (safe for Hostinger shared hosting).
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Whether the current request is HTTPS (Hostinger may set X-Forwarded-Proto).
 */
function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $proto === 'https';
}

/**
 * Client IP (best-effort; Hostinger may proxy).
 */
function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '0.0.0.0';
}

/**
 * Truncated User-Agent for storage.
 */
function client_user_agent(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($ua, 0, 500);
    }
    return substr($ua, 0, 500);
}

/**
 * Cryptographically secure random hex string.
 */
function secure_random_hex(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Constant-time string compare.
 */
function hash_equals_safe(string $known, string $user): bool
{
    return hash_equals($known, $user);
}

/**
 * Log an application error without exposing details to users.
 */
function log_security_error(string $context, Throwable $e): void
{
    error_log('[SabeelAuth] ' . $context . ': ' . $e->getMessage());
}
