<?php
declare(strict_types=1);

function participant_exists(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare('SELECT id FROM participants WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => strtolower(trim($email))]);
    return $stmt->fetch() !== false;
}

/**
 * Saves a participant. If email already exists with lower score, updates.
 * Returns true if inserted or updated, false if email exists with equal or higher score.
 */
function save_participant(PDO $pdo, string $email, int $score, int $totalCards, string $sessionToken): bool
{
    $email = strtolower(trim($email));

    $existing = $pdo->prepare('SELECT id, score FROM participants WHERE email = :email LIMIT 1');
    $existing->execute([':email' => $email]);
    $row = $existing->fetch();

    if ($row === false) {
        $stmt = $pdo->prepare('
            INSERT INTO participants (email, score, total_cards, submitted_at, session_token)
            VALUES (:email, :score, :total, :ts, :token)
        ');
        $stmt->execute([
            ':email' => $email,
            ':score' => $score,
            ':total' => $totalCards,
            ':ts'    => time(),
            ':token' => $sessionToken,
        ]);
        return true;
    }

    if ($score > (int) $row['score']) {
        $stmt = $pdo->prepare('
            UPDATE participants
            SET score = :score, total_cards = :total, submitted_at = :ts, session_token = :token
            WHERE email = :email
        ');
        $stmt->execute([
            ':score' => $score,
            ':total' => $totalCards,
            ':ts'    => time(),
            ':token' => $sessionToken,
            ':email' => $email,
        ]);
        return true;
    }

    return false;
}
