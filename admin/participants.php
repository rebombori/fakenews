<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db/db.php';
require_once __DIR__ . '/inc/auth.php';

admin_require_auth();

$pdo = db_sqlite();

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $minScore = isset($_GET['min_score']) ? max(0, (int) $_GET['min_score']) : 0;

    $stmt = $pdo->prepare('
        SELECT email, score, total_cards,
               CAST(ROUND(CAST(score AS REAL) / total_cards * 100) AS INTEGER) AS pct,
               datetime(submitted_at, "unixepoch") AS date,
               session_token
        FROM participants
        WHERE score >= :min
        ORDER BY score DESC, submitted_at ASC
    ');
    $stmt->execute([':min' => $minScore]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="participantes_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Email', 'Aciertos', 'Total', 'Precisión (%)', 'Fecha', 'Token sesión'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['email'],
            $row['score'],
            $row['total_cards'],
            $row['pct'],
            $row['date'],
            $row['session_token'],
        ], ';');
    }
    fclose($out);
    exit;
}

// Filtros
$minScore = isset($_GET['min_score']) ? max(0, (int) $_GET['min_score']) : 0;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 50;
$offset   = ($page - 1) * $perPage;

$total = (int) $pdo->query("SELECT COUNT(*) FROM participants WHERE score >= {$minScore}")->fetchColumn();

$stmt = $pdo->prepare('
    SELECT email, score, total_cards,
           CAST(ROUND(CAST(score AS REAL) / total_cards * 100) AS INTEGER) AS pct,
           datetime(submitted_at, "unixepoch") AS date
    FROM participants
    WHERE score >= :min
    ORDER BY score DESC, submitted_at ASC
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':min',    $minScore, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $perPage,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$rows  = $stmt->fetchAll();
$pages = (int) ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel — Participantes</title>
<style>
  * { box-sizing:border-box; }
  body { background:#f0f2f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; margin:0; }
  .topbar { background:#1877f2; color:#fff; padding:12px 24px; display:flex; justify-content:space-between; align-items:center; }
  .topbar nav a { color:rgba(255,255,255,.85); text-decoration:none; margin-left:16px; font-size:.9rem; font-weight:600; }
  .topbar nav a:hover { color:#fff; }
  .wrap { max-width:1000px; margin:24px auto; padding:0 16px; }
  h1 { font-size:1.4rem; margin:0 0 16px; }
  .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
  .toolbar label { font-size:.875rem; color:#65676b; }
  .toolbar input[type=number] { padding:7px 10px; border:1px solid #ccc; border-radius:6px; width:100px; }
  .toolbar a, .toolbar button { background:#1877f2; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; font-size:.875rem; cursor:pointer; text-decoration:none; }
  .toolbar a:hover, .toolbar button:hover { background:#166fe5; }
  .toolbar .btn-csv { background:#27ae60; }
  .toolbar .btn-csv:hover { background:#229954; }
  table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1); }
  th { text-align:left; padding:10px 12px; background:#f0f2f5; color:#65676b; font-weight:600; font-size:.85rem; border-bottom:2px solid #e4e6eb; }
  td { padding:10px 12px; border-bottom:1px solid #e4e6eb; font-size:.875rem; }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:#f7f8fa; }
  .pct-badge { display:inline-block; padding:2px 8px; border-radius:99px; font-weight:700; font-size:.8rem; }
  .pct-high { background:#e7f3e8; color:#1a7f37; }
  .pct-mid  { background:#fff3cd; color:#856404; }
  .pct-low  { background:#fce8e8; color:#c0392b; }
  .pagination { display:flex; gap:6px; margin-top:16px; }
  .pagination a { padding:6px 12px; border-radius:6px; background:#fff; border:1px solid #e4e6eb; text-decoration:none; color:#050505; font-size:.875rem; }
  .pagination a.active { background:#1877f2; color:#fff; border-color:#1877f2; }
  .empty { text-align:center; padding:40px; color:#65676b; }
</style>
</head>
<body>
<div class="topbar">
  <strong>FakeNews — Admin</strong>
  <nav>
    <a href="/admin/">Dashboard</a>
    <a href="/admin/participants.php">Participantes</a>
    <a href="/admin/config.php">Configuración</a>
    <a href="/admin/logout.php">Salir</a>
  </nav>
</div>

<div class="wrap">
<h1>Participantes (<?= $total ?>)</h1>

<form class="toolbar" method="GET">
  <label>Puntuación mínima:
    <input type="number" name="min_score" value="<?= $minScore ?>" min="0">
  </label>
  <button type="submit">Filtrar</button>
  <a href="?export=csv&min_score=<?= $minScore ?>" class="btn-csv">Exportar CSV</a>
</form>

<?php if (empty($rows)): ?>
  <p class="empty">No hay participantes con ese filtro.</p>
<?php else: ?>
<table>
  <tr>
    <th>#</th><th>Email</th><th>Aciertos</th><th>Total</th><th>Precisión</th><th>Fecha</th>
  </tr>
  <?php foreach ($rows as $i => $row):
    $pct = (int) $row['pct'];
    $cls = $pct >= 70 ? 'pct-high' : ($pct >= 40 ? 'pct-mid' : 'pct-low');
  ?>
  <tr>
    <td><?= $offset + $i + 1 ?></td>
    <td><?= htmlspecialchars($row['email']) ?></td>
    <td><?= $row['score'] ?></td>
    <td><?= $row['total_cards'] ?></td>
    <td><span class="pct-badge <?= $cls ?>"><?= $pct ?>%</span></td>
    <td><?= htmlspecialchars($row['date']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php for ($p = 1; $p <= $pages; $p++): ?>
  <a href="?page=<?= $p ?>&min_score=<?= $minScore ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
