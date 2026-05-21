<?php
// fakenews/tests/test_session.php
declare(strict_types=1);

// Simulate PHP session without HTTP
session_id('test_session_id');
$_SESSION = [];

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/helpers.php';

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

require_once __DIR__ . '/../inc/game/session.php';

// Test dataset
$round = [
    ['id' => 'r1', 'fake' => false, 'title' => 'Real 1', 'summary' => 'x', 'source' => 's', 'source_type' => 'real', 'source_reputation' => 3, 'topic' => 't', 'difficulty' => 'easy'],
    ['id' => 'r2', 'fake' => false, 'title' => 'Real 2', 'summary' => 'x', 'source' => 's', 'source_type' => 'real', 'source_reputation' => 3, 'topic' => 't', 'difficulty' => 'easy'],
    ['id' => 'f1', 'fake' => true,  'title' => 'Fake 1', 'summary' => 'x', 'source' => 's', 'source_type' => 'fake', 'source_reputation' => 1, 'topic' => 't', 'difficulty' => 'easy'],
];

// init
assert_true(!is_game_started(), 'not started before init');
init_game_session($round, 'token_abc');
assert_true(is_game_started(), 'started after init');
assert_true(!is_game_complete(), 'not complete after init');

// answer all cards
$r1 = save_answer('r1', 'real');  // correct
assert_eq(true,  $r1['correct'],          'r1 real → correct');
assert_eq(false, $r1['done'],             'not done after 1/3');
assert_eq(1,     $r1['correct_count'],    'correct_count=1');

$r2 = save_answer('r2', 'fake');  // incorrect
assert_eq(false, $r2['correct'],          'r2 marked fake → incorrect');
assert_eq(false, $r2['done'],             'not done after 2/3');
assert_eq(1,     $r2['correct_count'],    'correct_count still 1');

$r3 = save_answer('f1', 'fake');  // correct
assert_eq(true,  $r3['correct'],          'f1 fake → correct');
assert_eq(true,  $r3['done'],             'done after 3/3');
assert_eq(2,     $r3['correct_count'],    'correct_count=2');

// score summary
$summary = get_score_summary();
assert_eq(2, $summary['correct'],  'summary correct=2');
assert_eq(3, $summary['total'],    'summary total=3');
assert_eq(67, $summary['pct'],     'summary pct=67');

// real cards
$reals = get_real_cards_from_round();
assert_eq(2, count($reals), 'two real cards in round');

// reset
reset_game();
assert_true(!is_game_started(), 'not started after reset');
