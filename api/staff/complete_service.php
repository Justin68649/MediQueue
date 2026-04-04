<?php
// File: api/staff/complete_service.php
// Complete Service API

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
    error_log('CSRF validation failed for complete_service.php');
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
    $notes = $input['notes'] ?? null;
    
    // Verify queue entry belongs to this staff
    $stmt = $conn->prepare("
        SELECT q.*, u.full_name as patient_name, u.email as patient_email, u.phone as patient_phone
        FROM queue_entries q
        JOIN users u ON q.patient_id = u.id
        WHERE q.id = ? AND q.staff_id = ? AND q.status = 'serving'
    ");
    $stmt->execute([$queue_id, $staff_id]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queue) {
        sendJsonResponse(false, 'Queue entry not found or not currently being served by you');
    }
    
    // Calculate service time
    $service_time = round((time() - strtotime($queue['serving_started_at'])) / 60);
    $total_wait_time = round((time() - strtotime($queue['joined_at'])) / 60);
    
    // Complete service
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("
        UPDATE queue_entries 
        SET status = 'completed', completed_at = NOW(), service_time = ?, wait_time = ?, notes = CONCAT(IFNULL(notes, ''), ' ', ?)
        WHERE id = ?
    ");
    $stmt->execute([$service_time, $total_wait_time, $notes, $queue_id]);
    
    // Clear counter
    $stmt = $conn->prepare("
        UPDATE counters 
        SET current_customer_id = NULL 
        WHERE current_customer_id = ?
    ");
    $stmt->execute([$queue_id]);
    
    // Send completion notification to patient
    $notification_title = "Service Completed - {$queue['queue_number']}";
    $notification_message = "Your service has been completed. Thank you for visiting " . APP_NAME . ".";
    sendNotification($queue['patient_id'], $notification_title, $notification_message, 'info', $queue_id);
    
    // Send notification to admin about service completion (non-blocking)
    try {
        $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll();
        
        foreach ($admins as $admin) {
            $adminMsg = "Staff {$_SESSION['user_name']} completed service for {$queue['queue_number']} (Service time: {$service_time} min)";
            sendNotification($admin['id'], "Service Completed - {$queue['queue_number']}", $adminMsg, 'info', $queue_id);
        }
    } catch (Exception $adminEx) {
        error_log('Admin notification failed (non-blocking): ' . $adminEx->getMessage());
    }
    
    // Send SMS if enabled
    if (getSystemSetting('sms_enabled') === 'true') {
        $sms_message = APP_NAME . ": Your service is complete. Thank you for visiting us!";
        sendSMS($queue['patient_phone'], $sms_message);
    }
    
    // Send email feedback request
    $email_subject = "Service Completed - Please Share Your Feedback";
    $email_body = "
        <h2>Thank You for Visiting " . APP_NAME . "</h2>
        <p>Dear {$queue['patient_name']},</p>
        <p>Your service has been completed successfully.</p>
        <p><strong>Queue Number:</strong> {$queue['queue_number']}</p>
        <p><strong>Service Time:</strong> {$service_time} minutes</p>
        <p><strong>Total Wait Time:</strong> {$total_wait_time} minutes</p>
        <br>
        <p>We value your feedback. Please click the link below to rate your experience:</p>
        <p><a href='" . APP_URL . "/patient/feedback.php'>Share Your Feedback</a></p>
        <br>
        <p>Thank you for choosing us.</p>
    ";
    sendEmail($queue['patient_email'], $email_subject, $email_body);
    
    $conn->commit();
    
    // Update staff statistics cache
    $stmt = $conn->prepare("
        UPDATE users 
        SET total_served = total_served + 1,
            avg_service_time = ((avg_service_time * total_served) + ?) / (total_served + 1)
        WHERE id = ?
    ");
    $stmt->execute([$service_time, $staff_id]);
    
    // Log audit
    logAudit($staff_id, 'completed_service', "Completed service for {$queue['queue_number']}. Service time: {$service_time} min");
    
    sendJsonResponse(true, 'Service completed successfully', [
        'queue_id' => $queue_id,
        'queue_number' => $queue['queue_number'],
        'patient_name' => $queue['patient_name'],
        'service_time' => $service_time,
        'total_wait_time' => $total_wait_time
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    error_log("Error in complete_service.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to complete service: ' . $e->getMessage(), [], 500);
}
?>