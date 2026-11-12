<?php
/**
 * PDO connection factory. Prepared statements only, exceptions on, and no
 * emulation (so MySQL itself sees the parameterised query).
 */

declare(strict_types=1);

/** Database section of config.php. */
function db_config(): array
{
    $config = require TT_ROOT . '/config.php';
    return $config['db'] ?? [];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'] ?? '127.0.0.1',
        (int)($c['port'] ?? 3306),
        $c['name'] ?? '',
        $c['charset'] ?? 'utf8mb4'
    );

    try {
        $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Credentials are never included here; only a hint useful for setup.
        tt_fatal(
            'DB connection failed: ' . $e->getMessage(),
            'The application cannot reach the database. Check config.php and make sure sql/database.sql has been imported.'
        );
    }

    return $pdo;
}

/** Shared repository instance. */
function tasks(): TaskRepository
{
    static $repo = null;
    return $repo ??= new TaskRepository(db());
}
