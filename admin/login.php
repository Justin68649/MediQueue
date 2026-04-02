<?php
// File: admin/login.php
// Admin Login Page

require_once '../config/config.php';

if (isLoggedIn() && $_SESSION['role'] === 'admin') {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <div class="gradient-primary rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-shield text-white text-3xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900">Admin Portal</h2>
                <p class="mt-2 text-gray-600">Secure access to system administration</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">
                <form id="loginForm" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="email" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="password" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                    </div>
                    
                    <button type="submit" class="gradient-primary text-white w-full py-3 rounded-lg font-semibold hover:opacity-90">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login as Admin
                    </button>
                </form>
                
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Demo: admin@mediqueue.com / Admin@123
                    </p>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="../" class="text-teal-600 hover:text-teal-500 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            const submitBtn = e.target.querySelector('button');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging in...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                
                const data = await response.json();
                
                if (data.success && data.data.user.role === 'admin') {
                    window.location.href = 'index.php';
                } else {
                    alert('Invalid admin credentials');
                    submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Login as Admin';
                    submitBtn.disabled = false;
                }
            } catch (error) {
                alert('Login failed');
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Login as Admin';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>