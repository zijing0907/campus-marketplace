<?php
// Database connection. Any page or API file that needs the database does:
//   require_once __DIR__ . '/../includes/db.php';
//   $stmt = db()->prepare('SELECT * FROM listings WHERE id = ?');
//   $stmt->execute([$id]);

/**
 * Return the shared PDO connection, creating it on first use.
 * "static" keeps the connection between calls, so a page connects only once.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $configFile = __DIR__ . '/../config/config.php';
        if (!is_file($configFile)) {
            throw new RuntimeException('Missing config/config.php. Copy config/config.example.php and fill it in.');
        }
        $config = require $configFile;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_port'] ?? 3306,
            $config['db_name']
        );

        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            // Throw an exception when a query fails, instead of failing silently.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Return rows as ['column' => value] arrays.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements, the defence against SQL injection (NFR-01).
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}
