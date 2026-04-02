<?php
require_once 'config/config.php';
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'patient';

$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare('SELECT q.*, d.name as department_name FROM queue_entries q JOIN departments d ON q.department_id = d.id WHERE q.patient_id = ? AND q.status IN ("waiting", "called", "serving") ORDER BY q.joined_at DESC LIMIT 1');
$stmt->execute([2]);
$queue = $stmt->fetch(PDO::FETCH_ASSOC);

echo 'Queue found: ' . ($queue ? 'YES' : 'NO') . PHP_EOL;
if ($queue) {
    echo 'Queue data: ' . json_encode($queue) . PHP_EOL;
}
?>