<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/inc/auth.php';

admin_session_start();
$_SESSION = [];
session_destroy();
header('Location: ' . base_url('/admin/login.php'));
exit;
