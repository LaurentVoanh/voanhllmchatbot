<?php
// ============================================================
// AETHER v5.0 — GLOSSARY API
// ============================================================
require_once 'config.php';
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['sid'])) {
    echo json_encode(['error' => 'SESSION_EXPIRED']);
    exit;
}

$session = $_SESSION['sid'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET : récupérer le glossaire de la session
if ($method === 'GET') {
    $terms = get_glossary($session);
    echo json_encode(['terms' => $terms]);
    exit;
}

// POST : actions add, define, delete
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // ADD : ajouter un terme manuellement
    if ($action === 'add') {
        $term = trim($input['term'] ?? '');
        $definition = trim($input['definition'] ?? '');
        $context = trim($input['context'] ?? '');
        
        if (!$term) {
            echo json_encode(['error' => 'Term required']);
            exit;
        }
        
        save_glossary_term($session, $term, $definition, $context);
        echo json_encode(['success' => true, 'term' => $term]);
        exit;
    }
    
    // DEFINE : demander à l'IA de définir un terme contextuellement
    if ($action === 'define') {
        $term = trim($input['term'] ?? '');
        
        if (!$term) {
            echo json_encode(['error' => 'Term required']);
            exit;
        }
        
        // Récupérer le contexte récent
        $history = get_history($session, 6);
        $context_text = implode("\n", array_map(fn($m) => strtoupper($m['role']).': '.$m['content'], $history));
        
        // Appeler Mistral pour une définition contextuelle courte
        function do_curl(string $url, string $key, array $payload, int $timeout = 30): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer $key", "Content-Type: application/json"],
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
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
                ['role' => 'system', 'content' => "Définis ce terme de manière concise (2-3 phrases max) en lien avec le contexte de conversation fourni. Réponds uniquement avec la définition."],
                ['role' => 'user', 'content' => "Terme: $term\n\nContexte:\n$context_text"]
            ],
            'temperature' => 0.2,
            'max_tokens'  => 150
        ]);
        
        $definition = '';
        if ($res['code'] === 200 && $res['raw']) {
            $d = json_decode($res['raw'], true);
            $definition = $d['choices'][0]['message']['content'] ?? '';
        }
        
        // Sauvegarder dans le glossaire
        save_glossary_term($session, $term, $definition, substr($context_text, 0, 500));
        
        echo json_encode([
            'term'       => $term,
            'definition' => $definition,
            'saved'      => true
        ]);
        exit;
    }
    
    // DELETE : supprimer un terme
    if ($action === 'delete') {
        $term = trim($input['term'] ?? '');
        
        if (!$term) {
            echo json_encode(['error' => 'Term required']);
            exit;
        }
        
        $db = get_db();
        $db->prepare("DELETE FROM glossary WHERE session_id=? AND term=?")
          ->execute([$session, $term]);
        
        echo json_encode(['success' => true, 'deleted' => $term]);
        exit;
    }
}

// DELETE method : supprimer un terme
if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $input);
    $term = trim($input['term'] ?? $_GET['term'] ?? '');
    
    if (!$term) {
        echo json_encode(['error' => 'Term required']);
        exit;
    }
    
    $db = get_db();
    $db->prepare("DELETE FROM glossary WHERE session_id=? AND term=?")
      ->execute([$session, $term]);
    
    echo json_encode(['success' => true, 'deleted' => $term]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
