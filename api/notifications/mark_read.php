<?php
// File: api/notifications/mark_read.php
// Mark notification(s) as read

header('Content-Type: application/json');
require_once '../../config/config.php';
requireLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $userId = $_SESSION['user_id'];
    
    if (isset($data['notification_id'])) {
        // Mark single notification as read
        $notificationId = (int)$data['notification_id'];
        
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        
    } elseif (isset($data['mark_all']) && $data['mark_all'] === true) {
        // Mark all notifications as read
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid action provided']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating notification: ' . $e->getMessage()
    ]);
}
?>