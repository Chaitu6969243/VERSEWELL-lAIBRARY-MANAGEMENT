<?php
/**
 * Create admin user for the library system
 */
require_once 'config.php';

try {
    $pdo = getDatabaseConnection();
    
    // Check if admin already exists
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
    $stmt->execute(['admin@admin.com']);
    
    if ($stmt->fetch()) {
        echo "Admin user already exists!\n";
    } else {
        // Create admin user
        $stmt = $pdo->prepare('INSERT INTO admins (name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            'Administrator',
            'admin@admin.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
            1
        ]);
        
        echo "Admin user created successfully!\n";
        echo "Email: admin@admin.com\n";
        echo "Password: admin123\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>