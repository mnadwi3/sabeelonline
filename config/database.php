<?php
/**
 * PDO database connection (singleton).
 *
 * Location: /config/database.php
 */

declare(strict_types=1);

/**
 * Return a shared PDO instance.
 *
 * @throws PDOException when connection fails (caller should catch / log)
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    /** @var array $config */
    $config = $GLOBALS['app_config'] ?? require __DIR__ . '/config.php';
    $db = $config['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
