<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session
session_start();

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'headers' => getallheaders(),
    'session_config' => [
        'save_handler' => ini_get('session.save_handler'),
        'save_path' => ini_get('session.save_path'),
        'cookie_lifetime' => ini_get('session.cookie_lifetime'),
        'cookie_domain' => ini_get('session.cookie_domain'),
        'cookie_path' => ini_get('session.cookie_path'),
        'cookie_secure' => ini_get('session.cookie_secure'),
        'cookie_httponly' => ini_get('session.cookie_httponly'),
        'cookie_samesite' => ini_get('session.cookie_samesite'),
        'use_cookies' => ini_get('session.use_cookies'),
        'name' => ini_get('session.name')
    ]
];

// If we have admin session, show that info
if (isset($_SESSION['admin_id'])) {
    $debug_info['admin_session'] = [
        'admin_id' => $_SESSION['admin_id'],
        'admin_username' => $_SESSION['admin_username'] ?? 'not set'
    ];
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>