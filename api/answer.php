<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/game/session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw     = file_get_contents('php://input');
$payload = is_string($raw) ? (json_decode($raw, true) ?? []) : [];

if (!is_array($payload)) {
    $payload = [];
}

$cardId = trim((string) ($payload['card_id'] ?? ''));
$answer = trim((string) ($payload['answer'] ?? ''));

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if ($cardId === '' || !in_array($answer, ['real', 'fake'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload: card_id and answer (real|fake) required']);
    exit;
}

if (!is_game_started() || is_game_complete()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'No active game or game already complete']);
    exit;
}

$result = save_answer($cardId, $answer);

echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
exit;
