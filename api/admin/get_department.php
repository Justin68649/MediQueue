<?php
// File: api/admin/get_department.php
// Get Single Department Details API

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Unauthorized. Admin access required.', [], 401);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $dept_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($dept_id <= 0) {
        sendJsonResponse(false, 'Valid department ID is required');
    }
    
    $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$dept_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        sendJsonResponse(false, 'Department not found');
    }
    
    sendJsonResponse(true, 'Department retrieved successfully', ['department' => $department]);
    
} catch (Exception $e) {
    error_log("Error in get_department.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve department: ' . $e->getMessage(), [], 500);
}
?>