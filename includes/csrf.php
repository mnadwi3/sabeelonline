<?php
/**
 * CSRF token generation and validation.
 *
 * Location: /includes/csrf.php
 */

declare(strict_types=1);

/**
 * Get or create the current CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        regenerate_csrf_token();
    }
    return (string) $_SESSION['_csrf_token'];
}

/**
 * Issue a new CSRF token (call after successful state-changing actions).
 */
function regenerate_csrf_token(): string
{
    $_SESSION['_csrf_token'] = secure_random_hex(32);
    $_SESSION['_csrf_issued_at'] = time();
    return (string) $_SESSION['_csrf_token'];
}

/**
 * Hidden input for forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Validate CSRF from POST (or custom array). Regenerates token after success.
 */
function validate_csrf(?string $token = null): bool
{
    $token = $token ?? (string) ($_POST['_csrf'] ?? '');
    $sessionToken = (string) ($_SESSION['_csrf_token'] ?? '');

    if ($token === '' || $sessionToken === '') {
        return false;
    }

    if (!hash_equals($sessionToken, $token)) {
        return false;
    }

    // One-time use: rotate after successful validation
    regenerate_csrf_token();
    return true;
}

/**
 * Require valid CSRF or abort with 403.
 */
function require_csrf(): void
{
    if (!validate_csrf()) {
        http_response_code(403);
        exit('Invalid or missing security token. Please go back and try again.');
    }
}
