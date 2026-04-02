<?php
// File: api/patient/cancel_queue.php
// Cancel Queue Entry for Patient

require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

requireRole('patient');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendJsonResponse(false, 'Invalid JSON input');
}

// For JSON API requests from authenticated users, CSRF validation is optional
if (!empty($data['csrf_token'])) {
    if (!validateCSRFToken($data['csrf_token'])) {
        sendJsonResponse(false, 'CSRF token validation failed', [], 403);
    }
}

if (empty($data['queue_id'])) {
    sendJsonResponse(false, 'Queue ID is required');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    sendJsonResponse(false, 'Database connection failed');
}

// Verify the queue entry belongs to the patient
try {
    $stmt = $conn->prepare("
        SELECT id, status, queue_number FROM queue_entries 
        WHERE id = ? AND patient_id = ?
    ");
    $stmt->execute([$data['queue_id'], $_SESSION['user_id']]);
    $queue = $stmt->fetch();
} catch (Exception $e) {
    error_log('Queue lookup error: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to lookup queue');
}

if (!$queue) {
    sendJsonResponse(false, 'Queue not found or does not belong to you');
}

// Can only cancel waiting, called, or serving queues
if (!in_array($queue['status'], ['waiting', 'called', 'serving'])) {
    sendJsonResponse(false, 'Queue cannot be cancelled in its current status (' . $queue['status'] . ')');
}

// Cancel the queue entry
try {
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("
        UPDATE queue_entries 
        SET status = 'cancelled', cancelled_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$data['queue_id']]);
    
    // Send cancellation notification (non-blocking)
    try {
        $message = "Your queue position #{$queue['queue_number']} has been cancelled.";
        sendNotification($_SESSION['user_id'], 'Queue Cancelled', $message, 'info', $data['queue_id']);
    } catch (Exception $notifEx) {
        error_log('Notification failed (non-blocking): ' . $notifEx->getMessage());
    }
    
    // Log audit (non-blocking)
    try {
        logAudit($_SESSION['user_id'], 'cancelled_queue', "Cancelled queue: {$queue['queue_number']}");
    } catch (Exception $auditEx) {
        error_log('Audit logging failed (non-blocking): ' . $auditEx->getMessage());
    }
    
    $conn->commit();
    
    sendJsonResponse(true, 'Queue cancelled successfully', [
        'queue_number' => $queue['queue_number']
    ]);
    
} catch (Throwable $e) {
    $inTransaction = false;
    try {
        $inTransaction = $conn->inTransaction();
    } catch (Exception $ex) {
        // Transaction state check failed
    }
    
    if ($inTransaction) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackError) {
            error_log('Rollback failed: ' . $rollbackError->getMessage());
        }
    }
    
    error_log('Queue cancel error: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to cancel queue. Please try again.');
}
