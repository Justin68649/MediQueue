<?php
require_once 'config/config.php';
try {
    $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
    $conn = getDB();
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
    $stmt->execute([$hash, 'admin@mediqueue.com']);
    echo "Updated admin password to: $hash\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>