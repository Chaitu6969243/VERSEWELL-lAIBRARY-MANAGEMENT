<?php
// Database configuration
define('DB_HOST', '127.0.0.1'); // Using IP instead of localhost
define('DB_PORT', '3306');      // Default MySQL port
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'Chaitu@2324');
define('DB_NAME', 'versewell_library');

// Session configuration
function configureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session settings before starting
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_lifetime', '0'); // Session cookie (expires when browser closes)
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_path', '/');
        
        session_start();
    }
}

// Create database connection
function getDatabaseConnection() {
    try {
        // Using TCP connection for MySQL Workbench
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
        
        $pdo = new PDO(
            $dsn,
            DB_USERNAME,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// Set JSON response headers
function setJsonHeaders() {
    header('Content-Type: application/json');
    
    // Allow credentials with specific origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8000';
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Get JSON input
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// Validate required fields
function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
}
?>