<?php
/**
 * Password changes use the one Admin Login account.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

header('Location: /pages/change-password.php');
exit;
