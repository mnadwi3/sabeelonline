<?php
/**
 * Bootstrap the secure authentication module.
 *
 * Include this at the top of every auth-related page:
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 *
 * Location: /includes/bootstrap.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$GLOBALS['app_config'] = require dirname(__DIR__) . '/config/config.php';

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Auth.php';
require_once __DIR__ . '/auth.php';

send_security_headers();
start_secure_session();

try {
    $pdo = db();
    $GLOBALS['auth_service'] = new Auth($pdo, $GLOBALS['app_config']);
    $GLOBALS['auth_service']->attemptRememberLogin();
} catch (Throwable $e) {
    log_security_error('bootstrap', $e);
    http_response_code(503);
    exit('The authentication service is temporarily unavailable. Please try again later.');
}
