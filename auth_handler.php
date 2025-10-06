<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$input) {
        error_log("Invalid JSON input: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }

    if (!isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No action specified']);
        exit;
    }

    switch ($input['action']) {
        case 'register':
            handleRegistration($pdo, $input);
            break;
        case 'login':
            handleLogin($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

function handleRegistration($pdo, $data) {
    error_log("Registration attempt with data: " . print_r($data, true));

    // Validate required fields
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name, email, and password are required']);
        return;
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            return;
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Prepare SQL statement
        $sql = "INSERT INTO users (
            name, 
            email, 
            password, 
            is_active,
            is_admin,
            email_notifications,
            sms_notifications,
            created_at
        ) VALUES (?, ?, ?, true, false, ?, ?, NOW())";

        error_log("Executing SQL: " . $sql);

        // Normalize notification flags to integer 0/1 values
        $emailNotifications = isset($data['email_notifications'])
            ? (int) filter_var($data['email_notifications'], FILTER_VALIDATE_BOOLEAN)
            : 1;
        $smsNotifications = isset($data['sms_notifications'])
            ? (int) filter_var($data['sms_notifications'], FILTER_VALIDATE_BOOLEAN)
            : 0;

        // Insert new user
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $data['email'],
            $hashedPassword,
            $emailNotifications,
            $smsNotifications
        ]);

        if (!$result) {
            throw new Exception("Failed to insert user: " . implode(", ", $stmt->errorInfo()));
        }

        $userId = $pdo->lastInsertId();
        
        $pdo->commit();

        error_log("User registered successfully with ID: " . $userId);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $userId
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
}

function handleLogin($pdo, $data) {
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, password, is_active, is_admin
            FROM users 
            WHERE email = ?
        ");
        
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }

        if (!$user['is_active']) {
            http_response_code(403);
            echo json_encode(['error' => 'Account is inactive']);
            return;
        }

        // Start PHP session and store user id
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = $user['is_admin'] ?? 0;

        unset($user['password']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'session' => true,
            'user' => $user
        ]);
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
    }
}
?>