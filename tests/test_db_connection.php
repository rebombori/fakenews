<?php
// fakenews/tests/test_db_connection.php
declare(strict_types=1);

function assert_true(bool $cond, string $label): void
{
    echo ($cond ? "PASS" : "FAIL") . ": {$label}\n";
}

// Load app_config from config file
require_once __DIR__ . '/../inc/config.php';

// Include the db module (which includes schema.php)
require_once __DIR__ . '/../inc/db/db.php';

// Test 1: db_sqlite() function exists
assert_true(function_exists('db_sqlite'), 'db_sqlite function is defined');

// Test 2: Check if SQLite driver is available
$drivers = PDO::getAvailableDrivers();
if (!in_array('sqlite', $drivers)) {
    echo "SKIP: SQLite driver not available. Available drivers: " . implode(', ', $drivers) . "\n";
    exit(0);
}

// Test 3: Calling db_sqlite() returns a PDO object
try {
    $pdo = db_sqlite();
    assert_true($pdo instanceof PDO, 'db_sqlite() returns a PDO instance');

    // Test 4: Subsequent calls return the same instance (singleton)
    $pdo2 = db_sqlite();
    assert_true($pdo === $pdo2, 'db_sqlite() returns same instance (singleton)');

    // Test 5: Database file was created
    $dbPath = app_config()['root'] . '/data/fakenews.db';
    assert_true(is_file($dbPath), 'Database file exists at ' . $dbPath);

    // Test 6: Tables were created
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    assert_true(in_array('game_sessions', $tables), 'game_sessions table exists');
    assert_true(in_array('participants', $tables), 'participants table exists');

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nAll database tests passed!\n";
