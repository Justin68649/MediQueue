<?php
// File: api/admin/export_reports.php
// Export Reports to CSV/PDF

require_once '../../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

try {
    $export_type = $_GET['type'] ?? 'csv'; // csv or pdf
    $report_type = $_GET['report'] ?? 'summary'; // summary, departments, staff
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $time_from = $_GET['time_from'] ?? '00:00';
    $time_to = $_GET['time_to'] ?? '23:59';
    $all_time = isset($_GET['all_time']) && $_GET['all_time'] === '1';

    if (!$all_time && (!$date_from || !$date_to)) {
        $all_time = true;
    }

    $useDateFilter = !$all_time;
    if ($useDateFilter) {
        $start_datetime = $date_from . ' ' . $time_from;
        $end_datetime = $date_to . ' ' . $time_to;
        $params = [$start_datetime, $end_datetime];
    } else {
        $params = [];
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if ($export_type === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . APP_NAME . '_' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($report_type === 'summary') {
            // Summary Report
            fputcsv($output, ['Date', 'Total Visits', 'Completed', 'Cancelled', 'No Show', 'Avg Wait Time (min)']);
            
            $stmt = $conn->prepare("
                SELECT 
                    DATE(joined_at) as date,
                    COUNT(*) as total_visits,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                    AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_wait_time
                FROM queue_entries
                " . ($useDateFilter ? "WHERE joined_at BETWEEN ? AND ?" : "") . "
                GROUP BY DATE(joined_at)
                ORDER BY date DESC
            ");
            $stmt->execute($params);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['date'],
                    $row['total_visits'],
                    $row['completed'],
                    $row['cancelled'],
                    $row['no_show'],
                    round($row['avg_wait_time'] ?? 0, 2)
                ]);
            }
            
        } elseif ($report_type === 'departments') {
            // Department Performance Report
            fputcsv($output, ['Department', 'Total Visits', 'Completed', 'Completion Rate %', 'Avg Wait Time (min)', 'Unique Patients']);
            
            $stmt = $conn->prepare("
                SELECT 
                    d.name as department,
                    COUNT(q.id) as total,
                    SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    AVG(TIMESTAMPDIFF(MINUTE, q.joined_at, q.completed_at)) as avg_wait,
                    COUNT(DISTINCT q.patient_id) as patients
                FROM departments d
                LEFT JOIN queue_entries q ON d.id = q.department_id " .
                    ($useDateFilter ? "AND q.joined_at BETWEEN ? AND ?" : "") . "
                GROUP BY d.id
                ORDER BY total DESC
            ");
            $stmt->execute($params);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $completion_rate = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100, 1) : 0;
                fputcsv($output, [
                    $row['department'],
                    $row['total'],
                    $row['completed'],
                    $completion_rate,
                    round($row['avg_wait'] ?? 0, 2),
                    $row['patients']
                ]);
            }
            
        } elseif ($report_type === 'staff') {
            // Staff Performance Report
            fputcsv($output, ['Staff Name', 'Patients Served', 'Avg Service Time (min)', 'Avg Rating', 'Feedback Count']);
            
            $stmt = $conn->prepare("
                SELECT 
                    u.full_name as staff_name,
                    COUNT(q.id) as patients_served,
                    AVG(TIMESTAMPDIFF(MINUTE, q.serving_started_at, q.completed_at)) as avg_service_time,
                    AVG(f.rating) as avg_rating,
                    COUNT(f.id) as feedback_count
                FROM users u
                LEFT JOIN queue_entries q ON u.id = q.staff_id " .
                    ($useDateFilter ? "AND q.completed_at BETWEEN ? AND ?" : "") . "
                LEFT JOIN feedback f ON q.id = f.queue_entry_id
                WHERE u.role = 'staff'
                GROUP BY u.id
                HAVING patients_served > 0
                ORDER BY patients_served DESC
            ");
            $stmt->execute($params);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['staff_name'],
                    $row['patients_served'],
                    round($row['avg_service_time'] ?? 0, 2),
                    round($row['avg_rating'] ?? 0, 1),
                    $row['feedback_count']
                ]);
            }
        }
        
        fclose($output);
        exit;
        
    } elseif ($export_type === 'pdf') {
        // For now, redirect to print view
        // In production, you would use a PDF library like TCPDF or mPDF
        $queryString = $useDateFilter
            ? 'date_from=' . urlencode($date_from) . '&time_from=' . urlencode($time_from) . '&date_to=' . urlencode($date_to) . '&time_to=' . urlencode($time_to)
            : 'all_time=1';
        header('Location: ../admin/reports.php?' . $queryString . '&print=1');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error in export_reports.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to generate report: ' . $e->getMessage(), [], 500);
}
?>
