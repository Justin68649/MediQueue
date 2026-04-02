<?php
// File: api/auth/register.php
// User Registration API

require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendJsonResponse(false, 'Invalid JSON input');
}

// Validate input
$required = ['full_name', 'email', 'phone', 'password', 'role'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        sendJsonResponse(false, "Field '{$field}' is required");
    }
}

// Sanitize and normalize inputs
$data['full_name'] = trim($data['full_name']);
$data['email'] = strtolower(trim($data['email']));
$data['password'] = trim($data['password']);
$data['phone'] = preg_replace('/\D/', '', $data['phone']);

// Handle optional patient fields
$data['date_of_birth'] = !empty($data['dob']) ? trim($data['dob']) : null;
$data['gender'] = !empty($data['gender']) ? trim($data['gender']) : null;
$data['blood_group'] = !empty($data['blood_group']) ? trim($data['blood_group']) : null;
$data['address'] = !empty($data['address']) ? trim($data['address']) : null;

// Validate email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Invalid email address');
}

// Validate phone - accept 10-15 digits
if (!preg_match('/^[0-9]{10,15}$/', $data['phone'])) {
    sendJsonResponse(false, 'Invalid phone number (requires 10-15 digits)');
}

// Validate password strength
if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
    sendJsonResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
}

// Validate role
$validRoles = ['patient', 'staff', 'admin'];
if (!in_array($data['role'], $validRoles)) {
    sendJsonResponse(false, 'Invalid role specified');
}

// Validate optional patient fields
if ($data['date_of_birth'] && !strtotime($data['date_of_birth'])) {
    sendJsonResponse(false, 'Invalid date of birth');
}

$validGenders = ['male', 'female', 'other'];
if ($data['gender'] && !in_array($data['gender'], $validGenders)) {
    sendJsonResponse(false, 'Invalid gender specified');
}

$validBloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
if ($data['blood_group'] && !in_array($data['blood_group'], $validBloodGroups)) {
    sendJsonResponse(false, 'Invalid blood group specified');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    sendJsonResponse(false, 'Database connection failed');
}

// Check if user exists (case-insensitive email)
$stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR phone = ?");
$stmt->execute([$data['email'], $data['phone']]);
if ($stmt->fetch()) {
    sendJsonResponse(false, 'User with this email or phone already exists');
}

// Generate unique user ID
$user_id = strtoupper(substr($data['role'], 0, 1)) . date('YmdHis') . rand(10, 99);

// Hash password
$hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("
    INSERT INTO users (user_id, full_name, email, phone, password, role, department_id, date_of_birth, gender, blood_group, address) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$department_id = ($data['role'] === 'staff') ? ($data['department_id'] ?? null) : null;

try {
    $result = $stmt->execute([
        $user_id,
        $data['full_name'],
        $data['email'],
        $data['phone'],
        $hashed_password,
        $data['role'],
        $department_id,
        $data['date_of_birth'],
        $data['gender'],
        $data['blood_group'],
        $data['address']
    ]);
} catch (Exception $e) {
    error_log('Registration insert error: ' . $e->getMessage());
    sendJsonResponse(false, 'Registration failed: ' . $e->getMessage());
}

if ($result) {
    // Send welcome email (non-blocking)
    try {
        $subject = "Welcome to " . APP_NAME;
        $body = "<h2>Welcome " . htmlspecialchars($data['full_name']) . "!</h2>
                 <p>Your account has been created successfully.</p>
                 <p><strong>User ID:</strong> " . htmlspecialchars($user_id) . "</p>
                 <p><strong>Role:</strong> " . htmlspecialchars(ucfirst($data['role'])) . "</p>
                 <p>You can now login to your dashboard.</p>";
        
        sendEmail($data['email'], $subject, $body);
    } catch (Exception $e) {
        error_log('Welcome email failed (non-blocking): ' . $e->getMessage());
    }
    
    // Log audit
    try {
        logAudit(null, 'user_registered', "New user registered: {$user_id}");
    } catch (Exception $e) {
        error_log('Audit logging failed: ' . $e->getMessage());
    }
    
    sendJsonResponse(true, 'Registration successful', ['user_id' => $user_id]);
} else {
    sendJsonResponse(false, 'Registration failed. Please try again.');
}