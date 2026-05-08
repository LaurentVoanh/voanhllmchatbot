<?php
// ============================================================
// AETHER v4.0 — CONFIG (CORRIGÉ — vrais modèles Free Tier)
// ============================================================

define('MISTRAL_KEYS', [
    'responder' => '5qaRTfdsake',
    'analyzer1' => 'o3rfdsShytu',
    'analyzer2' => 'vEzfdsuXkF',
]);

// Modèles exacts Free Tier (pas d'alias *-latest)
$GLOBALS['models'] = [
    'chat'      => 'open-mistral-nemo',
    'analysis'  => 'mistral-small-2506',
    'reasoning' => 'mistral-large-2512',
    'creative'  => 'mistral-small-2506',
    'code'      => 'codestral-2508',
    'fast'      => 'ministral-3b-2512',
];

function select_model(string $task = 'chat'): string {
    return $GLOBALS['models'][$task] ?? $GLOBALS['models']['chat'];
}

function get_key(string $role = 'responder'): string {
    $keys = MISTRAL_KEYS;
    return $keys[$role] ?? array_values($keys)[0];
}

define('DB_PATH',     __DIR__ . '/db/aether.sqlite');
define('MISTRAL_API', 'https://api.mistral.ai/v1/chat/completions');

// Session démarrée ICI pour que tous les fichiers qui include config.php l'aient
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
