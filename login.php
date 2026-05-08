<?php
require_once 'config.php';
require_once 'database.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(trim($input['email'] ?? ''));
header('Content-Type: application/json; charset=utf-8');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Email invalide'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = get_db();
// Crée user si inexistant
$db->prepare("INSERT OR IGNORE INTO users (email, created_at) VALUES (?, CURRENT_TIMESTAMP)")->execute([$email]);
$user = $db->prepare("SELECT id, email, created_at FROM users WHERE email = ?")->execute([$email]) ? null : null;
$stmt = $db->prepare("SELECT id, email, created_at FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION['user_email'] = $email;
$_SESSION['user_id']    = $user['id'];
$_SESSION['sid']        = 'u' . $user['id'] . '_' . bin2hex(random_bytes(6));
ensure_session($_SESSION['sid']);

echo json_encode([
    'ok'       => true,
    'email'    => $email,
    'sid'      => substr($_SESSION['sid'], 0, 12),
    'member_since' => $user['created_at'] ?? date('Y-m-d'),
], JSON_UNESCAPED_UNICODE);
