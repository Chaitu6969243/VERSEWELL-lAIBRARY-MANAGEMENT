<?php
/**
 * Add sample data for testing the admin panel
 */
require_once 'config.php';

try {
    $pdo = getDatabaseConnection();
    
    // Add sample users
    $users = [
        ['John Doe', 'john.doe@example.com', 'password123', '1234567890'],
        ['Jane Smith', 'jane.smith@example.com', 'password123', '0987654321'],
        ['Bob Johnson', 'bob.johnson@example.com', 'password123', '5555555555']
    ];
    
    foreach ($users as $user) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$user[1]]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, phone, is_active) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $user[0],
                $user[1],
                password_hash($user[2], PASSWORD_DEFAULT),
                $user[3],
                1
            ]);
            echo "Created user: {$user[0]}\n";
        }
    }
    
    // Add sample books
    $books = [
        ['The Great Gatsby', '["F. Scott Fitzgerald"]', '978-0-7432-7356-5', 'A classic American novel'],
        ['To Kill a Mockingbird', '["Harper Lee"]', '978-0-06-112008-4', 'A timeless story of racial injustice'],
        ['1984', '["George Orwell"]', '978-0-452-28423-4', 'Dystopian social science fiction novel'],
        ['Pride and Prejudice', '["Jane Austen"]', '978-0-14-143951-8', 'A romantic novel of manners'],
        ['The Catcher in the Rye', '["J.D. Salinger"]', '978-0-316-76948-0', 'Coming-of-age story']
    ];
    
    foreach ($books as $book) {
        $stmt = $pdo->prepare('SELECT id FROM books WHERE isbn = ?');
        $stmt->execute([$book[2]]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO books (title, authors, isbn, description, total_copies, available_copies) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $book[0],
                $book[1],
                $book[2],
                $book[3],
                3, // total copies
                2  // available copies (1 borrowed)
            ]);
            echo "Created book: {$book[0]}\n";
        }
    }
    
    // Add sample borrowings
    $stmt = $pdo->prepare('SELECT u.id as user_id, b.id as book_id FROM users u, books b WHERE u.email = ? AND b.title = ? LIMIT 1');
    $stmt->execute(['john.doe@example.com', 'The Great Gatsby']);
    $result = $stmt->fetch();
    
    if ($result) {
        $stmt = $pdo->prepare('SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = "borrowed"');
        $stmt->execute([$result['user_id'], $result['book_id']]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO borrowings (user_id, book_id, borrowed_at, due_date, status) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), "borrowed")');
            $stmt->execute([$result['user_id'], $result['book_id']]);
            echo "Created borrowing for John Doe - The Great Gatsby\n";
        }
    }
    
    // Add another borrowing (overdue)
    $stmt = $pdo->prepare('SELECT u.id as user_id, b.id as book_id FROM users u, books b WHERE u.email = ? AND b.title = ? LIMIT 1');
    $stmt->execute(['jane.smith@example.com', '1984']);
    $result = $stmt->fetch();
    
    if ($result) {
        $stmt = $pdo->prepare('SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = "borrowed"');
        $stmt->execute([$result['user_id'], $result['book_id']]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO borrowings (user_id, book_id, borrowed_at, due_date, status) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), "borrowed")');
            $stmt->execute([$result['user_id'], $result['book_id']]);
            echo "Created overdue borrowing for Jane Smith - 1984\n";
        }
    }
    
    echo "\nSample data created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>