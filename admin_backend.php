<?php
/**
 * Lightweight admin backend API for admin.html/admin.js
 * Provides: whoami, admin login, users/borrowings/books admin endpoints
 */
require_once 'config.php';

// Configure and start session with proper settings
configureSession();

setJsonHeaders();

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    sendJsonResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Read JSON input for POST/PUT bodies
$input = null;
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // also try parsing form data fallback
        $input = $_POST ?: [];
    }
}

function requireAdminSession($pdo) {
    // Session is already started at the top of this file
    error_log("requireAdminSession called - Session ID: " . session_id() . ", Session data: " . json_encode($_SESSION));
    
    if (empty($_SESSION['admin_id'])) {
        error_log("No admin_id in session");
        sendJsonResponse(['error' => 'Admin authorization required', 'debug' => ['session_id' => session_id(), 'session_data' => $_SESSION]], 401);
    }
    
    $stmt = $pdo->prepare('SELECT id, name, email, role, is_active FROM admins WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        error_log("Admin not found in database for ID: " . $_SESSION['admin_id']);
        sendJsonResponse(['error' => 'Admin session invalid'], 401);
    }
    return $admin;
}

// whoami endpoint
if ($method === 'GET' && ($action === '' && basename($_SERVER['PHP_SELF']) === 'admin_backend.php' && empty($_GET))) {
    // If someone calls admin_backend.php with no params, show a small info
    sendJsonResponse(['message' => 'Admin backend: available endpoints: ?action=whoami, login, users, borrowings, books']);
}

if ($method === 'GET' && (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'whoami') !== false || $action === 'whoami')) {
    // Session is already started at the top of this file
    $out = [
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'session_id' => session_id(),
        'session_data' => $_SESSION,
        'has_admin_id' => !empty($_SESSION['admin_id'])
    ];
    
    if (!empty($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare('SELECT id, name, email, role, is_active FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $out['admin'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $out['user'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    // Debug log
    error_log("Whoami called - Session ID: " . session_id() . ", Admin ID: " . ($_SESSION['admin_id'] ?? 'none'));
    
    sendJsonResponse($out);
}

// Admin login (POST /admin_backend.php/login)
if ($method === 'POST' && (strpos($_SERVER['REQUEST_URI'], '/login') !== false || $action === 'login')) {
    $body = $input ?? $_POST;
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;
    if (!$email || !$password) sendJsonResponse(['error' => 'Missing email or password'], 400);
    try {
        $stmt = $pdo->prepare('SELECT id, name, email, role, is_active, password FROM admins WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $valid = false;
        if ($admin && isset($admin['password'])) {
            $stored = $admin['password'];
            if (is_string($stored) && strpos($stored, '$') === 0) {
                $valid = password_verify($password, $stored);
            } else {
                $valid = hash_equals((string)$stored, (string)$password);
            }
        }
        if ($valid) {
            // Session is already started at the top of this file
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['name'];
            $_SESSION['is_admin'] = 1;
            unset($admin['password']);
            
            // Debug: Log what we're setting in session
            error_log("Admin login successful - Session ID: " . session_id() . ", Admin ID: " . $admin['id']);
            
            sendJsonResponse([
                'message' => 'Admin login successful', 
                'admin' => $admin, 
                'session' => true,
                'session_id' => session_id(),
                'debug_session' => $_SESSION
            ]);
        } else {
            sendJsonResponse(['error' => 'Invalid admin credentials'], 401);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// All other admin actions require admin session
if ($action === 'users') {
    if ($method === 'GET') {
        requireAdminSession($pdo);
        try {
            $stmt = $pdo->query("SELECT id, name, email, phone, created_at, is_active FROM users ORDER BY created_at DESC");
            sendJsonResponse($stmt->fetchAll());
        } catch (PDOException $e) { sendJsonResponse(['error' => $e->getMessage()], 500); }
    } elseif ($method === 'POST') {
        requireAdminSession($pdo);
        $data = $input ?? $_POST;
        $required = ['name','email','password'];
        foreach ($required as $r) if (empty($data[$r])) sendJsonResponse(['error'=>'Missing '.$r],400);
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)');
            $stmt->execute([$data['name'],$data['email'],password_hash($data['password'], PASSWORD_DEFAULT), $data['phone'] ?? null]);
            sendJsonResponse(['message'=>'User created','id'=>$pdo->lastInsertId()]);
        } catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } elseif ($method === 'PUT') {
        requireAdminSession($pdo);
        $data = $input ?? [];
        if (empty($data['id'])) sendJsonResponse(['error'=>'User ID required'],400);
        $fields = [];$params = [];
        foreach (['name','email','phone'] as $f) if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        if (isset($data['is_active'])) { $fields[]='is_active = ?'; $params[] = $data['is_active'] ? 1 : 0; }
        if (empty($fields)) sendJsonResponse(['error'=>'No fields to update'],400);
        $params[] = $data['id'];
        try { $stmt = $pdo->prepare('UPDATE users SET '.implode(', ',$fields).' WHERE id = ?'); $stmt->execute($params); sendJsonResponse(['message'=>'User updated']); }
        catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } elseif ($method === 'DELETE') {
        requireAdminSession($pdo);
        $data = $input ?? $_POST;
        if (empty($data['id'])) sendJsonResponse(['error'=>'User ID required'],400);
        try { $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?'); $stmt->execute([$data['id']]); sendJsonResponse(['message'=>'User deactivated']); }
        catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } else { sendJsonResponse(['error'=>'Method not allowed'],405); }
    exit;
}

if ($action === 'borrowings') {
    if ($method === 'GET') {
        requireAdminSession($pdo);
        try {
            $stmt = $pdo->query("SELECT b.*, u.name as user_name, u.email as user_email, bk.title as book_title, bk.authors, bk.cover_url, CASE WHEN b.status = 'borrowed' AND b.due_date < CURDATE() THEN 'overdue' ELSE b.status END as display_status, DATEDIFF(CURDATE(), b.due_date) as days_overdue FROM borrowings b JOIN users u ON b.user_id = u.id JOIN books bk ON b.book_id = bk.id ORDER BY CASE WHEN b.status = 'borrowed' AND b.due_date < CURDATE() THEN 1 ELSE 2 END, b.borrowed_at DESC");
            sendJsonResponse($stmt->fetchAll());
        } catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } elseif ($method === 'PUT') {
        requireAdminSession($pdo);
        $data = $input ?? $_POST;
        if (empty($data['borrowing_id'])) sendJsonResponse(['error'=>'borrowing_id required'],400);
        try {
            $stmt = $pdo->prepare('SELECT book_id, status FROM borrowings WHERE id = ?'); $stmt->execute([$data['borrowing_id']]); $bor = $stmt->fetch();
            if (!$bor) sendJsonResponse(['error'=>'Borrowing not found'],404);
            if ($bor['status'] !== 'borrowed') sendJsonResponse(['error'=>'Book not currently borrowed'],400);
            $pdo->prepare("UPDATE borrowings SET status='returned', returned_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$data['borrowing_id']]);
            $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = ?')->execute([$bor['book_id']]);
            sendJsonResponse(['message'=>'Book returned successfully']);
        } catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } else { sendJsonResponse(['error'=>'Method not allowed'],405); }
    exit;
}

if ($action === 'books') {
    if ($method === 'GET') {
        requireAdminSession($pdo);
        try { $stmt = $pdo->query('SELECT b.*, COUNT(br.id) as total_borrowed, COUNT(CASE WHEN br.status = "borrowed" THEN 1 END) as currently_borrowed FROM books b LEFT JOIN borrowings br ON b.id = br.book_id GROUP BY b.id ORDER BY b.created_at DESC'); sendJsonResponse($stmt->fetchAll()); }
        catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } elseif ($method === 'POST') {
        requireAdminSession($pdo);
        $data = $input ?? $_POST;
        if (empty($data['title'])) sendJsonResponse(['error'=>'title required'],400);
        try {
            $stmt = $pdo->prepare('INSERT INTO books (google_book_id, title, authors, isbn, cover_url, description, pages, published_year, categories, preview_link, info_link, total_copies, available_copies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['google_book_id'] ?? null, $data['title'], json_encode($data['authors'] ?? []), $data['isbn'] ?? null, $data['cover_url'] ?? null, $data['description'] ?? null, $data['pages'] ?? null, $data['published_year'] ?? null, json_encode($data['categories'] ?? []), $data['preview_link'] ?? null, $data['info_link'] ?? null, $data['total_copies'] ?? 1, $data['available_copies'] ?? 1]);
            sendJsonResponse(['message'=>'Book added','id'=>$pdo->lastInsertId()]);
        } catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } elseif ($method === 'PUT') {
        requireAdminSession($pdo);
        $data = $input ?? [];
        if (empty($data['id'])) sendJsonResponse(['error'=>'id required'],400);
        $fields=[];$params=[]; foreach (['title','isbn','cover_url','description','total_copies','available_copies'] as $f) if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        if (empty($fields)) sendJsonResponse(['error'=>'No fields to update'],400); $params[] = $data['id']; try { $stmt = $pdo->prepare('UPDATE books SET '.implode(', ',$fields).' WHERE id = ?'); $stmt->execute($params); sendJsonResponse(['message'=>'Book updated']); } catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } elseif ($method === 'DELETE') {
        requireAdminSession($pdo);
        $data = $input ?? $_POST; if (empty($data['id'])) sendJsonResponse(['error'=>'id required'],400); try { $stmt = $pdo->prepare('DELETE FROM books WHERE id = ?'); $stmt->execute([$data['id']]); sendJsonResponse(['message'=>'Book deleted']); } catch (PDOException $e) { sendJsonResponse(['error'=>$e->getMessage()],500); }
    } else { sendJsonResponse(['error'=>'Method not allowed'],405); }
    exit;
}

// If we reached here, invalid action
sendJsonResponse(['error'=>'Invalid admin action or endpoint'], 404);
