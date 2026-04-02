<?php
// File: patient/profile.php
// Patient Profile Management

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'patient') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get user profile
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $blood_group = $_POST['blood_group'] ?? '';
    
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, date_of_birth = ?, gender = ?, blood_group = ? WHERE id = ?");
    if ($stmt->execute([$full_name, $phone, $address, $date_of_birth, $gender, $blood_group, $_SESSION['user_id']])) {
        $_SESSION['user_name'] = $full_name;
        $message = 'Profile updated successfully!';
        $messageType = 'success';
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } else {
        $message = 'Failed to update profile.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
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
        <div class="max-w-4xl mx-auto">
            <!-- Profile Header -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6">
                <div class="gradient-primary px-6 py-8 text-white">
                    <div class="flex items-center">
                        <div class="bg-white rounded-full w-20 h-20 flex items-center justify-center">
                            <i class="fas fa-user-circle text-teal-600 text-5xl"></i>
                        </div>
                        <div class="ml-6">
                            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                            <p class="text-teal-100">Patient ID: <?php echo $user['user_id']; ?></p>
                            <p class="text-teal-100">Member since: <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-3 gap-6">
                <!-- Profile Information -->
                <div class="md:col-span-2">
                    <div class="bg-white rounded-2xl shadow-xl p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-edit text-teal-600 mr-2"></i>Edit Profile
                        </h2>
                        
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100">
                                <p class="text-xs text-gray-500 mt-1">Email cannot be changed. Contact admin for assistance.</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                <select name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                                    <option value="">Select gender</option>
                                    <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Blood Group</label>
                                <select name="blood_group" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                                    <option value="">Select blood group</option>
                                    <option value="A+" <?php echo ($user['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($user['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($user['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($user['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="O+" <?php echo ($user['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($user['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                    <option value="AB+" <?php echo ($user['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($user['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea name="address" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="gradient-primary text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90 transition">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-white rounded-2xl shadow-xl p-6 mt-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-key text-teal-600 mr-2"></i>Change Password
                        </h2>
                        
                        <form id="changePasswordForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" id="current_password" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" id="new_password" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" id="confirm_password" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            </div>
                            
                            <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-600 transition">
                                <i class="fas fa-key mr-2"></i>Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats Sidebar -->
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl shadow-xl p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-chart-simple text-teal-600 mr-2"></i>Quick Stats
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Visits</span>
                                <span class="font-bold text-teal-600" id="totalVisits">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Completed</span>
                                <span class="font-bold text-green-600" id="completedVisits">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Cancelled</span>
                                <span class="font-bold text-red-600" id="cancelledVisits">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Avg. Wait Time</span>
                                <span class="font-bold text-blue-600" id="avgWaitTime">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-xl p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-bell text-teal-600 mr-2"></i>Notification Settings
                        </h3>
                        <div class="space-y-3">
                            <label class="flex items-center justify-between">
                                <span class="text-gray-600">Email Notifications</span>
                                <input type="checkbox" id="email_notif" class="toggle-switch">
                            </label>
                            <label class="flex items-center justify-between">
                                <span class="text-gray-600">SMS Notifications</span>
                                <input type="checkbox" id="sms_notif" class="toggle-switch">
                            </label>
                            <label class="flex items-center justify-between">
                                <span class="text-gray-600">Sound Alerts</span>
                                <input type="checkbox" id="sound_notif" class="toggle-switch">
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load user stats
        async function loadStats() {
            try {
                const response = await fetch('../api/patient/get_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalVisits').textContent = data.stats.total_visits || 0;
                    document.getElementById('completedVisits').textContent = data.stats.completed_count || 0;
                    document.getElementById('cancelledVisits').textContent = data.stats.cancelled_count || 0;
                    document.getElementById('avgWaitTime').textContent = (data.stats.avg_wait_time || 0) + ' min';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Handle password change
        document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                showNotification('New passwords do not match', 'error');
                return;
            }
            
            if (newPassword.length < 8) {
                showNotification('Password must be at least 8 characters', 'error');
                return;
            }
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('../api/auth/change_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Password changed successfully!', 'success');
                    document.getElementById('changePasswordForm').reset();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error changing password:', error);
                showNotification('Failed to change password', 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });

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

        // Load notification settings from localStorage
        function loadNotificationSettings() {
            const emailNotif = localStorage.getItem('email_notifications') === 'true';
            const smsNotif = localStorage.getItem('sms_notifications') === 'true';
            const soundNotif = localStorage.getItem('sound_notifications') === 'true';
            
            document.getElementById('email_notif').checked = emailNotif;
            document.getElementById('sms_notif').checked = smsNotif;
            document.getElementById('sound_notif').checked = soundNotif;
        }

        // Save notification settings
        document.getElementById('email_notif').addEventListener('change', (e) => {
            localStorage.setItem('email_notifications', e.target.checked);
        });
        
        document.getElementById('sms_notif').addEventListener('change', (e) => {
            localStorage.setItem('sms_notifications', e.target.checked);
        });
        
        document.getElementById('sound_notif').addEventListener('change', (e) => {
            localStorage.setItem('sound_notifications', e.target.checked);
        });

        // Initialize
        loadStats();
        loadNotificationSettings();
    </script>
</body>
</html>