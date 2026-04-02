<?php
// File: api/admin/create_department.php
// Create New Department API

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
    
    // Validate required fields
    if (empty($input['name'])) {
        sendJsonResponse(false, 'Department name is required');
    }
    
    if (empty($input['prefix'])) {
        sendJsonResponse(false, 'Queue prefix is required');
    }
    
    // Check if prefix already exists
    $stmt = $conn->prepare("SELECT id FROM departments WHERE prefix = ?");
    $stmt->execute([$input['prefix']]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'Prefix already exists. Please use a different prefix.');
    }
    
    // Insert department
    $stmt = $conn->prepare("
        INSERT INTO departments (name, description, prefix, color, avg_service_time, is_active) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $input['name'],
        $input['description'] ?? null,
        strtoupper($input['prefix']),
        $input['color'] ?? '#0D9488',
        $input['avg_service_time'] ?? 15,
        isset($input['is_active']) ? (int)$input['is_active'] : 1
    ]);
    
    if ($result) {
        $department_id = $conn->lastInsertId();
        
        // Log audit
        logAudit($_SESSION['user_id'], 'created_department', "Created department: {$input['name']}");
        
        sendJsonResponse(true, 'Department created successfully', ['id' => $department_id]);
    } else {
        sendJsonResponse(false, 'Failed to create department');
    }
    
} catch (Exception $e) {
    error_log("Error in create_department.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to create department: ' . $e->getMessage(), [], 500);
}
?>