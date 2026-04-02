<?php
// File: api/patient/join_queue.php
// Join Queue API for Patients

require_once __DIR__ . '/../config/config.php';
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
// since JSON payloads cannot be submitted from a cross-origin form
if (!empty($data['csrf_token'])) {
    if (!validateCSRFToken($data['csrf_token'])) {
        sendJsonResponse(false, 'CSRF token validation failed', [], 403);
    }
}

if (empty($data['department_id'])) {
    sendJsonResponse(false, 'Department ID is required');
}

if (!is_numeric($data['department_id'])) {
    sendJsonResponse(false, 'Invalid department ID');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    sendJsonResponse(false, 'Database connection failed');
}

// Check if patient already has active queue entry
try {
    $stmt = $conn->prepare("
        SELECT id, status, queue_number 
        FROM queue_entries 
        WHERE patient_id = ? AND status IN ('waiting', 'called', 'serving')
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $existing = $stmt->fetch();
    
    // Debug logging
    error_log("join_queue.php: User ID: " . $_SESSION['user_id'] . ", Existing queue: " . ($existing ? json_encode($existing) : 'NONE'));
} catch (Exception $e) {
    error_log('Queue check error: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to check existing queue');
}

if ($existing) {
    error_log("join_queue.php: Blocking join - existing queue: " . json_encode($existing));
    sendJsonResponse(false, 'You already have an active queue entry', [
        'queue_number' => $existing['queue_number'],
        'status' => $existing['status']
    ]);
}

// Check department queue size
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM queue_entries 
        WHERE department_id = ? AND status IN ('waiting', 'called', 'serving')
    ");
    $stmt->execute([$data['department_id']]);
    $result = $stmt->fetch();
    $queueCount = $result ? (int)$result['count'] : 0;
} catch (Exception $e) {
    error_log('Queue count error: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to get queue count');
}

$maxQueue = getSystemSetting('max_queue_size') ?: MAX_QUEUE_SIZE;
if ($queueCount >= $maxQueue) {
    sendJsonResponse(false, 'Department queue is full. Please try again later.');
}

// Generate queue number
$queueNumber = generateQueueNumber($data['department_id']);
$position = $queueCount + 1;
$estimatedWait = calculateWaitTime($position, $data['department_id']);

// Validate department exists
try {
    $deptStmt = $conn->prepare("SELECT id FROM departments WHERE id = ? AND is_active = 1");
    $deptStmt->execute([$data['department_id']]);
    if (!$deptStmt->fetch()) {
        sendJsonResponse(false, 'Department not found or is inactive');
    }
} catch (Exception $e) {
    error_log('Department validation error: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to validate department');
}

// Create queue entry
$conn->beginTransaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO queue_entries 
        (queue_number, patient_id, department_id, position, estimated_wait_time, priority) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $priority = $data['priority'] ?? 'normal';
    $stmt->execute([$queueNumber, $_SESSION['user_id'], $data['department_id'], $position, $estimatedWait, $priority]);
    
    $queueId = $conn->lastInsertId();
    
    // Send confirmation notification (non-blocking)
    try {
        $message = "You have joined the queue. Your number is {$queueNumber}. Estimated wait: {$estimatedWait} minutes.";
        sendNotification($_SESSION['user_id'], 'Queue Joined', $message, 'info', $queueId);
    } catch (Exception $notifEx) {
        error_log('Notification failed (non-blocking): ' . $notifEx->getMessage());
    }
    
    // Send email confirmation (non-blocking)
    try {
        $userStmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $user = $userStmt->fetch();
        
        if ($user && $user['email']) {
            $subject = "Queue Confirmation - " . APP_NAME;
            $emailBody = "<h2>Queue Confirmation</h2>
                          <p>Dear {$user['full_name']},</p>
                          <p>You have successfully joined the queue.</p>
                          <p><strong>Queue Number:</strong> {$queueNumber}</p>
                          <p><strong>Position:</strong> {$position}</p>
                          <p><strong>Estimated Wait Time:</strong> {$estimatedWait} minutes</p>
                          <p>You will be notified when it's your turn.</p>";
            
            sendEmail($user['email'], $subject, $emailBody);
        }
    } catch (Exception $emailEx) {
        error_log('Email failed (non-blocking): ' . $emailEx->getMessage());
    }
    
    $conn->commit();
    
    // Log audit (non-blocking)
    try {
        logAudit($_SESSION['user_id'], 'joined_queue', "Joined queue: {$queueNumber}");
    } catch (Exception $auditEx) {
        error_log('Audit logging failed (non-blocking): ' . $auditEx->getMessage());
    }
    
    sendJsonResponse(true, 'Successfully joined the queue', [
        'queue_number' => $queueNumber,
        'position' => $position,
        'estimated_wait' => $estimatedWait,
        'department_id' => $data['department_id']
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
    
    error_log('Queue join error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Failed to join queue. Please try again.');
}