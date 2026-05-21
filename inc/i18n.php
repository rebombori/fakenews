<?php
declare(strict_types=1);

function load_i18n(string $lang): array
{
    $safe = in_array($lang, ['es', 'val', 'en'], true) ? $lang : 'es';
    $path = dirname(__DIR__) . '/i18n/' . $safe . '.json';
    if (!is_file($path)) {
        $path = dirname(__DIR__) . '/i18n/es.json';
    }
    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}
