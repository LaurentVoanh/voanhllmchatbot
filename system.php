<?php
require_once 'config.php';
require_once 'database.php';
header('Content-Type: application/json; charset=utf-8');

$db = get_db();

// Compteurs DB
$total_sessions  = $db->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
$total_messages  = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$total_analyses  = $db->query("SELECT COUNT(*) FROM analyses")->fetchColumn();

// Taille DB
$db_size = file_exists(DB_PATH) ? round(filesize(DB_PATH) / 1024, 1) . ' KB' : '—';

// Validation clés
$keys = MISTRAL_KEYS;
$key_status = [];
foreach ($keys as $role => $key) {
    $key_status[] = [
        'role' => strtoupper($role),
        'ok'   => (!empty($key) && strlen($key) >= 20 && strpos($key, 'VOTRE') === false),
    ];
}
$keys_count = count(array_filter($key_status, fn($k) => $k['ok']));

// Uptime proxy (via session)
$uptime = date('d/m/Y H:i:s');

echo json_encode([
    'php'             => PHP_VERSION,
    'server'          => php_uname('s') . ' ' . php_uname('r'),
    'memory_limit'    => ini_get('memory_limit'),
    'max_exec'        => ini_get('max_execution_time'),
    'db_size'         => $db_size,
    'total_sessions'  => (int)$total_sessions,
    'total_messages'  => (int)$total_messages,
    'total_analyses'  => (int)$total_analyses,
    'keys_count'      => $keys_count,
    'key_status'      => $key_status,
    'model_chat'      => $GLOBALS['models']['chat']     ?? '—',
    'model_analysis'  => $GLOBALS['models']['analysis'] ?? '—',
    'uptime'          => $uptime,
], JSON_UNESCAPED_UNICODE);
