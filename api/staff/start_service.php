<?php
// File: api/staff/start_service.php
// Start Serving Patient API

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to perform this action', [], 401);
}

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Access denied. Staff only.', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$csrfToken || !validateCSRFToken($csrfToken)) {
    error_log('CSRF validation failed for start_service.php');
    sendJsonResponse(false, 'CSRF token validation failed', [], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    if (!is_array($input)) {
        sendJsonResponse(false, 'Invalid JSON input', [], 400);
    }
    
    if (empty($input['queue_id'])) {
        sendJsonResponse(false, 'Queue ID is required');
    }
    
    $queue_id = (int)$input['queue_id'];
    $staff_id = $_SESSION['user_id'];
    
    // Verify queue entry
    $stmt = $conn->prepare("
        SELECT q.*, u.full_name as patient_name
        FROM queue_entries q
        JOIN users u ON q.patient_id = u.id
        WHERE q.id = ? AND q.status = 'called'
    ");
    $stmt->execute([$queue_id]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queue) {
        sendJsonResponse(false, 'Queue entry not found or not in called status');
    }
    
    // Check if staff is already serving someone
    $stmt = $conn->prepare("
        SELECT id FROM queue_entries 
        WHERE staff_id = ? AND status = 'serving'
    ");
    $stmt->execute([$staff_id]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'You are already serving a patient. Please complete current service first.');
    }
    
    // Start service
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("
        UPDATE queue_entries 
        SET status = 'serving', staff_id = ?, serving_started_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$staff_id, $queue_id]);
    
    // Update counter status (optional, if counters table exists)
    try {
        $stmt = $conn->prepare("
            UPDATE counters 
            SET current_customer_id = ?, is_online = 1 
            WHERE id = (SELECT counter_id FROM users WHERE id = ?)
        ");
        $stmt->execute([$queue_id, $staff_id]);
    } catch (Exception $e) {
        error_log('Counter update skipped in start_service.php: ' . $e->getMessage());
    }
    
    // Send notification to patient
    $notification_title = "Service Started - {$queue['queue_number']}";
    $notification_message = "Dr. " . $_SESSION['user_name'] . " has started your service.";
    sendNotification($queue['patient_id'], $notification_title, $notification_message, 'info', $queue_id);
    
    $conn->commit();
    
    // Log audit
    logAudit($staff_id, 'started_service', "Started service for patient {$queue['queue_number']}");
    
    sendJsonResponse(true, 'Service started successfully', [
        'queue_id' => $queue_id,
        'queue_number' => $queue['queue_number'],
        'patient_name' => $queue['patient_name']
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    error_log("Error in start_service.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to start service: ' . $e->getMessage(), [], 500);
}
?>