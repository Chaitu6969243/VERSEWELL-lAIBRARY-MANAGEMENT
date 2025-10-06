<?php
// Simple test endpoint to check session state
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'set_session') {
    $_SESSION['admin_id'] = 1;
    $_SESSION['is_admin'] = 1;
    echo json_encode(['message' => 'Session set', 'session_id' => session_id()]);
} elseif ($action === 'check_session') {
    echo json_encode([
        'session_id' => session_id(),
        'admin_id' => $_SESSION['admin_id'] ?? null,
        'is_admin' => $_SESSION['is_admin'] ?? null,
        'session_data' => $_SESSION
    ]);
} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>