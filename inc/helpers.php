<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_encode_safe(mixed $value): string
{
    $result = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $result === false ? '{}' : $result;
}

function parse_json_array(?string $raw): array
{
    if (!is_string($raw) || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function as_bool(mixed $value): bool
{
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_verify(): bool
{
    $token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = (string) ($_SESSION['csrf_token'] ?? '');
    return $stored !== '' && hash_equals($stored, $token);
}

function tr(array $i18n, string $key, string $fallback = ''): string
{
    return isset($i18n[$key]) && is_string($i18n[$key]) ? $i18n[$key] : $fallback;
}
