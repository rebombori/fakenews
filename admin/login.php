<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/inc/auth.php';

admin_session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('/admin/'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    if (admin_is_brute_forced()) {
        $error = 'Demasiados intentos fallidos. Espera 15 minutos.';
    } elseif (admin_check_password($password)) {
        $_SESSION['admin_logged_in']      = true;
        $_SESSION['admin_login_attempts'] = 0;
        $_SESSION['admin_locked_at']      = 0;
        header('Location: ' . base_url('/admin/'));
        exit;
    } else {
        admin_record_failed_attempt();
        $error = 'Contraseña incorrecta.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel — Acceso</title>
<style>
  body { background:#f0f2f5; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; font-family:sans-serif; }
  .box { background:#fff; padding:32px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.1); width:100%; max-width:340px; }
  h2 { margin:0 0 20px; font-size:1.2rem; color:#050505; }
  input[type=password] { width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:1rem; margin-bottom:12px; }
  button { width:100%; background:#1877f2; color:#fff; border:none; padding:11px; border-radius:6px; font-size:1rem; font-weight:700; cursor:pointer; }
  button:hover { background:#166fe5; }
  .error { color:#c0392b; font-size:.875rem; margin-bottom:10px; }
</style>
</head>
<body>
<div class="box">
  <h2>Panel de administración</h2>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="POST">
    <input type="password" name="password" placeholder="Contraseña" autofocus required>
    <button type="submit">Acceder</button>
  </form>
</div>
</body>
</html>
