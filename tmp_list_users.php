<?php
require_once 'config.php';
$pdo = getDatabaseConnection();
$stmt = $pdo->query('SELECT id,email,name,created_at FROM users ORDER BY id DESC LIMIT 20');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
