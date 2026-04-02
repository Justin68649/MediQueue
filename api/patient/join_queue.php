<?php
// File: api/patient/join_queue.php
// Join Queue API for Patients

require_once '../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

requireRole('patient');

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['department_id'])) {
    sendJsonResponse(false, 'Department selection is required');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if patient already has active queue entry
$stmt = $conn->prepare("
    SELECT id, status, queue_number 
    FROM queue_entries 
    WHERE patient_id = ? AND status IN ('waiting', 'called', 'serving')
");
$stmt->execute([$_SESSION['user_id']]);
$existing = $stmt->fetch();

if ($existing) {
    sendJsonResponse(false, 'You already have an active queue entry', [
        'queue_number' => $existing['queue_number'],
        'status' => $existing['status']
    ]);
}

// Check department queue size
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM queue_entries 
    WHERE department_id = ? AND status IN ('waiting', 'called', 'serving')
");
$stmt->execute([$data['department_id']]);
$queueCount = $stmt->fetch()['count'];

$maxQueue = getSystemSetting('max_queue_size') ?: MAX_QUEUE_SIZE;
if ($queueCount >= $maxQueue) {
    sendJsonResponse(false, 'Department queue is full. Please try again later.');
}

// Generate queue number
$queueNumber = generateQueueNumber($data['department_id']);
$position = $queueCount + 1;
$estimatedWait = calculateWaitTime($position, $data['department_id']);

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
    
    // Send confirmation notification
    $message = "You have joined the queue. Your number is {$queueNumber}. Estimated wait: {$estimatedWait} minutes.";
    sendNotification($_SESSION['user_id'], 'Queue Joined', $message, 'info', $queueId);
    
    // Send email confirmation
    $userStmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch();
    
    $subject = "Queue Confirmation - " . APP_NAME;
    $emailBody = "<h2>Queue Confirmation</h2>
                  <p>Dear {$user['full_name']},</p>
                  <p>You have successfully joined the queue.</p>
                  <p><strong>Queue Number:</strong> {$queueNumber}</p>
                  <p><strong>Position:</strong> {$position}</p>
                  <p><strong>Estimated Wait Time:</strong> {$estimatedWait} minutes</p>
                  <p>You will be notified when it's your turn.</p>";
    
    sendEmail($user['email'], $subject, $emailBody);
    
    $conn->commit();
    
    logAudit($_SESSION['user_id'], 'joined_queue', "Joined queue: {$queueNumber}");
    
    sendJsonResponse(true, 'Successfully joined the queue', [
        'queue_number' => $queueNumber,
        'position' => $position,
        'estimated_wait' => $estimatedWait,
        'department_id' => $data['department_id']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    sendJsonResponse(false, 'Failed to join queue: ' . $e->getMessage());
}