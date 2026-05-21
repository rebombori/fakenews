<?php
declare(strict_types=1);

function ensure_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS game_sessions (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        session_token TEXT    NOT NULL UNIQUE,
        started_at    INTEGER NOT NULL,
        completed_at  INTEGER,
        completed     INTEGER NOT NULL DEFAULT 0,
        score         INTEGER,
        total_cards   INTEGER,
        lang          TEXT,
        device_type   TEXT,
        browser       TEXT,
        ip_anon       TEXT,
        country       TEXT,
        city          TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS participants (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        email         TEXT    NOT NULL UNIQUE,
        score         INTEGER NOT NULL,
        total_cards   INTEGER NOT NULL,
        submitted_at  INTEGER NOT NULL,
        session_token TEXT
    )');
}
