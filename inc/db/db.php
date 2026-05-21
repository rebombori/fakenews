<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/schema.php';

/**
 * Get or create a PDO SQLite connection singleton.
 */
function db_sqlite(): PDO
{
    static $pdo;
    if ($pdo !== null) return $pdo;

    // Determine database path from app config
    $config = app_config();
    $dbPath = $config['root'] . '/data/fakenews.db';

    // Ensure data directory exists
    $dataDir = dirname($dbPath);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    // Create PDO connection
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    // Ensure schema exists
    ensure_schema($pdo);

    return $pdo;
}
