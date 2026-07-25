<?php
/**
 * Front controller — send visitors to the public Student Portal
 */
declare(strict_types=1);

$query = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';

// Prefer absolute path so /student-portal/ always resolves correctly on Hostinger
$target = '/student-portal/public/index.php' . $query;

header('Location: ' . $target, true, 302);
exit;
