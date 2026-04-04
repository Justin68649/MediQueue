<?php
// File: api/notifications/unread_count.php
// Get count of unread notifications

header('Content-Type: application/json');
require_once '../../config/config.php';
requireLogin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$result['unread_count']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching unread count: ' . $e->getMessage()
    ]);
}
?>