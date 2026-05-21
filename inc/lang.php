<?php
declare(strict_types=1);

function supported_langs(): array
{
    return ['es', 'val', 'en'];
}

function normalize_lang(string $lang): string
{
    $lang = strtolower(trim($lang));
    if ($lang === '') return 'es';

    // Valencian / Catalan
    $valencianPrefixes = ['ca', 'val', 'va'];
    foreach ($valencianPrefixes as $prefix) {
        if ($lang === $prefix || str_starts_with($lang, $prefix . '-') || str_starts_with($lang, $prefix . '_')) {
            return 'val';
        }
    }

    if (str_starts_with($lang, 'en')) return 'en';
    if (str_starts_with($lang, 'es')) return 'es';

    return 'es';
}

function detect_lang_from_browser(): string
{
    $header = trim($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if ($header === '') return 'es';

    foreach (explode(',', $header) as $part) {
        $tag = strtolower(trim(explode(';', $part)[0]));
        if ($tag === '') continue;
        $normalized = normalize_lang($tag);
        if ($normalized === 'val' || $normalized === 'en') return $normalized;
        if (str_starts_with($tag, 'es')) return 'es';
    }
    return 'es';
}

function resolve_lang(): string
{
    $cookie = strtolower(trim($_COOKIE['lang'] ?? ''));
    if (in_array($cookie, supported_langs(), true)) return $cookie;

    $detected = detect_lang_from_browser();
    set_lang_cookie($detected);
    return $detected;
}

function set_lang_cookie(string $lang): void
{
    $lang = in_array($lang, supported_langs(), true) ? $lang : 'es';
    setcookie('lang', $lang, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}
