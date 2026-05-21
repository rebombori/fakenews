<?php
declare(strict_types=1);

final class DatasetLoaderService
{
    /**
     * @return array{items: array<int,array<string,mixed>>, errors: array<int,string>, stats: array<string,int>}
     */
    public static function loadFromDirectory(string $directory): array
    {
        $items = [];
        $errors = [];
        $ids = [];

        if (!is_dir($directory)) {
            return ['items' => [], 'errors' => ["Dataset directory not found: {$directory}"], 'stats' => self::buildStats([])];
        }

        $files = glob(rtrim($directory, '/') . '/*.json');
        if (!is_array($files)) {
            return ['items' => [], 'errors' => ["Could not read dataset directory: {$directory}"], 'stats' => self::buildStats([])];
        }

        sort($files);
        foreach ($files as $file) {
            $raw = file_get_contents($file);
            if (!is_string($raw)) {
                $errors[] = basename($file) . ': unreadable file';
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $errors[] = basename($file) . ': invalid JSON';
                continue;
            }

            $records = self::isList($decoded) ? $decoded : [$decoded];
            foreach ($records as $idx => $record) {
                if (!is_array($record)) {
                    $errors[] = basename($file) . ': item #' . ((int) $idx + 1) . ' is not an object';
                    continue;
                }

                $label = basename($file) . (self::isList($decoded) ? ' item #' . ((int) $idx + 1) : '');
                $validation = self::validateItem($record, $label);
                if ($validation !== null) {
                    $errors[] = $validation;
                    continue;
                }

                $id = (string) $record['id'];
                if (isset($ids[$id])) {
                    $errors[] = $label . ': duplicated id ' . $id;
                    continue;
                }
                $ids[$id] = true;
                $items[] = $record;
            }
        }

        return ['items' => $items, 'errors' => $errors, 'stats' => self::buildStats($items)];
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @return array{round: array<int,array<string,mixed>>, errors: array<int,string>}
     */
    public static function buildRound(array $items, int $realCount = 10, int $fakeCount = 10, array $preferredTopics = []): array
    {
        $real = array_values(array_filter($items, static fn(array $n): bool => empty($n['fake'])));
        $fake = array_values(array_filter($items, static fn(array $n): bool => !empty($n['fake'])));
        $errors = [];

        if (count($real) < $realCount) {
            $errors[] = "Not enough real news: have " . count($real) . ", need {$realCount}";
        }
        if (count($fake) < $fakeCount) {
            $errors[] = "Not enough fake news: have " . count($fake) . ", need {$fakeCount}";
        }
        if ($errors !== []) {
            return ['round' => [], 'errors' => $errors];
        }

        $selectedReal = self::pickWithTopicPriority($real, $realCount, $preferredTopics);
        $selectedFake = self::pickWithTopicPriority($fake, $fakeCount, $preferredTopics);
        $round = array_merge($selectedReal, $selectedFake);
        shuffle($round);

        return ['round' => $round, 'errors' => []];
    }

    /**
     * @param array<int,array<string,mixed>> $pool
     * @param array<int,string> $preferredTopics
     * @return array<int,array<string,mixed>>
     */
    private static function pickWithTopicPriority(array $pool, int $count, array $preferredTopics): array
    {
        if ($count <= 0) {
            return [];
        }

        $preferred = [];
        foreach ($preferredTopics as $topic) {
            $topic = strtolower(trim((string) $topic));
            if ($topic !== '') {
                $preferred[$topic] = true;
            }
        }

        if ($preferred === []) {
            shuffle($pool);
            return array_slice($pool, 0, $count);
        }

        $matches = [];
        $others = [];
        foreach ($pool as $item) {
            $topic = strtolower(trim((string) ($item['topic'] ?? '')));
            if ($topic !== '' && isset($preferred[$topic])) {
                $matches[] = $item;
            } else {
                $others[] = $item;
            }
        }

        shuffle($matches);
        shuffle($others);
        $selected = array_slice($matches, 0, $count);

        if (count($selected) < $count) {
            $selected = array_merge($selected, array_slice($others, 0, $count - count($selected)));
        }

        return $selected;
    }

    public static function validateItem(array $item, string $fileName): ?string
    {
        $required = ['id', 'title', 'summary', 'source', 'fake', 'source_type', 'source_reputation', 'topic', 'difficulty'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $item)) {
                return "{$fileName}: missing field {$key}";
            }
        }

        if (!is_string($item['id']) || trim($item['id']) === '') {
            return "{$fileName}: id must be non-empty string";
        }
        if (!self::hasTextField($item, 'title')) {
            return "{$fileName}: title must be non-empty string";
        }
        if (!self::hasTextField($item, 'summary')) {
            return "{$fileName}: summary must be non-empty string";
        }
        if (!is_string($item['source']) || trim($item['source']) === '') {
            return "{$fileName}: source must be non-empty string";
        }
        if (!is_bool($item['fake'])) {
            return "{$fileName}: fake must be boolean";
        }
        if (!is_string($item['source_type'])) {
            return "{$fileName}: source_type must be string";
        }

        $sourceType = strtolower(trim((string) $item['source_type']));
        $fake = (bool) $item['fake'];
        if ($fake && $sourceType !== 'fake') {
            return "{$fileName}: source_type must be fake when fake=true";
        }
        if (!$fake && $sourceType !== 'real') {
            return "{$fileName}: source_type must be real when fake=false";
        }

        $reputation = (int) $item['source_reputation'];
        if ($reputation < 1 || $reputation > 5) {
            return "{$fileName}: source_reputation must be in range 1..5";
        }

        if (!is_string($item['topic']) || trim($item['topic']) === '') {
            return "{$fileName}: topic must be non-empty string";
        }

        $difficulty = strtolower((string) $item['difficulty']);
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            return "{$fileName}: difficulty must be one of easy|medium|hard";
        }

        if (isset($item['url']) && !is_string($item['url'])) {
            return "{$fileName}: url must be string when provided";
        }

        return null;
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function buildStats(array $items): array
    {
        $stats = [
            'total' => count($items),
            'real' => 0,
            'fake' => 0,
            'topics' => 0,
        ];

        $topics = [];
        foreach ($items as $item) {
            if (!empty($item['fake'])) {
                $stats['fake']++;
            } else {
                $stats['real']++;
            }
            $topic = (string) ($item['topic'] ?? '');
            if ($topic !== '') {
                $topics[$topic] = true;
            }
        }
        $stats['topics'] = count($topics);

        return $stats;
    }

    /** @param array<string,mixed> $item */
    private static function hasTextField(array $item, string $field): bool
    {
        if (self::hasText($item[$field] ?? null)) {
            return true;
        }

        $altKeys = [
            "{$field}_es",
            "{$field}_val",
            "{$field}_en",
        ];
        foreach ($altKeys as $altKey) {
            if (self::hasText($item[$altKey] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private static function hasText(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (!is_array($value)) {
            return false;
        }

        $candidates = ['es', 'val', 'en'];
        foreach ($candidates as $k) {
            if (isset($value[$k]) && is_string($value[$k]) && trim($value[$k]) !== '') {
                return true;
            }
        }
        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                return true;
            }
        }
        return false;
    }
}
