<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/campaign.php';
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/db/tracking.php';
require_once __DIR__ . '/geo/geoip.php';

$cfg = app_config();
date_default_timezone_set((string) $cfg['timezone']);

if (!empty($cfg['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_name((string) $cfg['session_name']);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 2,
        'path'     => '/',
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

csrf_token();

// Language switch via GET ?lang=
if (isset($_GET['lang'])) {
    $newLang = normalize_lang((string) $_GET['lang']);
    set_lang_cookie($newLang);
    $_SESSION['lang'] = $newLang;
    $url = preg_replace('/[\r\n]/', '', (string) strtok($_SERVER['REQUEST_URI'], '?'));
    $params = $_GET;
    unset($params['lang']);
    $qs = $params ? '?' . http_build_query($params) : '';
    header('Location: ' . $url . $qs);
    exit;
}

$currentLang = resolve_lang();
$_SESSION['lang'] = $currentLang;
$i18n = load_i18n($currentLang);
$campaign = load_campaign();
