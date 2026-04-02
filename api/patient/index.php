<?php
// File: patient/index.php (COMPLETED)
// Patient Dashboard - Full Version

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'patient') {
    redirect(APP_URL . '/');
}

// Get user details
$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://js.pusher.com/7.0/pusher.min.js"></script>
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
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        .status-waiting { background: #FEF3C7; color: #92400E; }
        .status-called { background: #DBEAFE; color: #1E40AF; animation: pulse 2s infinite; }
        .status-serving { background: #D1FAE5; color: #065F46; }
        .status-completed { background: #D1FAE5; color: #065F46; }
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
                    <a href="history.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-history mr-1"></i>History
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
        <!-- User Info Banner -->
        <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-id-card text-blue-500 text-2xl mr-3"></i>
                <div>
                    <p class="text-sm text-gray-600">Patient ID: <strong><?php echo $user['user_id']; ?></strong></p>
                    <p class="text-sm text-gray-600">Phone: <strong><?php echo $user['phone']; ?></strong></p>
                    <p class="text-sm text-gray-600">Email: <strong><?php echo $user['email']; ?></strong></p>
                </div>
            </div>
        </div>

        <!-- Current Queue Status -->
        <div id="activeQueue" class="mb-8"></div>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Join New Queue -->
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-plus-circle text-teal-600 mr-2"></i>Join a Queue
                </h2>
                
                <form id="joinQueueForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Department</label>
                        <select id="department_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            <option value="">Choose department...</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority (Optional)</label>
                        <select id="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent (Additional Fee)</option>
                            <option value="emergency">Emergency (Priority)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="gradient-primary text-white w-full py-3 rounded-lg font-semibold hover:opacity-90 transition">
                        <i class="fas fa-ticket-alt mr-2"></i>Join Queue
                    </button>
                </form>
                
                <div id="joinResult" class="mt-4 hidden"></div>
            </div>

            <!-- Quick Stats -->
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-chart-line text-teal-600 mr-2"></i>Quick Stats
                </h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Today's Appointments</span>
                        <span class="text-2xl font-bold text-teal-600" id="todayCount">0</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Completed Visits</span>
                        <span class="text-2xl font-bold text-green-600" id="completedCount">0</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Total Spent Time</span>
                        <span class="text-2xl font-bold text-blue-600" id="totalTime">0 min</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent History -->
        <div class="mt-8 bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-history text-teal-600 mr-2"></i>Recent Appointments
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Queue #</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Department</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Wait Time</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Action</th>
                        </tr>
                    </thead>
                    <tbody id="historyTable" class="divide-y divide-gray-200">
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">Loading history...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Notification Sound -->
    <audio id="notificationSound" preload="auto">
        <source src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3" type="audio/mpeg">
    </audio>

    <script>
        // Global variables
        let refreshInterval = null;
        let currentQueueId = null;

        // Load departments
        async function loadDepartments() {
            try {
                const response = await fetch('../api/public/get_departments.php');
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('department_id');
                    data.departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id;
                        option.textContent = `${dept.name} (${dept.prefix}) - Est. wait: ${dept.avg_service_time} min`;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading departments:', error);
            }
        }

        // Load active queue status
        async function loadActiveQueue() {
            try {
                const response = await fetch('../api/patient/get_status.php');
                const data = await response.json();
                
                if (data.success && data.has_active) {
                    currentQueueId = data.queue.id;
                    displayActiveQueue(data.queue);
                } else {
                    document.getElementById('activeQueue').innerHTML = `
                        <div class="bg-gray-100 rounded-xl p-6 text-center">
                            <i class="fas fa-inbox text-gray-400 text-5xl mb-3"></i>
                            <p class="text-gray-600">No active queue. Join a queue to get started.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading queue:', error);
            }
        }

        // Display active queue
        function displayActiveQueue(queue) {
            const statusClass = `status-${queue.status}`;
            let statusIcon = '';
            let statusMessage = '';
            
            switch(queue.status) {
                case 'waiting':
                    statusIcon = 'fa-clock';
                    statusMessage = `You are in position ${queue.position}. Estimated wait: ${queue.estimated_wait_time} minutes.`;
                    break;
                case 'called':
                    statusIcon = 'fa-bell';
                    statusMessage = `Your number ${queue.queue_number} has been called! Please proceed to ${queue.department_name}.`;
                    playNotification();
                    break;
                case 'serving':
                    statusIcon = 'fa-user-md';
                    statusMessage = `You are currently being served at ${queue.department_name}.`;
                    break;
                case 'completed':
                    statusIcon = 'fa-check-circle';
                    statusMessage = `Service completed. Thank you for visiting!`;
                    break;
            }
            
            const html = `
                <div class="bg-white rounded-2xl shadow-xl p-6 queue-card">
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-ticket-alt text-teal-600 mr-2"></i>Current Queue Status
                        </h2>
                        <button onclick="cancelQueue()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </button>
                    </div>
                    
                    <div class="grid md:grid-cols-3 gap-6">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <i class="fas fa-hashtag text-teal-600 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-600">Queue Number</p>
                            <p class="text-4xl font-bold text-teal-600">${queue.queue_number}</p>
                        </div>
                        
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <i class="fas fa-building text-teal-600 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-600">Department</p>
                            <p class="text-xl font-bold text-gray-800">${queue.department_name}</p>
                        </div>
                        
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <i class="fas ${statusIcon} text-teal-600 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-600">Status</p>
                            <p class="text-xl font-bold ${statusClass} px-3 py-1 rounded-full inline-block">${queue.status.toUpperCase()}</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        <span class="text-blue-800">${statusMessage}</span>
                    </div>
                    
                    ${queue.status === 'waiting' ? `
                        <div class="mt-4">
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="gradient-primary rounded-full h-2 transition-all duration-500" style="width: ${calculateProgress(queue.position)}%"></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-2">Queue progress: ${queue.position} people ahead of you</p>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('activeQueue').innerHTML = html;
        }

        // Calculate progress
        function calculateProgress(position) {
            // Assuming max queue size of 50
            const maxPosition = 50;
            const progress = ((maxPosition - position) / maxPosition) * 100;
            return Math.min(100, Math.max(0, progress));
        }

        // Join queue
        document.getElementById('joinQueueForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const departmentId = document.getElementById('department_id').value;
            const priority = document.getElementById('priority').value;
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Joining...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('../api/patient/join_queue.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ department_id: departmentId, priority: priority })
                });
                
                const data = await response.json();
                const resultDiv = document.getElementById('joinResult');
                
                if (data.status === 'success') {
                    resultDiv.className = 'mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg';
                    resultDiv.innerHTML = `
                        <i class="fas fa-check-circle mr-2"></i>
                        ${data.message || 'Successfully joined the queue'}<br>
                        <strong>Queue Number:</strong> ${data.data?.queue_number || '-'}<br>
                        <strong>Position:</strong> ${data.data?.position || '-'}<br>
                        <strong>Estimated Wait:</strong> ${data.data?.estimated_wait || '-'} minutes
                    `;
                    resultDiv.classList.remove('hidden');
                    
                    // Reset form and reload queue
                    document.getElementById('joinQueueForm').reset();
                    loadActiveQueue();
                    loadStats();
                    loadHistory();
                    
                    // Auto hide after 5 seconds
                    setTimeout(() => {
                        resultDiv.classList.add('hidden');
                    }, 5000);
                } else {
                    resultDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg';
                    resultDiv.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i>${data.message || 'Failed to join queue.'}`;
                    resultDiv.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error joining queue:', error);
                const resultDiv = document.getElementById('joinResult');
                resultDiv.className = 'mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg';
                resultDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Failed to join queue. Please try again.';
                resultDiv.classList.remove('hidden');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });

        // Cancel queue
        async function cancelQueue() {
            if (!confirm('Are you sure you want to cancel your queue position?')) return;
            
            try {
                const response = await fetch('../api/patient/cancel_queue.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ queue_id: currentQueueId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Queue cancelled successfully', 'success');
                    loadActiveQueue();
                    loadStats();
                    loadHistory();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error cancelling queue:', error);
                showNotification('Failed to cancel queue', 'error');
            }
        }

        // Load stats
        async function loadStats() {
            try {
                const response = await fetch('../api/patient/get_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('todayCount').textContent = data.stats.today_count || 0;
                    document.getElementById('completedCount').textContent = data.stats.completed_count || 0;
                    document.getElementById('totalTime').textContent = (data.stats.total_wait_time || 0) + ' min';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Load history
        async function loadHistory() {
            try {
                const response = await fetch('../api/patient/get_history.php');
                const data = await response.json();
                
                const tbody = document.getElementById('historyTable');
                
                if (data.success && data.history.length > 0) {
                    tbody.innerHTML = '';
                    data.history.forEach(entry => {
                        const row = `
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-bold text-teal-600">${entry.queue_number}</td>
                                <td class="px-4 py-3">${entry.department_name}</td>
                                <td class="px-4 py-3">${new Date(entry.joined_at).toLocaleDateString()}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold status-${entry.status}">
                                        ${entry.status.toUpperCase()}
                                    </span>
                                </td>
                                <td class="px-4 py-3">${entry.wait_time || '-'} min</td>
                                <td class="px-4 py-3">
                                    ${entry.status === 'completed' ? `
                                        <button onclick="giveFeedback(${entry.id})" class="text-teal-600 hover:text-teal-800">
                                            <i class="fas fa-star mr-1"></i>Feedback
                                        </button>
                                    ` : '-'}
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">No appointment history found</td></tr>';
                }
            } catch (error) {
                console.error('Error loading history:', error);
            }
        }

        // Play notification
        function playNotification() {
            const audio = document.getElementById('notificationSound');
            if (audio) {
                audio.play().catch(e => console.log('Audio play failed:', e));
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-20 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-all transform translate-x-0 ${
                type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            } text-white`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Give feedback
        function giveFeedback(queueId) {
            const rating = prompt('Rate your experience (1-5 stars):', '5');
            if (rating && rating >= 1 && rating <= 5) {
                submitFeedback(queueId, rating);
            }
        }

        async function submitFeedback(queueId, rating) {
            try {
                const response = await fetch('../api/patient/submit_feedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ queue_id: queueId, rating: rating })
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification('Thank you for your feedback!', 'success');
                }
            } catch (error) {
                console.error('Error submitting feedback:', error);
            }
        }

        // Initialize real-time updates
        function initRealTimeUpdates() {
            // Refresh every 10 seconds
            refreshInterval = setInterval(() => {
                loadActiveQueue();
                loadStats();
            }, 10000);
        }

        // Initialize page
        async function init() {
            await loadDepartments();
            await loadActiveQueue();
            await loadStats();
            await loadHistory();
            initRealTimeUpdates();
        }

        init();
    </script>
</body>
</html>