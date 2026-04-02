<?php
// File: staff/history.php
// Staff Service History

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("
    SELECT q.*, u.full_name as patient_name, u.phone as patient_phone, d.name as department_name
    FROM queue_entries q
    JOIN users u ON q.patient_id = u.id
    JOIN departments d ON q.department_id = d.id
    WHERE q.staff_id = ? AND q.status = 'completed'
    ORDER BY q.completed_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$_SESSION['user_id'], $limit, $offset]);
$history = $stmt->fetchAll();

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM queue_entries WHERE staff_id = ? AND status = 'completed'");
$count_stmt->execute([$_SESSION['user_id']]);
$total = $count_stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service History - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <nav class="gradient-primary text-white shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-md text-2xl"></i>
                    <span class="text-xl font-bold">Service History</span>
                </div>
                <div>
                    <a href="index.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left">Queue #</th>
                        <th class="px-6 py-3 text-left">Patient</th>
                        <th class="px-6 py-3 text-left">Department</th>
                        <th class="px-6 py-3 text-left">Completed At</th>
                        <th class="px-6 py-3 text-left">Service Time</th>
                        <th class="px-6 py-3 text-left">Wait Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($history as $entry): ?>
                    <tr>
                        <td class="px-6 py-4 font-bold text-teal-600"><?php echo $entry['queue_number']; ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($entry['patient_name']); ?></td>
                        <td class="px-6 py-4"><?php echo $entry['department_name']; ?></td>
                        <td class="px-6 py-4"><?php echo date('M d, Y h:i A', strtotime($entry['completed_at'])); ?></td>
                        <td class="px-6 py-4">
                            <?php 
                            $service_time = $entry['service_time'] ?? round((strtotime($entry['completed_at']) - strtotime($entry['serving_started_at'])) / 60);
                            echo htmlspecialchars($service_time) . ' min';
                            ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php 
                            $wait_time = $entry['wait_time'] ?? round((strtotime($entry['serving_started_at']) - strtotime($entry['joined_at'])) / 60);
                            echo htmlspecialchars($wait_time) . ' min';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        function adjustServiceTime(queueId, currentTime) {
            const newTime = prompt('Enter actual service time in minutes:', currentTime);
            if (!newTime || isNaN(newTime) || Number(newTime) <= 0) {
                alert('Please enter a valid number of minutes.');
                return;
            }

            fetch('../api/staff/update_service_time.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    queue_id: queueId,
                    service_time: Math.round(Number(newTime)),
                    csrf_token: window.csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Service time updated. Refreshing list...');
                    window.location.reload();
                } else {
                    alert('Update failed: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Error updating service time:', err);
                alert('An error occurred while updating service time.');
            });
        }
    </script>
</body>
</html>