<?php
// File: setup/seed_demo_users.php
// Script to insert demo users into the database

require_once '../config/config.php';

try {
    $conn = getDB();
    
    // Patient demo user
    $patientPassword = password_hash('Patient@123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("
        INSERT IGNORE INTO users (user_id, full_name, email, phone, password, role, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'PAT001',
        'John Doe',
        'patient@mediqueue.com',
        '0712345678',
        $patientPassword,
        'patient',
        1
    ]);
    
    // Staff demo user
    $staffPassword = password_hash('Staff@123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("
        INSERT IGNORE INTO users (user_id, full_name, email, phone, password, role, is_active, department_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'STAFF001',
        'Jane Smith',
        'staff@mediqueue.com',
        '0787654321',
        $staffPassword,
        'staff',
        1,
        1 // General Consultation department
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Demo users created successfully!',
        'data' => [
            'patient' => [
                'email' => 'patient@mediqueue.com',
                'password' => 'Patient@123'
            ],
            'staff' => [
                'email' => 'staff@mediqueue.com',
                'password' => 'Staff@123'
            ],
            'admin' => [
                'email' => 'admin@mediqueue.com',
                'password' => 'Admin@123'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error creating demo users: ' . $e->getMessage()
    ]);
}
?>
