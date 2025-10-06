<?php
require_once 'config.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$email = $argv[1] ?? 'admin@admin.com';
$password = $argv[2] ?? 'admin123';
$name = $argv[3] ?? 'Administrator';
$role = $argv[4] ?? 'admin';

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role)");
    $stmt->execute([$name, $email, $hash, $role]);
    echo "Admin inserted/updated successfully. Email: $email\n";
} catch (PDOException $e) {
    echo "Failed to insert admin: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

return 0;
