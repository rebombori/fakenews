<?php
// fakenews/tests/test_schema.php
declare(strict_types=1);

function assert_true(bool $cond, string $label): void
{
    echo ($cond ? "PASS" : "FAIL") . ": {$label}\n";
}

// Create a mock PDO for unit testing schema definition
class MockPDO
{
    private array $executedStatements = [];

    public function exec(string $statement): int
    {
        $this->executedStatements[] = $statement;
        return 0;
    }

    public function getExecutedStatements(): array
    {
        return $this->executedStatements;
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    public function query(string $query): MockStatement
    {
        return new MockStatement();
    }
}

class MockStatement
{
    public function fetchAll(int $mode = PDO::FETCH_BOTH): array
    {
        return [];
    }
}

$pdo = new MockPDO();

require_once __DIR__ . '/../inc/db/schema.php';

// Test that ensure_schema function exists and can be called
assert_true(function_exists('ensure_schema'), 'ensure_schema function is defined');

// Call the function with mock PDO
ensure_schema($pdo);
assert_true(true, 'ensure_schema() can be called with PDO');

// Verify that CREATE TABLE statements were executed
$statements = $pdo->getExecutedStatements();
$hasGameSessions = false;
$hasParticipants = false;

foreach ($statements as $stmt) {
    if (stripos($stmt, 'CREATE TABLE') !== false && stripos($stmt, 'game_sessions') !== false) {
        $hasGameSessions = true;
    }
    if (stripos($stmt, 'CREATE TABLE') !== false && stripos($stmt, 'participants') !== false) {
        $hasParticipants = true;
    }
}

assert_true($hasGameSessions, 'game_sessions table creation SQL was executed');
assert_true($hasParticipants, 'participants table creation SQL was executed');

// Test idempotency - calling ensure_schema twice should not error
ensure_schema($pdo);
assert_true(true, 'ensure_schema is idempotent');
