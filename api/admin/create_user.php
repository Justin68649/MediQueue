<?php
// File: api/admin/create_user.php
// Create New User API (Admin only)

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
    $required = ['full_name', 'email', 'phone', 'role'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendJsonResponse(false, "Field '{$field}' is required");
        }
    }
    
    // Validate email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid email address');
    }
    
    // Validate phone
    if (!preg_match('/^[0-9]{10,12}$/', $input['phone'])) {
        sendJsonResponse(false, 'Invalid phone number');
    }
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$input['email'], $input['phone']]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'User with this email or phone already exists');
    }
    
    // Generate unique user ID
    $role_prefix = substr($input['role'], 0, 1);
    $user_id = strtoupper($role_prefix) . date('Ymd') . rand(100, 999);
    
    // Set password (default or provided)
    $password = !empty($input['password']) ? $input['password'] : 'Password@123';
    if (strlen($password) < 8) {
        sendJsonResponse(false, 'Password must be at least 8 characters');
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (user_id, full_name, email, phone, password, role, department_id, is_active, date_of_birth, gender, blood_group, address) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $department_id = ($input['role'] === 'staff') ? ($input['department_id'] ?? null) : null;
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
    
    $result = $stmt->execute([
        $user_id,
        $input['full_name'],
        $input['email'],
        $input['phone'],
        $hashed_password,
        $input['role'],
        $department_id,
        $is_active,
        $input['date_of_birth'] ?? null,
        $input['gender'] ?? null,
        $input['blood_group'] ?? null,
        $input['address'] ?? null
    ]);
    
    if ($result) {
        $new_user_id = $conn->lastInsertId();
        
        // Send welcome email
        $subject = "Welcome to " . APP_NAME;
        $body = "
            <h2>Welcome to " . APP_NAME . "!</h2>
            <p>Dear {$input['full_name']},</p>
            <p>Your account has been created by the administrator.</p>
            <p><strong>Login Credentials:</strong></p>
            <ul>
                <li><strong>User ID:</strong> {$user_id}</li>
                <li><strong>Email:</strong> {$input['email']}</li>
                <li><strong>Password:</strong> {$password}</li>
            </ul>
            <p>Please change your password after first login.</p>
            <p><a href='" . APP_URL . "/'>Login Here</a></p>
        ";
        sendEmail($input['email'], $subject, $body);
        
        // Log audit
        logAudit($_SESSION['user_id'], 'created_user', "Created user: {$user_id} ({$input['role']})");
        
        sendJsonResponse(true, 'User created successfully', [
            'user_id' => $user_id,
            'password' => $password
        ]);
    } else {
        sendJsonResponse(false, 'Failed to create user');
    }
    
} catch (Exception $e) {
    error_log("Error in create_user.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to create user: ' . $e->getMessage(), [], 500);
}
?>