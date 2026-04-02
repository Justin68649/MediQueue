<?php
// File: api/staff/get_queue.php
// Get Queue for Staff Dashboard (AJAX endpoint)

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to view queue', [], 401);
}

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $staff_id = $_SESSION['user_id'];
    
    // Get staff department
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $department_id = $staff['department_id'];
    
    // If admin, can view all departments
    if ($_SESSION['role'] === 'admin' && isset($_GET['department_id'])) {
        $department_id = (int)$_GET['department_id'];
    }
    
    // Get queue statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting_count,
            COUNT(CASE WHEN status = 'called' THEN 1 END) as called_count,
            COUNT(CASE WHEN status = 'serving' THEN 1 END) as serving_count,
            AVG(CASE WHEN status = 'waiting' THEN estimated_wait_time END) as avg_wait_time
        FROM queue_entries
        WHERE department_id = ? AND DATE(joined_at) = CURDATE()
    ");
    $stmt->execute([$department_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get waiting queue
    $stmt = $conn->prepare("
        SELECT 
            q.id,
            q.queue_number,
            q.status,
            q.priority,
            q.joined_at,
            q.estimated_wait_time,
            u.id as patient_id,
            u.full_name as patient_name,
            u.phone as patient_phone,
            TIMESTAMPDIFF(MINUTE, q.joined_at, NOW()) as wait_time_minutes
        FROM queue_entries q
        JOIN users u ON q.patient_id = u.id
        WHERE q.department_id = ? 
        AND q.status IN ('waiting', 'called')
        AND DATE(q.joined_at) = CURDATE()
        ORDER BY 
            CASE q.priority 
                WHEN 'emergency' THEN 1
                WHEN 'urgent' THEN 2
                ELSE 3
            END,
            q.joined_at ASC
    ");
    $stmt->execute([$department_id]);
    $waiting_queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get currently serving
    $stmt = $conn->prepare("
        SELECT 
            q.*,
            u.full_name as patient_name,
            u.phone as patient_phone,
            TIMESTAMPDIFF(MINUTE, q.serving_started_at, NOW()) as service_duration
        FROM queue_entries q
        JOIN users u ON q.patient_id = u.id
        WHERE q.staff_id = ? AND q.status = 'serving'
    ");
    $stmt->execute([$staff_id]);
    $serving = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent completed (last 5)
    $stmt = $conn->prepare("
        SELECT 
            q.queue_number,
            u.full_name as patient_name,
            q.completed_at,
            TIMESTAMPDIFF(MINUTE, q.serving_started_at, q.completed_at) as service_time
        FROM queue_entries q
        JOIN users u ON q.patient_id = u.id
        WHERE q.staff_id = ? AND q.status = 'completed'
        ORDER BY q.completed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$staff_id]);
    $recent_completed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate estimated time for each waiting patient
    foreach ($waiting_queue as &$patient) {
        $position = array_search($patient, $waiting_queue) + 1;
        $avg_service_time = $stats['avg_wait_time'] ?? 15;
        $patient['estimated_remaining'] = $position * $avg_service_time;
        $patient['position'] = $position;
        
        // Format wait time
        if ($patient['wait_time_minutes'] < 60) {
            $patient['wait_time_formatted'] = $patient['wait_time_minutes'] . ' min';
        } else {
            $hours = floor($patient['wait_time_minutes'] / 60);
            $minutes = $patient['wait_time_minutes'] % 60;
            $patient['wait_time_formatted'] = $hours . 'h ' . $minutes . 'm';
        }
    }
    
    sendJsonResponse(true, 'Queue data retrieved', [
        'stats' => [
            'waiting' => (int)$stats['waiting_count'],
            'called' => (int)$stats['called_count'],
            'serving' => (int)$stats['serving_count'],
            'avg_wait_time' => round($stats['avg_wait_time'] ?? 15)
        ],
        'waiting_queue' => $waiting_queue,
        'currently_serving' => $serving,
        'recent_completed' => $recent_completed,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_queue.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve queue: ' . $e->getMessage(), [], 500);
}
?>