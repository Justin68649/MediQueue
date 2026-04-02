<?php
// File: api/admin/update_user.php
// Update User API (Admin only)

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Unauthorized. Admin access required.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        sendJsonResponse(false, 'User ID is required');
    }
    
    $user_id = (int)$input['id'];
    
    // Don't allow disabling own account
    if ($user_id == $_SESSION['user_id'] && isset($input['is_active']) && $input['is_active'] == 0) {
        sendJsonResponse(false, 'You cannot deactivate your own account');
    }
    
    // Build update query dynamically
    $update_fields = [];
    $params = [];
    
    $allowed_fields = ['full_name', 'email', 'phone', 'role', 'department_id', 'is_active'];
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    // Handle password update
    if (!empty($input['password'])) {
        if (strlen($input['password']) < 8) {
            sendJsonResponse(false, 'Password must be at least 8 characters');
        }
        $update_fields[] = "password = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($update_fields)) {
        sendJsonResponse(false, 'No fields to update');
    }
    
    $params[] = $user_id;
    $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Log audit
        logAudit($_SESSION['user_id'], 'updated_user', "Updated user ID: {$user_id}");
        
        sendJsonResponse(true, 'User updated successfully');
    } else {
        sendJsonResponse(false, 'Failed to update user');
    }
    
} catch (Exception $e) {
    error_log("Error in update_user.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to update user: ' . $e->getMessage(), [], 500);
}
?>