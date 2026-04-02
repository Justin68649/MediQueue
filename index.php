<?php
// File: index.php
// Main Landing Page - Portal Selection

require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Healthcare Queue Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .portal-card {
            transition: all 0.3s ease;
        }
        .portal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .hero-bg {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.9) 0%, rgba(13, 148, 136, 0.9) 100%),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%23ffffff" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="%23ffffff" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="gradient-primary text-white shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-hospital-user text-3xl"></i>
                    <div>
                        <h1 class="text-2xl font-bold"><?php echo APP_NAME; ?></h1>
                        <p class="text-sm opacity-90">Healthcare Queue Management System</p>
                    </div>
                </div>
                <div class="text-sm">
                    <span class="opacity-90">Version <?php echo APP_VERSION; ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-bg text-white py-20">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl md:text-6xl font-bold mb-6">
                Welcome to <?php echo APP_NAME; ?>
            </h2>
            <p class="text-xl md:text-2xl mb-8 opacity-90 max-w-3xl mx-auto">
                Streamline your healthcare experience with our intelligent queue management system.
                Choose your portal below to get started.
            </p>
        </div>
    </section>

    <!-- Portal Selection -->
    <section class="py-16">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h3 class="text-3xl font-bold text-gray-800 mb-4">Choose Your Portal</h3>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Select the appropriate portal based on your role in the healthcare system.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <!-- Patient Portal -->
                <div class="portal-card bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-blue-500 text-white p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <i class="fas fa-user-injured text-4xl mb-2"></i>
                                <h4 class="text-xl font-bold">Patient Portal</h4>
                            </div>
                            <i class="fas fa-users text-6xl opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600 mb-6">
                            Join queues, check your status, view history, and provide feedback.
                        </p>
                        <div class="space-y-3">
                            <a href="patient/login.php" class="block gradient-primary text-white text-center py-3 rounded-lg font-semibold hover:opacity-90 transition">
                                <i class="fas fa-sign-in-alt mr-2"></i>Enter Patient Portal
                            </a>
                            <div class="text-xs text-gray-500 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Demo: patient@mediqueue.com / Patient@123
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Portal -->
                <div class="portal-card bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-green-500 text-white p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <i class="fas fa-user-md text-4xl mb-2"></i>
                                <h4 class="text-xl font-bold">Staff Portal</h4>
                            </div>
                            <i class="fas fa-stethoscope text-6xl opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600 mb-6">
                            Manage queues, call patients, update status, and view statistics.
                        </p>
                        <div class="space-y-3">
                            <a href="staff/login.php" class="block bg-green-500 text-white text-center py-3 rounded-lg font-semibold hover:bg-green-600 transition">
                                <i class="fas fa-sign-in-alt mr-2"></i>Enter Staff Portal
                            </a>
                            <div class="text-xs text-gray-500 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Demo: staff@mediqueue.com / Staff@123
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Portal -->
                <div class="portal-card bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-purple-500 text-white p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <i class="fas fa-user-shield text-4xl mb-2"></i>
                                <h4 class="text-xl font-bold">Admin Portal</h4>
                            </div>
                            <i class="fas fa-cogs text-6xl opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600 mb-6">
                            System administration, user management, reports, and settings.
                        </p>
                        <div class="space-y-3">
                            <a href="admin/login.php" class="block bg-purple-500 text-white text-center py-3 rounded-lg font-semibold hover:bg-purple-600 transition">
                                <i class="fas fa-sign-in-alt mr-2"></i>Enter Admin Portal
                            </a>
                            <div class="text-xs text-gray-500 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Demo: admin@mediqueue.com / Admin@123
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="bg-gray-100 py-16">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h3 class="text-3xl font-bold text-gray-800 mb-4">Why Choose <?php echo APP_NAME; ?>?</h3>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="bg-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 gradient-primary text-white">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-lg mb-2">Real-time Updates</h4>
                    <p class="text-gray-600 text-sm">Get instant notifications about your queue status</p>
                </div>

                <div class="text-center">
                    <div class="bg-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 gradient-primary text-white">
                        <i class="fas fa-mobile-alt text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-lg mb-2">Mobile Friendly</h4>
                    <p class="text-gray-600 text-sm">Access from any device, anywhere, anytime</p>
                </div>

                <div class="text-center">
                    <div class="bg-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 gradient-primary text-white">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-lg mb-2">Analytics</h4>
                    <p class="text-gray-600 text-sm">Comprehensive reports and performance metrics</p>
                </div>

                <div class="text-center">
                    <div class="bg-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 gradient-primary text-white">
                        <i class="fas fa-shield-alt text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-lg mb-2">Secure</h4>
                    <p class="text-gray-600 text-sm">Enterprise-grade security and data protection</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto px-6 text-center">
            <div class="flex items-center justify-center mb-4">
                <i class="fas fa-hospital-user text-2xl mr-3"></i>
                <span class="text-xl font-bold"><?php echo APP_NAME; ?></span>
            </div>
            <p class="text-gray-400 mb-4">
                © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </p>
            <div class="flex justify-center space-x-6">
                <a href="#" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-envelope"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-phone"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-map-marker-alt"></i>
                </a>
            </div>
        </div>
    </footer>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate portal cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.portal-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>