<?php
declare(strict_types=1);

function stats_overview(PDO $pdo): array
{
    $total        = (int) $pdo->query('SELECT COUNT(*) FROM game_sessions')->fetchColumn();
    $completed    = (int) $pdo->query('SELECT COUNT(*) FROM game_sessions WHERE completed = 1')->fetchColumn();
    $avgScore     = $pdo->query('SELECT AVG(CAST(score AS REAL) / total_cards * 100) FROM game_sessions WHERE completed = 1 AND total_cards > 0')->fetchColumn();
    $participants = (int) $pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();

    return [
        'total_visits'    => $total,
        'completed'       => $completed,
        'completion_rate' => $total > 0 ? round($completed / $total * 100) : 0,
        'avg_accuracy'    => $avgScore !== null ? (int) round((float) $avgScore) : 0,
        'participants'    => $participants,
    ];
}

function stats_by_day(PDO $pdo, int $days = 30): array
{
    $since = time() - ($days * 86400);
    $stmt  = $pdo->prepare('
        SELECT date(started_at, "unixepoch") AS day,
               COUNT(*) AS visits,
               SUM(completed) AS completions
        FROM game_sessions
        WHERE started_at >= :since
        GROUP BY day
        ORDER BY day ASC
    ');
    $stmt->execute([':since' => $since]);
    return $stmt->fetchAll();
}

function stats_by_country(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare('
        SELECT country, COUNT(*) AS cnt
        FROM game_sessions
        WHERE country IS NOT NULL AND country != ""
        GROUP BY country
        ORDER BY cnt DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function stats_by_device(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT device_type, COUNT(*) AS cnt
        FROM game_sessions
        WHERE device_type IS NOT NULL
        GROUP BY device_type
        ORDER BY cnt DESC
    ');
    return $stmt->fetchAll();
}

function stats_by_browser(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT browser, COUNT(*) AS cnt
        FROM game_sessions
        WHERE browser IS NOT NULL
        GROUP BY browser
        ORDER BY cnt DESC
    ');
    return $stmt->fetchAll();
}

function stats_score_distribution(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT CAST(ROUND(CAST(score AS REAL) / total_cards * 10) * 10 AS INTEGER) AS bucket,
               COUNT(*) AS cnt
        FROM game_sessions
        WHERE completed = 1 AND total_cards > 0
        GROUP BY bucket
        ORDER BY bucket ASC
    ');
    return $stmt->fetchAll();
}
