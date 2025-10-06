<?php
class NotificationManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function sendDueSoonNotification($borrowingId) {
        return $this->createNotification($borrowingId, 'due_soon');
    }
    
    public function sendOverdueNotification($borrowingId) {
        return $this->createNotification($borrowingId, 'overdue');
    }
    
    public function sendRenewalApprovedNotification($borrowingId) {
        return $this->createNotification($borrowingId, 'renewal_approved');
    }
    
    public function sendReminder($borrowingId) {
        return $this->createNotification($borrowingId, 'reminder');
    }
    
    private function createNotification($borrowingId, $type) {
        try {
            // Get borrowing and user information
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.email, u.phone, u.email_notifications, u.sms_notifications,
                       bk.title as book_title
                FROM borrowings b
                JOIN users u ON b.user_id = u.id
                JOIN books bk ON b.book_id = bk.id
                WHERE b.id = ?
            ");
            $stmt->execute([$borrowingId]);
            $data = $stmt->fetch();
            
            if (!$data) {
                throw new Exception("Borrowing record not found");
            }
            
            // Generate message based on notification type
            $message = $this->generateMessage($type, $data);
            
            // Insert notification record
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_logs (
                    user_id, borrowing_id, notification_type,
                    message, status
                ) VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $data['user_id'],
                $borrowingId,
                $type,
                $message
            ]);
            
            $notificationId = $this->pdo->lastInsertId();
            
            // Send notifications based on user preferences
            $sent = false;
            if ($data['email_notifications']) {
                $sent = $this->sendEmail($data['email'], $message) || $sent;
            }
            if ($data['sms_notifications'] && $data['phone']) {
                $sent = $this->sendSMS($data['phone'], $message) || $sent;
            }
            
            // Update notification status
            $status = $sent ? 'sent' : 'failed';
            $stmt = $this->pdo->prepare("
                UPDATE notification_logs
                SET status = ?, sent_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$status, $notificationId]);
            
            return [
                'success' => true,
                'notification_id' => $notificationId,
                'status' => $status
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateMessage($type, $data) {
        $bookTitle = $data['book_title'];
        
        switch ($type) {
            case 'due_soon':
                return "Reminder: Your book '$bookTitle' is due in 2 days. Please return it to avoid late fees.";
            
            case 'overdue':
                $daysOverdue = floor((time() - strtotime($data['due_date'])) / (60 * 60 * 24));
                $fine = $daysOverdue * 0.50;
                return "Your book '$bookTitle' is overdue by $daysOverdue days. Current fine: $" . number_format($fine, 2);
            
            case 'renewal_approved':
                $newDueDate = date('F j, Y', strtotime($data['due_date']));
                return "Your renewal request for '$bookTitle' has been approved. New due date: $newDueDate";
            
            case 'reminder':
                $dueDate = date('F j, Y', strtotime($data['due_date']));
                return "Just a reminder that your book '$bookTitle' is due on $dueDate.";
            
            default:
                return "Notification about your borrowed book: '$bookTitle'";
        }
    }
    
    private function sendEmail($email, $message) {
        // Implement email sending logic here
        // Return true if sent successfully, false otherwise
        return true;
    }
    
    private function sendSMS($phone, $message) {
        // Implement SMS sending logic here
        // Return true if sent successfully, false otherwise
        return true;
    }
}
?>