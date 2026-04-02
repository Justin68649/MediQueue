<?php
// File: patient/feedback.php
// Patient Feedback Form

require_once '../config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'patient') {
    redirect(APP_URL . '/');
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get completed visits without feedback
$stmt = $conn->prepare("
    SELECT q.id, q.queue_number, d.name as department_name, q.completed_at
    FROM queue_entries q
    JOIN departments d ON q.department_id = d.id
    WHERE q.patient_id = ? 
    AND q.status = 'completed'
    AND q.id NOT IN (SELECT queue_entry_id FROM feedback WHERE patient_id = ?)
    ORDER BY q.completed_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$pending_feedback = $stmt->fetchAll();

// Get existing feedback
$stmt = $conn->prepare("
    SELECT f.*, q.queue_number, d.name as department_name, 
           u.full_name as staff_name
    FROM feedback f
    JOIN queue_entries q ON f.queue_entry_id = q.id
    JOIN departments d ON q.department_id = d.id
    LEFT JOIN users u ON f.staff_id = u.id
    WHERE f.patient_id = ?
    ORDER BY f.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$feedbacks = $stmt->fetchAll();

// Get overall ratings
$stmt = $conn->prepare("
    SELECT 
        AVG(rating) as avg_rating,
        AVG(wait_time_rating) as avg_wait_rating,
        AVG(service_quality) as avg_service_quality,
        COUNT(*) as total_feedback
    FROM feedback
    WHERE patient_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$ratings = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        window.csrfToken = <?php echo json_encode(getCSRFToken()); ?>;
    </script>
    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
        }
        .rating-star {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .rating-star:hover {
            transform: scale(1.1);
        }
        .rating-star.selected {
            color: #F59E0B;
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
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-star text-teal-600 mr-3"></i>Share Your Feedback
                </h1>
                <p class="text-gray-600 mt-2">Your feedback helps us improve our service quality</p>
            </div>

            <!-- Ratings Summary -->
            <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Your Ratings Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-teal-600"><?php echo number_format($ratings['avg_rating'] ?? 0, 1); ?></div>
                        <div class="text-sm text-gray-600">Overall Rating</div>
                        <div class="text-yellow-500 mt-1">
                            <?php 
                            $avg = round($ratings['avg_rating'] ?? 0);
                            for($i = 1; $i <= 5; $i++) {
                                echo $i <= $avg ? '★' : '☆';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600"><?php echo number_format($ratings['avg_wait_rating'] ?? 0, 1); ?></div>
                        <div class="text-sm text-gray-600">Wait Time</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600"><?php echo number_format($ratings['avg_service_quality'] ?? 0, 1); ?></div>
                        <div class="text-sm text-gray-600">Service Quality</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600"><?php echo $ratings['total_feedback'] ?? 0; ?></div>
                        <div class="text-sm text-gray-600">Total Feedback</div>
                    </div>
                </div>
            </div>

            <!-- Pending Feedback -->
            <?php if (count($pending_feedback) > 0): ?>
                <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-clock text-yellow-600 mr-2"></i>Pending Feedback
                    </h2>
                    <div class="space-y-4">
                        <?php foreach ($pending_feedback as $pending): ?>
                            <div class="border rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-teal-600"><?php echo $pending['queue_number']; ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pending['department_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($pending['completed_at'])); ?></p>
                                    </div>
                                    <button onclick="openFeedbackModal(<?php echo $pending['id']; ?>)" 
                                            class="gradient-primary text-white px-4 py-2 rounded-lg hover:opacity-90">
                                        <i class="fas fa-star mr-2"></i>Rate Now
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Previous Feedback -->
            <?php if (count($feedbacks) > 0): ?>
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-history text-teal-600 mr-2"></i>Your Previous Feedback
                    </h2>
                    <div class="space-y-4">
                        <?php foreach ($feedbacks as $feedback): ?>
                            <div class="border-b pb-4 last:border-b-0">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-semibold text-teal-600"><?php echo $feedback['queue_number']; ?></span>
                                        <span class="text-sm text-gray-500 ml-2"><?php echo htmlspecialchars($feedback['department_name']); ?></span>
                                    </div>
                                    <div class="text-yellow-500">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <?php echo $i <= $feedback['rating'] ? '★' : '☆'; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if ($feedback['comment']): ?>
                                    <p class="text-gray-600 text-sm mt-2">"<?php echo htmlspecialchars($feedback['comment']); ?>"</p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-400 mt-2"><?php echo date('M d, Y h:i A', strtotime($feedback['created_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="gradient-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-xl font-bold">Rate Your Experience</h3>
                <button onclick="closeFeedbackModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form id="feedbackForm" class="p-6">
                <input type="hidden" id="queueId" name="queue_id">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Overall Rating</label>
                    <div class="flex justify-center space-x-2" id="ratingStars">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-3xl text-gray-300 rating-star" data-rating="<?php echo $i; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="rating" name="rating" required>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Wait Time Experience</label>
                    <div class="flex gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="wait_time_rating" value="5" class="mr-2"> Very Fast
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="wait_time_rating" value="4" class="mr-2"> Fast
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="wait_time_rating" value="3" class="mr-2"> Average
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="wait_time_rating" value="2" class="mr-2"> Slow
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="wait_time_rating" value="1" class="mr-2"> Very Slow
                        </label>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Staff Service Quality</label>
                    <div class="flex gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="service_quality" value="5" class="mr-2"> Excellent
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="service_quality" value="4" class="mr-2"> Good
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="service_quality" value="3" class="mr-2"> Average
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="service_quality" value="2" class="mr-2"> Poor
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="service_quality" value="1" class="mr-2"> Very Poor
                        </label>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Your Comments</label>
                    <textarea name="comment" rows="4" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                              placeholder="Share your experience..."></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Suggestions for Improvement</label>
                    <textarea name="suggestions" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                              placeholder="How can we serve you better?"></textarea>
                </div>
                
                <button type="submit" class="gradient-primary text-white w-full py-3 rounded-lg font-semibold hover:opacity-90">
                    <i class="fas fa-paper-plane mr-2"></i>Submit Feedback
                </button>
            </form>
        </div>
    </div>

    <script>
        let selectedRating = 0;
        
        // Rating star functionality
        const stars = document.querySelectorAll('#ratingStars .rating-star');
        stars.forEach(star => {
            star.addEventListener('click', function() {
                selectedRating = parseInt(this.dataset.rating);
                document.getElementById('rating').value = selectedRating;
                
                stars.forEach((s, index) => {
                    if (index < selectedRating) {
                        s.classList.add('selected');
                        s.style.color = '#F59E0B';
                    } else {
                        s.classList.remove('selected');
                        s.style.color = '#D1D5DB';
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const hoverRating = parseInt(this.dataset.rating);
                stars.forEach((s, index) => {
                    if (index < hoverRating) {
                        s.style.color = '#FCD34D';
                    }
                });
            });
            
            star.addEventListener('mouseleave', function() {
                stars.forEach((s, index) => {
                    if (index < selectedRating) {
                        s.style.color = '#F59E0B';
                    } else {
                        s.style.color = '#D1D5DB';
                    }
                });
            });
        });
        
        // Open feedback modal
        function openFeedbackModal(queueId) {
            document.getElementById('queueId').value = queueId;
            document.getElementById('feedbackModal').classList.remove('hidden');
            document.getElementById('feedbackModal').classList.add('flex');
        }
        
        // Close feedback modal
        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
            document.getElementById('feedbackModal').classList.remove('flex');
            document.getElementById('feedbackForm').reset();
            selectedRating = 0;
            stars.forEach(star => {
                star.style.color = '#D1D5DB';
                star.classList.remove('selected');
            });
        }
        
        // Handle feedback submission
        document.getElementById('feedbackForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                queue_id: formData.get('queue_id'),
                rating: parseInt(formData.get('rating')),
                wait_time_rating: parseInt(formData.get('wait_time_rating')) || null,
                service_quality: parseInt(formData.get('service_quality')) || null,
                comment: formData.get('comment'),
                suggestions: formData.get('suggestions')
            };
            
            if (!data.rating) {
                showNotification('Please select a rating', 'error');
                return;
            }
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('../api/patient/submit_feedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ...data,
                        csrf_token: window.csrfToken
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Thank you for your feedback!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.message, 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to submit feedback', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Show notification
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
        
        // Close modal on outside click
        document.getElementById('feedbackModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFeedbackModal();
            }
        });
    </script>
</body>
</html>