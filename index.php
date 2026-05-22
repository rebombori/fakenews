<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/game/round.php';
require_once __DIR__ . '/inc/game/session.php';
require_once __DIR__ . '/services/DatasetLoaderService.php';
require_once __DIR__ . '/inc/db/participants.php';
require_once __DIR__ . '/inc/email/mailer.php';

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'play_again' && csrf_verify()) {
        reset_game();
        header('Location: ' . base_url('/'));
        exit;
    }

    if ($action === 'submit_email' && csrf_verify()) {
        $email     = trim((string) ($_POST['email'] ?? ''));
        $score     = max(0, (int) ($_POST['score'] ?? 0));
        $total     = max(1, (int) ($_POST['total'] ?? 1));
        $token     = (string) ($_SESSION['session_token'] ?? '');

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo = db_sqlite();

            // Record completed session
            record_session_complete($pdo, $token, $score, $total);

            $saved = save_participant($pdo, $email, $score, $total, $token);

            if ($saved) {
                $campaignForEmail = load_campaign();
                $langForEmail     = (string) ($_SESSION['lang'] ?? 'es');
                $link             = $campaignForEmail['campaign_link'] ?? '';
                $pct              = $total > 0 ? (int) round(($score / $total) * 100) : 0;
                send_confirmation_email($email, $campaignForEmail, $langForEmail, $score, $total, $pct, $link);
            }

            $_SESSION['email_submitted'] = true;
        }

        header('Location: ' . base_url('/?screen=thanks'));
        exit;
    }
}

// ── Routing ──────────────────────────────────────────────────────────────────
$screen = (string) ($_GET['screen'] ?? 'game');
if (!in_array($screen, ['game', 'result', 'thanks'], true)) {
    $screen = 'game';
}

if ($screen === 'game' && is_game_complete()) {
    header('Location: ' . base_url('/?screen=result'));
    exit;
}
if ($screen === 'result' && !is_game_started()) {
    header('Location: ' . base_url('/'));
    exit;
}

// ── Init round if not started ─────────────────────────────────────────────────
if ($screen === 'game' && !is_game_started()) {
    $dataDir  = app_config()['root'] . '/data/news';
    $dataset  = DatasetLoaderService::loadFromDirectory($dataDir);
    $roundResult = build_round(
        $dataset['items'],
        $campaign['total_cards'],
        $campaign['min_real']
    );
    if ($roundResult['error'] !== null) {
        die('Error loading dataset: ' . htmlspecialchars($roundResult['error']));
    }
    $token = bin2hex(random_bytes(16));
    init_game_session($roundResult['round'], $token);
    $pdo = db_sqlite();
    $clientIp = get_client_ip();
    $geo = get_geo_from_ip($clientIp);
    $ua  = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    record_session_start(
        $pdo,
        $token,
        $currentLang,
        detect_device_type($ua),
        detect_browser($ua),
        anonymize_ip($clientIp),
        $geo['country'],
        $geo['city']
    );
}

// ── View data ────────────────────────────────────────────────────────────────
$currentCard  = $screen === 'game' ? get_current_card() : null;
$cardIndex    = (int) ($_SESSION['current_index'] ?? 0);
$totalCards   = count((array) ($_SESSION['round_cards'] ?? []));
$score        = $screen === 'result' ? get_score_summary() : null;
$realCards    = $screen === 'result' ? get_real_cards_from_round() : [];
$langSuffix   = match ($currentLang) { 'en' => 'en', 'es' => 'es', default => 'val' };

function news_title(array $card, string $lang): string
{
    $key = 'title_' . $lang;
    $val = trim((string) ($card[$key] ?? ''));
    return $val !== '' ? $val : trim((string) ($card['title'] ?? ''));
}

function news_summary(array $card, string $lang): string
{
    $key = 'summary_' . $lang;
    $val = trim((string) ($card[$key] ?? ''));
    return $val !== '' ? $val : trim((string) ($card['summary'] ?? ''));
}

function reputation_stars(int $rep): string
{
    return str_repeat('★', $rep) . str_repeat('☆', 5 - $rep);
}

$csrf = csrf_token();
$reportText  = campaign_text($campaign, 'report', $currentLang);
$campaignLink = $campaign['campaign_link'] ?? '';
$campaignLabel= campaign_text($campaign, 'campaign_link_label', $currentLang);
$lotteryLegal = campaign_text($campaign, 'lottery_legal', $currentLang);
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang === 'val' ? 'ca' : $currentLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(tr($i18n, 'game_title', 'Real o Falsa?')) ?></title>
<link rel="stylesheet" href="<?= e(base_url('/assets/style.css')) ?>">
<style>
  body { background: #f0f0f0; color: #1c1e21; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; }
  .fb-topbar { background: #000; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; }
  .fb-topbar .logo { color: #FFFF00; font-weight: 900; font-size: 1.1rem; letter-spacing: 0.04em; text-transform: uppercase; }
  .lang-switcher { display: flex; gap: 6px; }
  .lang-switcher a { color: rgba(255,255,255,0.7); font-size: 0.8rem; font-weight: 600; text-decoration: none; padding: 3px 8px; border-radius: 4px; border: 1px solid transparent; }
  .lang-switcher a.active, .lang-switcher a:hover { background: #FFFF00; color: #000; border-color: #FFFF00; }
  .game-wrap { max-width: 520px; margin: 24px auto; padding: 0 12px; }
  .progress-bar-wrap { background: #fff; border-radius: 8px; padding: 10px 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  .progress-label { font-size: 0.82rem; color: #65676b; margin-bottom: 6px; }
  .progress-bar { background: #e4e6eb; border-radius: 99px; height: 6px; }
  .progress-fill { background: #000; height: 6px; border-radius: 99px; transition: width .3s; }
  .news-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.15); overflow: hidden; margin-bottom: 14px; }
  .card-header { background: #f5f5f5; padding: 10px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #FFFF00; }
  .card-header .source-avatar { width: 36px; height: 36px; border-radius: 50%; background: #000; display: flex; align-items: center; justify-content: center; color: #FFFF00; font-weight: 700; font-size: 1rem; flex-shrink: 0; }
  .card-header .source-info .source-name { font-weight: 600; font-size: 0.9rem; color: #1c1e21; }
  .card-header .source-info .source-rep { font-size: 0.75rem; color: #f5a623; }
  .card-body { padding: 16px; }
  .card-title { font-size: 1.05rem; font-weight: 700; color: #050505; margin: 0 0 8px; line-height: 1.35; }
  .card-summary { font-size: 0.9rem; color: #3c4043; line-height: 1.5; margin: 0; }
  .difficulty-badge { display: inline-block; font-size: 0.7rem; padding: 2px 7px; border-radius: 99px; margin-top: 10px; font-weight: 600; }
  .diff-easy   { background: #e7f3e8; color: #1a7f37; }
  .diff-medium { background: #fff3cd; color: #856404; }
  .diff-hard   { background: #fce8e8; color: #c0392b; }
  .answer-btns { display: flex; gap: 10px; padding: 0 16px 12px; }
  .btn-real { flex: 1; background: #e7f3e8; color: #1a7f37; border: 2px solid #1a7f37; border-radius: 8px; padding: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .15s; }
  .btn-real:hover { background: #1a7f37; color: #fff; }
  .btn-fake { flex: 1; background: #fce8e8; color: #c0392b; border: 2px solid #c0392b; border-radius: 8px; padding: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .15s; }
  .btn-fake:hover { background: #c0392b; color: #fff; }
  .btn-real:disabled, .btn-fake:disabled { opacity: .5; cursor: not-allowed; }
  .ai-logo-wrap { padding: 8px 16px 16px; text-align: center; }
  .ai-logo { width: 100%; max-width: 220px; height: auto; display: inline-block; }
  .result-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.15); padding: 20px; margin-bottom: 14px; }
  .score-big { font-size: 3rem; font-weight: 800; color: #000; text-align: center; line-height: 1; }
  .score-label { text-align: center; color: #65676b; margin: 4px 0 16px; font-size: 0.9rem; }
  .score-pct { text-align: center; font-size: 1.3rem; font-weight: 700; color: #050505; margin-bottom: 16px; }
  .section-title { font-weight: 700; font-size: 1rem; color: #050505; margin: 0 0 10px; }
  .real-list { list-style: none; padding: 0; margin: 0; }
  .real-list li { padding: 8px 0; border-bottom: 1px solid #e4e6eb; font-size: 0.9rem; }
  .real-list li:last-child { border-bottom: none; }
  .real-list .headline { font-weight: 600; color: #1c1e21; }
  .real-list .src { color: #65676b; font-size: 0.8rem; }
  .report-text { font-size: 0.9rem; color: #3c4043; line-height: 1.6; white-space: pre-line; }
  .campaign-btn { display: block; text-align: center; background: #000; color: #FFFF00; padding: 12px; border-radius: 8px; font-weight: 700; text-decoration: none; margin-top: 12px; letter-spacing: 0.02em; }
  .campaign-btn:hover { background: #222; }
  .email-form input[type=email] { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; margin-bottom: 8px; }
  .email-form button { width: 100%; background: #000; color: #FFFF00; border: none; padding: 11px; border-radius: 6px; font-size: 1rem; font-weight: 700; cursor: pointer; letter-spacing: 0.02em; }
  .email-form button:hover { background: #222; }
  .legal-text { font-size: 0.75rem; color: #65676b; margin-top: 6px; }
  .play-again-btn { width: 100%; background: #e4e6eb; color: #050505; border: none; padding: 11px; border-radius: 6px; font-size: 0.95rem; font-weight: 600; cursor: pointer; margin-top: 8px; }
  .play-again-btn:hover { background: #d8dadf; }
  .feedback-overlay { display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 100; }
  .feedback-overlay.show { display: flex; }
  .feedback-bubble { font-size: 5rem; animation: popIn .35s ease; }
  @keyframes popIn { from { transform: scale(0.3); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  .thanks-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.15); padding: 32px 20px; text-align: center; margin-bottom: 14px; }
  .thanks-icon { font-size: 3rem; margin-bottom: 12px; }
  .ai-footer-logo { padding: 4px 0 8px; text-align: center; }
  .ai-footer-logo img { width: 100%; max-width: 200px; height: auto; opacity: 0.85; }
</style>
</head>
<body>

<div class="fb-topbar">
  <span class="logo"><?= $currentLang === 'en' ? 'Amnesty International' : 'Amnistía Internacional' ?></span>
  <div class="lang-switcher">
    <a href="?lang=es<?= $screen !== 'game' ? '&screen=' . $screen : '' ?>" class="<?= $currentLang === 'es' ? 'active' : '' ?>"><?= tr($i18n, 'lang_es', 'ES') ?></a>
    <a href="?lang=val<?= $screen !== 'game' ? '&screen=' . $screen : '' ?>" class="<?= $currentLang === 'val' ? 'active' : '' ?>"><?= tr($i18n, 'lang_val', 'VAL') ?></a>
    <a href="?lang=en<?= $screen !== 'game' ? '&screen=' . $screen : '' ?>" class="<?= $currentLang === 'en' ? 'active' : '' ?>"><?= tr($i18n, 'lang_en', 'EN') ?></a>
  </div>
</div>

<div class="game-wrap">

<?php if ($screen === 'game' && $currentCard !== null): ?>

<div class="progress-bar-wrap">
  <div class="progress-label">
    <?= e(str_replace(['{current}', '{total}'], [$cardIndex + 1, $totalCards], tr($i18n, 'card_counter', 'Noticia {current} de {total}'))) ?>
  </div>
  <div class="progress-bar">
    <div class="progress-fill" style="width:<?= $totalCards > 0 ? round(($cardIndex / $totalCards) * 100) : 0 ?>%"></div>
  </div>
</div>

<?php
  $title   = news_title($currentCard, $langSuffix);
  $summary = news_summary($currentCard, $langSuffix);
  $source  = e((string) ($currentCard['source'] ?? ''));
  $rep     = max(1, min(5, (int) ($currentCard['source_reputation'] ?? 3)));
  $diff    = (string) ($currentCard['difficulty'] ?? 'medium');
  $diffLabel = tr($i18n, 'difficulty_' . $diff, $diff);
  $cardId  = e((string) ($currentCard['id'] ?? ''));
  $avatar  = strtoupper(mb_substr($currentCard['source'] ?? '?', 0, 1));
?>

<div class="news-card" id="news-card">
  <div class="card-header">
    <div class="source-avatar"><?= e($avatar) ?></div>
    <div class="source-info">
      <div class="source-name"><?= $source ?></div>
      <div class="source-rep" title="<?= e(tr($i18n, 'reputation_label', 'Reputación')) ?>">
        <?= reputation_stars($rep) ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <p class="card-title"><?= e($title) ?></p>
    <p class="card-summary"><?= e($summary) ?></p>
    <span class="difficulty-badge diff-<?= e($diff) ?>"><?= e($diffLabel) ?></span>
  </div>
</div>

<div class="answer-btns">
  <button class="btn-real" id="btn-real" data-answer="real" data-card="<?= $cardId ?>">
    <?= e(tr($i18n, 'btn_real', '✓ Real')) ?>
  </button>
  <button class="btn-fake" id="btn-fake" data-answer="fake" data-card="<?= $cardId ?>">
    <?= e(tr($i18n, 'btn_fake', '✗ Falsa')) ?>
  </button>
</div>

<div class="ai-logo-wrap">
  <img src="<?= e(base_url($currentLang === 'en' ? '/assets/logo_en.svg' : '/assets/logo_es.svg')) ?>"
       alt="<?= $currentLang === 'en' ? 'Amnesty International' : 'Amnistía Internacional' ?>"
       class="ai-logo">
</div>

<div class="feedback-overlay" id="feedback-overlay">
  <div class="feedback-bubble" id="feedback-bubble"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
const BASE_URL = <?= json_encode(base_url()) ?>;
const I18N = {
  loading: <?= json_encode(tr($i18n, 'loading', 'Cargando...')) ?>,
  error:   <?= json_encode(tr($i18n, 'error_generic', 'Error. Inténtalo de nuevo.')) ?>
};

async function sendAnswer(cardId, answer) {
  document.getElementById('btn-real').disabled = true;
  document.getElementById('btn-fake').disabled = true;

  try {
    const resp = await fetch(BASE_URL + '/api/answer.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      body: JSON.stringify({ card_id: cardId, answer, csrf_token: CSRF })
    });
    const data = await resp.json();

    if (!data.ok) {
      alert(I18N.error);
      document.getElementById('btn-real').disabled = false;
      document.getElementById('btn-fake').disabled = false;
      return;
    }

    const overlay = document.getElementById('feedback-overlay');
    const bubble  = document.getElementById('feedback-bubble');
    bubble.textContent = data.correct ? '✅' : '❌';
    overlay.classList.add('show');

    setTimeout(() => {
      overlay.classList.remove('show');
      if (data.done) {
        window.location.href = BASE_URL + '/?screen=result';
      } else {
        window.location.reload();
      }
    }, 700);

  } catch (e) {
    alert(I18N.error);
    document.getElementById('btn-real').disabled = false;
    document.getElementById('btn-fake').disabled = false;
  }
}

document.getElementById('btn-real').addEventListener('click', function() {
  sendAnswer(this.dataset.card, 'real');
});
document.getElementById('btn-fake').addEventListener('click', function() {
  sendAnswer(this.dataset.card, 'fake');
});
</script>

<?php elseif ($screen === 'result' && $score !== null): ?>

<div class="result-card">
  <div class="score-big"><?= (int) $score['correct'] ?>/<?= (int) $score['total'] ?></div>
  <div class="score-pct"><?= e(str_replace('{pct}', (string) $score['pct'], tr($i18n, 'result_pct', '{pct}%'))) ?></div>
  <p style="text-align:center;color:#65676b;font-size:.9rem;margin:0">
    <?= e(str_replace(['{correct}', '{total}'], [(string) $score['correct'], (string) $score['total']], tr($i18n, 'result_score', 'Has acertado {correct} de {total}'))) ?>
  </p>
</div>

<?php if ($realCards): ?>
<div class="result-card">
  <p class="section-title"><?= e(tr($i18n, 'result_real_headlines', 'Las noticias reales de esta partida')) ?></p>
  <ul class="real-list">
    <?php foreach ($realCards as $card): ?>
    <li>
      <div class="headline"><?= e(news_title($card, $langSuffix)) ?></div>
      <div class="src"><?= e((string) ($card['source'] ?? '')) ?></div>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($reportText): ?>
<div class="result-card">
  <p class="section-title"><?= e(tr($i18n, 'result_report_title', 'Sobre esta campaña')) ?></p>
  <p class="report-text"><?= e($reportText) ?></p>
  <?php if ($campaignLink): ?>
  <a href="<?= e($campaignLink) ?>" target="_blank" rel="noopener noreferrer" class="campaign-btn">
    <?= e($campaignLabel ?: 'Más información') ?>
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="result-card">
  <p class="section-title"><?= e(tr($i18n, 'result_participate', 'Participa en el sorteo')) ?></p>
  <?php if (!empty($_SESSION['email_submitted'])): ?>
    <p style="color:#1a7f37;font-weight:600"><?= e(tr($i18n, 'result_already_participated', 'Ya has enviado tu participación.')) ?></p>
  <?php else: ?>
  <form class="email-form" method="POST" action="<?= e(base_url('/')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="submit_email">
    <input type="hidden" name="score" value="<?= $score['correct'] ?>">
    <input type="hidden" name="total" value="<?= $score['total'] ?>">
    <input type="email" name="email" required
           placeholder="<?= e(tr($i18n, 'result_email_placeholder', 'tu@email.com')) ?>">
    <button type="submit"><?= e(tr($i18n, 'result_email_submit', 'Participar')) ?></button>
    <?php if ($lotteryLegal): ?>
    <p class="legal-text"><?= e($lotteryLegal) ?></p>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>

<form method="POST" action="<?= e(base_url('/')) ?>">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="play_again">
  <button type="submit" class="play-again-btn"><?= e(tr($i18n, 'result_play_again', 'Jugar de nuevo')) ?></button>
</form>

<div class="ai-footer-logo">
  <img src="<?= e(base_url($currentLang === 'en' ? '/assets/logo_en.svg' : '/assets/logo_es.svg')) ?>"
       alt="<?= $currentLang === 'en' ? 'Amnesty International' : 'Amnistía Internacional' ?>">
</div>

<?php elseif ($screen === 'thanks'): ?>

<div class="thanks-card">
  <div class="thanks-icon">🎉</div>
  <h2 style="margin:0 0 8px"><?= e(tr($i18n, 'thanks_title', '¡Gracias por participar!')) ?></h2>
  <p style="color:#65676b"><?= e(tr($i18n, 'thanks_msg', 'Hemos registrado tu participación.')) ?></p>
  <?php if ($campaignLink): ?>
  <a href="<?= e($campaignLink) ?>" target="_blank" rel="noopener noreferrer" class="campaign-btn" style="margin-top:16px">
    <?= e($campaignLabel ?: 'Más información') ?>
  </a>
  <?php endif; ?>
</div>

<form method="POST" action="<?= e(base_url('/')) ?>">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="play_again">
  <button type="submit" class="play-again-btn"><?= e(tr($i18n, 'thanks_play_again', 'Jugar de nuevo')) ?></button>
</form>

<div class="ai-footer-logo">
  <img src="<?= e(base_url($currentLang === 'en' ? '/assets/logo_en.svg' : '/assets/logo_es.svg')) ?>"
       alt="<?= $currentLang === 'en' ? 'Amnesty International' : 'Amnistía Internacional' ?>">
</div>

<?php endif; ?>
</div>
</body>
</html>
