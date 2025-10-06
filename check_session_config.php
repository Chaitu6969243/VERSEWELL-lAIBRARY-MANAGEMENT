<?php
// Check PHP session configuration
echo "PHP Session Configuration:\n";
echo "========================\n";
echo "session.save_handler: " . ini_get('session.save_handler') . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_secure: " . (ini_get('session.cookie_secure') ? 'true' : 'false') . "\n";
echo "session.cookie_httponly: " . (ini_get('session.cookie_httponly') ? 'true' : 'false') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "session.use_cookies: " . (ini_get('session.use_cookies') ? 'true' : 'false') . "\n";
echo "session.name: " . ini_get('session.name') . "\n";

// Test if we can start a session
session_start();
echo "\nSession Test:\n";
echo "=============\n";
echo "Session ID: " . session_id() . "\n";
echo "Session status: " . session_status() . "\n";

// Set a test value
$_SESSION['test'] = 'hello';
echo "Test session value set\n";

// Try to retrieve it
echo "Test session value: " . ($_SESSION['test'] ?? 'not found') . "\n";

?>