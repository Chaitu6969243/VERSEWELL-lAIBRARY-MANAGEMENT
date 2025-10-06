<?php
/**
 * Book API for managing books, search, and borrowing operations
 * Integrates with Google Books API and local database
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

// Get JSON input for POST requests
$input = null;
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // For FormData requests, use $_POST
        $input = $_POST ?: [];
    }
    
    // Merge with $_POST data to handle FormData properly
    if (!empty($_POST)) {
        $input = array_merge($input ?: [], $_POST);
    }
}

// Get action from URL or POST data
$action = $_GET['action'] ?? $input['action'] ?? '';

// Google Books API configuration
define('GOOGLE_BOOKS_API_KEY', ''); // Add your Google Books API key here
define('GOOGLE_BOOKS_BASE_URL', 'https://www.googleapis.com/books/v1/volumes');

/**
 * Check if user is logged in
 */
function requireUserSession() {
    if (empty($_SESSION['user_id'])) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
    }
    return $_SESSION['user_id'];
}

/**
 * Search books using Google Books API
 */
function searchGoogleBooks($query, $startIndex = 0, $maxResults = 20) {
    $url = GOOGLE_BOOKS_BASE_URL . '?q=' . urlencode($query);
    $url .= '&startIndex=' . intval($startIndex);
    $url .= '&maxResults=' . intval($maxResults);
    
    if (GOOGLE_BOOKS_API_KEY) {
        $url .= '&key=' . GOOGLE_BOOKS_API_KEY;
    }
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Versewell Library System'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['error' => 'Failed to fetch books from Google Books API'];
    }
    
    return json_decode($response, true);
}

/**
 * Format Google Books API response for frontend
 */
function formatGoogleBooksResponse($googleResponse) {
    if (!isset($googleResponse['items'])) {
        return ['books' => [], 'totalItems' => 0];
    }
    
    $books = [];
    foreach ($googleResponse['items'] as $item) {
        $volumeInfo = $item['volumeInfo'] ?? [];
        $imageLinks = $volumeInfo['imageLinks'] ?? [];
        
        $book = [
            'google_book_id' => $item['id'],
            'title' => $volumeInfo['title'] ?? 'Unknown Title',
            'authors' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : 'Unknown Author',
            'isbn' => getISBN($volumeInfo['industryIdentifiers'] ?? []),
            'cover_url' => $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? '',
            'description' => $volumeInfo['description'] ?? '',
            'pages' => $volumeInfo['pageCount'] ?? 0,
            'published_year' => extractYear($volumeInfo['publishedDate'] ?? ''),
            'categories' => isset($volumeInfo['categories']) ? implode(', ', $volumeInfo['categories']) : '',
            'language' => $volumeInfo['language'] ?? 'en',
            'preview_link' => $volumeInfo['previewLink'] ?? '',
            'info_link' => $volumeInfo['infoLink'] ?? '',
            'available' => true // Google Books are always "available" for our system
        ];
        
        $books[] = $book;
    }
    
    return [
        'books' => $books,
        'totalItems' => $googleResponse['totalItems'] ?? count($books)
    ];
}

/**
 * Extract ISBN from industry identifiers
 */
function getISBN($identifiers) {
    foreach ($identifiers as $identifier) {
        if (in_array($identifier['type'], ['ISBN_13', 'ISBN_10'])) {
            return $identifier['identifier'];
        }
    }
    return '';
}

/**
 * Extract year from date string
 */
function extractYear($dateString) {
    if (preg_match('/(\d{4})/', $dateString, $matches)) {
        return intval($matches[1]);
    }
    return null;
}

/**
 * Save book to local database if not exists
 */
function saveBookToDatabase($pdo, $bookData) {
    try {
        // Check if book already exists
        $stmt = $pdo->prepare('SELECT id FROM books WHERE google_book_id = ?');
        $stmt->execute([$bookData['google_book_id']]);
        
        if ($stmt->fetch()) {
            return true; // Book already exists
        }
        
        // Insert new book
        $stmt = $pdo->prepare('
            INSERT INTO books (
                google_book_id, title, authors, isbn, cover_url, description, 
                pages, published_year, categories, language, preview_link, info_link,
                total_copies, available_copies
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
        ');
        
        return $stmt->execute([
            $bookData['google_book_id'],
            $bookData['title'],
            $bookData['authors'],
            $bookData['isbn'],
            $bookData['cover_url'],
            $bookData['description'],
            $bookData['pages'],
            $bookData['published_year'],
            $bookData['categories'],
            $bookData['language'],
            $bookData['preview_link'],
            $bookData['info_link']
        ]);
        
    } catch (PDOException $e) {
        error_log('Error saving book to database: ' . $e->getMessage());
        return false;
    }
}

// Handle API endpoints

// Search books
if ($method === 'GET' && $action === 'search') {
    $query = $_GET['query'] ?? $_GET['q'] ?? '';
    $startIndex = intval($_GET['startIndex'] ?? 0);
    $maxResults = intval($_GET['maxResults'] ?? $_GET['limit'] ?? 20);

    if (empty($query)) {
        sendJsonResponse(['error' => 'Query parameter is required'], 400);
    }

    $googleResponse = searchGoogleBooks($query, $startIndex, $maxResults);

    if (isset($googleResponse['error'])) {
        sendJsonResponse(['error' => $googleResponse['error']], 500);
    }

    $formattedResponse = formatGoogleBooksResponse($googleResponse);

    if (empty($formattedResponse['books'])) {
        sendJsonResponse(['success' => true, 'books' => [], 'total' => 0, 'message' => 'No books found for the given query.']);
    }

    // Debugging: Log the query parameter
    error_log('Search query: ' . $query);

    // Debugging: Log the Google Books API response
    if (isset($googleResponse['error'])) {
        error_log('Google Books API error: ' . $googleResponse['error']);
    } else {
        error_log('Google Books API response: ' . json_encode($googleResponse));
    }

    sendJsonResponse(['success' => true, 'books' => $formattedResponse['books'], 'total' => $formattedResponse['totalItems']]);
}

// Get trending books (popular/recent books)
if ($method === 'GET' && $action === 'trending') {
    $maxResults = intval($_GET['maxResults'] ?? $_GET['limit'] ?? 20);
    
    // Use popular search terms for trending books
    $trendingQueries = [
        'bestseller', 'popular', 'fiction', 'romance', 'mystery', 'science fiction'
    ];
    
    $randomQuery = $trendingQueries[array_rand($trendingQueries)];
    $googleResponse = searchGoogleBooks($randomQuery, 0, $maxResults);
    
    if (isset($googleResponse['error'])) {
        sendJsonResponse(['error' => $googleResponse['error']], 500);
    }
    
    $formattedResponse = formatGoogleBooksResponse($googleResponse);
    sendJsonResponse(['success' => true, 'books' => $formattedResponse['books'], 'total' => $formattedResponse['totalItems']]);
}

// Get book details
if ($method === 'GET' && ($action === 'book' || $action === 'details')) {
    $bookId = $_GET['id'] ?? '';
    
    if (empty($bookId)) {
        sendJsonResponse(['error' => 'Book ID is required'], 400);
    }
    
    $url = GOOGLE_BOOKS_BASE_URL . '/' . urlencode($bookId);
    if (GOOGLE_BOOKS_API_KEY) {
        $url .= '?key=' . GOOGLE_BOOKS_API_KEY;
    }
    
    $response = @file_get_contents($url);
    if ($response === false) {
        sendJsonResponse(['error' => 'Failed to fetch book details'], 500);
    }
    
    $bookData = json_decode($response, true);
    if (!$bookData) {
        sendJsonResponse(['error' => 'Invalid book data'], 500);
    }
    
    // Format single book response
    $volumeInfo = $bookData['volumeInfo'] ?? [];
    $imageLinks = $volumeInfo['imageLinks'] ?? [];
    
    $book = [
        'google_book_id' => $bookData['id'],
        'title' => $volumeInfo['title'] ?? 'Unknown Title',
        'authors' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : 'Unknown Author',
        'isbn' => getISBN($volumeInfo['industryIdentifiers'] ?? []),
        'cover_url' => $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? '',
        'description' => $volumeInfo['description'] ?? '',
        'pages' => $volumeInfo['pageCount'] ?? 0,
        'published_year' => extractYear($volumeInfo['publishedDate'] ?? ''),
        'categories' => isset($volumeInfo['categories']) ? implode(', ', $volumeInfo['categories']) : '',
        'language' => $volumeInfo['language'] ?? 'en',
        'preview_link' => $volumeInfo['previewLink'] ?? '',
        'info_link' => $volumeInfo['infoLink'] ?? ''
    ];
    
    sendJsonResponse(array_merge(['success' => true], $book));
}

// Borrow book
if ($method === 'POST' && $action === 'borrow') {
    $userId = requireUserSession();
    
    // Debug: Log what we received
    error_log("Borrow request received. Input: " . print_r($input, true));
    error_log("POST data: " . print_r($_POST, true));
    error_log("Raw input: " . file_get_contents('php://input'));
    
    $googleBookId = $input['google_book_id'] ?? '';
    $duration = intval($input['duration'] ?? 14); // Default 14 days
    
    error_log("Extracted google_book_id: '$googleBookId', duration: $duration");
    
    if (empty($googleBookId)) {
        error_log("ERROR: Google Book ID is empty");
        sendJsonResponse(['error' => 'Google Book ID is required'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // First, get book details from Google Books API
        $url = GOOGLE_BOOKS_BASE_URL . '/' . urlencode($googleBookId);
        if (GOOGLE_BOOKS_API_KEY) {
            $url .= '?key=' . GOOGLE_BOOKS_API_KEY;
        }
        
        $response = @file_get_contents($url);
        if ($response === false) {
            throw new Exception('Failed to fetch book details from Google Books');
        }
        
        $googleBookData = json_decode($response, true);
        if (!$googleBookData) {
            throw new Exception('Invalid book data from Google Books');
        }
        
        // Format book data
        $volumeInfo = $googleBookData['volumeInfo'] ?? [];
        $imageLinks = $volumeInfo['imageLinks'] ?? [];
        
        $bookData = [
            'google_book_id' => $googleBookData['id'],
            'title' => $volumeInfo['title'] ?? 'Unknown Title',
            'authors' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : 'Unknown Author',
            'isbn' => getISBN($volumeInfo['industryIdentifiers'] ?? []),
            'cover_url' => $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? '',
            'description' => $volumeInfo['description'] ?? '',
            'pages' => $volumeInfo['pageCount'] ?? 0,
            'published_year' => extractYear($volumeInfo['publishedDate'] ?? ''),
            'categories' => isset($volumeInfo['categories']) ? implode(', ', $volumeInfo['categories']) : '',
            'language' => $volumeInfo['language'] ?? 'en',
            'preview_link' => $volumeInfo['previewLink'] ?? '',
            'info_link' => $volumeInfo['infoLink'] ?? ''
        ];
        
        // Save book to database if it doesn't exist
        saveBookToDatabase($pdo, $bookData);
        
        // Get book ID from database
        $stmt = $pdo->prepare('SELECT id FROM books WHERE google_book_id = ?');
        $stmt->execute([$googleBookId]);
        $book = $stmt->fetch();
        
        if (!$book) {
            throw new Exception('Failed to save book to database');
        }
        
        $bookId = $book['id'];
        
        // Check if user already has this book borrowed
        $stmt = $pdo->prepare('
            SELECT id FROM borrowings 
            WHERE user_id = ? AND book_id = ? AND status = "borrowed"
        ');
        $stmt->execute([$userId, $bookId]);
        
        if ($stmt->fetch()) {
            throw new Exception('You have already borrowed this book');
        }
        
        // Calculate due date
        $dueDate = date('Y-m-d', strtotime("+{$duration} days"));
        
        // Create borrowing record
        $stmt = $pdo->prepare('
            INSERT INTO borrowings (user_id, book_id, due_date, status)
            VALUES (?, ?, ?, "borrowed")
        ');
        $stmt->execute([$userId, $bookId, $dueDate]);
        
        $borrowingId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        sendJsonResponse([
            'message' => 'Book borrowed successfully',
            'borrowing_id' => $borrowingId,
            'due_date' => $dueDate,
            'book' => $bookData
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        sendJsonResponse(['error' => $e->getMessage()], 400);
    }
}

// Get user's borrowed books
if ($method === 'GET' && $action === 'my-books') {
    $userId = requireUserSession();
    
    try {
        $stmt = $pdo->prepare('
            SELECT 
                b.id as borrowing_id,
                b.borrowed_at,
                b.due_date,
                b.status,
                b.fine_amount,
                b.renewal_count,
                bk.google_book_id,
                bk.title,
                bk.authors,
                bk.cover_url,
                bk.pages,
                bk.published_year
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            WHERE b.user_id = ?
            ORDER BY b.borrowed_at DESC
        ');
        $stmt->execute([$userId]);
        $borrowings = $stmt->fetchAll();
        
        sendJsonResponse($borrowings);
        
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// Return book
if ($method === 'POST' && $action === 'return') {
    $userId = requireUserSession();
    $borrowingId = $input['borrowing_id'] ?? '';
    
    if (empty($borrowingId)) {
        sendJsonResponse(['error' => 'Borrowing ID is required'], 400);
    }
    
    try {
        // Verify the borrowing belongs to the user
        $stmt = $pdo->prepare('
            SELECT id, due_date FROM borrowings 
            WHERE id = ? AND user_id = ? AND status = "borrowed"
        ');
        $stmt->execute([$borrowingId, $userId]);
        $borrowing = $stmt->fetch();
        
        if (!$borrowing) {
            sendJsonResponse(['error' => 'Borrowing not found or already returned'], 404);
        }
        
        // Calculate fine if overdue
        $fine = 0;
        $dueDate = new DateTime($borrowing['due_date']);
        $returnDate = new DateTime();
        
        if ($returnDate > $dueDate) {
            $overdueDays = $returnDate->diff($dueDate)->days;
            $fine = $overdueDays * 100; // ₹100 per day
        }
        
        // Update borrowing record
        $stmt = $pdo->prepare('
            UPDATE borrowings 
            SET status = "returned", returned_at = NOW(), fine_amount = ?
            WHERE id = ?
        ');
        $stmt->execute([$fine, $borrowingId]);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Book returned successfully',
            'fine_amount' => $fine,
            'overdue_days' => $overdueDays ?? 0
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => $e->getMessage()], 500);
    }
}

// Request renewal
if ($method === 'POST' && $action === 'renew') {
    $userId = requireUserSession();
    $borrowingId = $input['borrowing_id'] ?? '';
    
    if (empty($borrowingId)) {
        sendJsonResponse(['error' => 'Borrowing ID is required'], 400);
    }
    
    try {
        // Verify the borrowing belongs to the user
        $stmt = $pdo->prepare('
            SELECT id, renewal_count FROM borrowings 
            WHERE id = ? AND user_id = ? AND status = "borrowed"
        ');
        $stmt->execute([$borrowingId, $userId]);
        $borrowing = $stmt->fetch();
        
        if (!$borrowing) {
            sendJsonResponse(['error' => 'Borrowing not found'], 404);
        }
        
        if ($borrowing['renewal_count'] >= 2) {
            sendJsonResponse(['error' => 'Maximum renewal limit reached'], 400);
        }
        
        // Update renewal request
        $stmt = $pdo->prepare('
            UPDATE borrowings 
            SET renewal_requested = TRUE, last_renewal_date = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$borrowingId]);
        
        sendJsonResponse(['message' => 'Renewal requested successfully']);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => $e->getMessage()], 500);
    }
}

// Debug session status
if ($method === 'GET' && $action === 'debug-session') {
    sendJsonResponse([
        'session_id' => session_id(),
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'user_id' => $_SESSION['user_id'] ?? null,
        'is_admin' => $_SESSION['is_admin'] ?? null,
        'all_session_data' => $_SESSION
    ]);
}

// Default response for unknown actions
sendJsonResponse(['error' => 'Invalid action or method'], 400);
?>