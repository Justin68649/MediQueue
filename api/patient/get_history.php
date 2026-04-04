<?php
// File: api/patient/get_history.php
// Get Patient's Appointment History

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to view your history', [], 401);
}

if ($_SESSION['role'] !== 'patient') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Get parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    
    // Build query
    $where_conditions = ["q.patient_id = ?"];
    $params = [$_SESSION['user_id']];
    
    if ($status && $status !== 'all') {
        $where_conditions[] = "q.status = ?";
        $params[] = $status;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(q.joined_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(q.joined_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    // Get total count
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM queue_entries q
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get history data
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare("
        SELECT 
            q.id,
            q.queue_number,
            q.status,
            q.priority,
            q.joined_at,
            q.called_at,
            q.serving_started_at,
            q.completed_at,
            q.estimated_wait_time,
            q.service_time,
            q.wait_time,
            q.notes,
            d.id as department_id,
            d.name as department_name,
            d.color as department_color,
            d.prefix as department_prefix,
            u.id as staff_id,
            u.full_name as staff_name,
            TIMESTAMPDIFF(MINUTE, q.joined_at, q.completed_at) as total_wait_time,
            CASE 
                WHEN q.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, q.joined_at, q.completed_at)
                ELSE NULL
            END as wait_time_minutes
        FROM queue_entries q
        LEFT JOIN departments d ON q.department_id = d.id
        LEFT JOIN users u ON q.staff_id = u.id
        WHERE $where_clause
        ORDER BY q.joined_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_visits,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_count,
            AVG(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(MINUTE, joined_at, completed_at) END) as avg_wait_time,
            SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency_count,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count
        FROM queue_entries
        WHERE patient_id = ?
    ");
    $stats_stmt->execute([$_SESSION['user_id']]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the response
    foreach ($history as &$entry) {
        $entry['joined_at_formatted'] = date('M d, Y h:i A', strtotime($entry['joined_at']));
        $entry['date'] = date('Y-m-d', strtotime($entry['joined_at']));
        $entry['time'] = date('h:i A', strtotime($entry['joined_at']));
        
        if ($entry['called_at']) {
            $entry['called_at_formatted'] = date('M d, Y h:i A', strtotime($entry['called_at']));
        }
        
        if ($entry['completed_at']) {
            $entry['completed_at_formatted'] = date('M d, Y h:i A', strtotime($entry['completed_at']));
        }

        // Real service / wait time if available
        $entry['service_time'] = $entry['service_time'] ?? null;
        $entry['wait_time'] = $entry['wait_time'] ?? $entry['wait_time_minutes'] ?? null;
        
        // Add status badge class
        switch ($entry['status']) {
            case 'waiting':
                $entry['status_class'] = 'status-waiting';
                break;
            case 'called':
                $entry['status_class'] = 'status-called';
                break;
            case 'serving':
                $entry['status_class'] = 'status-serving';
                break;
            case 'completed':
                $entry['status_class'] = 'status-completed';
                break;
            case 'cancelled':
                $entry['status_class'] = 'status-cancelled';
                break;
            case 'no_show':
                $entry['status_class'] = 'status-no_show';
                break;
            default:
                $entry['status_class'] = 'status-default';
                break;
        }
        
        // Check if feedback exists
        $feedback_stmt = $conn->prepare("SELECT id FROM feedback WHERE queue_entry_id = ? AND patient_id = ?");
        $feedback_stmt->execute([$entry['id'], $_SESSION['user_id']]);
        $entry['has_feedback'] = $feedback_stmt->fetch() ? true : false;
    }
    
    sendJsonResponse(true, 'History retrieved successfully', [
        'history' => $history,
        'stats' => $stats,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'total_pages' => ceil($total / $limit),
            'current_page' => floor($offset / $limit) + 1
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_history.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve history: ' . $e->getMessage(), [], 500);
}
?>