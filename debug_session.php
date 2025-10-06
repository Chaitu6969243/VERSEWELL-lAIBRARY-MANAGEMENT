<?php
// Test admin session directly
require_once 'config.php';

session_start();

echo "Testing Admin Session Debug\n";
echo "===========================\n";

// Test 1: Direct login
echo "1. Testing direct admin session setup...\n";
$_SESSION['admin_id'] = 1;
$_SESSION['is_admin'] = 1;

echo "Session ID: " . session_id() . "\n";
echo "Session admin_id: " . ($_SESSION['admin_id'] ?? 'not set') . "\n";
echo "Session is_admin: " . ($_SESSION['is_admin'] ?? 'not set') . "\n";

// Test 2: Check database admin
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('SELECT id, name, email, role, is_active FROM admins WHERE id = ? AND is_active = 1');
    $stmt->execute([1]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "Database admin found: " . json_encode($admin) . "\n";
    } else {
        echo "No admin found in database with ID 1\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

// Test 3: Test the admin backend whoami endpoint internally
echo "\n2. Testing admin_backend whoami internally...\n";

// Simulate GET request for whoami
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'whoami';

ob_start();
include 'admin_backend.php';
$whoami_output = ob_get_clean();

echo "Whoami output: " . $whoami_output . "\n";

// Test 4: Test users endpoint
echo "\n3. Testing admin_backend users internally...\n";
$_GET['action'] = 'users';

ob_start();
include 'admin_backend.php';
$users_output = ob_get_clean();

echo "Users output: " . $users_output . "\n";

?>