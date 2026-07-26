<?php
/**
 * Blog login — redirects to the unified staff login.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$redirect = '/blog/dashboard.php';
header('Location: ' . sabeel_login_url($redirect));
exit;
