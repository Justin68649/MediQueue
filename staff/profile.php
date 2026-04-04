<?php
// File: staff/profile.php
// Staff Profile Management

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($full_name === '' || $phone === '') {
        $message = 'Full name and phone are required.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $phone, $_SESSION['user_id']])) {
                $_SESSION['user_name'] = $full_name;
                $message = 'Profile updated successfully!';
                $messageType = 'success';

                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $staff = $stmt->fetch();
            } else {
                $errorInfo = $stmt->errorInfo();
                $message = 'Failed to update profile: ' . ($errorInfo[2] ?? 'Unknown error');
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Failed to update profile: ' . $e->getMessage();
            $messageType = 'error';
        }
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
    <style>.gradient-primary { background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%); }</style>
</head>
<body class="bg-gray-50">
    <nav class="gradient-primary text-white shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-cog text-2xl"></i>
                    <span class="text-xl font-bold">My Profile</span>
                </div>
                <a href="index.php" class="hover:text-teal-200 transition">
                    <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8 max-w-2xl">
        <div class="bg-white rounded-xl shadow-md p-8">
            <?php if (isset($message)): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Staff ID</label>
                    <input type="text" value="<?php echo $staff['user_id']; ?>" disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" value="<?php echo $staff['email']; ?>" disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Phone</label>
                    <input type="tel" name="phone" value="<?php echo $staff['phone']; ?>" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Department</label>
                    <input type="text" value="<?php echo $staff['department_name'] ?? 'N/A'; ?>" disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                </div>
                
                <button type="submit" class="gradient-primary text-white px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </form>
            
            <div class="mt-8 pt-6 border-t">
                <h3 class="font-bold mb-3">Change Password</h3>
                <form id="passwordForm">
                    <input type="password" id="current_password" placeholder="Current Password" class="w-full px-4 py-2 border rounded-lg mb-3">
                    <input type="password" id="new_password" placeholder="New Password" class="w-full px-4 py-2 border rounded-lg mb-3">
                    <input type="password" id="confirm_password" placeholder="Confirm Password" class="w-full px-4 py-2 border rounded-lg mb-3">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                alert('Passwords do not match');
                return;
            }
            
            const response = await fetch('../api/auth/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    current_password: document.getElementById('current_password').value,
                    new_password: newPass,
                    confirm_password: confirmPass
                })
            });
            
            const data = await response.json();
            alert(data.message);
            if (data.success) {
                document.getElementById('passwordForm').reset();
            }
        });
    </script>
</body>
</html>