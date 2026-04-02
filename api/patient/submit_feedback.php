<?php
// File: api/patient/submit_feedback.php
// Submit Patient Feedback

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Please login to submit feedback', [], 401);
}

if ($_SESSION['role'] !== 'patient') {
    sendJsonResponse(false, 'Access denied', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(false, 'Invalid JSON input');
    }

    $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$csrfToken || !validateCSRFToken($csrfToken)) {
        sendJsonResponse(false, 'CSRF token validation failed', [], 403);
    }
    
    // Validate required fields
    if (empty($input['queue_id'])) {
        sendJsonResponse(false, 'Queue entry ID is required');
    }
    
    if (!isset($input['rating']) || $input['rating'] < 1 || $input['rating'] > 5) {
        sendJsonResponse(false, 'Valid rating (1-5) is required');
    }
    
    if (isset($input['wait_time_rating']) && ($input['wait_time_rating'] < 1 || $input['wait_time_rating'] > 5)) {
        sendJsonResponse(false, 'Valid wait time rating (1-5) is required');
    }
    
    if (isset($input['service_quality']) && ($input['service_quality'] < 1 || $input['service_quality'] > 5)) {
        sendJsonResponse(false, 'Valid service quality rating (1-5) is required');
    }
    
    $queue_id = (int)$input['queue_id'];
    $rating = (int)$input['rating'];
    $wait_time_rating = isset($input['wait_time_rating']) ? (int)$input['wait_time_rating'] : null;
    $service_quality = isset($input['service_quality']) ? (int)$input['service_quality'] : null;
    $comment = isset($input['comment']) ? trim($input['comment']) : null;
    $suggestions = isset($input['suggestions']) ? trim($input['suggestions']) : null;
    
    // Verify that the queue entry belongs to the patient and is completed
    $stmt = $conn->prepare("
        SELECT q.id, q.patient_id, q.status, q.staff_id, d.name as department_name
        FROM queue_entries q
        LEFT JOIN departments d ON q.department_id = d.id
        WHERE q.id = ? AND q.patient_id = ?
    ");
    $stmt->execute([$queue_id, $_SESSION['user_id']]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queue) {
        sendJsonResponse(false, 'Queue entry not found or does not belong to you');
    }
    
    if ($queue['status'] !== 'completed') {
        sendJsonResponse(false, 'Feedback can only be submitted for completed services');
    }
    
    // Check if feedback already exists
    $stmt = $conn->prepare("SELECT id FROM feedback WHERE queue_entry_id = ? AND patient_id = ?");
    $stmt->execute([$queue_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'Feedback already submitted for this appointment');
    }
    
    // Insert feedback
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("
        INSERT INTO feedback (
            queue_entry_id, 
            patient_id, 
            staff_id, 
            rating, 
            wait_time_rating, 
            service_quality, 
            comment, 
            suggestions,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $queue_id,
        $_SESSION['user_id'],
        $queue['staff_id'],
        $rating,
        $wait_time_rating,
        $service_quality,
        $comment,
        $suggestions
    ]);
    
    if (!$result) {
        throw new Exception('Failed to save feedback');
    }
    
    $feedback_id = $conn->lastInsertId();
    
    // Update notification for staff about feedback
    if ($queue['staff_id']) {
        $staff_message = "Patient has rated your service: {$rating}/5 stars";
        sendNotification($queue['staff_id'], 'New Feedback Received', $staff_message, 'info');
    }
    
    // Log audit
    logAudit($_SESSION['user_id'], 'submitted_feedback', "Submitted feedback for queue #{$queue_id}, rating: {$rating}");
    
    $conn->commit();
    
    // Send thank you email
    $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $subject = "Thank You for Your Feedback - " . APP_NAME;
        $email_body = "
            <h2>Thank You for Your Feedback!</h2>
            <p>Dear {$user['full_name']},</p>
            <p>Thank you for taking the time to share your experience at {$queue['department_name']}.</p>
            <p>Your feedback helps us improve our service quality and provide better healthcare experience.</p>
            <br>
            <p><strong>Your Rating:</strong> {$rating}/5 stars</p>
            " . ($comment ? "<p><strong>Your Comment:</strong> " . htmlspecialchars($comment) . "</p>" : "") . "
            <br>
            <p>We truly value your opinion and will use it to enhance our services.</p>
            <br>
            <p>Best regards,<br>" . APP_NAME . " Team</p>
        ";
        sendEmail($user['email'], $subject, $email_body);
    }
    
    sendJsonResponse(true, 'Thank you for your valuable feedback!', [
        'feedback_id' => $feedback_id,
        'rating' => $rating,
        'message' => 'Your feedback has been recorded successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    error_log("Error in submit_feedback.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to submit feedback: ' . $e->getMessage(), [], 500);
}
?>