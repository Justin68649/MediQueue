<?php
// File: api/staff/call_patient.php
// Call Patient API - Notify patient that it's their turn

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

// For JSON API requests from authenticated users, parse input first.
$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$csrfToken || !validateCSRFToken($csrfToken)) {
    error_log('CSRF validation failed for call_patient.php');
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
    
    // Verify queue entry belongs to staff's department
    $stmt = $conn->prepare("
        SELECT q.*, u.full_name as patient_name, u.email as patient_email, u.phone as patient_phone,
               d.name as department_name
        FROM queue_entries q
        JOIN departments d ON q.department_id = d.id
        JOIN users u ON q.patient_id = u.id
        WHERE q.id = ? AND q.status = 'waiting'
    ");
    $stmt->execute([$queue_id]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queue) {
        sendJsonResponse(false, 'Queue entry not found or already called');
    }
    
    // Check if staff belongs to this department
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff['department_id'] != $queue['department_id'] && $_SESSION['role'] !== 'admin') {
        sendJsonResponse(false, 'You can only call patients from your department');
    }
    
    // Update queue status to 'called'
    $stmt = $conn->prepare("
        UPDATE queue_entries 
        SET status = 'called', called_at = NOW(), notification_sent = 1 
        WHERE id = ?
    ");
    $stmt->execute([$queue_id]);
    
    // Send notification to patient
    $notification_title = "Queue Called - {$queue['queue_number']}";
    $notification_message = "Your number {$queue['queue_number']} has been called. Please proceed to {$queue['department_name']}.";
    
    sendNotification($queue['patient_id'], $notification_title, $notification_message, 'queue_called', $queue_id);
    
    // Send SMS if enabled
    if (getSystemSetting('sms_enabled') === 'true') {
        $sms_message = "MediQueue: Your number {$queue['queue_number']} has been called. Please proceed to {$queue['department_name']}.";
        sendSMS($queue['patient_phone'], $sms_message);
    }
    
    // Send email
    $email_subject = "Queue Called - " . APP_NAME;
    $email_body = "
        <h2>Your Queue Number Has Been Called</h2>
        <p>Dear {$queue['patient_name']},</p>
        <p>Your queue number <strong>{$queue['queue_number']}</strong> has been called.</p>
        <p>Please proceed to <strong>{$queue['department_name']}</strong> immediately.</p>
        <p>Thank you for your patience.</p>
    ";
    sendEmail($queue['patient_email'], $email_subject, $email_body);
    
    // Log audit
    logAudit($staff_id, 'called_patient', "Called patient {$queue['queue_number']} in {$queue['department_name']}");
    
    sendJsonResponse(true, 'Patient called successfully', [
        'queue_id' => $queue_id,
        'queue_number' => $queue['queue_number'],
        'patient_name' => $queue['patient_name']
    ]);
    
} catch (Exception $e) {
    error_log("Error in call_patient.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to call patient: ' . $e->getMessage(), [], 500);
}
?>