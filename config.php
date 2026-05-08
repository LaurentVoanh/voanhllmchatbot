<?php
// ============================================================
// AETHER v5.0 — CONFIG (COPILOTE CONTEXTUEL)
// ============================================================

// Modes opérationnels
define('MODES', [
    'info'       => 'Recherche d\'information',
    'redaction'  => 'Rédaction de texte',
    'critique'   => 'Analyse critique',
    'brainstorm' => 'Brainstorming créatif',
    'technique'  => 'Code & technique',
    'apprendre'  => 'Mode tuteur / apprentissage'
]);

// System prompts par mode
define('SYSTEM_PROMPTS', [
    'info'       => "Tu es AETHER v5.0, mode recherche d'information. Réponds de manière factuelle, cite tes sources quand possible, structure clairement.",
    'redaction'  => "Tu es AETHER v5.0, mode rédaction. Aide l'utilisateur à produire des textes clairs, bien structurés, avec un ton adapté au contexte.",
    'critique'   => "Tu es AETHER v5.0, mode analyse critique. Examine les arguments, identifie les faiblesses, propose des améliorations raisonnées.",
    'brainstorm' => "Tu es AETHER v5.0, mode brainstorming. Génère des idées créatives, explore des pistes variées, sois ouvert et non-jugeant.",
    'technique'  => "Tu es AETHER v5.0, mode technique. Sois précis, donne des exemples concrets, du code si pertinent, explique les concepts complexes simplement.",
    'apprendre'  => "Tu es AETHER v5.0, mode tuteur. Vérifie la compréhension, utilise des analogies, donne des exemples, pose des questions de validation."
]);

// Suggestions de modèles avec descriptions humaines
define('MODEL_SUGGESTIONS', [
    'open-mistral-nemo'     => ['nom' => 'Nemo', 'usage' => 'Chat généraliste, rapide et équilibré'],
    'mistral-small-2506'    => ['nom' => 'Small', 'usage' => 'Analyse, résumé, reformulation'],
    'mistral-large-2512'    => ['nom' => 'Large', 'usage' => 'Raisonnement complexe, analyse critique'],
    'codestral-2508'        => ['nom' => 'Codestral', 'usage' => 'Code, technique, développement'],
    'ministral-3b-2512'     => ['nom' => 'Ministral', 'usage' => 'Tâches rapides, correction, extraction']
]);

// Clés API Mistral (round-robin pour distribution de charge)
define('MISTRAL_KEYS', [
    'key1' => '5qaRTfdsake',
    'key2' => 'o3rfdsShytu',
    'key3' => 'vEzfdsuXkF'
]);

// Cache news : 15 minutes
define('NEWS_CACHE_TTL', 900);

// Fréquence de mise à jour du contexte (tous les X messages)
define('CONTEXT_UPDATE_EVERY', 5);

// Modèles par défaut selon la tâche
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

// Rotation dynamique des clés API basée sur un compteur en session
function get_key_round_robin(): string {
    if (!isset($_SESSION['key_counter'])) {
        $_SESSION['key_counter'] = 0;
    }
    $keys = array_values(MISTRAL_KEYS);
    $index = $_SESSION['key_counter'] % count($keys);
    $_SESSION['key_counter']++;
    return $keys[$index];
}

// Ancienne fonction get_key pour rétro-compatibilité
function get_key(string $role = 'key1'): string {
    $keys = MISTRAL_KEYS;
    return $keys[$role] ?? get_key_round_robin();
}

define('DB_PATH',     __DIR__ . '/db/aether.sqlite');
define('MISTRAL_API', 'https://api.mistral.ai/v1/chat/completions');

// Session démarrée ICI pour que tous les fichiers qui include config.php l'aient
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
