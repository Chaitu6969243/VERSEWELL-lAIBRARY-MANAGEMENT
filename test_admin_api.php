<?php
// Test admin backend directly
session_start();

// Simulate login
$_SESSION['admin_id'] = 1;
$_SESSION['is_admin'] = 1;

echo "Session admin_id: " . ($_SESSION['admin_id'] ?? 'not set') . "\n";
echo "Session is_admin: " . ($_SESSION['is_admin'] ?? 'not set') . "\n";

// Test whoami
echo "\n=== Testing whoami ===\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/admin_backend.php?action=whoami');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name().'='.session_id());
$result = curl_exec($ch);
curl_close($ch);
echo $result . "\n";

// Test users
echo "\n=== Testing users ===\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/admin_backend.php?action=users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name().'='.session_id());
$result = curl_exec($ch);
curl_close($ch);
echo $result . "\n";
?>