<?php
// fakenews/tests/test_schema.php
declare(strict_types=1);

function assert_true(bool $cond, string $label): void
{
    echo ($cond ? "PASS" : "FAIL") . ": {$label}\n";
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: pdo_sqlite not available in this environment (required in production)\n";
    exit(0);
}

// Use in-memory DB for tests
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA journal_mode=WAL');
$pdo->exec('PRAGMA foreign_keys=ON');

require_once __DIR__ . '/../inc/db/schema.php';
ensure_schema($pdo);

// Verify tables
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
$tableNames = array_column($tables, 'name');
assert_true(in_array('game_sessions', $tableNames), 'game_sessions table exists');
assert_true(in_array('participants', $tableNames),   'participants table exists');

// Verify game_sessions columns
$cols = $pdo->query("PRAGMA table_info(game_sessions)")->fetchAll();
$colNames = array_column($cols, 'name');
foreach (['id','session_token','started_at','completed_at','completed','score','total_cards','lang','device_type','browser','ip_anon','country','city'] as $col) {
    assert_true(in_array($col, $colNames), "game_sessions has column: {$col}");
}

// Verify participants columns
$cols = $pdo->query("PRAGMA table_info(participants)")->fetchAll();
$colNames = array_column($cols, 'name');
foreach (['id','email','score','total_cards','submitted_at','session_token'] as $col) {
    assert_true(in_array($col, $colNames), "participants has column: {$col}");
}

// Idempotency: calling ensure_schema twice must not error
ensure_schema($pdo);
assert_true(true, 'ensure_schema is idempotent');
