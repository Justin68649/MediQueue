<?php
// File: api/staff/get_stats.php
// Get Staff Performance Statistics

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to view statistics', [], 401);
}

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $staff_id = $_SESSION['user_id'];
    $period = isset($_GET['period']) ? $_GET['period'] : 'today'; // today, week, month, year
    
    // Set date range based on period
    switch ($period) {
        case 'today':
            $date_condition = "DATE(completed_at) = CURDATE()";
            break;
        case 'week':
            $date_condition = "YEARWEEK(completed_at) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $date_condition = "MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE())";
            break;
        case 'year':
            $date_condition = "YEAR(completed_at) = YEAR(CURDATE())";
            break;
        default:
            $date_condition = "DATE(completed_at) = CURDATE()";
    }
    
    // Get overall stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_served,
            AVG(TIMESTAMPDIFF(MINUTE, serving_started_at, completed_at)) as avg_service_time,
            AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_patient_wait_time,
            MIN(TIMESTAMPDIFF(MINUTE, serving_started_at, completed_at)) as fastest_service,
            MAX(TIMESTAMPDIFF(MINUTE, serving_started_at, completed_at)) as slowest_service,
            COUNT(CASE WHEN priority = 'emergency' THEN 1 END) as emergency_cases,
            COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_cases,
            SUM(CASE WHEN feedback.rating >= 4 THEN 1 ELSE 0 END) as positive_feedback,
            COUNT(feedback.id) as total_feedback,
            AVG(feedback.rating) as avg_rating
        FROM queue_entries q
        LEFT JOIN feedback ON q.id = feedback.queue_entry_id
        WHERE q.staff_id = ? AND q.status = 'completed' AND $date_condition
    ");
    $stmt->execute([$staff_id]);
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get daily breakdown (last 7 days)
    $stmt = $conn->prepare("
        SELECT 
            DATE(completed_at) as date,
            COUNT(*) as served,
            AVG(TIMESTAMPDIFF(MINUTE, serving_started_at, completed_at)) as avg_time
        FROM queue_entries
        WHERE staff_id = ? AND status = 'completed' 
        AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(completed_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$staff_id]);
    $daily_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get hourly distribution (peak hours)
    $stmt = $conn->prepare("
        SELECT 
            HOUR(completed_at) as hour,
            COUNT(*) as served
        FROM queue_entries
        WHERE staff_id = ? AND status = 'completed'
        GROUP BY HOUR(completed_at)
        ORDER BY served DESC
        LIMIT 5
    ");
    $stmt->execute([$staff_id]);
    $peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department comparison (if admin)
    $department_comparison = null;
    if ($_SESSION['role'] === 'admin') {
        $stmt = $conn->prepare("
            SELECT 
                d.name as department,
                COUNT(q.id) as total_served,
                AVG(TIMESTAMPDIFF(MINUTE, q.serving_started_at, q.completed_at)) as avg_time,
                AVG(f.rating) as avg_rating
            FROM departments d
            LEFT JOIN queue_entries q ON d.id = q.department_id AND q.status = 'completed'
            LEFT JOIN feedback f ON q.id = f.queue_entry_id
            WHERE q.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY d.id, d.name
            ORDER BY total_served DESC
        ");
        $stmt->execute();
        $department_comparison = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get satisfaction trend
    $stmt = $conn->prepare("
        SELECT 
            DATE(f.created_at) as date,
            AVG(f.rating) as avg_rating,
            COUNT(f.id) as feedback_count
        FROM feedback f
        JOIN queue_entries q ON f.queue_entry_id = q.id
        WHERE q.staff_id = ? AND f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(f.created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$staff_id]);
    $satisfaction_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate performance score
    $performance_score = 0;
    if ($overall['total_served'] > 0) {
        $speed_score = max(0, min(100, 100 - (($overall['avg_service_time'] ?? 0) / 2)));
        $quality_score = ($overall['avg_rating'] ?? 0) * 20;
        $volume_score = min(100, ($overall['total_served'] / 20) * 100);
        $performance_score = round(($speed_score + $quality_score + $volume_score) / 3);
    }
    
    sendJsonResponse(true, 'Statistics retrieved', [
        'period' => $period,
        'overall' => [
            'total_served' => (int)$overall['total_served'],
            'avg_service_time' => round($overall['avg_service_time'] ?? 0),
            'avg_patient_wait_time' => round($overall['avg_patient_wait_time'] ?? 0),
            'fastest_service' => (int)$overall['fastest_service'],
            'slowest_service' => (int)$overall['slowest_service'],
            'emergency_cases' => (int)$overall['emergency_cases'],
            'urgent_cases' => (int)$overall['urgent_cases'],
            'positive_feedback' => (int)$overall['positive_feedback'],
            'total_feedback' => (int)$overall['total_feedback'],
            'avg_rating' => round($overall['avg_rating'] ?? 0, 1),
            'satisfaction_rate' => $overall['total_feedback'] > 0 
                ? round(($overall['positive_feedback'] / $overall['total_feedback']) * 100)
                : 0,
            'performance_score' => $performance_score
        ],
        'daily_breakdown' => $daily_breakdown,
        'peak_hours' => $peak_hours,
        'department_comparison' => $department_comparison,
        'satisfaction_trend' => $satisfaction_trend,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_stats.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve statistics: ' . $e->getMessage(), [], 500);
}
?>