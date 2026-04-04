<?php
// File: api/notifications/delete_notification.php
// Delete a notification

header('Content-Type: application/json');
require_once '../../config/config.php';
requireLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['notification_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $userId = $_SESSION['user_id'];
    $notificationId = (int)$data['notification_id'];
    
    $stmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Notification deleted'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting notification: ' . $e->getMessage()
    ]);
}
?>