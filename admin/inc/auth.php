<?php
declare(strict_types=1);

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name('fakenews_admin');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 4,
        'path'     => '/',
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
    ]);
    session_start();
}

function admin_require_auth(): void
{
    admin_session_start();
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /admin/login.php');
        exit;
    }
}

function admin_check_password(string $input): bool
{
    $cfg = app_config();
    $stored = (string) ($cfg['admin_password'] ?? '');
    if ($stored === '') return false;
    return hash_equals($stored, $input);
}

function admin_is_brute_forced(): bool
{
    $attempts  = (int) ($_SESSION['admin_login_attempts'] ?? 0);
    $lockedAt  = (int) ($_SESSION['admin_locked_at'] ?? 0);

    if ($lockedAt > 0 && (time() - $lockedAt) < 900) {
        return true;
    }
    if ($lockedAt > 0 && (time() - $lockedAt) >= 900) {
        $_SESSION['admin_login_attempts'] = 0;
        $_SESSION['admin_locked_at']      = 0;
    }
    return false;
}

function admin_record_failed_attempt(): void
{
    $_SESSION['admin_login_attempts'] = ((int) ($_SESSION['admin_login_attempts'] ?? 0)) + 1;
    if ((int) $_SESSION['admin_login_attempts'] >= 3) {
        $_SESSION['admin_locked_at'] = time();
    }
}
