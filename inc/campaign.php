<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/SimpleYaml.php';

function load_campaign(): array
{
    static $campaign;
    if ($campaign !== null) return $campaign;

    $path = app_config()['root'] . '/config/campaign.yaml';
    $raw  = SimpleYaml::parseFile($path);

    $campaign = [
        'total_cards'        => max(2, (int) ($raw['total_cards'] ?? 10)),
        'min_real'           => max(1, (int) ($raw['min_real'] ?? 3)),
        'report'             => (array) ($raw['report'] ?? []),
        'campaign_link'      => trim((string) ($raw['campaign_link'] ?? '')),
        'campaign_link_label'=> (array) ($raw['campaign_link_label'] ?? []),
        'lottery_legal'      => (array) ($raw['lottery_legal'] ?? []),
        'email_confirmation' => (array) ($raw['email_confirmation'] ?? []),
    ];

    return $campaign;
}

function campaign_text(array $campaign, string $key, string $lang): string
{
    $data = $campaign[$key] ?? [];
    if (is_string($data)) return $data;
    if (!is_array($data)) return '';
    return trim((string) ($data[$lang] ?? $data['es'] ?? ''));
}
