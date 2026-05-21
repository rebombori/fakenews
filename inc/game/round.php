<?php
declare(strict_types=1);

/**
 * Builds a round with random real/fake split.
 * Guarantees: real >= minReal, fake >= 1, real + fake = totalCards.
 *
 * @return array{round: array<int,array<string,mixed>>, error: string|null}
 */
function build_round(array $allItems, int $totalCards, int $minReal): array
{
    $realItems = array_values(array_filter($allItems, static fn(array $n): bool => empty($n['fake'])));
    $fakeItems = array_values(array_filter($allItems, static fn(array $n): bool => !empty($n['fake'])));

    // At least minReal real items and at least 1 fake
    $maxReal = min(count($realItems), $totalCards - 1);
    $minReal = max(1, min($minReal, $maxReal));

    if (count($realItems) < $minReal || count($fakeItems) < 1) {
        return ['round' => [], 'error' => 'Not enough items in dataset'];
    }

    $realCount = random_int($minReal, $maxReal);
    $fakeCount = $totalCards - $realCount;

    if (count($fakeItems) < $fakeCount) {
        // Adjust if not enough fake items
        $fakeCount = count($fakeItems);
        $realCount = $totalCards - $fakeCount;
        if ($realCount < $minReal || count($realItems) < $realCount) {
            return ['round' => [], 'error' => 'Not enough items in dataset'];
        }
    }

    shuffle($realItems);
    shuffle($fakeItems);

    $round = array_merge(
        array_slice($realItems, 0, $realCount),
        array_slice($fakeItems, 0, $fakeCount)
    );
    shuffle($round);

    return ['round' => $round, 'error' => null];
}
