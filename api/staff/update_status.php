<?php
// File: api/staff/update_status.php
// Update Staff Availability Status

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to update status', [], 401);
}

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$csrfToken || !validateCSRFToken($csrfToken)) {
    error_log('CSRF validation failed for update_status.php');
    sendJsonResponse(false, 'CSRF token validation failed', [], 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    if (!is_array($input)) {
        sendJsonResponse(false, 'Invalid JSON input', [], 400);
    }

    $status = $input['status'] ?? 'online'; // online, offline, break
    $staff_id = $_SESSION['user_id'];
    
    // Validate status
    $allowed_status = ['online', 'offline', 'break'];
    if (!in_array($status, $allowed_status)) {
        sendJsonResponse(false, 'Invalid status');
    }
    
    // Update staff availability
    $stmt = $conn->prepare("
        INSERT INTO staff_availability (staff_id, date, is_available, break_start, break_end)
        VALUES (?, CURDATE(), ?, NULL, NULL)
        ON DUPLICATE KEY UPDATE 
            is_available = VALUES(is_available),
            break_start = CASE WHEN VALUES(is_available) = 0 THEN NOW() ELSE NULL END,
            break_end = CASE WHEN VALUES(is_available) = 1 AND break_start IS NOT NULL THEN NOW() ELSE break_end END
    ");
    
    $is_available = ($status === 'online') ? 1 : 0;
    $stmt->execute([$staff_id, $is_available]);
    
    // Update counter status (optional, if counters table exists)
    try {
        $stmt = $conn->prepare("
            UPDATE counters c
            JOIN users u ON u.counter_id = c.id
            SET c.is_online = ?
            WHERE u.id = ?
        ");
        $stmt->execute([$is_available, $staff_id]);
    } catch (Exception $e) {
        error_log('Counter status update skipped in update_status.php: ' . $e->getMessage());
    }
    
    // If going offline, complete any ongoing service
    if ($status === 'offline') {
        $stmt = $conn->prepare("
            UPDATE queue_entries 
            SET status = 'waiting', staff_id = NULL, serving_started_at = NULL
            WHERE staff_id = ? AND status = 'serving'
        ");
        $stmt->execute([$staff_id]);
    }
    
    // Log status change
    logAudit($staff_id, 'status_change', "Changed status to {$status}");
    
    $status_messages = [
        'online' => 'You are now online and ready to serve patients',
        'offline' => 'You are now offline. Patients will be redirected to other staff',
        'break' => 'You are on break. Please return by clicking online'
    ];
    
    sendJsonResponse(true, $status_messages[$status], [
        'status' => $status,
        'is_available' => $is_available
    ]);
    
} catch (Exception $e) {
    error_log("Error in update_status.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to update status: ' . $e->getMessage(), [], 500);
}
?>