<?php
// File: admin/departments.php
// Department Management

require_once '../../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Handle department deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $dept_id = $_GET['delete'];
    
    // Check if department has users
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE department_id = ?");
    $stmt->execute([$dept_id]);
    $user_count = $stmt->fetch()['count'];
    
    if ($user_count == 0) {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$dept_id]);
        $message = "Department deleted successfully";
        $message_type = "success";
    } else {
        $message = "Cannot delete department with assigned staff members";
        $message_type = "error";
    }
}

// Get all departments
$stmt = $conn->query("
    SELECT d.*,
           COUNT(DISTINCT u.id) as staff_count,
           COUNT(DISTINCT q.id) as today_visits,
           COUNT(CASE WHEN q.status = 'waiting' THEN 1 END) as waiting_count
    FROM departments d
    LEFT JOIN users u ON d.id = u.department_id AND u.role = 'staff'
    LEFT JOIN queue_entries q ON d.id = q.department_id AND DATE(q.joined_at) = CURDATE()
    GROUP BY d.id
    ORDER BY d.name
");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="gradient-primary text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-building text-2xl"></i>
                    <span class="text-xl font-bold">Department Management</span>
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
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-building text-teal-600 mr-3"></i>Departments
            </h1>
            <button onclick="openDepartmentModal()" class="gradient-primary text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90">
                <i class="fas fa-plus mr-2"></i>Add Department
            </button>
        </div>

        <?php if (isset($message)): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($departments as $dept): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition">
                    <div class="p-4" style="background: <?php echo $dept['color']; ?>20; border-bottom: 3px solid <?php echo $dept['color']; ?>">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-xl font-bold" style="color: <?php echo $dept['color']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">Prefix: <?php echo $dept['prefix']; ?></p>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="editDepartment(<?php echo $dept['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteDepartment(<?php echo $dept['id']; ?>)" 
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-teal-600"><?php echo $dept['staff_count']; ?></p>
                                <p class="text-xs text-gray-500">Staff Members</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-orange-600"><?php echo $dept['waiting_count']; ?></p>
                                <p class="text-xs text-gray-500">Waiting Now</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600"><?php echo $dept['today_visits']; ?></p>
                                <p class="text-xs text-gray-500">Today's Visits</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-purple-600"><?php echo $dept['avg_service_time']; ?> min</p>
                                <p class="text-xs text-gray-500">Avg Service</p>
                            </div>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t">
                            <span class="text-sm text-gray-600">
                                <i class="fas fa-clock mr-1"></i>Created: <?php echo date('M Y', strtotime($dept['created_at'])); ?>
                            </span>
                            <span class="inline-flex items-center">
                                <span class="w-2 h-2 rounded-full <?php echo $dept['is_active'] ? 'bg-green-500' : 'bg-red-500'; ?> mr-2"></span>
                                <span class="text-sm"><?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Department Modal -->
    <div id="deptModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="gradient-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-xl font-bold" id="modalTitle">Add Department</h3>
                <button onclick="closeDepartmentModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form id="deptForm" class="p-6">
                <input type="hidden" id="dept_id" name="dept_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department Name *</label>
                    <input type="text" id="name" name="name" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" name="description" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Queue Prefix * (e.g., GEN, PHA)</label>
                    <input type="text" id="prefix" name="prefix" required maxlength="5" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Theme Color</label>
                    <div class="flex items-center space-x-3">
                        <input type="color" id="color" name="color" value="#0D9488" 
                               class="w-16 h-10 border rounded cursor-pointer">
                        <span id="colorPreview" class="color-preview" style="background: #0D9488"></span>
                        <span class="text-sm text-gray-500">Click to choose color</span>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Average Service Time (minutes)</label>
                    <input type="number" id="avg_service_time" name="avg_service_time" value="15" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="is_active" name="is_active" value="1" checked class="mr-2">
                        <span class="text-sm text-gray-700">Department Active</span>
                    </label>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 gradient-primary text-white py-2 rounded-lg font-semibold hover:opacity-90">
                        <i class="fas fa-save mr-2"></i>Save Department
                    </button>
                    <button type="button" onclick="closeDepartmentModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Color preview
        document.getElementById('color').addEventListener('change', function() {
            document.getElementById('colorPreview').style.backgroundColor = this.value;
        });
        
        // Open modal for adding department
        function openDepartmentModal() {
            document.getElementById('modalTitle').textContent = 'Add Department';
            document.getElementById('deptForm').reset();
            document.getElementById('dept_id').value = '';
            document.getElementById('color').value = '#0D9488';
            document.getElementById('colorPreview').style.backgroundColor = '#0D9488';
            document.getElementById('deptModal').classList.remove('hidden');
            document.getElementById('deptModal').classList.add('flex');
        }
        
        // Edit department
        async function editDepartment(deptId) {
            try {
                const response = await fetch(`../../api/admin/get_department.php?id=${deptId}`);
                const data = await response.json();
                
                if (data.success) {
                    const dept = data.department;
                    document.getElementById('modalTitle').textContent = 'Edit Department';
                    document.getElementById('dept_id').value = dept.id;
                    document.getElementById('name').value = dept.name;
                    document.getElementById('description').value = dept.description || '';
                    document.getElementById('prefix').value = dept.prefix;
                    document.getElementById('color').value = dept.color;
                    document.getElementById('colorPreview').style.backgroundColor = dept.color;
                    document.getElementById('avg_service_time').value = dept.avg_service_time;
                    document.getElementById('is_active').checked = dept.is_active == 1;
                    
                    document.getElementById('deptModal').classList.remove('hidden');
                    document.getElementById('deptModal').classList.add('flex');
                }
            } catch (error) {
                alert('Failed to load department data');
            }
        }
        
        // Delete department
        function deleteDepartment(deptId) {
            if (confirm('Are you sure you want to delete this department? This will affect staff assignments.')) {
                window.location.href = `?delete=${deptId}`;
            }
        }
        
        // Close modal
        function closeDepartmentModal() {
            document.getElementById('deptModal').classList.add('hidden');
            document.getElementById('deptModal').classList.remove('flex');
        }
        
        // Handle form submission
        document.getElementById('deptForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                id: formData.get('dept_id'),
                name: formData.get('name'),
                description: formData.get('description'),
                prefix: formData.get('prefix').toUpperCase(),
                color: formData.get('color'),
                avg_service_time: formData.get('avg_service_time'),
                is_active: formData.get('is_active') ? 1 : 0
            };
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            submitBtn.disabled = true;
            
            try {
                const url = data.id ? '../../api/admin/update_department.php' : '../../api/admin/create_department.php';
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
                alert('Failed to save department');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Close modal on outside click
        document.getElementById('deptModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDepartmentModal();
            }
        });
    </script>
</body>
</html>