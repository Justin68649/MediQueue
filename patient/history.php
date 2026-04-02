<?php
// File: patient/history.php
// Patient Appointment History

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'patient') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? date('Y-m');
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = ["patient_id = ?"];
$params = [$_SESSION['user_id']];

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE_FORMAT(joined_at, '%Y-%m') = ?";
    $params[] = $date_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM queue_entries WHERE $where_clause");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get history data
$params[] = $limit;
$params[] = $offset;
$stmt = $conn->prepare("
    SELECT q.*, d.name as department_name, d.color as department_color,
           u.full_name as staff_name
    FROM queue_entries q
    LEFT JOIN departments d ON q.department_id = d.id
    LEFT JOIN users u ON q.staff_id = u.id
    WHERE $where_clause
    ORDER BY q.joined_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$history = $stmt->fetchAll();

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        AVG(TIMESTAMPDIFF(MINUTE, joined_at, completed_at)) as avg_wait_time
    FROM queue_entries 
    WHERE patient_id = ?
");
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch();

// Get available months for filter
$months_stmt = $conn->prepare("
    SELECT DISTINCT DATE_FORMAT(joined_at, '%Y-%m') as month,
           DATE_FORMAT(joined_at, '%M %Y') as month_name
    FROM queue_entries 
    WHERE patient_id = ?
    ORDER BY joined_at DESC
");
$months_stmt->execute([$_SESSION['user_id']]);
$months = $months_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment History - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.csrfToken = <?php echo json_encode(getCSRFToken()); ?>;
    </script>
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-waiting { background: #FEF3C7; color: #92400E; }
        .status-called { background: #DBEAFE; color: #1E40AF; animation: pulse 2s infinite; }
        .status-serving { background: #D1FAE5; color: #065F46; }
        .status-completed { background: #D1FAE5; color: #065F46; }
        .status-cancelled { background: #FEE2E2; color: #991B1B; }
        .status-no_show { background: #FEE2E2; color: #991B1B; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="gradient-primary text-white shadow-lg">
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
                    <a href="profile.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-user mr-1"></i>Profile
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
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-history text-teal-600 mr-3"></i>Appointment History
            </h1>
            <p class="text-gray-600 mt-2">View all your past and upcoming appointments</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Visits</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total'] ?? 0; ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Completed</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $stats['completed'] ?? 0; ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Cancelled</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $stats['cancelled'] ?? 0; ?></p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg. Wait Time</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo round($stats['avg_wait_time'] ?? 0); ?> min</p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-clock text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="waiting" <?php echo $status_filter === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                        <option value="called" <?php echo $status_filter === 'called' ? 'selected' : ''; ?>>Called</option>
                        <option value="serving" <?php echo $status_filter === 'serving' ? 'selected' : ''; ?>>Serving</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                    <select name="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        <option value="">All Time</option>
                        <?php foreach ($months as $month): ?>
                            <option value="<?php echo $month['month']; ?>" <?php echo $date_filter === $month['month'] ? 'selected' : ''; ?>>
                                <?php echo $month['month_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="gradient-primary text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90">
                        <i class="fas fa-filter mr-2"></i>Apply Filter
                    </button>
                </div>
                
                <div>
                    <a href="history.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-600">
                        <i class="fas fa-sync-alt mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- History Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queue #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wait Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($history) > 0): ?>
                            <?php foreach ($history as $entry): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-teal-600"><?php echo htmlspecialchars($entry['queue_number']); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold" style="background: <?php echo $entry['department_color']; ?>20; color: <?php echo $entry['department_color']; ?>">
                                            <?php echo htmlspecialchars($entry['department_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($entry['joined_at'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($entry['joined_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($entry['staff_name']): ?>
                                            <div class="flex items-center">
                                                <i class="fas fa-user-md text-gray-400 mr-2"></i>
                                                <span class="text-sm"><?php echo htmlspecialchars($entry['staff_name']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="status-badge status-<?php echo $entry['status']; ?>">
                                            <?php echo strtoupper($entry['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($entry['completed_at']): ?>
                                            <?php 
                                            $wait_time = round((strtotime($entry['completed_at']) - strtotime($entry['joined_at'])) / 60);
                                            ?>
                                            <span class="text-sm font-semibold <?php echo $wait_time <= 15 ? 'text-green-600' : ($wait_time <= 30 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                <?php echo $wait_time; ?> min
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2">
                                            <button onclick="viewDetails(<?php echo $entry['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 transition">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($entry['status'] === 'completed' && !hasFeedback($entry['id'])): ?>
                                                <button onclick="giveFeedback(<?php echo $entry['id']; ?>)" 
                                                        class="text-yellow-600 hover:text-yellow-800 transition">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($entry['status'] === 'waiting'): ?>
                                                <button onclick="cancelQueue(<?php echo $entry['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-800 transition">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                    No appointment history found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> results
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>" 
                               class="px-3 py-1 border rounded hover:bg-gray-100">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>" 
                               class="px-3 py-1 border rounded <?php echo $i == $page ? 'gradient-primary text-white' : 'hover:bg-gray-100'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>" 
                               class="px-3 py-1 border rounded hover:bg-gray-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Export Section -->
        <div class="mt-8 flex justify-end space-x-4">
            <button onclick="exportData('csv')" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                <i class="fas fa-file-csv mr-2"></i>Export CSV
            </button>
            <button onclick="window.print()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-print mr-2"></i>Print
            </button>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="gradient-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-xl font-bold">Appointment Details</h3>
                <button onclick="closeModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="modalContent" class="p-6">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <script>
        // Check if feedback exists
        <?php
        function hasFeedback($queueId) {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id FROM feedback WHERE queue_entry_id = ?");
            $stmt->execute([$queueId]);
            return $stmt->fetch() ? true : false;
        }
        ?>
        
        // View details
        async function viewDetails(queueId) {
            const modal = document.getElementById('detailsModal');
            const modalContent = document.getElementById('modalContent');
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modalContent.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-3xl text-teal-600"></i><p class="mt-2">Loading...</p></div>';
            
            try {
                const response = await fetch(`../api/patient/get_queue_details.php?id=${queueId}`);
                const data = await response.json();
                
                if (data.success) {
                    modalContent.innerHTML = `
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm text-gray-500">Queue Number</label>
                                    <p class="font-bold text-teal-600 text-xl">${data.data.queue_number}</p>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-500">Department</label>
                                    <p class="font-semibold">${data.data.department_name}</p>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-500">Status</label>
                                    <p><span class="status-badge status-${data.data.status}">${data.data.status.toUpperCase()}</span></p>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-500">Priority</label>
                                    <p class="capitalize">${data.data.priority}</p>
                                </div>
                                <div>
                                    <label class="text-sm text-gray-500">Joined At</label>
                                    <p>${new Date(data.data.joined_at).toLocaleString()}</p>
                                </div>
                                ${data.data.called_at ? `
                                <div>
                                    <label class="text-sm text-gray-500">Called At</label>
                                    <p>${new Date(data.data.called_at).toLocaleString()}</p>
                                </div>
                                ` : ''}
                                ${data.data.serving_started_at ? `
                                <div>
                                    <label class="text-sm text-gray-500">Service Started</label>
                                    <p>${new Date(data.data.serving_started_at).toLocaleString()}</p>
                                </div>
                                ` : ''}
                                ${data.data.completed_at ? `
                                <div>
                                    <label class="text-sm text-gray-500">Completed At</label>
                                    <p>${new Date(data.data.completed_at).toLocaleString()}</p>
                                </div>
                                ` : ''}
                                ${data.data.staff_name ? `
                                <div>
                                    <label class="text-sm text-gray-500">Served By</label>
                                    <p>${data.data.staff_name}</p>
                                </div>
                                ` : ''}
                                ${data.data.notes ? `
                                <div class="col-span-2">
                                    <label class="text-sm text-gray-500">Notes</label>
                                    <p class="text-gray-700">${data.data.notes}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                } else {
                    modalContent.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl"></i><p class="mt-2">${data.message}</p></div>`;
                }
            } catch (error) {
                console.error('Error:', error);
                modalContent.innerHTML = '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl"></i><p class="mt-2">Failed to load details</p></div>';
            }
        }
        
        // Close modal
        function closeModal() {
            const modal = document.getElementById('detailsModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        
        // Give feedback
        async function giveFeedback(queueId) {
            const rating = prompt('Rate your experience (1-5 stars):\n1 = Poor\n2 = Fair\n3 = Good\n4 = Very Good\n5 = Excellent', '5');
            
            if (rating && rating >= 1 && rating <= 5) {
                const comment = prompt('Any additional comments? (Optional)');
                
                try {
                    const response = await fetch('../api/patient/submit_feedback.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            queue_id: queueId, 
                            rating: parseInt(rating),
                        comment: comment || '',
                        csrf_token: window.csrfToken
                        showNotification('Thank you for your feedback!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Failed to submit feedback', 'error');
                }
            }
        }
        
        // Cancel queue
        async function cancelQueue(queueId) {
            if (!confirm('Are you sure you want to cancel this appointment?')) return;
            
            try {
                const response = await fetch('../api/patient/cancel_queue.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ queue_id: queueId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Appointment cancelled successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to cancel appointment', 'error');
            }
        }
        
        // Export data
        async function exportData(format) {
            const status = '<?php echo $status_filter; ?>';
            const date = '<?php echo $date_filter; ?>';
            
            window.location.href = `../api/patient/export_history.php?format=${format}&status=${status}&date=${date}`;
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
        
        // Close modal on outside click
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>