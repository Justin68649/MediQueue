<?php
// File: api/admin/export_report.php
// Export Report as CSV

require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 401 Unauthorized');
    echo "Unauthorized access";
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'daily';

// Set filename
$filename = "report_{$date_from}_to_{$date_to}.csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Date',
    'Total Visits',
    'Completed',
    'Cancelled',
    'No Show',
    'Avg Wait Time (min)',
    'Avg Service Time (min)',
    'Unique Patients'
]);

// Get data
$stmt = $conn->prepare("
    SELECT 
        DATE(joined_at) as date,
        COUNT(*) as total_visits,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_wait_time,
        AVG(TIMESTAMPDIFF(MINUTE, serving_started_at, completed_at)) as avg_service_time,
        COUNT(DISTINCT patient_id) as unique_patients
    FROM queue_entries
    WHERE DATE(joined_at) BETWEEN ? AND ?
    GROUP BY DATE(joined_at)
    ORDER BY date DESC
");
$stmt->execute([$date_from, $date_to]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['date'],
        $row['total_visits'],
        $row['completed'],
        $row['cancelled'],
        $row['no_show'],
        round($row['avg_wait_time'] ?? 0),
        round($row['avg_service_time'] ?? 0),
        $row['unique_patients']
    ]);
}

// Add department breakdown section
fputcsv($output, []);
fputcsv($output, ['Department Breakdown']);
fputcsv($output, ['Department', 'Total Visits', 'Completed', 'Avg Wait Time']);

$dept_stmt = $conn->prepare("
    SELECT 
        d.name,
        COUNT(q.id) as total,
        SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed,
        AVG(TIMESTAMPDIFF(MINUTE, q.joined_at, q.completed_at)) as avg_wait
    FROM departments d
    LEFT JOIN queue_entries q ON d.id = q.department_id 
        AND DATE(q.joined_at) BETWEEN ? AND ?
    GROUP BY d.id
");
$dept_stmt->execute([$date_from, $date_to]);

while ($dept = $dept_stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $dept['name'],
        $dept['total'],
        $dept['completed'],
        round($dept['avg_wait'] ?? 0)
    ]);
}

// Add staff performance section
fputcsv($output, []);
fputcsv($output, ['Staff Performance']);
fputcsv($output, ['Staff Name', 'Patients Served', 'Avg Service Time', 'Avg Rating']);

$staff_stmt = $conn->prepare("
    SELECT 
        u.full_name,
        COUNT(q.id) as patients_served,
        AVG(TIMESTAMPDIFF(MINUTE, q.serving_started_at, q.completed_at)) as avg_service,
        AVG(f.rating) as avg_rating
    FROM users u
    LEFT JOIN queue_entries q ON u.id = q.staff_id 
        AND DATE(q.completed_at) BETWEEN ? AND ?
    LEFT JOIN feedback f ON q.id = f.queue_entry_id
    WHERE u.role = 'staff'
    GROUP BY u.id
    HAVING patients_served > 0
    ORDER BY patients_served DESC
");
$staff_stmt->execute([$date_from, $date_to]);

while ($staff = $staff_stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $staff['full_name'],
        $staff['patients_served'],
        round($staff['avg_service'] ?? 0),
        round($staff['avg_rating'] ?? 0, 1)
    ]);
}

fclose($output);

// Log audit
logAudit($_SESSION['user_id'], 'exported_report', "Exported report from {$date_from} to {$date_to}");
exit;
?>