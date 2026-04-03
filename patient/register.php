<?php
// File: patient/register.php
// Patient Registration Page

require_once '../config/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn() && $_SESSION['role'] === 'patient') {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .step-active {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
            color: white;
        }
        .step-completed {
            background: #10B981;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto">
            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div class="step-item flex-1 text-center">
                        <div class="step-circle w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center mx-auto mb-2" id="step1Circle">1</div>
                        <span class="text-sm text-gray-600">Personal Info</span>
                    </div>
                    <div class="flex-1 h-1 bg-gray-300 mx-2"></div>
                    <div class="step-item flex-1 text-center">
                        <div class="step-circle w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center mx-auto mb-2" id="step2Circle">2</div>
                        <span class="text-sm text-gray-600">Contact Info</span>
                    </div>
                    <div class="flex-1 h-1 bg-gray-300 mx-2"></div>
                    <div class="step-item flex-1 text-center">
                        <div class="step-circle w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center mx-auto mb-2" id="step3Circle">3</div>
                        <span class="text-sm text-gray-600">Security</span>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="text-center mb-8">
                    <div class="gradient-primary rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-plus text-white text-2xl"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900">Create Account</h2>
                    <p class="mt-2 text-sm text-gray-600">Join our healthcare queue system</p>
                </div>

                <form id="registerForm" class="space-y-6">
                    <!-- Step 1: Personal Information -->
                    <div id="step1" class="step-content">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                <input type="text" id="full_name" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                                       placeholder="Enter your full name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth *</label>
                                <input type="date" id="dob" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Gender *</label>
                                <select id="gender" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Blood Group</label>
                                <select id="blood_group" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                                    <option value="">Select blood group</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Contact Information -->
                    <div id="step2" class="step-content hidden">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <input type="email" id="email" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                                       placeholder="Enter your email">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <input type="tel" id="phone" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                                       placeholder="Enter your phone number">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea id="address" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                                          placeholder="Enter your address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Security -->
                    <div id="step3" class="step-content hidden">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                                <div class="relative">
                                    <input type="password" id="password" required 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                                           placeholder="Create a password">
                                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-2 flex items-center text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters with 1 number and 1 special character</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                                <div class="relative">
                                    <input type="password" id="confirm_password" required 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                                           placeholder="Confirm your password">
                                    <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-2 flex items-center text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" id="terms" required class="h-4 w-4 text-teal-600">
                                    <span class="ml-2 text-sm text-gray-600">
                                        I agree to the <a href="#" class="text-teal-600">Terms and Conditions</a> and 
                                        <a href="#" class="text-teal-600">Privacy Policy</a>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex justify-between pt-4">
                        <button type="button" id="prevBtn" onclick="changeStep(-1)" 
                                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition hidden">
                            <i class="fas fa-arrow-left mr-2"></i>Previous
                        </button>
                        <button type="button" id="nextBtn" onclick="changeStep(1)" 
                                class="gradient-primary text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90 transition ml-auto">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                        <button type="submit" id="submitBtn" 
                                class="gradient-primary text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90 transition hidden">
                            <i class="fas fa-user-plus mr-2"></i>Register
                        </button>
                    </div>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Already have an account? 
                        <a href="login.php" class="text-teal-600 hover:text-teal-500">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 3;

        function updateSteps() {
            // Update step circles
            for (let i = 1; i <= totalSteps; i++) {
                const circle = document.getElementById(`step${i}Circle`);
                if (i < currentStep) {
                    circle.className = 'step-circle w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center mx-auto mb-2';
                    circle.innerHTML = '<i class="fas fa-check"></i>';
                } else if (i === currentStep) {
                    circle.className = 'step-circle w-10 h-10 rounded-full gradient-primary text-white flex items-center justify-center mx-auto mb-2';
                    circle.innerHTML = i;
                } else {
                    circle.className = 'step-circle w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center mx-auto mb-2';
                    circle.innerHTML = i;
                }
            }

            // Show/hide step content
            for (let i = 1; i <= totalSteps; i++) {
                const content = document.getElementById(`step${i}`);
                if (i === currentStep) {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            }

            // Update buttons
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');

            if (currentStep === 1) {
                prevBtn.classList.add('hidden');
            } else {
                prevBtn.classList.remove('hidden');
            }

            if (currentStep === totalSteps) {
                nextBtn.classList.add('hidden');
                submitBtn.classList.remove('hidden');
            } else {
                nextBtn.classList.remove('hidden');
                submitBtn.classList.add('hidden');
            }
        }

        function changeStep(direction) {
            const newStep = currentStep + direction;
            if (newStep >= 1 && newStep <= totalSteps) {
                currentStep = newStep;
                updateSteps();
            }
        }

        // Validate current step before proceeding
        function validateStep(step) {
            switch(step) {
                case 1:
                    const fullName = document.getElementById('full_name').value;
                    const dob = document.getElementById('dob').value;
                    const gender = document.getElementById('gender').value;
                    
                    if (!fullName) {
                        showNotification('Please enter your full name', 'error');
                        return false;
                    }
                    if (!dob) {
                        showNotification('Please enter your date of birth', 'error');
                        return false;
                    }
                    if (!gender) {
                        showNotification('Please select your gender', 'error');
                        return false;
                    }
                    return true;
                    
                case 2:
                    const email = document.getElementById('email').value;
                    const phone = document.getElementById('phone').value;
                    
                    if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                        showNotification('Please enter a valid email address', 'error');
                        return false;
                    }
                    if (!phone || phone.length < 10) {
                        showNotification('Please enter a valid phone number', 'error');
                        return false;
                    }
                    return true;
                    
                case 3:
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    const terms = document.getElementById('terms').checked;
                    
                    if (password.length < 8) {
                        showNotification('Password must be at least 8 characters', 'error');
                        return false;
                    }
                    if (password !== confirmPassword) {
                        showNotification('Passwords do not match', 'error');
                        return false;
                    }
                    if (!terms) {
                        showNotification('Please accept the terms and conditions', 'error');
                        return false;
                    }
                    return true;
                    
                default:
                    return true;
            }
        }

        // Handle next button click
        document.getElementById('nextBtn').onclick = () => {
            if (validateStep(currentStep)) {
                changeStep(1);
            }
        };

        // Handle form submission
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!validateStep(3)) return;
            
            const formData = {
                full_name: document.getElementById('full_name').value,
                dob: document.getElementById('dob').value,
                gender: document.getElementById('gender').value,
                blood_group: document.getElementById('blood_group').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                address: document.getElementById('address').value,
                password: document.getElementById('password').value,
                role: 'patient'
            };
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating account...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('../api/auth/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Registration successful! Redirecting to login...', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showNotification(data.message, 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Registration error:', error);
                showNotification('Registration failed. Please try again.', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });

        // Password show/hide toggle handlers
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');

        function flipPasswordVisibility(input, button) {
            if (!input || !button) return;
            const currentType = input.getAttribute('type');
            if (currentType === 'password') {
                input.setAttribute('type', 'text');
                button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.setAttribute('type', 'password');
                button.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }

        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', () => flipPasswordVisibility(passwordField, togglePasswordBtn));
        }

        if (toggleConfirmPasswordBtn) {
            toggleConfirmPasswordBtn.addEventListener('click', () => flipPasswordVisibility(confirmPasswordField, toggleConfirmPasswordBtn));
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${
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

        // Initialize
        updateSteps();
    </script>
</body>
</html>