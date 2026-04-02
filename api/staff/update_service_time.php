<?php
// File: api/staff/update_service_time.php
// Update service time for completed queue entry

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
    sendJsonResponse(false, 'CSRF token validation failed', [], 403);
}

if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON input', [], 400);
}

$queue_id = isset($input['queue_id']) ? (int)$input['queue_id'] : 0;
$service_time = isset($input['service_time']) ? (int)$input['service_time'] : 0;

if ($queue_id <= 0 || $service_time <= 0) {
    sendJsonResponse(false, 'Invalid queue ID or service time', [], 400);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id FROM queue_entries WHERE id = ? AND status = 'completed'");
    $stmt->execute([$queue_id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        sendJsonResponse(false, 'Completed queue entry not found', [], 404);
    }

    $stmt = $conn->prepare("UPDATE queue_entries SET service_time = ? WHERE id = ?");
    $stmt->execute([$service_time, $queue_id]);

    logAudit($_SESSION['user_id'], 'updated_service_time', "Updated service time for queue #{$queue_id} to {$service_time} min");

    sendJsonResponse(true, 'Service time updated successfully', [
        'queue_id' => $queue_id,
        'service_time' => $service_time
    ]);

} catch (Exception $e) {
    error_log('Error in update_service_time.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to update service time', [], 500);
}
?>