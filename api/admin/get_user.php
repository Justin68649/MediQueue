<?php
// File: api/admin/get_user.php
// Get Single User Details API

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Unauthorized. Admin access required.', [], 401);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($user_id <= 0) {
        sendJsonResponse(false, 'Valid user ID is required');
    }
    
    $stmt = $conn->prepare("
        SELECT u.*, d.name as department_name 
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendJsonResponse(false, 'User not found');
    }
    
    // Remove sensitive data
    unset($user['password']);
    
    sendJsonResponse(true, 'User retrieved successfully', ['user' => $user]);
    
} catch (Exception $e) {
    error_log("Error in get_user.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve user: ' . $e->getMessage(), [], 500);
}
?>