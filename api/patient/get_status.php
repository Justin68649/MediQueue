<?php
// File: api/patient/get_status.php
// Get Patient's Current Queue Status

require_once '../../config/config.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to view your queue status', [], 401);
}

if ($_SESSION['role'] !== 'patient') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Get active queue entry for the patient
    $stmt = $conn->prepare("
        SELECT 
            q.*,
            d.name as department_name,
            d.color as department_color,
            d.prefix as department_prefix,
            (SELECT COUNT(*) FROM queue_entries 
             WHERE department_id = q.department_id 
             AND status = 'waiting' 
             AND joined_at < q.joined_at) as ahead_count,
            (SELECT COUNT(*) FROM queue_entries 
             WHERE department_id = q.department_id 
             AND status = 'waiting') as total_waiting
        FROM queue_entries q
        JOIN departments d ON q.department_id = d.id
        WHERE q.patient_id = ? 
        AND q.status IN ('waiting', 'called', 'serving')
        ORDER BY q.joined_at DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("get_status.php: User ID: " . $_SESSION['user_id'] . ", Queue found: " . ($queue ? 'YES' : 'NO'));
    if ($queue) {
        error_log("get_status.php: Queue details: " . json_encode($queue));
    }
    
    if ($queue) {
        // Calculate estimated remaining time
        $estimated_remaining = null;
        if ($queue['status'] == 'waiting' && $queue['estimated_wait_time']) {
            $avg_service_time = $queue['estimated_wait_time'];
            $estimated_remaining = $queue['ahead_count'] * $avg_service_time;
        }
        
        // Get estimated position change (real-time)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as current_position 
            FROM queue_entries 
            WHERE department_id = ? 
            AND status = 'waiting' 
            AND joined_at < ?
        ");
        $stmt->execute([$queue['department_id'], $queue['joined_at']]);
        $current_position = $stmt->fetch(PDO::FETCH_ASSOC)['current_position'];
        
        sendJsonResponse(true, 'Queue status retrieved', [
            'has_active' => true,
            'queue' => [
                'id' => $queue['id'],
                'queue_number' => $queue['queue_number'],
                'department_id' => $queue['department_id'],
                'department_name' => $queue['department_name'],
                'department_color' => $queue['department_color'],
                'status' => $queue['status'],
                'priority' => $queue['priority'],
                'position' => $current_position + 1,
                'ahead_count' => $queue['ahead_count'],
                'total_waiting' => $queue['total_waiting'],
                'estimated_wait_time' => $queue['estimated_wait_time'],
                'estimated_remaining' => $estimated_remaining,
                'joined_at' => $queue['joined_at'],
                'called_at' => $queue['called_at'],
                'serving_started_at' => $queue['serving_started_at'],
                'notification_sent' => (bool)$queue['notification_sent']
            ]
        ]);
    } else {
        sendJsonResponse(true, 'No active queue', [
            'has_active' => false
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_status.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve queue status: ' . $e->getMessage(), [], 500);
}
?>