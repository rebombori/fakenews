<?php
declare(strict_types=1);

function get_geo_from_ip(string $ip): array
{
    // Do not geolocate private/local IPs
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['country' => '', 'city' => ''];
    }

    $cached = $_SESSION['geo'] ?? null;
    if (is_array($cached)) return $cached;

    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=country,city,status';
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);

    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw)) {
        $_SESSION['geo'] = ['country' => '', 'city' => ''];
        return $_SESSION['geo'];
    }

    $data = json_decode($raw, true);
    $result = [
        'country' => (is_array($data) && ($data['status'] ?? '') === 'success') ? (string) ($data['country'] ?? '') : '',
        'city'    => (is_array($data) && ($data['status'] ?? '') === 'success') ? (string) ($data['city']    ?? '') : '',
    ];

    $_SESSION['geo'] = $result;
    return $result;
}
