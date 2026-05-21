<?php
// fakenews/tests/test_lang.php
declare(strict_types=1);

// Bootstrap mínimo: no necesita sesión ni DB
require_once __DIR__ . '/../inc/config.php';

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    if ($expected === $actual) {
        echo "PASS: {$label}\n";
    } else {
        echo "FAIL: {$label} — expected " . json_encode($expected) . ", got " . json_encode($actual) . "\n";
    }
}

require_once __DIR__ . '/../inc/lang.php';

// normalize_lang
assert_eq('val', normalize_lang('ca'),           'ca → val');
assert_eq('val', normalize_lang('ca-ES'),         'ca-ES → val');
assert_eq('val', normalize_lang('ca-Valencia'),   'ca-Valencia → val');
assert_eq('val', normalize_lang('val'),           'val → val');
assert_eq('en',  normalize_lang('en'),            'en → en');
assert_eq('en',  normalize_lang('en-US'),         'en-US → en');
assert_eq('en',  normalize_lang('en-GB'),         'en-GB → en');
assert_eq('es',  normalize_lang('es'),            'es → es');
assert_eq('es',  normalize_lang('es-ES'),         'es-ES → es');
assert_eq('es',  normalize_lang('fr'),            'fr → es (fallback)');
assert_eq('es',  normalize_lang('de'),            'de → es (fallback)');
assert_eq('es',  normalize_lang(''),              'empty → es (fallback)');

// detect_lang_from_browser
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ca-ES,ca;q=0.9,es;q=0.8';
assert_eq('val', detect_lang_from_browser(), 'Accept-Language ca-ES → val');

$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
assert_eq('en', detect_lang_from_browser(), 'Accept-Language en-US → en');

$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9';
assert_eq('es', detect_lang_from_browser(), 'Accept-Language es-ES → es');

$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9,de;q=0.8';
assert_eq('es', detect_lang_from_browser(), 'Accept-Language fr → es (fallback)');

$_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
assert_eq('es', detect_lang_from_browser(), 'Empty Accept-Language → es');
