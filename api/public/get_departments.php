<?php
// File: api/public/get_departments.php
// Return active departments for patient queue selection

header('Content-Type: application/json; charset=utf-8');

try {
    // Load database config without requiring login
    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../../config/database.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $stmt = $conn->prepare("SELECT id, name, prefix, color, avg_service_time FROM departments WHERE is_active = 1 ORDER BY name ASC");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->errorInfo()[2]);
    }
    
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($departments === false) {
        throw new Exception('Failed to fetch departments');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'count' => count($departments)
    ]);
    exit;
    
} catch (Exception $e) {
    error_log('Department API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not load departments',
        'error' => $e->getMessage()
    ]);
    exit;
}
