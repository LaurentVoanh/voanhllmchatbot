<?php
require_once 'config.php';
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['sid'])) {
        // Supprimer toutes les données de la session courante
        clear_session_data($_SESSION['sid']);
        unset($_SESSION['sid']);
    }
    echo json_encode(['ok' => true]);
}
