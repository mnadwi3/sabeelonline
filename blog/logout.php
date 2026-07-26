<?php
/**
 * Logout — clear unified session and return to staff login.
 */

require_once __DIR__ . '/includes/auth.php';

logout_user();

header('Location: ../pages/login.php');
exit;
