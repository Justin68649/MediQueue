<?php
// File: api/auth/change_password.php
// Change user password endpoint

require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

// API auth token/session check
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendJsonResponse(false, 'Invalid JSON input');
}

$current_password = trim($data['current_password'] ?? '');
$new_password = trim($data['new_password'] ?? '');
$confirm_password = trim($data['confirm_password'] ?? '');

if ($current_password === '' || $new_password === '' || $confirm_password === '') {
    sendJsonResponse(false, 'Current and new passwords are required');
}

if ($new_password !== $confirm_password) {
    sendJsonResponse(false, 'New password and confirmation do not match');
}

if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
    sendJsonResponse(false, 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long');
}

if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/\d/', $new_password)) {
    // optional: require mixed chars
    // but this may be too strict; keep if desired
    // sendJsonResponse(false, 'Password must include uppercase, lowercase letters, and numbers');
}

$userId = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJsonResponse(false, 'User not found', [], 404);
    }

    if (!password_verify($current_password, $user['password'])) {
        sendJsonResponse(false, 'Current password is incorrect');
    }

    if (password_verify($new_password, $user['password'])) {
        sendJsonResponse(false, 'New password must be different from current password');
    }

    $newHash = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
    $update->execute([$newHash, $userId]);

    // Audit log
    try {
        logAudit($userId, 'change_password', 'User changed password');
    } catch (Exception $e) {
        error_log('Audit logging failed in change_password: ' . $e->getMessage());
    }

    sendJsonResponse(true, 'Password changed successfully');

} catch (Exception $e) {
    error_log('change_password error: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to change password: ' . $e->getMessage());
}
