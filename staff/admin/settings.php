<?php
// File: admin/settings.php
// System Settings Management

require_once '../../config/config.php';
header('Cache-Control: max-age=60, private'); // Cache for 1 minute
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Handle settings update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        foreach ($settings as $key => $value) {
            // Sanitize key to prevent injection
            if (!preg_match('/^[a-z_]+$/', $key)) continue;
            
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_at) 
                VALUES (?, ?, 'text', NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = VALUES(updated_at)
            ");
            $stmt->execute([$key, $value]);
        }
        
        // Handle file upload for logo - with size and type validation
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $max_size = 2 * 1024 * 1024; // 2MB
            if ($_FILES['logo']['size'] > $max_size) {
                throw new Exception('Logo file size exceeds 2MB limit');
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = mime_content_type($_FILES['logo']['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Invalid logo file type. Only JPEG, PNG, GIF, or WebP allowed');
            }
            
            $upload_dir = '../../assets/images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate safe filename
            $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                // Delete old logo if exists
                $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'company_logo'");
                $stmt->execute();
                $old_logo = $stmt->fetchColumn();
                if ($old_logo && file_exists($upload_dir . $old_logo)) {
                    @unlink($upload_dir . $old_logo);
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                    VALUES ('company_logo', ?, 'text')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$filename]);
            }
        }
        
        $conn->commit();
        $message = 'Settings saved successfully!';
        $message_type = 'success';
        
        // Log audit (non-blocking)
        try {
            logAudit($_SESSION['user_id'], 'updated_settings', 'System settings updated');
        } catch (Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
        
    } catch (Exception $e) {
        try {
            $conn->rollback();
        } catch (Exception $ex) {}
        $message = 'Failed to save settings: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all settings
$stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get available timezone options
$timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .settings-card {
            transition: all 0.2s ease;
        }
        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-cog text-2xl"></i>
                    <span class="text-xl font-bold">System Settings</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="users.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-users mr-1"></i>Users
                    </a>
                    <a href="departments.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-building mr-1"></i>Departments
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
                <i class="fas fa-cog text-teal-600 mr-3"></i>System Settings
            </h1>
            <p class="text-gray-600 mt-1">Configure your queue management system</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- General Settings -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden settings-card">
                <div class="gradient-primary px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-globe mr-2"></i>General Settings
                    </h2>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                        <input type="text" name="settings[company_name]" value="<?php echo htmlspecialchars($settings['company_name'] ?? APP_NAME); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Address</label>
                        <textarea name="settings[company_address]" rows="2" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Phone</label>
                        <input type="text" name="settings[company_phone]" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Email</label>
                        <input type="email" name="settings[company_email]" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Logo</label>
                        <?php if (!empty($settings['company_logo'])): ?>
                            <div class="mb-2">
                                <img src="../../assets/images/<?php echo $settings['company_logo']; ?>" alt="Logo" class="h-16 w-auto">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">Recommended size: 200x60 pixels. Max 2MB.</p>
                    </div>
                </div>
            </div>

            <!-- Queue Settings -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden settings-card">
                <div class="gradient-primary px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-clock mr-2"></i>Queue Settings
                    </h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Operating Hours Start</label>
                            <input type="time" name="settings[operating_hours_start]" value="<?php echo htmlspecialchars($settings['operating_hours_start'] ?? '08:00'); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Operating Hours End</label>
                            <input type="time" name="settings[operating_hours_end]" value="<?php echo htmlspecialchars($settings['operating_hours_end'] ?? '17:00'); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Queue Size Per Department</label>
                            <input type="number" name="settings[max_queue_size]" value="<?php echo htmlspecialchars($settings['max_queue_size'] ?? '100'); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Default Wait Time (minutes)</label>
                            <input type="number" name="settings[default_wait_time]" value="<?php echo htmlspecialchars($settings['default_wait_time'] ?? '15'); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Auto-Call Interval (minutes)</label>
                            <input type="number" name="settings[auto_call_interval]" value="<?php echo htmlspecialchars($settings['auto_call_interval'] ?? '5'); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Queue Display Refresh (seconds)</label>
                            <input type="number" name="settings[queue_display_refresh]" value="<?php echo htmlspecialchars($settings['queue_display_refresh'] ?? '10'); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden settings-card">
                <div class="gradient-primary px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-bell mr-2"></i>Notification Settings
                    </h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid md:grid-cols-3 gap-4">
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-700">SMS Notifications</span>
                            <input type="checkbox" name="settings[sms_enabled]" value="true" 
                                   <?php echo ($settings['sms_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>
                                   class="toggle-switch h-5 w-5 text-teal-600">
                        </label>
                        
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-700">Email Notifications</span>
                            <input type="checkbox" name="settings[email_enabled]" value="true" 
                                   <?php echo ($settings['email_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>
                                   class="toggle-switch h-5 w-5 text-teal-600">
                        </label>
                        
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-700">Push Notifications</span>
                            <input type="checkbox" name="settings[push_enabled]" value="true" 
                                   <?php echo ($settings['push_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>
                                   class="toggle-switch h-5 w-5 text-teal-600">
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Welcome Message (Display Screen)</label>
                        <textarea name="settings[welcome_message]" rows="2" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($settings['welcome_message'] ?? 'Welcome to our Healthcare Facility'); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- System Maintenance -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden settings-card">
                <div class="gradient-primary px-6 py-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-shield-alt mr-2"></i>System Maintenance
                    </h2>
                </div>
                <div class="p-6 space-y-4">
                    <label class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <div>
                            <span class="font-medium text-gray-800">Maintenance Mode</span>
                            <p class="text-sm text-gray-600">When enabled, only admins can access the system</p>
                        </div>
                        <input type="checkbox" name="settings[maintenance_mode]" value="true" 
                               <?php echo ($settings['maintenance_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?>
                               class="toggle-switch h-5 w-5 text-teal-600">
                    </label>
                    
                    <div class="flex space-x-4 pt-4">
                        <button type="button" onclick="clearCache()" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600">
                            <i class="fas fa-trash-alt mr-2"></i>Clear Cache
                        </button>
                        <button type="button" onclick="backupDatabase()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-database mr-2"></i>Backup Database
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" class="gradient-primary text-white px-8 py-3 rounded-lg font-semibold hover:opacity-90">
                    <i class="fas fa-save mr-2"></i>Save All Settings
                </button>
            </div>
        </form>
    </div>

    <script>
        // Clear cache
        async function clearCache() {
            if (confirm('Clear system cache? This may temporarily slow down the system.')) {
                try {
                    const response = await fetch('../../api/admin/clear_cache.php', {
                        method: 'POST'
                    });
                    const data = await response.json();
                    alert(data.message);
                } catch (error) {
                    alert('Failed to clear cache');
                }
            }
        }
        
        // Backup database
        async function backupDatabase() {
            if (confirm('Download database backup? This may take a few moments.')) {
                try {
                    window.location.href = '../../api/admin/backup_database.php';
                } catch (error) {
                    alert('Failed to backup database');
                }
            }
        }
        
        // Toggle switch styling
        document.querySelectorAll('.toggle-switch').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    this.value = 'true';
                } else {
                    this.value = 'false';
                }
            });
        });
    </script>
</body>
</html>