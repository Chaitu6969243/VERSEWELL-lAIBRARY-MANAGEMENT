<?php
require_once 'config.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

try {
    $pdo->beginTransaction();

    // Create demo user
    $userEmail = 'demo.user@example.com';
    $userName = 'Demo User';
    $userPassword = password_hash('demopass123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$userEmail]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = $user['id'];
        echo "User exists (id={$userId})\n";
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, is_active, email_notifications, sms_notifications) VALUES (?, ?, ?, 1, 1, 0)');
        $stmt->execute([$userName, $userEmail, $userPassword]);
        $userId = $pdo->lastInsertId();
        echo "Inserted user id={$userId}\n";
    }

    // Create demo book
    $googleId = 'demo-volume-001';
    $title = 'Demo Book for Admin Panel';
    $authors = json_encode(['Demo Author']);
    $cover = 'https://via.placeholder.com/128x192.png?text=Demo+Cover';

    $stmt = $pdo->prepare('SELECT id FROM books WHERE google_book_id = ? OR title = ?');
    $stmt->execute([$googleId, $title]);
    $book = $stmt->fetch();

    if ($book) {
        $bookId = $book['id'];
        echo "Book exists (id={$bookId})\n";
    } else {
        $stmt = $pdo->prepare('INSERT INTO books (google_book_id, title, authors, isbn, cover_url, description, pages, published_year, categories, total_copies, available_copies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $googleId,
            $title,
            $authors,
            '0000000000',
            $cover,
            'This is a demo book inserted to populate admin panel for testing.',
            123,
            2025,
            json_encode(['Demo']),
            3,
            3
        ]);
        $bookId = $pdo->lastInsertId();
        echo "Inserted book id={$bookId}\n";
    }

    // Create a borrowing if none active exists for this user/book
    $stmt = $pdo->prepare("SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
    $stmt->execute([$userId, $bookId]);
    $bor = $stmt->fetch();

    if ($bor) {
        echo "Borrowing already exists (id={$bor['id']})\n";
    } else {
        // Ensure available_copies > 0
        $stmt = $pdo->prepare('SELECT available_copies FROM books WHERE id = ?');
        $stmt->execute([$bookId]);
        $b = $stmt->fetch();
        $available = $b ? (int)$b['available_copies'] : 0;

        if ($available <= 0) {
            echo "No copies available to borrow.\n";
        } else {
            $due = date('Y-m-d', strtotime('+14 days'));
            $stmt = $pdo->prepare('INSERT INTO borrowings (user_id, book_id, due_date) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $bookId, $due]);
            $borrowingId = $pdo->lastInsertId();

            // decrement available copies
            $stmt = $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = ?');
            $stmt->execute([$bookId]);

            echo "Inserted borrowing id={$borrowingId} due={$due}\n";
        }
    }

    $pdo->commit();
    echo "Sample data insertion complete.\n";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error inserting sample data: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

return 0;
