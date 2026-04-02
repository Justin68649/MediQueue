<?php
// File: admin/reports.php
// Reports & Analytics Dashboard

require_once '../../config/config.php';
header('Cache-Control: max-age=300, public'); // Cache for 5 minutes
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get date range for reports
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['type'] ?? 'daily';

// Generate report data based on type
switch ($report_type) {
    case 'daily':
        $group_by = "DATE(joined_at)";
        $date_format = "%Y-%m-%d";
        break;
    case 'weekly':
        $group_by = "YEARWEEK(joined_at)";
        $date_format = "%Y Week %v";
        break;
    case 'monthly':
        $group_by = "DATE_FORMAT(joined_at, '%Y-%m')";
        $date_format = "%M %Y";
        break;
    default:
        $group_by = "DATE(joined_at)";
        $date_format = "%Y-%m-%d";
}

// Get queue statistics - limit to last 30 days for performance
$limit_date = $date_from;
if (strtotime($date_to) - strtotime($date_from) > 86400 * 90) {
    // If range is more than 90 days, limit to 90 days
    $limit_date = date('Y-m-d', strtotime($date_to) - (86400 * 89));
}

$stmt = $conn->prepare("
    SELECT 
        DATE(joined_at) as date,
        COUNT(*) as total_visits,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_wait_time,
        AVG(TIMESTAMPDIFF(MINUTE, serving_started_at, completed_at)) as avg_service_time,
        COUNT(DISTINCT patient_id) as unique_patients
    FROM queue_entries
    WHERE DATE(joined_at) BETWEEN ? AND ?
    GROUP BY DATE(joined_at)
    ORDER BY date DESC
    LIMIT 100
");
$stmt->execute([$limit_date, $date_to]);
$queue_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department breakdown
$stmt = $conn->prepare("
    SELECT 
        d.name as department,
        d.color,
        COUNT(q.id) as total,
        SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed,
        AVG(TIMESTAMPDIFF(MINUTE, q.joined_at, q.completed_at)) as avg_wait,
        COUNT(DISTINCT q.patient_id) as patients
    FROM departments d
    LEFT JOIN queue_entries q ON d.id = q.department_id 
        AND DATE(q.joined_at) BETWEEN ? AND ?
    GROUP BY d.id
    ORDER BY total DESC
");
$stmt->execute([$date_from, $date_to]);
$department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff performance
$stmt = $conn->prepare("
    SELECT 
        u.full_name as staff_name,
        COUNT(q.id) as patients_served,
        AVG(TIMESTAMPDIFF(MINUTE, q.serving_started_at, q.completed_at)) as avg_service_time,
        AVG(f.rating) as avg_rating,
        COUNT(f.id) as feedback_count
    FROM users u
    LEFT JOIN queue_entries q ON u.id = q.staff_id 
        AND DATE(q.completed_at) BETWEEN ? AND ?
    LEFT JOIN feedback f ON q.id = f.queue_entry_id
    WHERE u.role = 'staff'
    GROUP BY u.id
    HAVING patients_served > 0
    ORDER BY patients_served DESC
");
$stmt->execute([$date_from, $date_to]);
$staff_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get peak hours
$stmt = $conn->prepare("
    SELECT 
        HOUR(joined_at) as hour,
        COUNT(*) as visits
    FROM queue_entries
    WHERE DATE(joined_at) BETWEEN ? AND ?
    GROUP BY HOUR(joined_at)
    ORDER BY hour ASC
");
$stmt->execute([$date_from, $date_to]);
$peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get satisfaction trends
$stmt = $conn->prepare("
    SELECT 
        DATE(f.created_at) as date,
        AVG(f.rating) as avg_rating,
        COUNT(f.id) as feedback_count,
        AVG(f.wait_time_rating) as avg_wait_rating,
        AVG(f.service_quality) as avg_service_quality
    FROM feedback f
    WHERE DATE(f.created_at) BETWEEN ? AND ?
    GROUP BY DATE(f.created_at)
    ORDER BY date DESC
");
$stmt->execute([$date_from, $date_to]);
$satisfaction_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary totals
$summary = [
    'total_visits' => array_sum(array_column($queue_stats, 'total_visits')),
    'completed' => array_sum(array_column($queue_stats, 'completed')),
    'cancelled' => array_sum(array_column($queue_stats, 'cancelled')),
    'avg_wait_time' => round(array_sum(array_column($queue_stats, 'avg_wait_time')) / max(1, count($queue_stats))),
    'avg_service_time' => round(array_sum(array_column($queue_stats, 'avg_service_time')) / max(1, count($queue_stats))),
    'unique_patients' => count(array_unique(array_column($queue_stats, 'unique_patients')))
];
$summary['completion_rate'] = $summary['total_visits'] > 0 
    ? round(($summary['completed'] / $summary['total_visits']) * 100, 1) 
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .report-card {
            transition: all 0.2s ease;
        }
        .report-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-chart-line text-2xl"></i>
                    <span class="text-xl font-bold">Reports & Analytics</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="users.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-users mr-1"></i>Users
                    </a>
                    <a href="../../api/auth/logout.php" class="bg-red-500 px-4 py-2 rounded-lg hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-chart-line text-teal-600 mr-3"></i>Reports & Analytics
            </h1>
            <p class="text-gray-600 mt-1">Comprehensive insights into your queue system</p>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                    <select name="type" class="px-4 py-2 border rounded-lg">
                        <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <button type="submit" class="gradient-primary text-white px-6 py-2 rounded-lg">
                        <i class="fas fa-sync-alt mr-2"></i>Generate Report
                    </button>
                </div>
                <div>
                    <button type="button" onclick="exportReport()" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="report-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Visits</p>
                        <p class="text-3xl font-bold text-teal-600"><?php echo number_format($summary['total_visits']); ?></p>
                    </div>
                    <div class="bg-teal-100 rounded-full p-3">
                        <i class="fas fa-calendar-check text-teal-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="report-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Completion Rate</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $summary['completion_rate']; ?>%</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="report-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg Wait Time</p>
                        <p class="text-3xl font-bold text-orange-600"><?php echo $summary['avg_wait_time']; ?> min</p>
                    </div>
                    <div class="bg-orange-100 rounded-full p-3">
                        <i class="fas fa-clock text-orange-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="report-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Unique Patients</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo number_format($summary['unique_patients']); ?></p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-users text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar text-teal-600 mr-2"></i>Daily Visits Trend
                </h2>
                <canvas id="visitsChart" height="250"></canvas>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-pie text-teal-600 mr-2"></i>Department Distribution
                </h2>
                <canvas id="departmentChart" height="250"></canvas>
            </div>
        </div>

        <!-- Peak Hours Chart -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-teal-600 mr-2"></i>Peak Hours Analysis
            </h2>
            <canvas id="peakHoursChart" height="200"></canvas>
        </div>

        <!-- Staff Performance Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
            <div class="gradient-primary px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-user-md mr-2"></i>Staff Performance
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patients Served</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Service Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Feedback</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($staff_performance as $staff): ?>
                            <tr>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                <td class="px-6 py-4"><?php echo $staff['patients_served']; ?></td>
                                <td class="px-6 py-4"><?php echo round($staff['avg_service_time']); ?> min</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <span class="mr-2"><?php echo round($staff['avg_rating'] ?? 0, 1); ?></span>
                                        <div class="text-yellow-500">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php echo $i <= round($staff['avg_rating'] ?? 0) ? '★' : '☆'; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                 </td>
                                <td class="px-6 py-4"><?php echo $staff['feedback_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Satisfaction Trends -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="gradient-primary px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-star mr-2"></i>Patient Satisfaction Trends
                </h2>
            </div>
            <div class="p-6">
                <canvas id="satisfactionChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <script defer>
        // Defer chart rendering until page is fully loaded
        window.addEventListener('load', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Visits Trend Chart
            const visitsData = <?php echo json_encode(array_reverse($queue_stats)); ?>;
            const visitsCtx = document.getElementById('visitsChart');
            if (visitsCtx) {
                new Chart(visitsCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: visitsData.map(d => d.date),
                        datasets: [{
                            label: 'Total Visits',
                            data: visitsData.map(d => d.total_visits),
                            borderColor: '#0D9488',
                            backgroundColor: 'rgba(13, 148, 136, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Completed',
                            data: visitsData.map(d => d.completed),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
            
            // Department Chart
            const deptData = <?php echo json_encode($department_stats); ?>;
            const deptCtx = document.getElementById('departmentChart');
            if (deptCtx) {
                new Chart(deptCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: deptData.map(d => d.department),
                        datasets: [{
                            data: deptData.map(d => d.total),
                            backgroundColor: deptData.map(d => d.color),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
            
            // Peak Hours Chart
            const peakData = <?php echo json_encode($peak_hours); ?>;
            const peakCtx = document.getElementById('peakHoursChart');
            if (peakCtx) {
                new Chart(peakCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: peakData.map(d => d.hour + ':00'),
                        datasets: [{
                            label: 'Number of Visits',
                            data: peakData.map(d => d.visits),
                            backgroundColor: '#0D9488',
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
            
            // Satisfaction Chart
            const satData = <?php echo json_encode(array_reverse($satisfaction_trends)); ?>;
            const satCtx = document.getElementById('satisfactionChart');
            if (satCtx) {
                new Chart(satCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: satData.map(d => d.date),
                        datasets: [{
                            label: 'Overall Rating',
                            data: satData.map(d => d.avg_rating),
                            borderColor: '#F59E0B',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Wait Time Rating',
                            data: satData.map(d => d.avg_wait_rating),
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Service Quality',
                            data: satData.map(d => d.avg_service_quality),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 5,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
        
        // Export report
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = '../../api/admin/export_report.php?' + params.toString();
        }
    </script>
</body>
</html>