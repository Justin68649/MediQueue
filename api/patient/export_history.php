<?php
// File: api/patient/export_history.php
// Export patient history as CSV

require_once '../../config/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Please login to export history';
    exit;
}

if ($_SESSION['role'] !== 'patient') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$format = $_GET['format'] ?? 'csv';
if (strtolower($format) !== 'csv') {
    http_response_code(400);
    echo 'Unsupported format';
    exit;
}

$status = $_GET['status'] ?? null;
$date = $_GET['date'] ?? null;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $where = ['q.patient_id = ?'];
    $params = [$_SESSION['user_id']];

    if ($status && $status !== 'all') {
        $where[] = 'q.status = ?';
        $params[] = $status;
    }

    if (!empty($date)) {
        $where[] = 'DATE(q.joined_at) = ?';
        $params[] = $date;
    }

    $whereSql = implode(' AND ', $where);

    $stmt = $conn->prepare("SELECT
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
        d.name AS department_name,
        u.full_name AS staff_name
    FROM queue_entries q
    LEFT JOIN departments d ON q.department_id = d.id
    LEFT JOIN users u ON q.staff_id = u.id
    WHERE $whereSql
    ORDER BY q.joined_at DESC");

    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CSV output
    $filename = 'appointment_history_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'Queue Number', 'Department', 'Staff', 'Status', 'Priority',
        'Joined At', 'Called At', 'Serving Started', 'Completed At',
        'Estimated Wait Time', 'Service Time', 'Wait Time', 'Notes'
    ]);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['queue_number'],
            $row['department_name'],
            $row['staff_name'],
            $row['status'],
            $row['priority'],
            $row['joined_at'],
            $row['called_at'],
            $row['serving_started_at'],
            $row['completed_at'],
            $row['estimated_wait_time'],
            $row['service_time'],
            $row['wait_time'],
            $row['notes']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log('Error in export_history.php: ' . $e->getMessage());
    http_response_code(500);
    echo 'Failed to export history';
    exit;
}
