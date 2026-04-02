<?php
// File: api/public/get_departments.php
// Return active departments for patient queue selection

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = getDB();
    $stmt = $conn->query("SELECT id, name, prefix, color, avg_service_time FROM departments WHERE is_active = 1 ORDER BY name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not load departments',
        'error' => $e->getMessage()
    ]);
    exit;
}
