<?php
// File: api/auth/register.php
// User Registration API

require_once '../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate input
$required = ['full_name', 'email', 'phone', 'password', 'role'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        sendJsonResponse(false, "Field '{$field}' is required");
    }
}

// Validate email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Invalid email address');
}

// Validate phone
if (!preg_match('/^[0-9]{10,12}$/', $data['phone'])) {
    sendJsonResponse(false, 'Invalid phone number');
}

// Validate password strength
if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
    sendJsonResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$stmt->execute([$data['email'], $data['phone']]);
if ($stmt->fetch()) {
    sendJsonResponse(false, 'User with this email or phone already exists');
}

// Generate unique user ID
$user_id = strtoupper(substr($data['role'], 0, 1)) . date('Ymd') . rand(100, 999);

// Hash password
$hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("
    INSERT INTO users (user_id, full_name, email, phone, password, role, department_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$department_id = ($data['role'] === 'staff') ? ($data['department_id'] ?? null) : null;

$result = $stmt->execute([
    $user_id,
    $data['full_name'],
    $data['email'],
    $data['phone'],
    $hashed_password,
    $data['role'],
    $department_id
]);

if ($result) {
    // Send welcome email
    $subject = "Welcome to " . APP_NAME;
    $body = "<h2>Welcome " . $data['full_name'] . "!</h2>
             <p>Your account has been created successfully.</p>
             <p><strong>User ID:</strong> " . $user_id . "</p>
             <p><strong>Role:</strong> " . ucfirst($data['role']) . "</p>
             <p>You can now login to your dashboard.</p>";
    
    sendEmail($data['email'], $subject, $body);
    
    // Log audit
    logAudit(null, 'user_registered', "New user registered: {$user_id}");
    
    sendJsonResponse(true, 'Registration successful', ['user_id' => $user_id]);
} else {
    sendJsonResponse(false, 'Registration failed');
}