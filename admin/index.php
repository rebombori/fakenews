<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/stats.php';

admin_require_auth();

$pdo       = db_sqlite();
$overview  = stats_overview($pdo);
$byDay     = stats_by_day($pdo, 30);
$countries = stats_by_country($pdo, 8);
$devices   = stats_by_device($pdo);
$browsers  = stats_by_browser($pdo);
$scoreDist = stats_score_distribution($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel — Dashboard</title>
<style>
  * { box-sizing: border-box; }
  body { background:#f0f2f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; margin:0; }
  .topbar { background:#1877f2; color:#fff; padding:12px 24px; display:flex; justify-content:space-between; align-items:center; }
  .topbar nav a { color:rgba(255,255,255,.85); text-decoration:none; margin-left:16px; font-size:.9rem; font-weight:600; }
  .topbar nav a:hover { color:#fff; }
  .wrap { max-width:1100px; margin:24px auto; padding:0 16px; }
  h1 { font-size:1.4rem; margin:0 0 20px; color:#050505; }
  .kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; margin-bottom:20px; }
  .kpi { background:#fff; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,.1); }
  .kpi .val { font-size:2rem; font-weight:800; color:#1877f2; }
  .kpi .lbl { font-size:.8rem; color:#65676b; margin-top:4px; }
  .card { background:#fff; border-radius:8px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.1); margin-bottom:16px; }
  .card h2 { font-size:1rem; margin:0 0 14px; color:#050505; }
  .bar-chart { display:flex; align-items:flex-end; gap:3px; height:100px; }
  .bar-wrap { flex:1; display:flex; flex-direction:column; align-items:center; }
  .bar { width:100%; background:#1877f2; border-radius:3px 3px 0 0; min-height:2px; transition:opacity .2s; }
  .bar:hover { opacity:.8; }
  .bar-label { font-size:.55rem; color:#65676b; margin-top:3px; transform:rotate(-45deg); white-space:nowrap; }
  table { width:100%; border-collapse:collapse; font-size:.875rem; }
  th { text-align:left; padding:8px; background:#f0f2f5; color:#65676b; font-weight:600; border-bottom:2px solid #e4e6eb; }
  td { padding:8px; border-bottom:1px solid #e4e6eb; }
  tr:last-child td { border-bottom:none; }
  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  @media(max-width:600px) { .two-col { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="topbar">
  <strong>FakeNews — Admin</strong>
  <nav>
    <a href="<?= e(base_url('/admin/')) ?>">Dashboard</a>
    <a href="<?= e(base_url('/admin/participants.php')) ?>">Participantes</a>
    <a href="<?= e(base_url('/admin/config.php')) ?>">Configuración</a>
    <a href="<?= e(base_url('/admin/logout.php')) ?>">Salir</a>
  </nav>
</div>

<div class="wrap">
<h1>Dashboard</h1>

<div class="kpi-grid">
  <div class="kpi"><div class="val"><?= $overview['total_visits'] ?></div><div class="lbl">Visitas totales</div></div>
  <div class="kpi"><div class="val"><?= $overview['completed'] ?></div><div class="lbl">Partidas completadas</div></div>
  <div class="kpi"><div class="val"><?= $overview['completion_rate'] ?>%</div><div class="lbl">Tasa de completado</div></div>
  <div class="kpi"><div class="val"><?= $overview['avg_accuracy'] ?>%</div><div class="lbl">Precisión media</div></div>
  <div class="kpi"><div class="val"><?= $overview['participants'] ?></div><div class="lbl">Participantes en sorteo</div></div>
</div>

<?php if ($byDay): ?>
<div class="card">
  <h2>Partidas por día (últimos 30 días)</h2>
  <div class="bar-chart">
    <?php
    $maxVisits = max(array_column($byDay, 'visits') ?: [1]);
    foreach ($byDay as $row):
      $h = max(2, (int) round(($row['visits'] / $maxVisits) * 100));
    ?>
    <div class="bar-wrap" title="<?= htmlspecialchars($row['day']) ?>: <?= $row['visits'] ?> visitas">
      <div class="bar" style="height:<?= $h ?>px"></div>
      <div class="bar-label"><?= htmlspecialchars(substr($row['day'], 5)) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="two-col">
  <?php if ($countries): ?>
  <div class="card">
    <h2>Países</h2>
    <table>
      <tr><th>País</th><th>Visitas</th></tr>
      <?php foreach ($countries as $r): ?>
      <tr><td><?= htmlspecialchars($r['country'] ?: '—') ?></td><td><?= $r['cnt'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Dispositivos</h2>
    <table>
      <tr><th>Tipo</th><th>Visitas</th></tr>
      <?php foreach ($devices as $r): ?>
      <tr><td><?= htmlspecialchars($r['device_type'] ?: '—') ?></td><td><?= $r['cnt'] ?></td></tr>
      <?php endforeach; ?>
    </table>
    <br>
    <h2>Navegadores</h2>
    <table>
      <tr><th>Navegador</th><th>Visitas</th></tr>
      <?php foreach ($browsers as $r): ?>
      <tr><td><?= htmlspecialchars($r['browser'] ?: '—') ?></td><td><?= $r['cnt'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php if ($scoreDist): ?>
<div class="card">
  <h2>Distribución de puntuaciones (partidas completadas)</h2>
  <table>
    <tr><th>Rango</th><th>Partidas</th></tr>
    <?php foreach ($scoreDist as $r): ?>
    <tr>
      <td><?= $r['bucket'] ?>% – <?= min(100, (int)$r['bucket'] + 9) ?>%</td>
      <td><?= $r['cnt'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

</div>
</body>
</html>
