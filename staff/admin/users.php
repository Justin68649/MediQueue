<?php
// File: admin/users.php
// User Management - CRUD Operations

require_once '../../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Don't allow deleting own account
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "User deleted successfully";
        $message_type = "success";
    } else {
        $message = "You cannot delete your own account";
        $message_type = "error";
    }
}

// Get all users with filters
$role_filter = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

if ($role_filter !== 'all') {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($search) {
    $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR user_id LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM users $where_clause");
$count_stmt->execute($params);
$total_users = $count_stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Get users
$params[] = $limit;
$params[] = $offset;
$stmt = $conn->prepare("
    SELECT u.*, d.name as department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    $where_clause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for dropdown
$dept_stmt = $conn->query("SELECT id, name FROM departments WHERE is_active = 1");
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .modal {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-users-cog text-2xl"></i>
                    <span class="text-xl font-bold">User Management</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="hover:text-teal-200 transition">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
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
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-users text-teal-600 mr-3"></i>User Management
            </h1>
            <button onclick="openUserModal()" class="gradient-primary text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90">
                <i class="fas fa-plus mr-2"></i>Add New User
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div>
                    <select name="role" class="px-4 py-2 border rounded-lg">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="patient" <?php echo $role_filter === 'patient' ? 'selected' : ''; ?>>Patient</option>
                    </select>
                </div>
                <div class="flex-1">
                    <input type="text" name="search" placeholder="Search by name, email, ID or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <button type="submit" class="bg-teal-600 text-white px-6 py-2 rounded-lg hover:bg-teal-700">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
                <div>
                    <a href="users.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                        <i class="fas fa-sync-alt mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Message Display -->
        <?php if (isset($message)): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm"><?php echo $user['user_id']; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm"><?php echo $user['email']; ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $user['phone']; ?></div>
                                     </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $roleColors = [
                                            'admin' => 'bg-purple-100 text-purple-800',
                                            'staff' => 'bg-blue-100 text-blue-800',
                                            'patient' => 'bg-green-100 text-green-800'
                                        ];
                                        $color = $roleColors[$user['role']] ?? 'bg-gray-100';
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $color; ?>">
                                            <?php echo strtoupper($user['role']); ?>
                                        </span>
                                     </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php echo $user['department_name'] ?? '-'; ?>
                                     </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center">
                                            <span class="w-2 h-2 rounded-full <?php echo $user['is_active'] ? 'bg-green-500' : 'bg-red-500'; ?> mr-2"></span>
                                            <span class="text-sm"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                        </span>
                                     </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                     </td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2">
                                            <button onclick="editUser(<?php echo $user['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>)" 
                                                    class="text-yellow-600 hover:text-yellow-800">
                                                <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                     </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2 block"></i>
                                    No users found
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
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> users
                    </div>
                    <div class="flex space-x-2">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" 
                               class="px-3 py-1 border rounded <?php echo $i == $page ? 'gradient-primary text-white' : 'hover:bg-gray-100'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="gradient-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-xl font-bold" id="modalTitle">Add New User</h3>
                <button onclick="closeUserModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form id="userForm" class="p-6">
                <input type="hidden" id="user_id" name="user_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" id="email" name="email" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                    <input type="tel" id="phone" name="phone" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                    <select id="role" name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        <option value="patient">Patient</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="mb-4" id="departmentField">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department (for Staff)</label>
                    <select id="department_id" name="department_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4" id="passwordField">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                    <input type="password" id="password" name="password" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                    <p class="text-xs text-gray-500 mt-1">Minimum 8 characters. Leave blank to keep current password when editing.</p>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 gradient-primary text-white py-2 rounded-lg font-semibold hover:opacity-90">
                        <i class="fas fa-save mr-2"></i>Save User
                    </button>
                    <button type="button" onclick="closeUserModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show/hide department field based on role
        document.getElementById('role').addEventListener('change', function() {
            const deptField = document.getElementById('departmentField');
            if (this.value === 'staff') {
                deptField.style.display = 'block';
            } else {
                deptField.style.display = 'none';
            }
        });
        
        // Open modal for adding user
        function openUserModal() {
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('userForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('password').required = true;
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('departmentField').style.display = 'none';
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('userModal').classList.add('flex');
        }
        
        // Edit user
        async function editUser(userId) {
            try {
                const response = await fetch(`../../api/admin/get_user.php?id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    const user = data.data?.user || data.user;
                    document.getElementById('modalTitle').textContent = 'Edit User';
                    document.getElementById('user_id').value = user.id;
                    document.getElementById('full_name').value = user.full_name;
                    document.getElementById('email').value = user.email;
                    document.getElementById('phone').value = user.phone;
                    document.getElementById('role').value = user.role;
                    document.getElementById('department_id').value = user.department_id || '';
                    document.getElementById('password').required = false;
                    document.getElementById('passwordField').style.display = 'block';
                    
                    if (user.role === 'staff') {
                        document.getElementById('departmentField').style.display = 'block';
                    } else {
                        document.getElementById('departmentField').style.display = 'none';
                    }
                    
                    document.getElementById('userModal').classList.remove('hidden');
                    document.getElementById('userModal').classList.add('flex');
                }
            } catch (error) {
                alert('Failed to load user data');
            }
        }
        
        // Toggle user status
        async function toggleUserStatus(userId, currentStatus) {
            const newStatus = currentStatus ? 0 : 1;
            const action = newStatus ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                try {
                    const response = await fetch('../../api/admin/update_user.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: userId, is_active: newStatus })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                } catch (error) {
                    alert('Failed to update user status');
                }
            }
        }
        
        // Delete user
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                window.location.href = `?delete=${userId}`;
            }
        }
        
        // Close modal
        function closeUserModal() {
            document.getElementById('userModal').classList.add('hidden');
            document.getElementById('userModal').classList.remove('flex');
        }
        
        // Handle form submission
        document.getElementById('userForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                id: formData.get('user_id'),
                full_name: formData.get('full_name'),
                email: formData.get('email'),
                phone: formData.get('phone'),
                role: formData.get('role'),
                department_id: formData.get('department_id') || null,
                password: formData.get('password')
            };
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            submitBtn.disabled = true;
            
            try {
                const url = data.id ? '../../api/admin/update_user.php' : '../../api/admin/create_user.php';
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                alert('Failed to save user');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Close modal on outside click
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserModal();
            }
        });
    </script>
</body>
</html>