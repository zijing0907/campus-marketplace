<?php
// Database connection. Any page or API file that needs the database calls db():
//   $stmt = db()->prepare('SELECT * FROM listings WHERE id = ?');
//   $stmt->execute([$id]);

require_once __DIR__ . '/functions.php';

/**
 * Return the shared PDO connection, creating it on first use.
 * "static" keeps the connection between calls, so a page connects only once.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = config();

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

        // Make MySQL use the same time zone as PHP (set in bootstrap.php), so the
        // dates it returns are correct wherever the server happens to be located.
        // date('P') gives the offset from UTC, for example "+08:00".
        $pdo->prepare('SET time_zone = ?')->execute([date('P')]);
    }

    return $pdo;
}
