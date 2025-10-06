<?php
require_once 'config.php';

try {
	// Get a PDO instance from config.php
	$pdo = getDatabaseConnection();

	// Use prepared statement with named parameters
	// Include a password value because the `password` column is NOT NULL in the DB
	$sql = "INSERT INTO users (name, email, password) VALUES (:name, :email, :password)";
	$stmt = $pdo->prepare($sql);

	// Use PHP's password_hash to store a secure hash (never store plaintext passwords)
	$passwordPlain = 'ChangeMe123!';
	$passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

	$stmt->execute([
		':name'     => 'chaitu',
		':email'    => 'chaitanya76@gmail.com',
		':password' => $passwordHash,
	]);

	// Free the statement cursor
	$stmt->closeCursor();

	echo "done";
} catch (PDOException $e) {
	http_response_code(500);
	echo "Error: " . $e->getMessage();
}

?>
