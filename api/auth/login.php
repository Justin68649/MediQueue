<?php
// File: api/auth/login.php
// User Login API

require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    sendJsonResponse(false, 'Invalid JSON input');
}

if (empty($data['email']) || empty($data['password'])) {
    sendJsonResponse(false, 'Email and password are required');
}

// Sanitize inputs
$data['email'] = strtolower(trim($data['email']));
$data['password'] = trim($data['password']);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    sendJsonResponse(false, 'Database connection failed');
}

// Get user
try {
    $stmt = $conn->prepare("
        SELECT id, user_id, full_name, email, phone, password, role, is_active, department_id 
        FROM users 
        WHERE LOWER(email) = LOWER(?) OR user_id = ?
    ");
    $stmt->execute([$data['email'], $data['email']]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log('Login query error: ' . $e->getMessage());
    sendJsonResponse(false, 'Login failed. Please try again.');
}

if (!$user || !password_verify($data['password'], $user['password'])) {
    // Log failed attempt
    try {
        logAudit(null, 'login_failed', "Failed login attempt for: {$data['email']}");
    } catch (Exception $e) {
        error_log('Audit logging failed: ' . $e->getMessage());
    }
    sendJsonResponse(false, 'Invalid credentials');
}

if (!$user['is_active']) {
    sendJsonResponse(false, 'Account is deactivated. Contact administrator.');
}

// Update last login
try {
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
} catch (Exception $e) {
    error_log('Last login update failed: ' . $e->getMessage());
}

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