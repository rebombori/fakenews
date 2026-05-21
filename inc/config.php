<?php
declare(strict_types=1);

function load_env_file(string $path): array
{
    if (!is_file($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return [];
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($value !== '' && (
            ($value[0] === '"' && str_ends_with($value, '"')) ||
            ($value[0] === "'" && str_ends_with($value, "'"))
        )) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
    return $env;
}

function envv(array $env, string $key, ?string $default = null): ?string
{
    return $env[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function env_bool(array $env, string $key, bool $default = false): bool
{
    $raw = strtolower(trim((string) envv($env, $key, $default ? '1' : '0')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function app_config(): array
{
    static $cfg;
    if ($cfg !== null) return $cfg;

    $root = dirname(__DIR__);
    $env = load_env_file($root . '/.env');

    $cfg = [
        'root'         => $root,
        'debug'        => env_bool($env, 'APP_DEBUG', false),
        'session_name' => envv($env, 'APP_SESSION_NAME', 'fakenews_session'),
        'timezone'     => envv($env, 'APP_TIMEZONE', 'Europe/Madrid'),
        'admin_password' => envv($env, 'ADMIN_PASSWORD', ''),
        'smtp' => [
            'host'      => envv($env, 'SMTP_HOST', ''),
            'port'      => (int) envv($env, 'SMTP_PORT', '587'),
            'user'      => envv($env, 'SMTP_USER', ''),
            'pass'      => envv($env, 'SMTP_PASS', ''),
            'from'      => envv($env, 'SMTP_FROM', ''),
            'from_name' => envv($env, 'SMTP_FROM_NAME', 'FakeNews Game'),
        ],
    ];

    return $cfg;
}
