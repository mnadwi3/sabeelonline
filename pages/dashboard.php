<?php
/**
 * Redirect to Admin Hub (main admin home).
 * Location: /pages/dashboard.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
requireSiteAdmin();

redirect('/admin-hub.html');
