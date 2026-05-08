<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['sid'])) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->prepare("DELETE FROM messages WHERE session_id=?")->execute([$_SESSION['sid']]);
        $db->prepare("DELETE FROM analyses WHERE session_id=?")->execute([$_SESSION['sid']]);
        unset($_SESSION['sid']);
    }
    echo json_encode(['ok' => true]);
}
