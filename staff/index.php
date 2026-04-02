<?php
// File: staff/index.php
// Staff Dashboard - Main Portal

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get staff details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();

// Get today's statistics for staff
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_served,
        COUNT(CASE WHEN status = 'serving' THEN 1 END) as currently_serving,
        AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_service_time
    FROM queue_entries 
    WHERE staff_id = ? AND DATE(completed_at) = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$today_stats = $stmt->fetch();

// Get department queue
$stmt = $conn->prepare("
    SELECT q.*, u.full_name as patient_name, u.phone as patient_phone
    FROM queue_entries q
    JOIN users u ON q.patient_id = u.id
    WHERE q.department_id = ? AND q.status IN ('waiting', 'called')
    ORDER BY 
        CASE q.priority 
            WHEN 'emergency' THEN 1
            WHEN 'urgent' THEN 2
            ELSE 3
        END,
        q.joined_at ASC
");
$stmt->execute([$staff['department_id']]);
$queue_list = $stmt->fetchAll();

// Get currently serving
$stmt = $conn->prepare("
    SELECT q.*, u.full_name as patient_name, u.phone as patient_phone
    FROM queue_entries q
    JOIN users u ON q.patient_id = u.id
    WHERE q.staff_id = ? AND q.status = 'serving'
");
$stmt->execute([$_SESSION['user_id']]);
$serving = $stmt->fetch();

// Get recent completed
$stmt = $conn->prepare("
    SELECT q.*, u.full_name as patient_name
    FROM queue_entries q
    JOIN users u ON q.patient_id = u.id
    WHERE q.staff_id = ? AND q.status = 'completed'
    ORDER BY q.completed_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_completed = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://js.pusher.com/7.0/pusher.min.js"></script>
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .status-waiting { background: #FEF3C7; color: #92400E; }
        .status-called { background: #DBEAFE; color: #1E40AF; animation: pulse 2s infinite; }
        .status-serving { background: #D1FAE5; color: #065F46; }
        .queue-card {
            transition: all 0.3s ease;
        }
        .queue-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .call-btn {
            transition: all 0.2s ease;
        }
        .call-btn:hover {
            transform: scale(1.05);
        }
    </style>
    <script>
        // Make CSRF token available globally for API calls
        window.csrfToken = <?php echo json_encode(getCSRFToken()); ?>;
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-md text-2xl"></i>
                    <span class="text-xl font-bold"><?php echo APP_NAME; ?> - Staff Portal</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span><i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="history.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-history mr-1"></i>History
                    </a>
                    <a href="profile.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-user-cog mr-1"></i>Profile
                    </a>
                    <a href="../api/auth/logout.php" class="bg-red-500 px-4 py-2 rounded-lg hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Waiting Queue</p>
                        <p class="text-3xl font-bold text-orange-600" id="waitingCount"><?php echo count(array_filter($queue_list, fn($q) => $q['status'] == 'waiting')); ?></p>
                    </div>
                    <div class="bg-orange-100 rounded-full p-3">
                        <i class="fas fa-users text-orange-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Today Served</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $today_stats['total_served'] ?? 0; ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Currently Serving</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $today_stats['currently_serving'] ?? 0; ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-user-check text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg Service Time</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo round($today_stats['avg_service_time'] ?? 0); ?> min</p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-clock text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Currently Serving -->
        <?php if ($serving): ?>
        <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-green-800">
                        <i class="fas fa-user-check mr-2"></i>Currently Serving
                    </h3>
                    <p class="text-2xl font-bold text-green-700 mt-2"><?php echo $serving['queue_number']; ?></p>
                    <p class="text-gray-700">Patient: <?php echo htmlspecialchars($serving['patient_name']); ?></p>
                    <p class="text-gray-600 text-sm">Started: <?php echo date('h:i A', strtotime($serving['serving_started_at'])); ?></p>
                </div>
                <button onclick="completeService(<?php echo $serving['id']; ?>)" 
                        class="bg-green-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-600 transition">
                    <i class="fas fa-check mr-2"></i>Complete Service
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Queue List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
            <div class="gradient-primary px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-list-ol mr-2"></i>Queue List - <?php echo htmlspecialchars($staff['department_name'] ?? 'My Department'); ?>
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Queue #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wait Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="queueTable" class="divide-y divide-gray-200">
                        <?php if (count($queue_list) > 0): ?>
                            <?php foreach ($queue_list as $queue): ?>
                                <tr class="hover:bg-gray-50 transition" id="queue-row-<?php echo $queue['id']; ?>">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-teal-600"><?php echo $queue['queue_number']; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-medium"><?php echo htmlspecialchars($queue['patient_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $queue['patient_phone']; ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $priorityClass = match($queue['priority']) {
                                            'emergency' => 'bg-red-100 text-red-800',
                                            'urgent' => 'bg-orange-100 text-orange-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $priorityClass; ?>">
                                            <?php echo strtoupper($queue['priority']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php echo date('h:i A', strtotime($queue['joined_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm" id="wait-time-<?php echo $queue['id']; ?>">
                                        <?php 
                                        $wait_minutes = round((time() - strtotime($queue['joined_at'])) / 60);
                                        echo $wait_minutes . ' min';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="status-<?php echo $queue['status']; ?> px-3 py-1 rounded-full text-xs font-semibold">
                                            <?php echo strtoupper($queue['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($queue['status'] == 'waiting'): ?>
                                            <button onclick="callPatient(<?php echo $queue['id']; ?>, '<?php echo $queue['queue_number']; ?>', '<?php echo htmlspecialchars($queue['patient_name']); ?>')" 
                                                    class="call-btn bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                                                <i class="fas fa-bullhorn mr-1"></i>Call
                                            </button>
                                        <?php elseif ($queue['status'] == 'called'): ?>
                                            <button onclick="startService(<?php echo $queue['id']; ?>)" 
                                                    class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                                                <i class="fas fa-play mr-1"></i>Start Service
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                    No patients in queue
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Completed -->
        <?php if (count($recent_completed) > 0): ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-history text-teal-600 mr-2"></i>Recently Completed
                </h2>
            </div>
            <div class="divide-y divide-gray-200">
                <?php foreach ($recent_completed as $completed): ?>
                    <div class="px-6 py-4 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-teal-600"><?php echo $completed['queue_number']; ?></span>
                            <span class="text-gray-600 ml-3"><?php echo htmlspecialchars($completed['patient_name']); ?></span>
                        </div>
                        <div class="text-sm text-gray-500">
                            Completed: <?php echo date('h:i A', strtotime($completed['completed_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Call Modal -->
    <div id="callModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="gradient-primary text-white px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold">Call Patient</h3>
            </div>
            <div class="p-6">
                <div class="text-center mb-4">
                    <i class="fas fa-bullhorn text-blue-500 text-5xl mb-3"></i>
                    <p class="text-gray-600">Calling patient:</p>
                    <p class="text-2xl font-bold text-teal-600" id="callQueueNumber"></p>
                    <p class="text-lg" id="callPatientName"></p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="confirmCall()" class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition">
                        <i class="fas fa-check mr-2"></i>Confirm Call
                    </button>
                    <button onclick="closeCallModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audio for notifications -->
    <audio id="callSound" preload="auto">
        <source src="https://assets.mixkit.co/sfx/preview/mixkit-announcement-bell-1291.mp3" type="audio/mpeg">
    </audio>

    <script>
        let pendingCallId = null;
        
        // Call patient
        function callPatient(queueId, queueNumber, patientName) {
            pendingCallId = queueId;
            document.getElementById('callQueueNumber').textContent = queueNumber;
            document.getElementById('callPatientName').textContent = patientName;
            document.getElementById('callModal').classList.remove('hidden');
            document.getElementById('callModal').classList.add('flex');
            
            // Play sound
            const audio = document.getElementById('callSound');
            audio.play().catch(e => console.log('Audio play failed:', e));
        }
        
        // Confirm call
        async function confirmCall() {
            if (!pendingCallId) return;
            
            try {
                const response = await fetch('../api/staff/call_patient.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ 
                        queue_id: pendingCallId,
                        csrf_token: window.csrfToken
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Patient called successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to call patient', 'error');
            } finally {
                closeCallModal();
            }
        }
        
        // Start service
        async function startService(queueId) {
            try {
                const response = await fetch('../api/staff/start_service.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ 
                        queue_id: queueId,
                        csrf_token: window.csrfToken
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Service started', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to start service', 'error');
            }
        }
        
        // Complete service
        async function completeService(queueId) {
            if (!confirm('Complete this service?')) return;
            
            try {
                const response = await fetch('../api/staff/complete_service.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ 
                        queue_id: queueId,
                        csrf_token: window.csrfToken
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Service completed!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to complete service', 'error');
            }
        }
        
        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-20 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            } text-white`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4">×</button>
                </div>
            `;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }
        
        function closeCallModal() {
            document.getElementById('callModal').classList.add('hidden');
            document.getElementById('callModal').classList.remove('flex');
            pendingCallId = null;
        }
        
        // Auto-refresh queue every 10 seconds
        setInterval(() => {
            location.reload();
        }, 10000);
        
        // Update wait times in real-time
        function updateWaitTimes() {
            const rows = document.querySelectorAll('[id^="wait-time-"]');
            rows.forEach(row => {
                const currentText = row.textContent;
                if (currentText && !currentText.includes('serving')) {
                    const minutes = parseInt(currentText);
                    if (!isNaN(minutes)) {
                        row.textContent = (minutes + 1) + ' min';
                    }
                }
            });
        }
        
        setInterval(updateWaitTimes, 60000); // Update every minute
    </script>
</body>
</html>