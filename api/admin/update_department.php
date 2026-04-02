<?php
// File: api/admin/update_department.php
// Update Department API

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
        sendJsonResponse(false, 'Department ID is required');
    }
    
    $dept_id = (int)$input['id'];
    
    // Build update query
    $update_fields = [];
    $params = [];
    
    if (isset($input['name'])) {
        $update_fields[] = "name = ?";
        $params[] = $input['name'];
    }
    
    if (isset($input['description'])) {
        $update_fields[] = "description = ?";
        $params[] = $input['description'];
    }
    
    if (isset($input['prefix'])) {
        // Check if new prefix conflicts
        $stmt = $conn->prepare("SELECT id FROM departments WHERE prefix = ? AND id != ?");
        $stmt->execute([$input['prefix'], $dept_id]);
        if ($stmt->fetch()) {
            sendJsonResponse(false, 'Prefix already exists. Please use a different prefix.');
        }
        $update_fields[] = "prefix = ?";
        $params[] = strtoupper($input['prefix']);
    }
    
    if (isset($input['color'])) {
        $update_fields[] = "color = ?";
        $params[] = $input['color'];
    }
    
    if (isset($input['avg_service_time'])) {
        $update_fields[] = "avg_service_time = ?";
        $params[] = (int)$input['avg_service_time'];
    }
    
    if (isset($input['is_active'])) {
        $update_fields[] = "is_active = ?";
        $params[] = (int)$input['is_active'];
    }
    
    if (empty($update_fields)) {
        sendJsonResponse(false, 'No fields to update');
    }
    
    $params[] = $dept_id;
    $sql = "UPDATE departments SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Log audit
        logAudit($_SESSION['user_id'], 'updated_department', "Updated department ID: {$dept_id}");
        
        sendJsonResponse(true, 'Department updated successfully');
    } else {
        sendJsonResponse(false, 'Failed to update department');
    }
    
} catch (Exception $e) {
    error_log("Error in update_department.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to update department: ' . $e->getMessage(), [], 500);
}
?>