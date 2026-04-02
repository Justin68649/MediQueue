<?php
// Debug script to check queue status
require_once 'config/config.php';

if (!isset($_GET['user_id'])) {
    die('Please provide user_id parameter');
}

$user_id = (int)$_GET['user_id'];

$db = Database::getInstance();
$conn = $db->getConnection();

echo "<h1>Debug Queue Status for User ID: $user_id</h1>";

// Check active queues
$stmt = $conn->prepare("SELECT id, status, queue_number, joined_at FROM queue_entries WHERE patient_id = ? AND status IN ('waiting', 'called', 'serving')");
$stmt->execute([$user_id]);
$active = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Active Queue Entries:</h2>";
if ($active) {
    echo "<pre>" . json_encode($active, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p>No active queue entries found</p>";
}

// Check all queues for this user
$stmt = $conn->prepare("SELECT id, status, queue_number, joined_at FROM queue_entries WHERE patient_id = ? ORDER BY joined_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>All Queue Entries (Last 10):</h2>";
if ($all) {
    echo "<pre>" . json_encode($all, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p>No queue entries found for this user</p>";
}

// Check users table
$stmt = $conn->prepare("SELECT id, user_id, full_name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>User Info:</h2>";
if ($user) {
    echo "<pre>" . json_encode($user, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p>User not found</p>";
}
?>