<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/campaign.php';
require_once __DIR__ . '/inc/auth.php';

admin_require_auth();

$yamlPath = app_config()['root'] . '/config/campaign.yaml';
$ok       = '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $totalCards = max(2, (int) ($_POST['total_cards'] ?? 10));
    $minReal    = max(1, (int) ($_POST['min_real'] ?? 3));

    $sanitize = static fn(string $s): string => trim($s);

    $data = [
        'total_cards' => $totalCards,
        'min_real'    => $minReal,

        'report' => [
            'es'  => $sanitize((string) ($_POST['report_es']  ?? '')),
            'val' => $sanitize((string) ($_POST['report_val'] ?? '')),
            'en'  => $sanitize((string) ($_POST['report_en']  ?? '')),
        ],
        'campaign_link' => $sanitize((string) ($_POST['campaign_link'] ?? '')),
        'campaign_link_label' => [
            'es'  => $sanitize((string) ($_POST['link_label_es']  ?? '')),
            'val' => $sanitize((string) ($_POST['link_label_val'] ?? '')),
            'en'  => $sanitize((string) ($_POST['link_label_en']  ?? '')),
        ],
        'lottery_legal' => [
            'es'  => $sanitize((string) ($_POST['legal_es']  ?? '')),
            'val' => $sanitize((string) ($_POST['legal_val'] ?? '')),
            'en'  => $sanitize((string) ($_POST['legal_en']  ?? '')),
        ],
        'email_confirmation' => [
            'subject' => [
                'es'  => $sanitize((string) ($_POST['email_subject_es']  ?? '')),
                'val' => $sanitize((string) ($_POST['email_subject_val'] ?? '')),
                'en'  => $sanitize((string) ($_POST['email_subject_en']  ?? '')),
            ],
            'body' => [
                'es'  => $sanitize((string) ($_POST['email_body_es']  ?? '')),
                'val' => $sanitize((string) ($_POST['email_body_val'] ?? '')),
                'en'  => $sanitize((string) ($_POST['email_body_en']  ?? '')),
            ],
        ],
    ];

    $yaml = build_campaign_yaml($data);

    if (file_put_contents($yamlPath, $yaml) !== false) {
        header('Location: ' . base_url('/admin/config.php?saved=1'));
        exit;
    } else {
        $error = 'Error al guardar el archivo. Verifica los permisos de escritura en config/.';
    }
}

if (isset($_GET['saved'])) {
    $ok = 'Configuración guardada correctamente.';
}

$campaign = load_campaign();

function build_campaign_yaml(array $d): string
{
    $esc = static fn(string $s): string => str_replace(['"', "\n"], ['\"', '\n'], $s);

    $yaml  = "total_cards: {$d['total_cards']}\n";
    $yaml .= "min_real: {$d['min_real']}\n\n";

    $yaml .= "report:\n";
    $yaml .= "  es:  \"" . $esc($d['report']['es'])  . "\"\n";
    $yaml .= "  val: \"" . $esc($d['report']['val']) . "\"\n";
    $yaml .= "  en:  \"" . $esc($d['report']['en'])  . "\"\n\n";

    $yaml .= "campaign_link: \"" . $esc($d['campaign_link']) . "\"\n\n";

    $yaml .= "campaign_link_label:\n";
    $yaml .= "  es:  \"" . $esc($d['campaign_link_label']['es'])  . "\"\n";
    $yaml .= "  val: \"" . $esc($d['campaign_link_label']['val']) . "\"\n";
    $yaml .= "  en:  \"" . $esc($d['campaign_link_label']['en'])  . "\"\n\n";

    $yaml .= "lottery_legal:\n";
    $yaml .= "  es:  \"" . $esc($d['lottery_legal']['es'])  . "\"\n";
    $yaml .= "  val: \"" . $esc($d['lottery_legal']['val']) . "\"\n";
    $yaml .= "  en:  \"" . $esc($d['lottery_legal']['en'])  . "\"\n\n";

    $yaml .= "email_confirmation:\n";
    $yaml .= "  subject:\n";
    $yaml .= "    es:  \"" . $esc($d['email_confirmation']['subject']['es'])  . "\"\n";
    $yaml .= "    val: \"" . $esc($d['email_confirmation']['subject']['val']) . "\"\n";
    $yaml .= "    en:  \"" . $esc($d['email_confirmation']['subject']['en'])  . "\"\n";
    $yaml .= "  body:\n";
    $yaml .= "    es:  \"" . $esc($d['email_confirmation']['body']['es'])  . "\"\n";
    $yaml .= "    val: \"" . $esc($d['email_confirmation']['body']['val']) . "\"\n";
    $yaml .= "    en:  \"" . $esc($d['email_confirmation']['body']['en'])  . "\"\n";

    return $yaml;
}

function ct(array $c, string $key, string $lang): string
{
    $d = $c[$key] ?? [];
    if (is_string($d)) return $d;
    return (string) ($d[$lang] ?? $d['es'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel — Configuración</title>
<style>
  * { box-sizing:border-box; }
  body { background:#f0f2f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; margin:0; }
  .topbar { background:#1877f2; color:#fff; padding:12px 24px; display:flex; justify-content:space-between; align-items:center; }
  .topbar nav a { color:rgba(255,255,255,.85); text-decoration:none; margin-left:16px; font-size:.9rem; font-weight:600; }
  .topbar nav a:hover { color:#fff; }
  .wrap { max-width:800px; margin:24px auto; padding:0 16px 40px; }
  h1 { font-size:1.4rem; margin:0 0 16px; }
  .card { background:#fff; border-radius:8px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.1); margin-bottom:16px; }
  .card h2 { font-size:1rem; margin:0 0 14px; color:#050505; border-bottom:1px solid #e4e6eb; padding-bottom:8px; }
  .field { margin-bottom:14px; }
  label { display:block; font-size:.85rem; font-weight:600; color:#444; margin-bottom:4px; }
  input[type=text], input[type=url], input[type=number], textarea {
    width:100%; padding:9px 10px; border:1px solid #ccc; border-radius:6px; font-size:.9rem; font-family:inherit;
  }
  textarea { resize:vertical; min-height:80px; }
  .hint { font-size:.75rem; color:#65676b; margin-top:3px; }
  .submit-btn { background:#1877f2; color:#fff; border:none; padding:12px 24px; border-radius:6px; font-size:1rem; font-weight:700; cursor:pointer; }
  .submit-btn:hover { background:#166fe5; }
  .ok    { background:#e7f3e8; color:#1a7f37; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-weight:600; }
  .error { background:#fce8e8; color:#c0392b; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-weight:600; }
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
<h1>Configuración de campaña</h1>

<?php if ($ok):    ?><div class="ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">

<div class="card">
  <h2>Parámetros del juego</h2>
  <div class="field">
    <label>Total de noticias por partida</label>
    <input type="number" name="total_cards" value="<?= $campaign['total_cards'] ?>" min="2" max="50">
  </div>
  <div class="field">
    <label>Mínimo de noticias reales por partida</label>
    <input type="number" name="min_real" value="<?= $campaign['min_real'] ?>" min="1">
    <p class="hint">El número real/falsa por partida será aleatorio, garantizando al menos este mínimo de reales.</p>
  </div>
</div>

<div class="card">
  <h2>Informe de la ONG</h2>
  <?php foreach (['es' => 'Español', 'val' => 'Valenciano', 'en' => 'Inglés'] as $l => $lbl): ?>
  <div class="field">
    <label><?= $lbl ?></label>
    <textarea name="report_<?= $l ?>"><?= htmlspecialchars(ct($campaign, 'report', $l)) ?></textarea>
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Enlace de campaña</h2>
  <div class="field">
    <label>URL</label>
    <input type="url" name="campaign_link" value="<?= htmlspecialchars($campaign['campaign_link'] ?? '') ?>">
  </div>
  <?php foreach (['es' => 'Etiqueta (ES)', 'val' => 'Etiqueta (VAL)', 'en' => 'Etiqueta (EN)'] as $l => $lbl): ?>
  <div class="field">
    <label><?= $lbl ?></label>
    <input type="text" name="link_label_<?= $l ?>" value="<?= htmlspecialchars(ct($campaign, 'campaign_link_label', $l)) ?>">
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Texto legal del sorteo</h2>
  <?php foreach (['es' => 'Español', 'val' => 'Valenciano', 'en' => 'Inglés'] as $l => $lbl): ?>
  <div class="field">
    <label><?= $lbl ?></label>
    <input type="text" name="legal_<?= $l ?>" value="<?= htmlspecialchars(ct($campaign, 'lottery_legal', $l)) ?>">
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Email de confirmación</h2>
  <p class="hint" style="margin-bottom:12px">Variables disponibles: <code>{score}</code> <code>{total}</code> <code>{pct}</code> <code>{link}</code></p>
  <?php foreach (['es' => 'Asunto (ES)', 'val' => 'Asunto (VAL)', 'en' => 'Asunto (EN)'] as $l => $lbl): ?>
  <div class="field">
    <label><?= $lbl ?></label>
    <input type="text" name="email_subject_<?= $l ?>" value="<?= htmlspecialchars(ct($campaign['email_confirmation'] ?? [], 'subject', $l)) ?>">
  </div>
  <?php endforeach; ?>
  <?php foreach (['es' => 'Cuerpo (ES)', 'val' => 'Cuerpo (VAL)', 'en' => 'Cuerpo (EN)'] as $l => $lbl): ?>
  <div class="field">
    <label><?= $lbl ?></label>
    <textarea name="email_body_<?= $l ?>"><?= htmlspecialchars(ct($campaign['email_confirmation'] ?? [], 'body', $l)) ?></textarea>
  </div>
  <?php endforeach; ?>
</div>

<button type="submit" class="submit-btn">Guardar configuración</button>
</form>
</div>
</body>
</html>
