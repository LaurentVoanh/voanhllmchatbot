<?php
// ============================================================
// AETHER v5.0 — LEFT COLUMN DATA API
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
$user_email = $_SESSION['user_email'] ?? 'anonyme';

$db = get_db();

// 1. Sessions précédentes (5 dernières)
$stmt = $db->prepare("
    SELECT s.id, s.created_at, 
           (SELECT COUNT(*) FROM messages m WHERE m.session_id = s.id) as msg_count
    FROM sessions s
    WHERE s.user_id = ? OR s.id = ?
    ORDER BY s.created_at DESC
    LIMIT 6
");
$stmt->execute([$_SESSION['user_id'] ?? null, $session]);
$sessions_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formater les sessions avec titres auto (simplifié)
$previous_sessions = [];
foreach ($sessions_raw as $s) {
    if ($s['id'] === $session) continue; // Exclure session courante
    
    // Récupérer le premier message pour générer un mini-titre
    $first_msg = $db->prepare("SELECT content FROM messages WHERE session_id=? ORDER BY created_at ASC LIMIT 1");
    $first_msg->execute([$s['id']]);
    $fm = $first_msg->fetch(PDO::FETCH_ASSOC);
    
    $title_preview = '';
    if ($fm) {
        $title_preview = substr(strip_tags($fm['content']), 0, 40);
        if (strlen($fm['content']) > 40) $title_preview .= '...';
    }
    
    $previous_sessions[] = [
        'id'        => $s['id'],
        'date'      => date('d/m H:i', strtotime($s['created_at'])),
        'messages'  => (int)$s['msg_count'],
        'preview'   => $title_preview ?: 'Session sans message'
    ];
}

// 2. Stats de la session courante
$stats = get_session_stats($session);
$msg_count = (int)($stats['cnt'] ?? 0);

// Calculer la durée (depuis le premier message)
$first_msg_time = $db->prepare("SELECT created_at FROM messages WHERE session_id=? ORDER BY created_at ASC LIMIT 1");
$first_msg_time->execute([$session]);
$fmt = $first_msg_time->fetch(PDO::FETCH_ASSOC);
$duration = $fmt ? (time() - strtotime($fmt['created_at'])) : 0;
$duration_formatted = sprintf('%dm %ds', floor($duration / 60), $duration % 60);

// 3. Suggestions IA (left_suggest) - à appeler via api.php normalement
// Ici on retourne des suggestions basiques si pas en cache
$left_suggestions = [
    'recommended_model' => $_SESSION['model_task'] ?? 'chat',
    'model_reason' => 'Modèle actuel',
    'recommended_mode' => $_SESSION['mode'] ?? 'info',
    'mode_reason' => 'Mode actif',
    'depth_suggestion' => $msg_count < 3 
        ? 'Tes questions sont courtes — essaie d\'ajouter plus de contexte' 
        : 'Continue d\'approfondir tes questions',
    'shortcuts' => ['Résume la session', 'Génère un plan', 'Export Markdown']
];

// 4. Modèles disponibles avec descriptions
$available_models = [];
foreach (MODEL_SUGGESTIONS as $model_id => $info) {
    $available_models[] = [
        'id'    => $model_id,
        'nom'   => $info['nom'],
        'usage' => $info['usage']
    ];
}

// 5. Mode actif et modes disponibles
$active_mode = $_SESSION['mode'] ?? 'info';
$available_modes = MODES;

echo json_encode([
    'email'             => $user_email,
    'session_id'        => substr($session, 0, 10),
    'duration'          => $duration_formatted,
    'message_count'     => $msg_count,
    'token_count'       => (int)($stats['tok'] ?? 0),
    'previous_sessions' => $previous_sessions,
    'suggestions'       => $left_suggestions,
    'models'            => $available_models,
    'active_model'      => $_SESSION['model_task'] ?? 'chat',
    'active_mode'       => $active_mode,
    'modes'             => $available_modes
]);
