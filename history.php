<?php
require_once 'config.php';
require_once 'database.php';
header('Content-Type: application/json; charset=utf-8');

$session = $_SESSION['sid'] ?? '';
if (!$session) {
    echo json_encode(['messages' => []]);
    exit;
}

$db   = get_db();
$stmt = $db->prepare("SELECT role, content, created_at, model_used, tokens_in, tokens_out FROM messages WHERE session_id=? ORDER BY created_at ASC LIMIT 100");
$stmt->execute([$session]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
