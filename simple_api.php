<?php
// Simple API for debugging - no complex routing
error_reporting(E_ALL);
ini_set('display_errors', 0);

// JSON error handler
set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => "PHP Error: $message"]);
    exit;
});

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'borrow':
        if ($method === 'POST') {
            handleBorrow($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'borrowings':
        if ($method === 'GET') {
            getBorrowings($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'return':
        if ($method === 'POST') {
            returnBook($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'add_book':
        if ($method === 'POST') {
            addBook($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid action. Use: borrow, borrowings, return, add_book']);
}

function handleBorrow($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['user_id']) || !isset($input['book_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id or book_id']);
            return;
        }
        
        $userId = $input['user_id'];
        $bookId = $input['book_id'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if user exists and is active
        $stmt = $pdo->prepare("SELECT id, is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Try to find book by google_book_id first, then by internal id
        $stmt = $pdo->prepare("SELECT id, title, available_copies FROM books WHERE google_book_id = ? OR id = ?");
        $stmt->execute([$bookId, $bookId]);
        $book = $stmt->fetch();
        
        if (!$book) {
            http_response_code(404);
            echo json_encode(['error' => 'Book not found in library']);
            return;
        }
        
        if ($book['available_copies'] <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Book not available for borrowing']);
            return;
        }
        
        // Check if user already borrowed this book
        $stmt = $pdo->prepare("SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
        $stmt->execute([$userId, $book['id']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'You have already borrowed this book']);
            return;
        }
        
        // Create borrowing record
        $dueDate = date('Y-m-d', strtotime('+14 days'));
        $stmt = $pdo->prepare("INSERT INTO borrowings (user_id, book_id, due_date) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $book['id'], $dueDate]);
        
        // Update available copies
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
        $stmt->execute([$book['id']]);
        
        echo json_encode([
            'message' => 'Book borrowed successfully',
            'due_date' => $dueDate,
            'borrowing_id' => $pdo->lastInsertId(),
            'book_title' => $book['title']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getBorrowings($pdo) {
    try {
        $userId = $_GET['user_id'] ?? null;
        
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
        echo json_encode($borrowings);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function returnBook($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['borrowing_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing borrowing_id']);
            return;
        }
        
        $borrowingId = $input['borrowing_id'];
        
        // Get borrowing details
        $stmt = $pdo->prepare("SELECT book_id FROM borrowings WHERE id = ? AND status = 'borrowed'");
        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch();
        
        if (!$borrowing) {
            http_response_code(404);
            echo json_encode(['error' => 'Borrowing not found or already returned']);
            return;
        }
        
        // Update borrowing status
        $stmt = $pdo->prepare("UPDATE borrowings SET status = 'returned', returned_at = NOW() WHERE id = ?");
        $stmt->execute([$borrowingId]);
        
        // Update available copies
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
        $stmt->execute([$borrowing['book_id']]);
        
        echo json_encode(['message' => 'Book returned successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function addBook($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['title'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing book title']);
            return;
        }
        
        $googleBookId = $input['google_book_id'] ?? null;
        $title = $input['title'];
        $authors = isset($input['authors']) ? json_encode($input['authors']) : '["Unknown Author"]';
        $isbn = $input['isbn'] ?? null;
        $cover = $input['cover'] ?? null;
        $description = $input['description'] ?? null;
        $pages = $input['pages'] ?? null;
        $year = $input['year'] ?? null;
        $categories = isset($input['categories']) ? json_encode($input['categories']) : null;
        $previewLink = $input['previewLink'] ?? null;
        $infoLink = $input['infoLink'] ?? null;
        
        // Check if book already exists
        if ($googleBookId) {
            $stmt = $pdo->prepare("SELECT id FROM books WHERE google_book_id = ?");
            $stmt->execute([$googleBookId]);
            if ($stmt->fetch()) {
                echo json_encode(['message' => 'Book already exists', 'status' => 'exists']);
                return;
            }
        }
        
        // Insert new book
        $stmt = $pdo->prepare("
            INSERT INTO books (google_book_id, title, authors, isbn, cover_url, description, pages, published_year, categories, preview_link, info_link) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $googleBookId, $title, $authors, $isbn, $cover, $description, 
            $pages, $year, $categories, $previewLink, $infoLink
        ]);
        
        echo json_encode([
            'message' => 'Book added successfully',
            'book_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>