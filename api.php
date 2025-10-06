<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in HTML

// Set up error handler to return JSON
set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode(['error' => "PHP Error: $message in $file at line $line"]);
    exit;
});

// Set up exception handler to return JSON
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Exception: ' . $exception->getMessage()]);
    exit;
});

require_once 'config.php';

setJsonHeaders();

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    sendJsonResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
    exit;
}

// Get request data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$input = null;

if ($requestMethod === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
    }
}

// Handle direct action requests first
if ($requestMethod === 'POST' && isset($input['action'])) {
    switch ($input['action']) {
        case 'register':
            handleRegistration($pdo, $input);
            exit;
        case 'login':
            handleLogin($pdo, $input);
            exit;
        default:
            // Continue to endpoint routing
            break;
    }
}

// Parse the request URI robustly
$requestUri = $_SERVER['REQUEST_URI'];
$uriPath = parse_url($requestUri, PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME']; // e.g., /api.php

// Remove the script path from the URI path to get the endpoint path
$path = '/';
if (strpos($uriPath, $scriptName) === 0) {
    $path = substr($uriPath, strlen($scriptName));
} elseif (strpos($uriPath, '/' . basename($scriptName)) !== false) {
    // fallback if script name appears differently
    $path = substr($uriPath, strpos($uriPath, '/' . basename($scriptName)) + strlen('/' . basename($scriptName)));
} else {
    // If not present, just use the uriPath
    $path = $uriPath;
}

// Split the path into segments and get the endpoint (first segment after api.php)
$pathSegments = explode('/', trim($path, '/'));
$endpoint = $pathSegments[0] ?? '';

// Define public endpoints that don't require authentication
$publicEndpoints = ['auth', 'books', 'whoami'];

// Check if endpoint requires authorization
// Allow explicit public URIs like /api.php/auth and /api.php/books as well as the endpoint list
$isPublicUri = (strpos($uriPath, '/api.php/auth') !== false)
    || (strpos($uriPath, '/api.php/books') !== false)
    || ($endpoint === 'auth')
    || ($endpoint === 'books');

// Allow admin login endpoint to be public (so /api.php/admin?action=login or /api.php/admin/login works)
if (!$isPublicUri) {
    // If query contains action=login
    if (isset($_GET['action']) && $_GET['action'] === 'login' && $endpoint === 'admin') {
        $isPublicUri = true;
    }

    // Also allow path-based /api.php/admin/login
    if (strpos($requestUri, '/admin/login') !== false) {
        $isPublicUri = true;
    }
}
if ((!in_array($endpoint, $publicEndpoints) && $endpoint !== '') && !$isPublicUri) {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

    // If there's no Authorization header, try PHP session-based auth
    $userData = null;
    if (!$token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        try {
            // First prefer a regular user session
            if (!empty($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT id, name, email, is_active, is_admin FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // If no user session, allow an admin session (admins stored in separate table)
            if (!$userData && !empty($_SESSION['admin_id'])) {
                $stmt = $pdo->prepare("SELECT id, name, email, role, is_active FROM admins WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($admin) {
                    $userData = [
                        'id' => $admin['id'],
                        'name' => $admin['name'],
                        'email' => $admin['email'],
                        'is_active' => $admin['is_active'],
                        'is_admin' => 1,
                        'role' => $admin['role'] ?? null
                    ];
                }
            }
        } catch (Exception $e) {
            sendJsonResponse(['error' => 'Failed to verify session'], 500);
        }
    }

    if (!$token && !$userData) {
           if (session_status() !== PHP_SESSION_ACTIVE) session_start();
           error_log("AUTH FAIL: Authorization required for endpoint={$endpoint}; token=" . ($token ?: 'null'));
           error_log("AUTH FAIL: headers=" . print_r($headers, true));
           error_log("AUTH FAIL: cookies=" . print_r($_COOKIE, true));
           error_log("AUTH FAIL: session=" . print_r($_SESSION, true));
           sendJsonResponse(['error' => 'Authorization required'], 401);
    }

    if ($token) {
        try {
            // Verify token and get user info
            $userData = verifyAuthToken($token);
            if (!$userData['is_active']) {
                sendJsonResponse(['error' => 'Account is inactive'], 403);
            }
        } catch (Exception $e) {
            sendJsonResponse(['error' => 'Invalid or expired token'], 401);
        }
    }

    // Add admin check for admin-only endpoints
    if ($endpoint === 'admin' && $userData && !$userData['is_admin']) {
           if (session_status() !== PHP_SESSION_ACTIVE) session_start();
           error_log("AUTH FAIL: Admin access required for endpoint={$endpoint}; userData=" . print_r($userData, true));
           error_log("AUTH FAIL: headers=" . print_r(getallheaders(), true));
           error_log("AUTH FAIL: cookies=" . print_r($_COOKIE, true));
           error_log("AUTH FAIL: session=" . print_r($_SESSION, true));
           sendJsonResponse(['error' => 'Admin access required'], 403);
    }
}

// Debug information
error_log("Request Method: " . $requestMethod);
error_log("Endpoint: " . $endpoint);
error_log("Input: " . print_r($input, true));

switch ($endpoint) {
    case 'books':
        handleBooksAPI($pdo, $requestMethod);
        break;
    case 'users':
        handleUsersAPI($pdo, $requestMethod);
        break;
    case 'auth':
        handleAuthAPI($pdo, $requestMethod);
        break;
    case 'admin':
        handleAdminAPI($pdo, $requestMethod);
        break;
    case 'stats':
        handleStatsAPI($pdo, $requestMethod);
        break;
    case 'borrowings':
        // Check if there's an ID in the URL for editing
        $borrowingId = isset($pathSegments[3]) ? $pathSegments[3] : null;
        handleBorrowingsAPI($pdo, $requestMethod, $borrowingId);
        break;
    case 'edit-borrowing':
        handleEditBorrowingAPI($pdo, $requestMethod);
        break;
    case 'renew-book':
        handleRenewBookAPI($pdo, $requestMethod);
        break;
    case 'send-reminder':
        handleSendReminderAPI($pdo, $requestMethod);
        break;
    case 'notifications':
        handleNotificationsAPI($pdo, $requestMethod);
        break;
    case 'whoami':
        handleWhoami($pdo, $requestMethod);
        break;
    default:
        sendJsonResponse(['error' => 'Invalid endpoint'], 404);
}

// Books API Handler
function handleBooksAPI($pdo, $method) {
    switch ($method) {
        case 'GET':
            getBooksFromAPI();
            break;
        case 'POST':
            addBookToLibrary($pdo);
            break;
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
}

// Get books from Google Books API
function getBooksFromAPI() {
    $query = $_GET['q'] ?? 'programming';
    $startIndex = (int)($_GET['startIndex'] ?? 0);
    $maxResults = (int)($_GET['maxResults'] ?? 12);
    
    $apiUrl = "https://www.googleapis.com/books/v1/volumes?" . http_build_query([
        'q' => $query,
        'startIndex' => $startIndex,
        'maxResults' => $maxResults,
        'printType' => 'books'
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]
    ]);
    
    $response = file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        sendJsonResponse(['error' => 'Failed to fetch books from Google Books API'], 500);
    }
    
    $data = json_decode($response, true);
    
    // Process and format the response
    $books = [];
    if (isset($data['items'])) {
        foreach ($data['items'] as $book) {
            $volumeInfo = $book['volumeInfo'] ?? [];
            $accessInfo = $book['accessInfo'] ?? [];
            
            $books[] = [
                'id' => $book['id'],
                'title' => $volumeInfo['title'] ?? 'Title not available',
                'authors' => $volumeInfo['authors'] ?? ['Unknown Author'],
                'cover' => $volumeInfo['imageLinks']['thumbnail'] ?? $volumeInfo['imageLinks']['smallThumbnail'] ?? $volumeInfo['imageLinks']['small'] ?? $volumeInfo['imageLinks']['medium'] ?? null,
                'year' => isset($volumeInfo['publishedDate']) ? date('Y', strtotime($volumeInfo['publishedDate'])) : 'N/A',
                'pages' => $volumeInfo['pageCount'] ?? null,
                'description' => $volumeInfo['description'] ?? 'No description available',
                'categories' => $volumeInfo['categories'] ?? [],
                'isbn' => $volumeInfo['industryIdentifiers'][0]['identifier'] ?? null,
                'previewLink' => $volumeInfo['previewLink'] ?? null,
                'infoLink' => $volumeInfo['infoLink'] ?? null,
                'canonicalVolumeLink' => $volumeInfo['canonicalVolumeLink'] ?? null,
                'webReaderLink' => $accessInfo['webReaderLink'] ?? null,
                'embeddable' => $accessInfo['embeddable'] ?? false,
                'publicDomain' => $accessInfo['publicDomain'] ?? false
            ];
        }
    }
    
    sendJsonResponse([
        'books' => $books,
        'totalItems' => $data['totalItems'] ?? 0,
        'hasMore' => ($startIndex + $maxResults) < ($data['totalItems'] ?? 0)
    ]);
}

// Add book to library database
function addBookToLibrary($pdo) {
    $input = getJsonInput();
    
    $required = ['google_book_id', 'title'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO books (
                google_book_id, title, authors, isbn, cover_url, description, 
                pages, published_year, categories, preview_link, info_link,
                total_copies, available_copies
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_copies = total_copies + 1,
                available_copies = available_copies + 1
        ");
        
        // Ensure authors is properly formatted as an array
        $authors = $input['authors'] ?? [];
        if (is_string($authors)) {
            $authors = [$authors];
        } elseif (!is_array($authors)) {
            $authors = [];
        }
        
        // Ensure categories is properly formatted as an array
        $categories = $input['categories'] ?? [];
        if (is_string($categories)) {
            $categories = [$categories];
        } elseif (!is_array($categories)) {
            $categories = [];
        }
        
        $stmt->execute([
            $input['google_book_id'],
            $input['title'],
            json_encode($authors),
            $input['isbn'] ?? null,
            $input['cover'] ?? $input['cover_url'] ?? null,
            $input['description'] ?? null,
            $input['pages'] ?? null,
            $input['year'] !== 'N/A' ? (int)$input['year'] : null,
            json_encode($categories),
            $input['previewLink'] ?? null,
            $input['infoLink'] ?? null,
            1,
            1
        ]);
        
        sendJsonResponse(['message' => 'Book added to library successfully', 'id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Users API Handler
function handleUsersAPI($pdo, $method) {
    switch ($method) {
        case 'GET':
            getUsers($pdo);
            break;
        case 'POST':
            createUser($pdo);
            break;
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getUsers($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, email, phone, created_at, is_active FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        sendJsonResponse($users);
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function createUser($pdo) {
    $input = getJsonInput();
    
    $required = ['name', 'email', 'password'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
    
    try {
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        // Insert using the 'password' column per schema (store hashed password)
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $input['name'],
            $input['email'],
            $hashedPassword,
            $input['phone'] ?? null
        ]);
        
        sendJsonResponse(['message' => 'User created successfully', 'id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            sendJsonResponse(['error' => 'Email already exists'], 409);
        } else {
            sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

// Auth API Handler
function handleAuthAPI($pdo, $method) {
    switch ($method) {
        case 'POST':
            $pathSegments = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
            $action = end($pathSegments);
            
            if ($action === 'login') {
                login($pdo);
            } elseif ($action === 'register') {
                register($pdo);
            } else {
                sendJsonResponse(['error' => 'Invalid auth action'], 404);
            }
            break;
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function login($pdo) {
    $input = getJsonInput();
    
    $required = ['email', 'password'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
    
    try {
        // Select the stored (hashed) password from the 'password' column
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$input['email']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($input['password'], $user['password'])) {
            // Start session and store user id for server-side session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = $user['is_admin'] ?? 0;

            unset($user['password']);
            sendJsonResponse(['message' => 'Login successful', 'user' => $user, 'session' => true]);
        } else {
            sendJsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function register($pdo) {
    createUser($pdo);
}

// Admin API Handler
function handleAdminAPI($pdo, $method) {
    $action = $_GET['action'] ?? '';
    
    // Handle legacy login endpoint
    if ($method === 'POST' && !$action) {
        $pathSegments = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
        $lastSegment = end($pathSegments);
        if ($lastSegment === 'login') {
            adminLogin($pdo);
            return;
        }
    }
    
    switch ($action) {
        case 'users':
            if ($method === 'GET') {
                getAllUsersAdmin($pdo);
            } elseif ($method === 'POST') {
                createUserAdmin($pdo);
            } elseif ($method === 'PUT') {
                updateUserAdmin($pdo);
            } elseif ($method === 'DELETE') {
                deleteUserAdmin($pdo);
            } else {
                sendJsonResponse(['error' => 'Method not allowed'], 405);
            }
            break;
        case 'borrowings':
            if ($method === 'GET') {
                getAllBorrowingsAdmin($pdo);
            } elseif ($method === 'PUT') {
                returnBookAdmin($pdo);
            } else {
                sendJsonResponse(['error' => 'Method not allowed'], 405);
            }
            break;
        case 'books':
            if ($method === 'GET') {
                getAllBooksAdmin($pdo);
            } elseif ($method === 'POST') {
                addBookAdmin($pdo);
            } elseif ($method === 'PUT') {
                updateBookAdmin($pdo);
            } elseif ($method === 'DELETE') {
                deleteBookAdmin($pdo);
            } else {
                sendJsonResponse(['error' => 'Method not allowed'], 405);
            }
            break;
        case 'login':
            if ($method === 'POST') {
                adminLogin($pdo);
            } else {
                sendJsonResponse(['error' => 'Method not allowed'], 405);
            }
            break;
        default:
            sendJsonResponse(['error' => 'Invalid admin action'], 404);
    }
}

function adminLogin($pdo) {
    $input = getJsonInput();
    
    $required = ['email', 'password'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
    
    try {
        // Select the password column (legacy schema uses `password`)
        $stmt = $pdo->prepare("SELECT id, name, email, role, is_active, password FROM admins WHERE email = ? AND is_active = 1");
        $stmt->execute([$input['email']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        $valid = false;
        if ($admin && isset($admin['password'])) {
            $stored = $admin['password'];
            // If it looks like a bcrypt/argon2 hash (starts with $), try password_verify
            if (is_string($stored) && strpos($stored, '$') === 0) {
                $valid = password_verify($input['password'], $stored);
            } else {
                // Fallback to constant-time comparison for plaintext legacy password
                $valid = hash_equals((string)$stored, (string)$input['password']);
            }
        }

        if ($valid) {
            // Start session and set admin session id
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['is_admin'] = 1;

            // Remove password fields before returning
            unset($admin['password_hash'], $admin['password']);
            sendJsonResponse(['message' => 'Admin login successful', 'admin' => $admin, 'session' => true]);
        } else {
            sendJsonResponse(['error' => 'Invalid admin credentials'], 401);
        }

    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Borrowings API Handler
function handleBorrowingsAPI($pdo, $method, $borrowingId = null) {
    switch ($method) {
        case 'GET':
            getBorrowings($pdo);
            break;
        case 'POST':
            createBorrowing($pdo);
            break;
        case 'PUT':
            if ($borrowingId) {
                updateBorrowing($pdo, $borrowingId);
            } else {
                returnBook($pdo);
            }
            break;
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
}

function getBorrowings($pdo) {
    $userId = $_GET['user_id'] ?? null;
    
    try {
        if ($userId) {
            $stmt = $pdo->prepare("
                SELECT b.*, bk.title, bk.authors, bk.cover_url 
                FROM borrowings b 
                JOIN books bk ON b.book_id = bk.id 
                WHERE b.user_id = ? 
                ORDER BY b.borrowed_at DESC
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->query("
                SELECT b.*, bk.title, bk.authors, bk.cover_url, u.name as user_name 
                FROM borrowings b 
                JOIN books bk ON b.book_id = bk.id 
                JOIN users u ON b.user_id = u.id 
                ORDER BY b.borrowed_at DESC
            ");
        }
        
        $borrowings = $stmt->fetchAll();
        
        // Decode JSON fields for proper frontend consumption
        foreach ($borrowings as &$borrowing) {
            if (isset($borrowing['authors']) && is_string($borrowing['authors'])) {
                $decodedAuthors = json_decode($borrowing['authors'], true);
                $borrowing['authors'] = is_array($decodedAuthors) ? $decodedAuthors : [$borrowing['authors']];
            }
        }
        
        sendJsonResponse($borrowings);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function createBorrowing($pdo) {
    $input = getJsonInput();
    
    $required = ['user_id', 'book_id'];
    $missing = validateRequiredFields($input, $required);
    
    // If user_id isn't provided, attempt to use session user id
    if (empty($input['user_id'])) {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!empty($_SESSION['user_id'])) {
            $input['user_id'] = $_SESSION['user_id'];
        }
    }

    $required = ['user_id', 'book_id'];
    $missing = validateRequiredFields($input, $required);

    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
    
    try {
        $bookId = $input['book_id'];
        $userId = $input['user_id'];
        
        // First, try to find the book by internal ID
        $stmt = $pdo->prepare("SELECT id, available_copies FROM books WHERE id = ?");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();
        
        // If not found by internal ID, try by google_book_id
        if (!$book) {
            $stmt = $pdo->prepare("SELECT id, available_copies FROM books WHERE google_book_id = ?");
            $stmt->execute([$bookId]);
            $book = $stmt->fetch();
            
            if ($book) {
                $bookId = $book['id']; // Use the internal ID for borrowing
            }
        }
        
        if (!$book) {
            sendJsonResponse(['error' => 'Book not found in library'], 404);
        }
        
        if ($book['available_copies'] <= 0) {
            sendJsonResponse(['error' => 'Book not available for borrowing'], 400);
        }
        
        // Check if user already borrowed this book
        $stmt = $pdo->prepare("SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
        $stmt->execute([$userId, $bookId]);
        if ($stmt->fetch()) {
            sendJsonResponse(['error' => 'You have already borrowed this book'], 400);
        }
        
        // Create borrowing record
        $dueDate = date('Y-m-d', strtotime('+14 days')); // 2 weeks from now
        
        $stmt = $pdo->prepare("INSERT INTO borrowings (user_id, book_id, due_date) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $bookId, $dueDate]);
        
        // Update available copies
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
        $stmt->execute([$bookId]);
        
        sendJsonResponse(['message' => 'Book borrowed successfully', 'due_date' => $dueDate, 'borrowing_id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function returnBook($pdo) {
    $input = getJsonInput();
    
    $required = ['borrowing_id'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
    
    try {
        $borrowingId = $input['borrowing_id'];
        
        // Get the borrowing record
        $stmt = $pdo->prepare("SELECT book_id, status FROM borrowings WHERE id = ?");
        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch();
        
        if (!$borrowing) {
            sendJsonResponse(['error' => 'Borrowing record not found'], 404);
        }
        
        if ($borrowing['status'] !== 'borrowed') {
            sendJsonResponse(['error' => 'Book is not currently borrowed'], 400);
        }
        
        // Update borrowing record
        $stmt = $pdo->prepare("UPDATE borrowings SET status = 'returned', returned_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$borrowingId]);
        
        // Update available copies
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
        $stmt->execute([$borrowing['book_id']]);
        
        sendJsonResponse(['message' => 'Book returned successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Duplicate removed - function defined above

// Stats API Handler
function handleStatsAPI($pdo, $method) {
    if ($method !== 'GET') {
        sendJsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    try {
        $stats = [
            'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
            'total_books' => $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
            'total_borrowings' => $pdo->query("SELECT COUNT(*) FROM borrowings")->fetchColumn(),
            'active_borrowings' => $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'")->fetchColumn(),
            'overdue_books' => $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed' AND due_date < CURDATE()")->fetchColumn(),
            'returned_books' => $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'returned'")->fetchColumn()
        ];
        
        // Recent activity
        $stmt = $pdo->query("
            SELECT b.*, u.name as user_name, bk.title as book_title 
            FROM borrowings b 
            JOIN users u ON b.user_id = u.id 
            JOIN books bk ON b.book_id = bk.id 
            ORDER BY b.created_at DESC 
            LIMIT 10
        ");
        $stats['recent_activity'] = $stmt->fetchAll();
        
        // Overdue books details
        $stmt = $pdo->query("
            SELECT b.*, u.name as user_name, u.email as user_email, bk.title as book_title,
                   DATEDIFF(CURDATE(), b.due_date) as days_overdue
            FROM borrowings b 
            JOIN users u ON b.user_id = u.id 
            JOIN books bk ON b.book_id = bk.id 
            WHERE b.status = 'borrowed' AND b.due_date < CURDATE()
            ORDER BY b.due_date ASC
        ");
        $stats['overdue_details'] = $stmt->fetchAll();
        
        sendJsonResponse($stats);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Simple debug endpoint to show current session identity
function handleWhoami($pdo, $method) {
    if ($method !== 'GET') {
        sendJsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    $out = ['session_active' => session_status() === PHP_SESSION_ACTIVE];

    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, name, email, is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $out['user'] = $user ?: null;
    }

    if (!empty($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare("SELECT id, name, email, role FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $out['admin'] = $admin ?: null;
    }

    sendJsonResponse($out);
}

// Admin User Management Functions
function getAllUsersAdmin($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.*, 
                   COUNT(b.id) as total_borrowings,
                   COUNT(CASE WHEN b.status = 'borrowed' THEN 1 END) as active_borrowings
            FROM users u 
            LEFT JOIN borrowings b ON u.id = b.user_id 
            GROUP BY u.id 
            ORDER BY u.created_at DESC
        ");
        
        sendJsonResponse($stmt->fetchAll());
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function createUserAdmin($pdo) {
    $input = getJsonInput();
    
    $required = ['name', 'email', 'password'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $input['name'],
            $input['email'],
            password_hash($input['password'], PASSWORD_DEFAULT),
            $input['phone'] ?? null
        ]);
        
        sendJsonResponse(['message' => 'User created successfully', 'id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            sendJsonResponse(['error' => 'Email already exists'], 400);
        } else {
            sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

function updateUserAdmin($pdo) {
    $input = getJsonInput();
    
    if (!isset($input['id'])) {
        sendJsonResponse(['error' => 'User ID is required'], 400);
        return;
    }
    
    try {
        $updateFields = [];
        $params = [];
        
        if (isset($input['name'])) {
            $updateFields[] = 'name = ?';
            $params[] = $input['name'];
        }
        if (isset($input['email'])) {
            $updateFields[] = 'email = ?';
            $params[] = $input['email'];
        }
        if (isset($input['phone'])) {
            $updateFields[] = 'phone = ?';
            $params[] = $input['phone'];
        }
        if (isset($input['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $params[] = $input['is_active'] ? 1 : 0;
        }
        
        if (empty($updateFields)) {
            sendJsonResponse(['error' => 'No fields to update'], 400);
            return;
        }
        
        $params[] = $input['id'];
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        sendJsonResponse(['message' => 'User updated successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function deleteUserAdmin($pdo) {
    $input = getJsonInput();
    
    if (!isset($input['id'])) {
        sendJsonResponse(['error' => 'User ID is required'], 400);
        return;
    }
    
    try {
        // Soft delete - set is_active to false
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        sendJsonResponse(['message' => 'User deactivated successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Admin Borrowing Management Functions
function getAllBorrowingsAdmin($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT b.*, u.name as user_name, u.email as user_email, 
                   bk.title as book_title, bk.authors, bk.cover_url,
                   CASE 
                       WHEN b.status = 'borrowed' AND b.due_date < CURDATE() THEN 'overdue'
                       ELSE b.status 
                   END as display_status,
                   DATEDIFF(CURDATE(), b.due_date) as days_overdue
            FROM borrowings b 
            JOIN users u ON b.user_id = u.id 
            JOIN books bk ON b.book_id = bk.id 
            ORDER BY 
                CASE WHEN b.status = 'borrowed' AND b.due_date < CURDATE() THEN 1 ELSE 2 END,
                b.borrowed_at DESC
        ");
        
        sendJsonResponse($stmt->fetchAll());
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function returnBookAdmin($pdo) {
    $input = getJsonInput();
    
    if (!isset($input['borrowing_id'])) {
        sendJsonResponse(['error' => 'Borrowing ID is required'], 400);
        return;
    }
    
    try {
        // Get borrowing details
        $stmt = $pdo->prepare("SELECT book_id, status FROM borrowings WHERE id = ?");
        $stmt->execute([$input['borrowing_id']]);
        $borrowing = $stmt->fetch();
        
        if (!$borrowing) {
            sendJsonResponse(['error' => 'Borrowing not found'], 404);
            return;
        }
        
        if ($borrowing['status'] !== 'borrowed') {
            sendJsonResponse(['error' => 'Book is not currently borrowed'], 400);
            return;
        }
        
        // Update borrowing record
        $stmt = $pdo->prepare("UPDATE borrowings SET status = 'returned', returned_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$input['borrowing_id']]);
        
        // Update available copies
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
        $stmt->execute([$borrowing['book_id']]);
        
        sendJsonResponse(['message' => 'Book returned successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Admin Book Management Functions
function getAllBooksAdmin($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT b.*, 
                   COUNT(br.id) as total_borrowed,
                   COUNT(CASE WHEN br.status = 'borrowed' THEN 1 END) as currently_borrowed
            FROM books b 
            LEFT JOIN borrowings br ON b.id = br.book_id 
            GROUP BY b.id 
            ORDER BY b.created_at DESC
        ");
        
        sendJsonResponse($stmt->fetchAll());
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function addBookAdmin($pdo) {
    $input = getJsonInput();
    
    $required = ['title'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO books (
                google_book_id, title, authors, isbn, cover_url, description, 
                pages, published_year, categories, preview_link, info_link,
                total_copies, available_copies
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['google_book_id'] ?? null,
            $input['title'],
            json_encode($input['authors'] ?? []),
            $input['isbn'] ?? null,
            $input['cover_url'] ?? null,
            $input['description'] ?? null,
            $input['pages'] ?? null,
            $input['published_year'] ?? null,
            json_encode($input['categories'] ?? []),
            $input['preview_link'] ?? null,
            $input['info_link'] ?? null,
            $input['total_copies'] ?? 1,
            $input['available_copies'] ?? 1
        ]);
        
        sendJsonResponse(['message' => 'Book added successfully', 'id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function updateBookAdmin($pdo) {
    $input = getJsonInput();
    
    if (!isset($input['id'])) {
        sendJsonResponse(['error' => 'Book ID is required'], 400);
        return;
    }
    
    try {
        $updateFields = [];
        $params = [];
        
        if (isset($input['title'])) {
            $updateFields[] = 'title = ?';
            $params[] = $input['title'];
        }
        if (isset($input['authors'])) {
            $updateFields[] = 'authors = ?';
            $params[] = json_encode($input['authors']);
        }
        if (isset($input['isbn'])) {
            $updateFields[] = 'isbn = ?';
            $params[] = $input['isbn'];
        }
        if (isset($input['cover_url'])) {
            $updateFields[] = 'cover_url = ?';
            $params[] = $input['cover_url'];
        }
        if (isset($input['description'])) {
            $updateFields[] = 'description = ?';
            $params[] = $input['description'];
        }
        if (isset($input['total_copies'])) {
            $updateFields[] = 'total_copies = ?';
            $params[] = $input['total_copies'];
        }
        if (isset($input['available_copies'])) {
            $updateFields[] = 'available_copies = ?';
            $params[] = $input['available_copies'];
        }
        
        if (empty($updateFields)) {
            sendJsonResponse(['error' => 'No fields to update'], 400);
            return;
        }
        
        $params[] = $input['id'];
        $sql = "UPDATE books SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        sendJsonResponse(['message' => 'Book updated successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function deleteBookAdmin($pdo) {
    $input = getJsonInput();
    
    if (!isset($input['id'])) {
        sendJsonResponse(['error' => 'Book ID is required'], 400);
        return;
    }
    
    try {
        // Check if book has active borrowings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'borrowed'");
        $stmt->execute([$input['id']]);
        $activeBorrowings = $stmt->fetchColumn();
        
        if ($activeBorrowings > 0) {
            sendJsonResponse(['error' => 'Cannot delete book with active borrowings'], 400);
            return;
        }
        
        // Delete the book
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        sendJsonResponse(['message' => 'Book deleted successfully']);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Update borrowing details
function updateBorrowing($pdo, $borrowingId) {
    $input = getJsonInput();
    
    try {
        // Check if borrowing exists
        $stmt = $pdo->prepare("SELECT * FROM borrowings WHERE id = ?");
        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch();
        
        if (!$borrowing) {
            sendJsonResponse(['success' => false, 'message' => 'Borrowing record not found'], 404);
            return;
        }
        
        // Build update query dynamically based on provided fields
        $updateFields = [];
        $params = [];
        
        if (isset($input['borrowed_date'])) {
            $updateFields[] = "borrowed_at = ?";
            $params[] = $input['borrowed_date'];
        }
        
        if (isset($input['due_date'])) {
            $updateFields[] = "due_date = ?";
            $params[] = $input['due_date'];
        }
        
        if (isset($input['status'])) {
            $updateFields[] = "status = ?";
            $params[] = $input['status'];
        }
        
        if (isset($input['return_date'])) {
            if (!empty($input['return_date'])) {
                $updateFields[] = "returned_at = ?";
                $params[] = $input['return_date'];
            } else {
                $updateFields[] = "returned_at = NULL";
            }
        }
        
        if (isset($input['notes'])) {
            // Add notes column if it doesn't exist
            $stmt = $pdo->query("SHOW COLUMNS FROM borrowings LIKE 'notes'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE borrowings ADD COLUMN notes TEXT NULL");
            }
            $updateFields[] = "notes = ?";
            $params[] = $input['notes'];
        }
        
        if (empty($updateFields)) {
            sendJsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
            return;
        }
        
        // Add borrowing ID to params
        $params[] = $borrowingId;
        
        // Execute update
        $updateSQL = "UPDATE borrowings SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($updateSQL);
        $success = $stmt->execute($params);
        
        if ($success && $stmt->rowCount() > 0) {
            sendJsonResponse(['success' => true, 'message' => 'Borrowing updated successfully']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'No changes made to borrowing'], 400);
        }
        
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Handle edit borrowing API endpoint
function handleEditBorrowingAPI($pdo, $method) {
    if ($method !== 'PUT') {
        sendJsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    $input = getJsonInput();
    
    if (!isset($input['id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Borrowing ID is required'], 400);
        return;
    }
    
    updateBorrowing($pdo, $input['id']);
}

// Handle renew book API endpoint
function handleRenewBookAPI($pdo, $method) {
    if ($method !== 'POST') {
        sendJsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    $input = getJsonInput();
    
    $required = ['book_id', 'user_id'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
        return;
    }
    
    try {
        // Find the active borrowing
        $stmt = $pdo->prepare("
            SELECT id, due_date, renewal_count 
            FROM borrowings 
            WHERE book_id = ? AND user_id = ? AND status = 'borrowed'
            ORDER BY borrowed_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$input['book_id'], $input['user_id']]);
        $borrowing = $stmt->fetch();
        
        if (!$borrowing) {
            sendJsonResponse(['success' => false, 'message' => 'No active borrowing found'], 404);
            return;
        }
        
        // Check renewal limit (max 2 renewals)
        if ($borrowing['renewal_count'] >= 2) {
            sendJsonResponse(['success' => false, 'message' => 'Maximum renewal limit reached'], 400);
            return;
        }
        
        // Extend due date by 14 days
        $newDueDate = date('Y-m-d', strtotime($borrowing['due_date'] . ' +14 days'));
        
        // Update borrowing
        $stmt = $pdo->prepare("
            UPDATE borrowings 
            SET due_date = ?, 
                renewal_requested = TRUE, 
                renewal_count = renewal_count + 1, 
                last_renewal_date = NOW()
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$newDueDate, $borrowing['id']]);
        
        if ($success) {
            sendJsonResponse([
                'success' => true, 
                'message' => 'Book renewed successfully',
                'new_due_date' => $newDueDate
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to renew book'], 500);
        }
        
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Handle send reminder API endpoint
function handleSendReminderAPI($pdo, $method) {
    if ($method !== 'POST') {
        sendJsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    $input = getJsonInput();
    
    $required = ['user_id'];
    $missing = validateRequiredFields($input, $required);
    
    if (!empty($missing)) {
        sendJsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
        return;
    }
    
    try {
        $userId = $input['user_id'];
        $userEmail = $input['user_email'] ?? '';
        $userName = $input['user_name'] ?? '';
        $overdueBooks = $input['overdue_books'] ?? [];
        
        // Verify user exists and get their information if not provided
        if (empty($userEmail) || empty($userName)) {
            $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendJsonResponse(['success' => false, 'message' => 'User not found'], 404);
                return;
            }
            
            $userEmail = $user['email'];
            $userName = $user['name'];
        }
        
        // Get actual overdue books from database for verification
        $stmt = $pdo->prepare("
            SELECT b.id, b.due_date, bk.title, bk.authors
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            WHERE b.user_id = ? 
            AND b.status = 'borrowed' 
            AND b.due_date < CURDATE()
            ORDER BY b.due_date ASC
        ");
        $stmt->execute([$userId]);
        $actualOverdueBooks = $stmt->fetchAll();
        
        if (empty($actualOverdueBooks)) {
            sendJsonResponse(['success' => false, 'message' => 'No overdue books found for this user'], 400);
            return;
        }
        
        // Log the reminder in notification_logs table (if it exists)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notification_logs (user_id, borrowing_id, notification_type, status, message) 
                VALUES (?, ?, 'reminder', 'sent', ?)
            ");
            
            foreach ($actualOverdueBooks as $book) {
                $message = "Reminder sent for overdue book: {$book['title']} (Due: {$book['due_date']})";
                $stmt->execute([$userId, $book['id'], $message]);
            }
        } catch (PDOException $e) {
            // If notification_logs table doesn't exist, continue without logging
            error_log("Could not log reminder: " . $e->getMessage());
        }
        
        // In a real application, you would send an actual email here
        // For now, we'll just simulate the sending
        $bookTitles = array_map(function($book) {
            return $book['title'];
        }, $actualOverdueBooks);
        
        $reminderMessage = "Reminder sent to {$userName} ({$userEmail}) for " . count($actualOverdueBooks) . " overdue book(s): " . implode(', ', $bookTitles);
        
        // Log the reminder activity (you could expand this to actually send emails)
        error_log("REMINDER SENT: " . $reminderMessage);
        
        sendJsonResponse([
            'success' => true, 
            'message' => 'Reminder sent successfully',
            'user_name' => $userName,
            'user_email' => $userEmail,
            'overdue_count' => count($actualOverdueBooks),
            'books' => $actualOverdueBooks
        ]);
        
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Simple notifications API handler
function handleNotificationsAPI($pdo, $method) {
    if ($method !== 'GET') {
        sendJsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    try {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
        
        if (!$userId) {
            sendJsonResponse(['error' => 'User ID required'], 400);
            return;
        }
        
        // Get notifications from notification_logs table
        $stmt = $pdo->prepare("
            SELECT 
                id,
                notification_type,
                message,
                status,
                sent_at
            FROM notification_logs 
            WHERE user_id = ? 
            ORDER BY sent_at DESC 
            LIMIT 20
        ");
        
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJsonResponse($notifications);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Failed to load notifications'], 500);
    }
}

// Handle form-based POST actions
function handleFormAction($pdo, $action) {
    switch ($action) {
        case 'update_user_status':
            updateUserStatus($pdo);
            break;
        case 'edit_user':
            editUser($pdo);
            break;
        case 'add_user':
            addUser($pdo);
            break;
        default:
            sendJsonResponse(['error' => 'Invalid form action'], 404);
    }
}

// Update user status (suspend/activate)
function updateUserStatus($pdo) {
    try {
        $userId = $_POST['user_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if (!$userId || !$status) {
            sendJsonResponse(['error' => 'Missing user_id or status'], 400);
            return;
        }
        
        if (!in_array($status, ['active', 'suspended'])) {
            sendJsonResponse(['error' => 'Invalid status. Must be active or suspended'], 400);
            return;
        }
        
        // Convert status to is_active boolean
        $isActive = ($status === 'active') ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $result = $stmt->execute([$isActive, $userId]);
        
        if ($result) {
            sendJsonResponse([
                'success' => true, 
                'message' => "User status updated to $status successfully"
            ]);
        } else {
            sendJsonResponse(['error' => 'Failed to update user status'], 500);
        }
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Edit user details
function editUser($pdo) {
    try {
        $userId = $_POST['user_id'] ?? null;
        $name = $_POST['name'] ?? null;
        $email = $_POST['email'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if (!$userId || !$name || !$email || !$status) {
            sendJsonResponse(['error' => 'Missing required fields: user_id, name, email, status'], 400);
            return;
        }
        
        if (!in_array($status, ['active', 'suspended'])) {
            sendJsonResponse(['error' => 'Invalid status. Must be active or suspended'], 400);
            return;
        }
        
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            sendJsonResponse(['error' => 'Email is already taken by another user'], 400);
            return;
        }
        
        // Convert status to is_active boolean
        $isActive = ($status === 'active') ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, is_active = ? WHERE id = ?");
        $result = $stmt->execute([$name, $email, $isActive, $userId]);
        
        if ($result) {
            sendJsonResponse([
                'success' => true, 
                'message' => 'User updated successfully'
            ]);
        } else {
            sendJsonResponse(['error' => 'Failed to update user'], 500);
        }
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Add User
function addUser($pdo) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Name, email, and password are required'
        ], 400);
        return;
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid email address'
        ], 400);
        return;
    }
    
    // Check if email already exists
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->execute([$email]);
    
    if ($check_stmt->fetch()) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Email already exists'
        ], 400);
        return;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Convert status to is_active boolean
    $is_active = ($status === 'active') ? 1 : 0;
    
    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, is_active, is_admin, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->execute([
        $name,
        $email,
        $hashed_password,
        $is_active
    ]);
    
    sendJsonResponse([
        'success' => true,
        'message' => 'User added successfully',
        'user_id' => $pdo->lastInsertId()
    ]);
}

// ...existing code...
?>