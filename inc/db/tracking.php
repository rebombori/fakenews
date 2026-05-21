<?php
declare(strict_types=1);

function record_session_start(PDO $pdo, string $token, string $lang, string $deviceType, string $browser, string $ipAnon, string $country, string $city): void
{
    $stmt = $pdo->prepare('
        INSERT INTO game_sessions (session_token, started_at, lang, device_type, browser, ip_anon, country, city)
        VALUES (:token, :started, :lang, :device, :browser, :ip, :country, :city)
        ON CONFLICT(session_token) DO NOTHING
    ');
    $stmt->execute([
        ':token'   => $token,
        ':started' => time(),
        ':lang'    => $lang,
        ':device'  => $deviceType,
        ':browser' => $browser,
        ':ip'      => $ipAnon,
        ':country' => $country,
        ':city'    => $city,
    ]);
}

function record_session_complete(PDO $pdo, string $token, int $score, int $totalCards): void
{
    $stmt = $pdo->prepare('
        UPDATE game_sessions
        SET completed = 1, completed_at = :now, score = :score, total_cards = :total
        WHERE session_token = :token
    ');
    $stmt->execute([
        ':now'   => time(),
        ':score' => $score,
        ':total' => $totalCards,
        ':token' => $token,
    ]);
}

function anonymize_ip(string $ip): string
{
    // IPv4: hide last octet → 83.45.12.xxx
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $parts[3] = 'xxx';
        return implode('.', $parts);
    }
    // IPv6: hide last 4 groups
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        for ($i = 4; $i < count($parts); $i++) {
            $parts[$i] = 'xxx';
        }
        return implode(':', $parts);
    }
    return 'unknown';
}

function detect_device_type(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
    if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) return 'mobile';
    return 'desktop';
}

function detect_browser(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if (str_contains($ua, 'edg/'))     return 'Edge';
    if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) return 'Opera';
    if (str_contains($ua, 'firefox'))  return 'Firefox';
    if (str_contains($ua, 'safari') && !str_contains($ua, 'chrome')) return 'Safari';
    if (str_contains($ua, 'chrome'))   return 'Chrome';
    return 'Other';
}

function get_client_ip(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        $ip = trim(explode(',', $_SERVER[$header] ?? '')[0]);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}
