<?php
// File: admin/index.php
// Admin Dashboard - Complete System Overview

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get overall system statistics
$stmt = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'patient') as total_patients,
        (SELECT COUNT(*) FROM users WHERE role = 'staff') as total_staff,
        (SELECT COUNT(*) FROM departments WHERE is_active = 1) as total_departments,
        (SELECT COUNT(*) FROM queue_entries WHERE DATE(joined_at) = CURDATE()) as today_visits,
        (SELECT COUNT(*) FROM queue_entries WHERE status = 'waiting' AND DATE(joined_at) = CURDATE()) as waiting_now,
        (SELECT COUNT(*) FROM queue_entries WHERE status = 'serving') as serving_now,
        (SELECT COUNT(*) FROM queue_entries WHERE DATE(completed_at) = CURDATE()) as completed_today
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly trends (last 6 months)
$stmt = $conn->query("
    SELECT 
        DATE_FORMAT(joined_at, '%M') as month,
        COUNT(*) as total,
        AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_wait
    FROM queue_entries
    WHERE joined_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND status = 'completed'
    GROUP BY DATE_FORMAT(joined_at, '%Y-%m'), DATE_FORMAT(joined_at, '%M')
    ORDER BY joined_at ASC
");
$monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department performance
$stmt = $conn->query("
    SELECT 
        d.name,
        d.prefix,
        d.color,
        COUNT(q.id) as total_visits,
        AVG(TIMESTAMPDIFF(MINUTE, q.joined_at, q.completed_at)) as avg_wait_time,
        COUNT(CASE WHEN q.status = 'waiting' THEN 1 END) as waiting
    FROM departments d
    LEFT JOIN queue_entries q ON d.id = q.department_id AND DATE(q.joined_at) = CURDATE()
    WHERE d.is_active = 1
    GROUP BY d.id
");
$department_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities
$stmt = $conn->query("
    SELECT 
        a.*,
        u.full_name as user_name,
        u.role as user_role
    FROM audit_logs a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 10
");
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get satisfaction score
$stmt = $conn->query("
    SELECT 
        AVG(rating) as avg_rating,
        COUNT(*) as total_feedback
    FROM feedback
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$satisfaction = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .department-card {
            transition: all 0.2s ease;
        }
        .department-card:hover {
            transform: translateX(5px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-shield text-2xl"></i>
                    <span class="text-xl font-bold">Admin Dashboard</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span><i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="users.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-users mr-1"></i>Users
                    </a>
                    <a href="departments.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-building mr-1"></i>Departments
                    </a>
                    <a href="settings.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-cog mr-1"></i>Settings
                    </a>
                    <a href="reports.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-chart-line mr-1"></i>Reports
                    </a>
                    <!-- Notification Icon -->
                    <div class="relative">
                        <button id="notificationBtn" class="relative hover:text-teal-200 transition focus:outline-none">
                            <i class="fas fa-bell text-xl"></i>
                            <span id="notificationBadge" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
                        </button>
                        <!-- Notification Dropdown -->
                        <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-2xl text-gray-800 hidden z-50 max-h-96 overflow-y-auto">
                            <div class="bg-gradient-primary text-white px-4 py-3 font-semibold flex justify-between items-center">
                                <span>Notifications</span>
                                <button id="markAllRead" class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded hover:bg-opacity-30 transition">Mark all read</button>
                            </div>
                            <div id="notificationList" class="divide-y">
                                <!-- Notifications will be loaded here -->
                            </div>
                            <div class="text-center py-4 text-gray-500 hidden" id="noNotifications">
                                <i class="fas fa-inbox text-2xl mb-2"></i>
                                <p>No notifications</p>
                            </div>
                        </div>
                    </div>
                    <a href="../api/auth/logout.php" class="bg-red-500 px-4 py-2 rounded-lg hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            <p class="text-gray-600 mt-1">Here's what's happening with your queue system today.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Patients</p>
                        <p class="text-3xl font-bold text-teal-600"><?php echo number_format($stats['total_patients']); ?></p>
                        <p class="text-xs text-green-600 mt-1">
                            <i class="fas fa-arrow-up"></i> +12% this month
                        </p>
                    </div>
                    <div class="bg-teal-100 rounded-full p-3">
                        <i class="fas fa-users text-teal-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Staff Members</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo number_format($stats['total_staff']); ?></p>
                        <p class="text-xs text-green-600 mt-1">
                            <i class="fas fa-arrow-up"></i> +5 this month
                        </p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Today's Visits</p>
                        <p class="text-3xl font-bold text-orange-600"><?php echo number_format($stats['today_visits']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $stats['waiting_now']; ?> waiting, <?php echo $stats['serving_now']; ?> serving
                        </p>
                    </div>
                    <div class="bg-orange-100 rounded-full p-3">
                        <i class="fas fa-calendar-check text-orange-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Satisfaction Score</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo round($satisfaction['avg_rating'] ?? 0, 1); ?> / 5</p>
                        <p class="text-xs text-gray-500 mt-1">
                            Based on <?php echo $satisfaction['total_feedback'] ?? 0; ?> reviews
                        </p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-star text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid lg:grid-cols-2 gap-8 mb-8">
            <!-- Monthly Trends Chart -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-line text-teal-600 mr-2"></i>Monthly Trends
                </h2>
                <canvas id="trendsChart" height="250"></canvas>
            </div>
            
            <!-- Department Performance Chart -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar text-teal-600 mr-2"></i>Department Performance
                </h2>
                <canvas id="departmentChart" height="250"></canvas>
            </div>
        </div>

        <!-- Department Queue Status -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
            <div class="gradient-primary px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-building mr-2"></i>Department Queue Status
                </h2>
            </div>
            <div class="divide-y divide-gray-200">
                <?php foreach ($department_performance as $dept): ?>
                    <div class="department-card p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <div class="w-3 h-3 rounded-full" style="background: <?php echo $dept['color']; ?>"></div>
                                <div>
                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($dept['name']); ?></span>
                                    <span class="text-sm text-gray-500 ml-2">(<?php echo $dept['prefix']; ?>)</span>
                                </div>
                            </div>
                            <div class="flex space-x-6">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-orange-600"><?php echo $dept['waiting']; ?></p>
                                    <p class="text-xs text-gray-500">Waiting</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-teal-600"><?php echo number_format($dept['total_visits']); ?></p>
                                    <p class="text-xs text-gray-500">Total Visits</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-purple-600"><?php echo round($dept['avg_wait_time'] ?? 0); ?> min</p>
                                    <p class="text-xs text-gray-500">Avg Wait</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-history text-teal-600 mr-2"></i>Recent Activities
                </h2>
            </div>
            <div class="divide-y divide-gray-200">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="p-4 flex items-center justify-between hover:bg-gray-50">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-user text-gray-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($activity['user_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $activity['action']; ?></p>
                                    <?php if ($activity['details']): ?>
                                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($activity['details']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500"><?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?></p>
                                <p class="text-xs text-gray-400"><?php echo $activity['ip_address']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2 block"></i>
                        No recent activities
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Monthly Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_trends); ?>;
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [{
                    label: 'Total Visits',
                    data: monthlyData.map(d => d.total),
                    borderColor: '#0D9488',
                    backgroundColor: 'rgba(13, 148, 136, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Avg Wait Time (min)',
                    data: monthlyData.map(d => Math.round(d.avg_wait || 0)),
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Department Performance Chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        const deptData = <?php echo json_encode($department_performance); ?>;
        
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: deptData.map(d => d.name),
                datasets: [{
                    label: 'Total Visits (Today)',
                    data: deptData.map(d => d.total_visits),
                    backgroundColor: deptData.map(d => d.color + '80'),
                    borderColor: deptData.map(d => d.color),
                    borderWidth: 2
                }, {
                    label: 'Waiting Now',
                    data: deptData.map(d => d.waiting),
                    backgroundColor: '#F59E0B80',
                    borderColor: '#F59E0B',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // ============ NOTIFICATION SYSTEM ============
        async function loadNotifications() {
            try {
                const response = await fetch('../api/notifications/get_notifications.php?limit=10', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                const notificationList = document.getElementById('notificationList');
                const badge = document.getElementById('notificationBadge');
                const noNotifications = document.getElementById('noNotifications');

                if (data.success && Array.isArray(data.notifications) && data.notifications.length > 0) {
                    notificationList.innerHTML = '';
                    noNotifications.classList.add('hidden');

                    data.notifications.forEach(notif => {
                        const notifEl = document.createElement('div');
                        notifEl.className = 'p-3 hover:bg-gray-100 cursor-pointer transition border-l-4 border-teal-500 ' + (notif.is_read ? 'bg-gray-50' : 'bg-teal-50');
                        notifEl.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-semibold text-sm">${notif.title}</p>
                                    <p class="text-xs text-gray-600 mt-1">${notif.message}</p>
                                    <p class="text-xs text-gray-400 mt-2">${new Date(notif.created_at).toLocaleString()}</p>
                                </div>
                                <button type="button" class="text-red-500 hover:text-red-700 ml-2">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `;
                        notifEl.onclick = () => markNotificationRead(notif.id);

                        const deleteBtn = notifEl.querySelector('button');
                        if (deleteBtn) {
                            deleteBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                deleteNotification(notif.id);
                            });
                        }

                        notificationList.appendChild(notifEl);
                    });

                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                } else {
                    notificationList.innerHTML = '';
                    noNotifications.classList.remove('hidden');
                    badge.classList.add('hidden');
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }
        
        async function markNotificationRead(notificationId) {
            try {
                await fetch('../api/notifications/mark_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId })
                });
                await loadNotifications();
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }
        
        async function deleteNotification(notificationId) {
            try {
                await fetch('../api/notifications/delete_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId })
                });
                await loadNotifications();
            } catch (error) {
                console.error('Error deleting notification:', error);
            }
        }
        
        // Mark all notifications as read
        async function markAllRead() {
            try {
                await fetch('../api/notifications/mark_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mark_all: true })
                });
                await loadNotifications();
            } catch (error) {
                console.error('Error marking all notifications as read:', error);
            }
        }
        
        // Load notifications on page load and refresh every 30 seconds
        async function initNotifications() {
            await loadNotifications();
            setInterval(loadNotifications, 30000);
            
            // Add event listener for mark all read button
            const markAllBtn = document.getElementById('markAllRead');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', markAllRead);
            }

            // Handle notification dropdown toggle
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');

            if (notificationBtn && notificationDropdown) {
                notificationBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.add('hidden');
                    }
                });
            }
        }

        initNotifications();
    </script>
</body>
</html>