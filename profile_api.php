<?php
/**
 * User Profile API for managing user data and preferences
 */
require_once 'config.php';

// Configure and start session
configureSession();

setJsonHeaders();

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    sendJsonResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get JSON input for POST requests
$input = null;
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST ?: [];
    }
}

/**
 * Check if user is logged in
 */
function requireUserSession() {
    if (empty($_SESSION['user_id'])) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
    }
    return $_SESSION['user_id'];
}

// Get user profile
if ($method === 'GET' && $action === 'profile') {
    $userId = requireUserSession();
    
    try {
        $stmt = $pdo->prepare('
            SELECT 
                id, name, email, phone, created_at, 
                email_notifications, sms_notifications, profile_photo
            FROM users 
            WHERE id = ? AND is_active = 1
        ');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendJsonResponse(['error' => 'User not found'], 404);
        }
        
        sendJsonResponse($user);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Update user profile
if ($method === 'POST' && $action === 'update-profile') {
    $userId = requireUserSession();
    
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $emailNotifications = $input['email_notifications'] ?? true;
    $smsNotifications = $input['sms_notifications'] ?? false;
    
    if (empty($name)) {
        sendJsonResponse(['error' => 'Name is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare('
            UPDATE users 
            SET name = ?, phone = ?, email_notifications = ?, sms_notifications = ?, updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$name, $phone, $emailNotifications, $smsNotifications, $userId]);
        
        sendJsonResponse(['message' => 'Profile updated successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Change password
if ($method === 'POST' && $action === 'change-password') {
    $userId = requireUserSession();
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        sendJsonResponse(['error' => 'All password fields are required'], 400);
    }
    
    if ($newPassword !== $confirmPassword) {
        sendJsonResponse(['error' => 'New passwords do not match'], 400);
    }
    
    if (strlen($newPassword) < 8) {
        sendJsonResponse(['error' => 'New password must be at least 8 characters long'], 400);
    }
    
    try {
        // Verify current password
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendJsonResponse(['error' => 'User not found'], 404);
        }
        
        $valid = false;
        if (strpos($user['password'], '$') === 0) {
            // Hashed password
            $valid = password_verify($currentPassword, $user['password']);
        } else {
            // Plain text password (legacy)
            $valid = hash_equals($user['password'], $currentPassword);
        }
        
        if (!$valid) {
            sendJsonResponse(['error' => 'Current password is incorrect'], 400);
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$hashedPassword, $userId]);
        
        sendJsonResponse(['message' => 'Password changed successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Get borrowing history
if ($method === 'GET' && $action === 'borrowing-history') {
    $userId = requireUserSession();
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $stmt = $pdo->prepare('
            SELECT 
                b.id as borrowing_id,
                b.borrowed_at,
                b.due_date,
                b.returned_at,
                b.status,
                b.fine_amount,
                b.renewal_count,
                bk.title,
                bk.authors,
                bk.cover_url,
                bk.google_book_id
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            WHERE b.user_id = ?
            ORDER BY b.borrowed_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$userId, $limit, $offset]);
        $borrowings = $stmt->fetchAll();
        
        // Get total count
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM borrowings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $total = $stmt->fetch()['total'];
        
        sendJsonResponse([
            'borrowings' => $borrowings,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Get user statistics
if ($method === 'GET' && $action === 'stats') {
    $userId = requireUserSession();
    
    try {
        // Total books borrowed
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM borrowings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $totalBorrowed = $stmt->fetch()['total'];
        
        // Currently borrowed books
        $stmt = $pdo->prepare('SELECT COUNT(*) as current FROM borrowings WHERE user_id = ? AND status = "borrowed"');
        $stmt->execute([$userId]);
        $currentlyBorrowed = $stmt->fetch()['current'];
        
        // Overdue books
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as overdue 
            FROM borrowings 
            WHERE user_id = ? AND status = "borrowed" AND due_date < CURDATE()
        ');
        $stmt->execute([$userId]);
        $overdue = $stmt->fetch()['overdue'];
        
        // Total fines
        $stmt = $pdo->prepare('SELECT SUM(fine_amount) as total_fines FROM borrowings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $totalFines = $stmt->fetch()['total_fines'] ?? 0;
        
        sendJsonResponse([
            'total_borrowed' => $totalBorrowed,
            'currently_borrowed' => $currentlyBorrowed,
            'overdue' => $overdue,
            'total_fines' => floatval($totalFines)
        ]);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Get notifications
if ($method === 'GET' && $action === 'notifications') {
    $userId = requireUserSession();
    $limit = intval($_GET['limit'] ?? 10);
    
    try {
        $stmt = $pdo->prepare('
            SELECT 
                n.id,
                n.notification_type,
                n.message,
                n.sent_at,
                n.status,
                b.due_date,
                bk.title as book_title
            FROM notification_logs n
            JOIN borrowings b ON n.borrowing_id = b.id
            JOIN books bk ON b.book_id = bk.id
            WHERE n.user_id = ?
            ORDER BY n.sent_at DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        $notifications = $stmt->fetchAll();
        
        sendJsonResponse($notifications);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Add profile_photo column if it doesn't exist
if ($method === 'POST' && $action === 'setup-photo-column') {
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL');
        sendJsonResponse(['message' => 'Profile photo column added successfully']);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            sendJsonResponse(['message' => 'Profile photo column already exists']);
        } else {
            sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

// Upload profile photo
if ($method === 'POST' && $action === 'upload-photo') {
    $userId = requireUserSession();
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(['error' => 'No photo uploaded or upload error'], 400);
    }
    
    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        sendJsonResponse(['error' => 'Invalid file type. Only JPG, PNG, GIF allowed'], 400);
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        sendJsonResponse(['error' => 'File too large. Maximum 5MB allowed'], 400);
    }
    
    try {
        $uploadDir = 'uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $pdo->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
            $stmt->execute([$filepath, $userId]);
            
            sendJsonResponse(['message' => 'Photo uploaded successfully', 'photo_url' => $filepath]);
        } else {
            sendJsonResponse(['error' => 'Failed to save photo'], 500);
        }
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Get upcoming due dates
if ($method === 'GET' && $action === 'due-soon') {
    $userId = requireUserSession();
    
    try {
        $stmt = $pdo->prepare('
            SELECT 
                b.id as borrowing_id,
                b.due_date,
                b.renewal_count,
                bk.title,
                bk.authors,
                bk.cover_url
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            WHERE b.user_id = ? 
            AND b.status = "borrowed" 
            AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            ORDER BY b.due_date ASC
        ');
        $stmt->execute([$userId]);
        $dueSoon = $stmt->fetchAll();
        
        sendJsonResponse($dueSoon);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Default response for unknown actions
sendJsonResponse(['error' => 'Invalid action or method'], 400);
?>