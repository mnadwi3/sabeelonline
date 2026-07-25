<?php
/**
 * Database connection — Hostinger
 * Put your real MySQL password in $db_pass below (same as hPanel → Databases).
 */

// Hide PHP errors on the live site (safer for visitors)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$db_host = 'localhost';
$db_name = 'u917534606_u123sabeel';
$db_user = 'u917534606_adminpanel';

// >>> Put the real MySQL password between the quotes <<<
$db_pass = 'Madarsa123*';

$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Generic message for visitors (details go to server error log)
    error_log('DB connection failed: ' . $e->getMessage());
    if (defined('DB_THROW_ON_FAIL') && DB_THROW_ON_FAIL) {
        throw $e;
    }
    exit('Database connection failed. Please check includes/db.php settings.');
}

function db_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_run(PDO $pdo, string $sql, array $params = []): bool
{
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}
