<?php
// File: staff/queue_management.php
// Enhanced Queue Management Page with Real-time Updates

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get staff info
$stmt = $conn->prepare("
    SELECT u.*, d.name as department_name, d.color as department_color 
    FROM users u
    JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Management - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/staff.css">
    <style>
        .gradient-primary { background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%); }
        .status-online { background: #10B981; }
        .status-offline { background: #EF4444; }
        .status-break { background: #F59E0B; }
        .queue-card { transition: all 0.2s ease; }
        .queue-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-list-ol text-2xl"></i>
                    <span class="text-xl font-bold">Queue Management</span>
                    <span class="text-sm bg-white bg-opacity-20 px-3 py-1 rounded-full">
                        <?php echo htmlspecialchars($staff['department_name']); ?>
                    </span>
                </div>
                <div class="flex items-center space-x-4">
                    <div id="statusIndicator" class="flex items-center">
                        <span class="w-3 h-3 rounded-full mr-2 status-online"></span>
                        <span id="statusText">Online</span>
                    </div>
                    <select id="statusSelect" class="bg-white text-gray-800 px-3 py-1 rounded-lg text-sm">
                        <option value="online">🟢 Online</option>
                        <option value="break">🟡 Break</option>
                        <option value="offline">🔴 Offline</option>
                    </select>
                    <a href="index.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-arrow-left mr-1"></i>Back
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Waiting</p>
                        <p class="text-3xl font-bold text-orange-600" id="waitingCount">0</p>
                    </div>
                    <i class="fas fa-clock text-orange-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Called</p>
                        <p class="text-3xl font-bold text-blue-600" id="calledCount">0</p>
                    </div>
                    <i class="fas fa-bell text-blue-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Serving</p>
                        <p class="text-3xl font-bold text-green-600" id="servingCount">0</p>
                    </div>
                    <i class="fas fa-user-check text-green-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg Wait</p>
                        <p class="text-3xl font-bold text-purple-600" id="avgWaitTime">0 min</p>
                    </div>
                    <i class="fas fa-chart-line text-purple-500 text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Waiting Queue -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h2 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-users mr-2 text-teal-600"></i>Queue List
                        </h2>
                    </div>
                    <div id="queueList" class="divide-y divide-gray-200 max-h-[600px] overflow-y-auto">
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                            Loading queue...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Currently Serving & Controls -->
            <div class="space-y-6">
                <!-- Currently Serving Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-green-50 px-6 py-4 border-b border-green-200">
                        <h2 class="text-xl font-bold text-green-800">
                            <i class="fas fa-user-check mr-2"></i>Currently Serving
                        </h2>
                    </div>
                    <div id="servingCard" class="p-6 text-center">
                        <div class="text-gray-500">No patient being served</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold text-gray-800 mb-4">
                        <i class="fas fa-bolt mr-2 text-yellow-600"></i>Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <button onclick="callNext()" class="w-full bg-blue-500 text-white py-3 rounded-lg font-semibold hover:bg-blue-600 transition">
                            <i class="fas fa-forward mr-2"></i>Call Next Patient
                        </button>
                        <button onclick="refreshQueue()" class="w-full bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh Queue
                        </button>
                    </div>
                </div>

                <!-- Recent Completed -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gray-50 px-6 py-3 border-b">
                        <h3 class="font-bold text-gray-800">
                            <i class="fas fa-history mr-2 text-teal-600"></i>Recent Completed
                        </h3>
                    </div>
                    <div id="recentList" class="divide-y divide-gray-200 max-h-[200px] overflow-y-auto">
                        <div class="p-4 text-center text-gray-500 text-sm">No recent services</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call Modal -->
    <div id="callModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 transform transition-all">
            <div class="gradient-primary text-white px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold">Call Patient</h3>
            </div>
            <div class="p-6 text-center">
                <i class="fas fa-bullhorn text-blue-500 text-5xl mb-3 animate-bounce"></i>
                <p class="text-gray-600 mb-2">Calling patient:</p>
                <p class="text-2xl font-bold text-teal-600" id="callQueueNumber">-</p>
                <p class="text-lg text-gray-700" id="callPatientName">-</p>
                <div class="flex space-x-3 mt-6">
                    <button onclick="confirmCall()" class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600">
                        <i class="fas fa-check mr-2"></i>Confirm
                    </button>
                    <button onclick="closeCallModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <audio id="notificationSound" preload="auto">
        <source src="https://assets.mixkit.co/sfx/preview/mixkit-announcement-bell-1291.mp3" type="audio/mpeg">
    </audio>

    <script>
        let pendingCallId = null;
        let refreshInterval = null;
        
        // Load queue data
        async function loadQueue() {
            try {
                const response = await fetch('../api/staff/get_queue.php');
                const data = await response.json();
                
                if (data.success) {
                    updateStats(data.data.stats);
                    updateQueueList(data.data.waiting_queue);
                    updateServingCard(data.data.currently_serving);
                    updateRecentList(data.data.recent_completed);
                }
            } catch (error) {
                console.error('Error loading queue:', error);
            }
        }
        
        // Update statistics
        function updateStats(stats) {
            document.getElementById('waitingCount').textContent = stats.waiting;
            document.getElementById('calledCount').textContent = stats.called;
            document.getElementById('servingCount').textContent = stats.serving;
            document.getElementById('avgWaitTime').textContent = stats.avg_wait_time + ' min';
        }
        
        // Update queue list
        function updateQueueList(queue) {
            const container = document.getElementById('queueList');
            
            if (!queue || queue.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2 block"></i>
                        No patients in queue
                    </div>
                `;
                return;
            }
            
            container.innerHTML = queue.map(patient => `
                <div class="queue-card p-4 hover:bg-gray-50 transition">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <span class="font-bold text-teal-600 text-lg">${patient.queue_number}</span>
                                ${patient.priority !== 'normal' ? `
                                    <span class="priority-${patient.priority} px-2 py-1 rounded-full text-xs font-bold">
                                        ${patient.priority.toUpperCase()}
                                    </span>
                                ` : ''}
                                <span class="badge-${patient.status} px-2 py-1 rounded-full text-xs">
                                    ${patient.status.toUpperCase()}
                                </span>
                            </div>
                            <p class="font-medium text-gray-800">${escapeHtml(patient.patient_name)}</p>
                            <p class="text-sm text-gray-500">${patient.patient_phone}</p>
                            <div class="flex space-x-4 mt-2 text-xs text-gray-500">
                                <span><i class="far fa-clock mr-1"></i>Joined: ${new Date(patient.joined_at).toLocaleTimeString()}</span>
                                <span><i class="fas fa-hourglass-half mr-1"></i>Waiting: ${patient.wait_time_formatted}</span>
                                <span><i class="fas fa-chart-line mr-1"></i>Position: ${patient.position}</span>
                            </div>
                        </div>
                        <div>
                            ${patient.status === 'waiting' ? `
                                <button onclick="callPatient(${patient.id}, '${patient.queue_number}', '${escapeHtml(patient.patient_name)}')" 
                                        class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                                    <i class="fas fa-bullhorn mr-1"></i>Call
                                </button>
                            ` : patient.status === 'called' ? `
                                <button onclick="startService(${patient.id})" 
                                        class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                                    <i class="fas fa-play mr-1"></i>Start
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        // Update serving card
        function updateServingCard(serving) {
            const container = document.getElementById('servingCard');
            
            if (!serving) {
                container.innerHTML = `
                    <div class="text-center text-gray-500 py-4">
                        <i class="fas fa-user-slash text-4xl mb-2 block"></i>
                        No patient being served
                    </div>
                `;
                return;
            }
            
            container.innerHTML = `
                <div class="text-center">
                    <div class="text-4xl font-bold text-green-600 mb-2">${serving.queue_number}</div>
                    <p class="font-medium text-gray-800">${escapeHtml(serving.patient_name)}</p>
                    <p class="text-sm text-gray-500">${serving.patient_phone}</p>
                    <div class="mt-3 p-3 bg-green-50 rounded-lg">
                        <p class="text-sm text-green-800">
                            <i class="fas fa-hourglass-half mr-1"></i>
                            Service duration: ${serving.service_duration || 0} minutes
                        </p>
                    </div>
                    <button onclick="completeService(${serving.id})" 
                            class="mt-4 bg-purple-500 text-white px-6 py-2 rounded-lg hover:bg-purple-600 transition w-full">
                        <i class="fas fa-check-circle mr-2"></i>Complete Service
                    </button>
                </div>
            `;
        }
        
        // Update recent completed list
        function updateRecentList(recent) {
            const container = document.getElementById('recentList');
            
            if (!recent || recent.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">No recent services</div>';
                return;
            }
            
            container.innerHTML = recent.map(item => `
                <div class="p-3 flex justify-between items-center">
                    <div>
                        <span class="font-bold text-teal-600">${item.queue_number}</span>
                        <span class="text-sm text-gray-600 ml-2">${escapeHtml(item.patient_name)}</span>
                    </div>
                    <div class="text-xs text-gray-500">
                        ${item.service_time} min
                    </div>
                </div>
            `).join('');
        }
        
        // Call patient
        function callPatient(queueId, queueNumber, patientName) {
            pendingCallId = queueId;
            document.getElementById('callQueueNumber').textContent = queueNumber;
            document.getElementById('callPatientName').textContent = patientName;
            document.getElementById('callModal').classList.remove('hidden');
            document.getElementById('callModal').classList.add('flex');
            
            // Play sound
            const audio = document.getElementById('notificationSound');
            audio.play().catch(e => console.log('Audio play failed:', e));
        }
        
        // Confirm call
        async function confirmCall() {
            if (!pendingCallId) return;
            
            try {
                const response = await fetch('../api/staff/call_patient.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ queue_id: pendingCallId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Patient called successfully!', 'success');
                    loadQueue();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
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
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ queue_id: queueId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Service started', 'success');
                    loadQueue();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to start service', 'error');
            }
        }
        
        // Complete service
        async function completeService(queueId) {
            if (!confirm('Complete this service?')) return;
            
            try {
                const response = await fetch('../api/staff/complete_service.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ queue_id: queueId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Service completed!', 'success');
                    loadQueue();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to complete service', 'error');
            }
        }
        
        // Call next patient
        async function callNext() {
            try {
                const response = await fetch('../api/staff/get_queue.php');
                const data = await response.json();
                
                if (data.success && data.data.waiting_queue.length > 0) {
                    const nextPatient = data.data.waiting_queue[0];
                    callPatient(nextPatient.id, nextPatient.queue_number, nextPatient.patient_name);
                } else {
                    showNotification('No patients in queue', 'warning');
                }
            } catch (error) {
                showNotification('Failed to get next patient', 'error');
            }
        }
        
        // Update staff status
        document.getElementById('statusSelect').addEventListener('change', async (e) => {
            const status = e.target.value;
            
            try {
                const response = await fetch('../api/staff/update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: status })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const indicator = document.getElementById('statusIndicator');
                    const statusText = document.getElementById('statusText');
                    
                    indicator.className = 'flex items-center';
                    if (status === 'online') {
                        indicator.innerHTML = '<span class="w-3 h-3 rounded-full mr-2 status-online"></span>';
                        statusText.textContent = 'Online';
                    } else if (status === 'break') {
                        indicator.innerHTML = '<span class="w-3 h-3 rounded-full mr-2 status-break"></span>';
                        statusText.textContent = 'On Break';
                    } else {
                        indicator.innerHTML = '<span class="w-3 h-3 rounded-full mr-2 status-offline"></span>';
                        statusText.textContent = 'Offline';
                    }
                    showNotification(data.message, 'success');
                    loadQueue();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to update status', 'error');
            }
        });
        
        // Refresh queue
        function refreshQueue() {
            loadQueue();
            showNotification('Queue refreshed', 'info');
        }
        
        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
            notification.className = `fixed top-20 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${bgColor} text-white`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
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
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Initialize
        loadQueue();
        refreshInterval = setInterval(loadQueue, 10000);
        
        // Close modal on outside click
        document.getElementById('callModal').addEventListener('click', function(e) {
            if (e.target === this) closeCallModal();
        });
    </script>
</body>
</html>