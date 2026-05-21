<?php
// fakenews/tests/test_round.php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';

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

require_once __DIR__ . '/../inc/game/round.php';

// Test dataset: 10 real, 10 fake
$items = [];
for ($i = 0; $i < 10; $i++) {
    $items[] = ['id' => "real_{$i}", 'fake' => false, 'title' => "Real {$i}", 'summary' => 'x', 'source' => 'src', 'source_type' => 'real', 'source_reputation' => 3, 'topic' => 't', 'difficulty' => 'easy'];
    $items[] = ['id' => "fake_{$i}", 'fake' => true,  'title' => "Fake {$i}", 'summary' => 'x', 'source' => 'src', 'source_type' => 'fake', 'source_reputation' => 1, 'topic' => 't', 'difficulty' => 'easy'];
}

// Run 20 times — total must always be correct
for ($run = 0; $run < 20; $run++) {
    $result = build_round($items, 10, 3);
    assert_eq(null, $result['error'], "run {$run}: no error");
    assert_eq(10, count($result['round']), "run {$run}: total=10");
    $real = count(array_filter($result['round'], fn($c) => empty($c['fake'])));
    $fake = 10 - $real;
    assert_true($real >= 3, "run {$run}: real >= min_real(3), got {$real}");
    assert_true($fake >= 1, "run {$run}: fake >= 1, got {$fake}");
}

// Error when dataset too small
$small = [
    ['id' => 'r0', 'fake' => false, 'title' => 'R', 'summary' => 'x', 'source' => 's', 'source_type' => 'real', 'source_reputation' => 3, 'topic' => 't', 'difficulty' => 'easy'],
    ['id' => 'f0', 'fake' => true,  'title' => 'F', 'summary' => 'x', 'source' => 's', 'source_type' => 'fake', 'source_reputation' => 1, 'topic' => 't', 'difficulty' => 'easy'],
];
$result = build_round($small, 10, 3);
assert_true($result['error'] !== null, 'returns error when dataset too small');
