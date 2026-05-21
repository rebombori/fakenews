<?php
// fakenews/tests/test_participants.php
declare(strict_types=1);

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    if ($expected === $actual) {
        echo "PASS: {$label}\n";
    } else {
        echo "FAIL: {$label} — expected " . json_encode($expected) . ", got " . json_encode($actual) . "\n";
    }
}
function assert_true(bool $cond, string $label): void
{
    echo ($cond ? "PASS" : "FAIL") . ": {$label}\n";
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: pdo_sqlite not available in this environment (required in production)\n";
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

require_once __DIR__ . '/../inc/db/schema.php';
ensure_schema($pdo);

require_once __DIR__ . '/../inc/db/participants.php';

// Email not found initially
assert_true(!participant_exists($pdo, 'test@example.com'), 'email not found initially');

// Save participant
$saved = save_participant($pdo, 'test@example.com', 7, 10, 'token_abc');
assert_true($saved, 'first save returns true');
assert_true(participant_exists($pdo, 'test@example.com'), 'email found after save');

// Duplicate with lower score: not updated
$saved2 = save_participant($pdo, 'test@example.com', 5, 10, 'token_xyz');
assert_true(!$saved2, 'duplicate email with lower score returns false (not updated)');
$row = $pdo->query("SELECT score FROM participants WHERE email='test@example.com'")->fetch();
assert_eq(7, (int) $row['score'], 'score remains 7 after failed update');

// Duplicate with higher score: updated
$saved3 = save_participant($pdo, 'test@example.com', 9, 10, 'token_xyz');
assert_true($saved3, 'duplicate email with higher score returns true (updated)');
$row = $pdo->query("SELECT score FROM participants WHERE email='test@example.com'")->fetch();
assert_eq(9, (int) $row['score'], 'score updated to 9');

// Different email saves fine
$saved4 = save_participant($pdo, 'other@example.com', 3, 10, 'token_999');
assert_true($saved4, 'different email saves ok');
