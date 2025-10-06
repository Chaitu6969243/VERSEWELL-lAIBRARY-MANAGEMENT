<?php
require_once 'config.php';
try {
    $pdo = getDatabaseConnection();
    echo 'Database connection successful' . "\n";
    
    // Test users query
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $userCount = $stmt->fetch()['count'];
    echo 'Total users: ' . $userCount . "\n";
    
    // Test books query
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM books');
    $bookCount = $stmt->fetch()['count'];
    echo 'Total books: ' . $bookCount . "\n";
    
    // Test borrowings query
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM borrowings');
    $borrowingCount = $stmt->fetch()['count'];
    echo 'Total borrowings: ' . $borrowingCount . "\n";
    
    // Test admin query
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM admins');
    $adminCount = $stmt->fetch()['count'];
    echo 'Total admins: ' . $adminCount . "\n";
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>