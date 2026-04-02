<?php
// File: api/auth/login.php
// User Login API

require_once '../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['email']) || empty($data['password'])) {
    sendJsonResponse(false, 'Email and password are required');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get user
$stmt = $conn->prepare("
    SELECT id, user_id, full_name, email, phone, password, role, is_active, department_id 
    FROM users 
    WHERE email = ? OR user_id = ?
");
$stmt->execute([$data['email'], $data['email']]);
$user = $stmt->fetch();

if (!$user || !password_verify($data['password'], $user['password'])) {
    // Log failed attempt
    logAudit(null, 'login_failed', "Failed login attempt for: {$data['email']}");
    sendJsonResponse(false, 'Invalid credentials');
}

if (!$user['is_active']) {
    sendJsonResponse(false, 'Account is deactivated. Contact administrator.');
}

// Update last login
$stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->execute([$user['id']]);

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['role'] = $user['role'];
$_SESSION['department_id'] = $user['department_id'];

// Generate CSRF token
generateCSRFToken();

// Log successful login
logAudit($user['id'], 'login_success', "User logged in");

// Redirect based on role
$redirect = match($user['role']) {
    'admin' => APP_URL . '/admin/',
    'staff' => APP_URL . '/staff/',
    'patient' => APP_URL . '/patient/',
    default => APP_URL . '/'
};

sendJsonResponse(true, 'Login successful', [
    'user' => [
        'id' => $user['user_id'],
        'name' => $user['full_name'],
        'role' => $user['role']
    ],
    'redirect' => $redirect
]);