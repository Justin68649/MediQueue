<?php
// File: api/notifications/create_notification.php
// Create a notification (internal use)

header('Content-Type: application/json');
require_once '../../config/config.php';

// This endpoint can be called from other parts of the system
// No requireLogin() here to allow internal calls

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['user_id'], $data['type'], $data['title'], $data['message'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields: user_id, type, title, message'
        ]);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $userId = (int)$data['user_id'];
    $queueEntryId = isset($data['queue_entry_id']) ? (int)$data['queue_entry_id'] : null;
    $type = $data['type'];
    $title = $data['title'];
    $message = $data['message'];
    $sentViaSms = isset($data['sent_via_sms']) ? (bool)$data['sent_via_sms'] : false;
    $sentViaEmail = isset($data['sent_via_email']) ? (bool)$data['sent_via_email'] : false;
    $sentViaPush = isset($data['sent_via_push']) ? (bool)$data['sent_via_push'] : true; // Default true for push
    
    $stmt = $conn->prepare("
        INSERT INTO notifications 
        (user_id, queue_entry_id, type, title, message, sent_via_sms, sent_via_email, sent_via_push) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $queueEntryId,
        $type,
        $title,
        $message,
        $sentViaSms,
        $sentViaEmail,
        $sentViaPush
    ]);
    
    $notificationId = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification created',
        'notification_id' => $notificationId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creating notification: ' . $e->getMessage()
    ]);
}
?>