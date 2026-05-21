<?php
declare(strict_types=1);

/**
 * Minimal YAML parser for simple config files used by this project.
 * Supports:
 * - key: value maps
 * - nested maps by 2-space indentation
 * - lists with "- value" or "- key: value"
 */
final class SimpleYaml
{
    public static function parseFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            return [];
        }
        return self::parse($raw);
    }

    public static function parse(string $yaml): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $yaml) ?: [];
        $root = [];
        $stack = [
            ['indent' => -1, 'type' => 'map', 'ref' => &$root],
        ];

        foreach ($lines as $line) {
            $raw = rtrim($line);
            if ($raw === '' || str_starts_with(ltrim($raw), '#')) {
                continue;
            }

            $indent = strlen($raw) - strlen(ltrim($raw, ' '));
            $text = ltrim($raw, ' ');

            while (count($stack) > 1 && $indent <= $stack[count($stack) - 1]['indent']) {
                array_pop($stack);
            }

            $parentIndex = count($stack) - 1;
            $parentRef =& $stack[$parentIndex]['ref'];
            $parentType = $stack[$parentIndex]['type'];

            if (str_starts_with($text, '- ')) {
                $itemText = substr($text, 2);
                if ($parentType !== 'list') {
                    // Convert empty map parent into list if needed.
                    if ($parentType === 'map' && $parentRef === []) {
                        $parentRef = [];
                    }
                }

                if (!is_array($parentRef)) {
                    $parentRef = [];
                }

                if (preg_match('/^([A-Za-z0-9_\-]+):\s*(.*)$/', $itemText, $m)) {
                    $entry = [];
                    $entry[$m[1]] = self::parseScalar($m[2]);
                    $parentRef[] = $entry;
                    $last = count($parentRef) - 1;
                    $stack[] = ['indent' => $indent, 'type' => 'map', 'ref' => &$parentRef[$last]];
                } else {
                    $parentRef[] = self::parseScalar($itemText);
                }
                continue;
            }

            if (!preg_match('/^([A-Za-z0-9_\-]+):\s*(.*)$/', $text, $m)) {
                continue;
            }

            $key = $m[1];
            $valueText = $m[2];
            if ($valueText === '') {
                $parentRef[$key] = [];
                $stack[] = ['indent' => $indent, 'type' => 'map', 'ref' => &$parentRef[$key]];
            } else {
                $parentRef[$key] = self::parseScalar($valueText);
            }
        }

        return $root;
    }

    private static function parseScalar(string $value)
    {
        $value = trim($value);
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value === 'null') {
            return null;
        }
        if (preg_match('/^-?[0-9]+$/', $value)) {
            return (int) $value;
        }
        if (preg_match('/^-?[0-9]+\.[0-9]+$/', $value)) {
            return (float) $value;
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }
        return $value;
    }
}
