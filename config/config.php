<?php
// File: config/config.php
// Main Configuration File - Central entry point for all application settings

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';

// Initialize database connection
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die("Database initialization failed: " . $e->getMessage());
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

// CORS Headers for API requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . APP_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 3600');
    exit();
}

// Set default headers based on request type
$isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
if ($isApiRequest) {
    header('Content-Type: application/json; charset=utf-8');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Response helper functions
function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

function sendJsonResponse($success, $message = '', $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'status' => $success ? 'success' : 'error',
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

function sendError($message, $statusCode = 400) {
    sendResponse(false, $message, null, $statusCode);
}

function sendSuccess($message, $data = null, $statusCode = 200) {
    sendResponse(true, $message, $data, $statusCode);
}

// User authentication helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getDepartmentId() {
    return $_SESSION['department_id'] ?? null;
}

function checkAuth($requiredRole = null) {
    if (!isLoggedIn()) {
        sendError('Unauthorized: Please login first', 401);
    }
    
    if ($requiredRole) {
        $userRole = getUserRole();
        if (is_array($requiredRole)) {
            if (!in_array($userRole, $requiredRole)) {
                sendError('Forbidden: Insufficient permissions', 403);
            }
        } else {
            if ($userRole !== $requiredRole) {
                sendError('Forbidden: Insufficient permissions', 403);
            }
        }
    }
}

function validateApiRequest($requiredRole = null) {
    // Check authentication
    checkAuth($requiredRole);
    
    // Validate CSRF token for POST/PUT/DELETE requests
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$csrfToken || !validateCSRFToken($csrfToken)) {
            sendError('CSRF token validation failed', 403);
        }
    }
}

// Input validation helpers
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,}$/', str_replace(['-', ' ', '+'], '', $phone));
}

// Logging function
function logActivity($userId, $action, $details = null) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ];
    
    error_log(json_encode($log));
}

// Redirect function
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

// Authentication check - Require login
function requireLogin() {
    if (!isLoggedIn()) {
        // Determine redirect based on current URL path
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentPath, '/admin/') !== false) {
            redirect(APP_URL . '/admin/login.php');
        } elseif (strpos($currentPath, '/staff/') !== false) {
            redirect(APP_URL . '/staff/login.php');
        } else {
            redirect(APP_URL . '/patient/login.php');
        }
    }
}

// Authorization check - Require specific role
function requireRole($role) {
    // Detect if it's a JSON API request
    $isJsonRequest = (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) || 
                     ($_SERVER['REQUEST_METHOD'] !== 'GET' && !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    
    if (!isLoggedIn()) {
        if ($isJsonRequest || strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            sendError('Unauthorized: Please login first', 401);
        } else {
            redirect(APP_URL . '/patient/login.php');
        }
    }
    
    if (getUserRole() !== $role) {
        if ($isJsonRequest || strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            sendError('Unauthorized: You do not have permission to access this resource', 403);
        } else {
            sendError('Unauthorized: You do not have permission to access this resource', 403);
        }
    }
}

// CSRF Token generation and validation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? generateCSRFToken();
}

// Queue helpers
function generateQueueNumber($departmentId) {
    // Department prefix
    try {
        $conn = getDB();
        $stmt = $conn->prepare('SELECT prefix FROM departments WHERE id = ? LIMIT 1');
        $stmt->execute([$departmentId]);
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        $prefix = $dept['prefix'] ?? 'GEN';

        // count existing entries for today
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM queue_entries WHERE department_id = ? AND DATE(joined_at) = CURDATE()");
        $stmt->execute([$departmentId]);
        $count = (int)$stmt->fetchColumn();

        return strtoupper($prefix) . date('His') . mt_rand(10, 99);
    } catch (Exception $e) {
        error_log('Queue number generation error: ' . $e->getMessage());
        return strtoupper($prefix ?? 'GEN') . mt_rand(100,999);
    }
}

function calculateWaitTime($position, $departmentId) {
    try {
        $conn = getDB();
        $stmt = $conn->prepare('SELECT avg_service_time FROM departments WHERE id = ? LIMIT 1');
        $stmt->execute([$departmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgMinute = $row['avg_service_time'] ?? DEFAULT_WAIT_TIME;
        return max(1, (int)$position * (int)$avgMinute);
    } catch (Exception $e) {
        error_log('Wait time calc error: ' . $e->getMessage());
        return (int)$position * DEFAULT_WAIT_TIME;
    }
}

// Audit logging
function logAudit($userId, $action, $description = '', $ipAddress = null) {
    try {
        $conn = getDB();
        $ipAddress = $ipAddress ?? $_SERVER['REMOTE_ADDR'];
        
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $action, $description, $ipAddress]);
    } catch (Exception $e) {
        error_log("Audit logging error: " . $e->getMessage());
    }
}

// System Settings Helper
function getSystemSetting($key, $default = null) {
    try {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        error_log("System setting error: " . $e->getMessage());
        return $default;
    }
}

// Notification Helper
function sendNotification($userId, $title, $message, $type = 'info', $queueEntryId = null) {
    try {
        $conn = getDB();
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, queue_entry_id, type, title, message, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $queueEntryId, $type, $title, $message]);
        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// Email Helper
function sendEmail($to, $subject, $body) {
    try {
        if (getSystemSetting('email_enabled') !== 'true') {
            return false;
        }
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . APP_NAME . " <noreply@mediqueue.local>\r\n";
        
        return mail($to, $subject, $body, $headers);
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

// SMS Helper
function sendSMS($phone, $message) {
    try {
        if (getSystemSetting('sms_enabled') !== 'true') {
            return false;
        }
        
        // Integration point for SMS gateway (Twilio, AWS SNS, etc.)
        // For now, log the SMS
        error_log("SMS to $phone: $message");
        return true;
    } catch (Exception $e) {
        error_log("SMS error: " . $e->getMessage());
        return false;
    }
}

?>


