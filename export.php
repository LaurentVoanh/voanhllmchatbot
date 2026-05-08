<?php
// ============================================================
// AETHER v5.0 — EXPORT SESSION (Markdown/TXT)
// ============================================================
require_once 'config.php';
require_once 'database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['sid'])) {
    http_response_code(403);
    exit('SESSION_EXPIRED');
}

$session = $_SESSION['sid'];
$format = $_GET['format'] ?? 'markdown';

// Récupérer l'historique complet
$messages = get_history($session, 1000);

// Récupérer le glossaire
$glossary = get_glossary($session);

// Récupérer le plan s'il existe
$plan = get_plan($session);

// Récupérer les stats
$stats = get_session_stats($session);

// Générer un titre auto via Mistral (best effort, non bloquant)
function generate_title(array $messages): string {
    if (empty($messages)) return 'Session AETHER';
    
    // Prendre les 3 premiers messages pour générer le titre
    $preview = array_slice($messages, 0, 3);
    $text = implode("\n", array_map(fn($m) => $m['content'], $preview));
    $text = substr($text, 0, 500);
    
    function do_curl(string $url, string $key, array $payload, int $timeout = 20): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $key", "Content-Type: application/json"],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['raw' => $raw, 'code' => $code, 'err' => $err];
    }
    
    $res = do_curl(MISTRAL_API, get_key_round_robin(), [
        'model'    => 'ministral-3b-2512',
        'messages' => [
            ['role' => 'system', 'content' => "Génère un titre court (max 5 mots) pour cette conversation. Réponds uniquement avec le titre, pas de guillemets."],
            ['role' => 'user', 'content' => "Conversation:\n$text"]
        ],
        'temperature' => 0.3,
        'max_tokens'  => 30
    ]);
    
    if ($res['code'] === 200 && $res['raw']) {
        $d = json_decode($res['raw'], true);
        $title = trim($d['choices'][0]['message']['content'] ?? '', " \t\n\r\"'");
        if ($title) return $title;
    }
    
    return 'Session AETHER ' . date('Y-m-d');
}

$title = generate_title($messages);
$filename = 'session_' . date('Y-m-d_H-i-s') . '.' . ($format === 'txt' ? 'txt' : 'md');

// Construire le document
if ($format === 'markdown') {
    $output = "# {$title}\n\n";
    $output .= "**Date:** " . date('d/m/Y à H:i') . "\n";
    $output .= "**Durée:** Session en cours\n";
    $output .= "**Messages:** " . count($messages) . "\n";
    if (!empty($stats['tok'])) {
        $output .= "**Tokens:** {$stats['tok']}\n";
    }
    $output .= "\n---\n\n";
    
    // Thèmes détectés (si disponibles)
    $last_context = get_last_context($session);
    if (!empty($last_context['themes'])) {
        $output .= "## Thèmes détectés\n\n";
        foreach ($last_context['themes'] as $theme) {
            $output .= "- {$theme}\n";
        }
        $output .= "\n---\n\n";
    }
    
    // Historique du chat
    $output .= "## Conversation\n\n";
    foreach ($messages as $msg) {
        $role_label = strtoupper($msg['role']) === 'assistant' ? 'AETHER' : 'VOUS';
        $output .= "### {$role_label}\n\n";
        $output .= "{$msg['content']}\n\n";
    }
    
    // Plan s'il existe
    if ($plan) {
        $output .= "\n---\n\n";
        $output .= "## Plan de session\n\n";
        $output .= $plan . "\n";
    }
    
    // Glossaire
    if (!empty($glossary)) {
        $output .= "\n---\n\n";
        $output .= "## Glossaire\n\n";
        foreach ($glossary as $term) {
            $output .= "### {$term['term']}\n\n";
            if ($term['definition']) {
                $output .= "{$term['definition']}\n\n";
            }
        }
    }
    
} else {
    // Format TXT brut
    $output = "{$title}\n";
    $output .= str_repeat('=', strlen($title)) . "\n\n";
    $output .= "Date: " . date('d/m/Y à H:i') . "\n";
    $output .= "Messages: " . count($messages) . "\n\n";
    $output .= str_repeat('-', 40) . "\n\n";
    
    foreach ($messages as $msg) {
        $role_label = strtoupper($msg['role']) === 'assistant' ? 'AETHER' : 'VOUS';
        $output .= "[{$role_label}]\n";
        $output .= "{$msg['content']}\n\n";
    }
    
    if (!empty($glossary)) {
        $output .= "\n" . str_repeat('-', 40) . "\n";
        $output .= "GLOSSAIRE\n\n";
        foreach ($glossary as $term) {
            $output .= "- {$term['term']}";
            if ($term['definition']) {
                $output .= ": {$term['definition']}";
            }
            $output .= "\n";
        }
    }
}

// Headers pour téléchargement
header('Content-Type: text/' . ($format === 'markdown' ? 'markdown' : 'plain') . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($output));
header('Cache-Control: no-cache');

echo $output;
