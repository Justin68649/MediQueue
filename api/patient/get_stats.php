<?php
// File: api/patient/get_stats.php
// Get Patient Statistics and Analytics

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to view your statistics', [], 401);
}

if ($_SESSION['role'] !== 'patient') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $patient_id = $_SESSION['user_id'];
    
    // Get overall statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_visits,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_visits,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_visits,
            SUM(CASE WHEN status IN ('waiting', 'called', 'serving') THEN 1 ELSE 0 END) as active_visits,
            AVG(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(MINUTE, joined_at, completed_at) END) as avg_wait_time,
            SUM(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(MINUTE, joined_at, completed_at) END) as total_wait_time,
            MIN(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(MINUTE, joined_at, completed_at) END) as min_wait_time,
            MAX(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(MINUTE, joined_at, completed_at) END) as max_wait_time,
            SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency_visits,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_visits
        FROM queue_entries
        WHERE patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get monthly statistics (last 12 months)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(joined_at, '%Y-%m') as month,
            DATE_FORMAT(joined_at, '%M %Y') as month_name,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_wait
        FROM queue_entries
        WHERE patient_id = ? 
        AND joined_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(joined_at, '%Y-%m'), DATE_FORMAT(joined_at, '%M %Y')
        ORDER BY month DESC
    ");
    $stmt->execute([$patient_id]);
    $monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department-wise statistics
    $stmt = $conn->prepare("
        SELECT 
            d.id,
            d.name as department_name,
            d.color as department_color,
            COUNT(q.id) as total_visits,
            SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
            AVG(CASE WHEN q.status = 'completed' THEN TIMESTAMPDIFF(MINUTE, q.joined_at, q.completed_at) END) as avg_wait_time
        FROM departments d
        LEFT JOIN queue_entries q ON d.id = q.department_id AND q.patient_id = ?
        GROUP BY d.id, d.name, d.color
        HAVING total_visits > 0
        ORDER BY total_visits DESC
    ");
    $stmt->execute([$patient_id]);
    $department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get daily statistics (last 30 days)
    $stmt = $conn->prepare("
        SELECT 
            DATE(joined_at) as date,
            COUNT(*) as visits,
            AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_wait
        FROM queue_entries
        WHERE patient_id = ? 
        AND joined_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status = 'completed'
        GROUP BY DATE(joined_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$patient_id]);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get peak hours preference
    $stmt = $conn->prepare("
        SELECT 
            HOUR(joined_at) as hour,
            COUNT(*) as visits
        FROM queue_entries
        WHERE patient_id = ?
        GROUP BY HOUR(joined_at)
        ORDER BY visits DESC
        LIMIT 3
    ");
    $stmt->execute([$patient_id]);
    $peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get average rating given
    $stmt = $conn->prepare("
        SELECT 
            AVG(rating) as avg_rating,
            AVG(wait_time_rating) as avg_wait_rating,
            AVG(service_quality) as avg_service_quality,
            COUNT(*) as total_feedback
        FROM feedback
        WHERE patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $feedback_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as today_visits,
            SUM(CASE WHEN status IN ('waiting', 'called', 'serving') THEN 1 ELSE 0 END) as active_now
        FROM queue_entries
        WHERE patient_id = ? AND DATE(joined_at) = CURDATE()
    ");
    $stmt->execute([$patient_id]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate satisfaction score (based on feedback)
    $satisfaction_score = 0;
    if ($feedback_stats['total_feedback'] > 0) {
        $satisfaction_score = round(
            ($feedback_stats['avg_rating'] + $feedback_stats['avg_wait_rating'] + $feedback_stats['avg_service_quality']) / 3,
            1
        );
    }
    
    sendJsonResponse(true, 'Statistics retrieved successfully', [
        'overall' => [
            'total_visits' => (int)$overall_stats['total_visits'],
            'completed_visits' => (int)$overall_stats['completed_visits'],
            'cancelled_visits' => (int)$overall_stats['cancelled_visits'],
            'no_show_visits' => (int)$overall_stats['no_show_visits'],
            'active_visits' => (int)$overall_stats['active_visits'],
            'avg_wait_time' => round($overall_stats['avg_wait_time'] ?? 0),
            'total_wait_time' => (int)$overall_stats['total_wait_time'],
            'min_wait_time' => (int)$overall_stats['min_wait_time'],
            'max_wait_time' => (int)$overall_stats['max_wait_time'],
            'emergency_visits' => (int)$overall_stats['emergency_visits'],
            'urgent_visits' => (int)$overall_stats['urgent_visits'],
            'completion_rate' => $overall_stats['total_visits'] > 0 
                ? round(($overall_stats['completed_visits'] / $overall_stats['total_visits']) * 100, 1)
                : 0
        ],
        'monthly' => $monthly_stats,
        'by_department' => $department_stats,
        'daily' => $daily_stats,
        'peak_hours' => $peak_hours,
        'feedback' => [
            'avg_rating' => round($feedback_stats['avg_rating'] ?? 0, 1),
            'avg_wait_rating' => round($feedback_stats['avg_wait_rating'] ?? 0, 1),
            'avg_service_quality' => round($feedback_stats['avg_service_quality'] ?? 0, 1),
            'total_feedback' => (int)$feedback_stats['total_feedback'],
            'satisfaction_score' => $satisfaction_score
        ],
        'today' => [
            'visits' => (int)$today_stats['today_visits'],
            'active_now' => (int)$today_stats['active_now']
        ],
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_stats.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve statistics: ' . $e->getMessage(), [], 500);
}
?>