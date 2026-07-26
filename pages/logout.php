<?php
/**
 * Logout — destroys session and remember-me tokens.
 * Location: /pages/logout.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validate_csrf()) {
        auth()->logout();
    }
} else {
    // Allow GET logout for convenience, but still clear session safely
    auth()->logout();
}

flash_set('info', 'You have been signed out.');
redirect((string) $GLOBALS['app_config']['login_path']);
