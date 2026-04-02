<?php
// File: api/auth/logout.php
// User Logout Handler

require_once '../../config/config.php';

// Log the logout action before clearing session
if (isLoggedIn()) {
    $userId = getUserId();
    $userRole = getUserRole();
    logAudit($userId, 'logout', "User logged out");
}

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to home page
header('Location: ' . APP_URL . '/');
exit();
?>
