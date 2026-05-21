<?php
declare(strict_types=1);

function init_game_session(array $round, string $sessionToken): void
{
    $_SESSION['session_token']  = $sessionToken;
    $_SESSION['round_cards']    = $round;
    $_SESSION['answers']        = [];
    $_SESSION['current_index']  = 0;
    $_SESSION['started_at']     = time();
}

function is_game_started(): bool
{
    return !empty($_SESSION['round_cards']);
}

function is_game_complete(): bool
{
    if (!is_game_started()) return false;
    return (int) ($_SESSION['current_index'] ?? 0) >= count((array) $_SESSION['round_cards']);
}

function save_answer(string $cardId, string $answer): array
{
    $round   = (array) ($_SESSION['round_cards'] ?? []);
    $answers = (array) ($_SESSION['answers'] ?? []);

    $answers[$cardId] = $answer;
    $_SESSION['answers'] = $answers;
    $_SESSION['current_index'] = count($answers);

    $correct = false;
    foreach ($round as $card) {
        if ((string) ($card['id'] ?? '') === $cardId) {
            $isFake  = !empty($card['fake']);
            $correct = ($answer === 'fake') === $isFake;
            break;
        }
    }

    $correctCount = get_correct_count();
    $done = count($answers) >= count($round);

    return [
        'correct'       => $correct,
        'correct_count' => $correctCount,
        'total_answered'=> count($answers),
        'done'          => $done,
        'next_index'    => (int) $_SESSION['current_index'],
    ];
}

function get_correct_count(): int
{
    $count   = 0;
    $round   = (array) ($_SESSION['round_cards'] ?? []);
    $answers = (array) ($_SESSION['answers'] ?? []);

    foreach ($round as $card) {
        $id = (string) ($card['id'] ?? '');
        if (!isset($answers[$id])) continue;
        $isFake = !empty($card['fake']);
        if (($answers[$id] === 'fake') === $isFake) $count++;
    }
    return $count;
}

function get_score_summary(): array
{
    $round   = (array) ($_SESSION['round_cards'] ?? []);
    $total   = count($round);
    $correct = get_correct_count();
    $pct     = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
    return ['correct' => $correct, 'total' => $total, 'pct' => $pct];
}

function get_real_cards_from_round(): array
{
    return array_values(array_filter(
        (array) ($_SESSION['round_cards'] ?? []),
        static fn(array $c): bool => empty($c['fake'])
    ));
}

function get_current_card(): ?array
{
    $round = (array) ($_SESSION['round_cards'] ?? []);
    $index = (int) ($_SESSION['current_index'] ?? 0);
    return $round[$index] ?? null;
}

function reset_game(): void
{
    unset(
        $_SESSION['round_cards'],
        $_SESSION['answers'],
        $_SESSION['current_index'],
        $_SESSION['started_at']
    );
}
