<?php
// File: patient/queue_status.php
// Real-time Queue Status Page

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'patient') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get active queue if any
$stmt = $conn->prepare("
    SELECT q.*, d.name as department_name, d.color as department_color,
           (SELECT COUNT(*) FROM queue_entries 
            WHERE department_id = q.department_id 
            AND status = 'waiting' 
            AND joined_at < q.joined_at) as ahead_count
    FROM queue_entries q
    JOIN departments d ON q.department_id = d.id
    WHERE q.patient_id = ? AND q.status IN ('waiting', 'called', 'serving')
    ORDER BY q.joined_at DESC
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$active_queue = $stmt->fetch();

// Get department queues status
$dept_stmt = $conn->prepare("
    SELECT d.*,
           COUNT(CASE WHEN q.status = 'waiting' THEN 1 END) as waiting_count,
           COUNT(CASE WHEN q.status = 'serving' THEN 1 END) as serving_count,
           AVG(CASE WHEN q.status = 'waiting' THEN q.estimated_wait_time END) as avg_wait
    FROM departments d
    LEFT JOIN queue_entries q ON d.id = q.department_id AND DATE(q.joined_at) = CURDATE()
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY d.name
");
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Status - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="refresh" content="10">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .queue-card {
            transition: all 0.3s ease;
        }
        .queue-card:hover {
            transform: translateY(-5px);
        }
        .progress-bar {
            transition: width 0.5s ease;
        }
        .status-waiting { background: #FEF3C7; color: #92400E; }
        .status-called { background: #DBEAFE; color: #1E40AF; animation: pulse 2s infinite; }
        .status-serving { background: #D1FAE5; color: #065F46; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        
        .pulse-ring {
            animation: pulseRing 2s infinite;
        }
        
        @keyframes pulseRing {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-hospital-user text-2xl"></i>
                    <span class="text-xl font-bold"><?php echo APP_NAME; ?></span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="history.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-history mr-1"></i>History
                    </a>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../api/auth/logout.php" class="bg-red-500 px-4 py-2 rounded-lg hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        <!-- Auto-refresh indicator -->
        <div class="text-right text-sm text-gray-500 mb-4">
            <i class="fas fa-sync-alt fa-spin mr-1"></i>
            Auto-refreshes every 10 seconds
        </div>

        <!-- Active Queue Section -->
        <?php if ($active_queue): ?>
            <div class="mb-8">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden queue-card">
                    <div class="gradient-primary px-6 py-4">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-ticket-alt mr-2"></i>Your Current Queue
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid md:grid-cols-4 gap-6">
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Queue Number</p>
                                <p class="text-4xl font-bold text-teal-600 mt-2"><?php echo $active_queue['queue_number']; ?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Department</p>
                                <p class="text-xl font-semibold mt-2"><?php echo htmlspecialchars($active_queue['department_name']); ?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="mt-2">
                                    <span class="status-badge status-<?php echo $active_queue['status']; ?> px-4 py-2 rounded-full font-semibold">
                                        <?php echo strtoupper($active_queue['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500">People Ahead</p>
                                <p class="text-3xl font-bold <?php echo $active_queue['ahead_count'] > 0 ? 'text-orange-600' : 'text-green-600'; ?> mt-2">
                                    <?php echo $active_queue['ahead_count']; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($active_queue['status'] == 'waiting'): ?>
                            <div class="mt-6">
                                <div class="flex justify-between text-sm text-gray-600 mb-2">
                                    <span>Queue Progress</span>
                                    <span><?php echo $active_queue['position']; ?>/<?php echo $active_queue['position'] + $active_queue['ahead_count']; ?></span>
                                </div>
                                <div class="bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <?php 
                                    $total = $active_queue['position'] + $active_queue['ahead_count'];
                                    $progress = ($total > 0) ? (($total - $active_queue['ahead_count']) / $total) * 100 : 0;
                                    ?>
                                    <div class="bg-teal-500 h-full transition-all" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Departments Section -->
        <div class="mt-12">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">Other Departments</h3>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($departments as $dept): ?>
                    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition">
                        <h4 class="font-bold text-lg mb-2"><?php echo $dept['name']; ?></h4>
                        <p class="text-gray-600 text-sm mb-4"><?php echo $dept['description']; ?></p>
                        <a href="join_queue.php?dept=<?php echo $dept['id']; ?>" class="text-teal-600 hover:text-teal-700 font-semibold">
                            Join Queue <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 10 seconds
        setTimeout(() => {
            location.reload();
        }, 10000);
    </script>
</body>
</html>
