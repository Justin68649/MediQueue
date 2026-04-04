<?php
// File: test_notifications.php
// Test script to verify the notification system

require_once 'config/config.php';

echo "=== MediQueue Notification System Test ===\n\n";

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // 1. Check if notifications table exists
    echo "1. Checking notifications table...\n";
    $stmt = $conn->prepare("SHOW TABLES LIKE 'notifications'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "   ✅ notifications table exists\n\n";
    } else {
        echo "   ❌ notifications table NOT found\n\n";
    }
    
    // 2. Get a test user (preferably a patient)
    echo "2. Finding test patient...\n";
    $stmt = $conn->prepare("SELECT id, email, full_name FROM users WHERE role = 'patient' LIMIT 1");
    $stmt->execute();
    $testUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testUser) {
        echo "   ✅ Found test patient: {$testUser['full_name']} (ID: {$testUser['id']})\n\n";
        
        // 3. Create a test notification
        echo "3. Creating test notification...\n";
        $testTitle = "Test Notification - " . date('H:i:s');
        $testMessage = "This is a test notification from the notification system test.";
        
        $stmt = $conn->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$testUser['id'], 'info', $testTitle, $testMessage]);
        $notificationId = $conn->lastInsertId();
        
        if ($notificationId) {
            echo "   ✅ Created test notification ID: {$notificationId}\n\n";
            
            // 4. Retrieve the notification
            echo "4. Retrieving notification from database...\n";
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ?");
            $stmt->execute([$notificationId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($notification) {
                echo "   ✅ Successfully retrieved notification:\n";
                echo "      Title: {$notification['title']}\n";
                echo "      Message: {$notification['message']}\n";
                echo "      Type: {$notification['type']}\n";
                echo "      Read: " . ($notification['is_read'] ? 'Yes' : 'No') . "\n\n";
            } else {
                echo "   ❌ Could not retrieve notification\n\n";
            }
            
            // 5. Test marking as read
            echo "5. Testing mark as read...\n";
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $stmt->execute([$notificationId]);
            
            $stmt = $conn->prepare("SELECT is_read FROM notifications WHERE id = ?");
            $stmt->execute([$notificationId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['is_read']) {
                echo "   ✅ Successfully marked notification as read\n\n";
            } else {
                echo "   ❌ Failed to mark notification as read\n\n";
            }
            
            // 6. Get unread count
            echo "6. Testing unread count query...\n";
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unread_count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$testUser['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "   ✅ Unread notifications for test user: {$result['unread_count']}\n\n";
            
            // 7. Test sendNotification function
            echo "7. Testing sendNotification function...\n";
            if (function_exists('sendNotification')) {
                $resultFunc = sendNotification($testUser['id'], 'Function Test', 'Testing sendNotification function');
                if ($resultFunc) {
                    echo "   ✅ sendNotification function works\n\n";
                } else {
                    echo "   ❌ sendNotification function failed\n\n";
                }
            } else {
                echo "   ❌ sendNotification function not found\n\n";
            }
            
            // 8. Check API endpoints
            echo "8. Checking API endpoint files...\n";
            $endpoints = [
                'api/notifications/get_notifications.php',
                'api/notifications/mark_read.php',
                'api/notifications/delete_notification.php',
                'api/notifications/unread_count.php',
                'api/notifications/create_notification.php'
            ];
            
            foreach ($endpoints as $endpoint) {
                if (file_exists($endpoint)) {
                    echo "   ✅ {$endpoint}\n";
                } else {
                    echo "   ❌ {$endpoint} - NOT FOUND\n";
                }
            }
            echo "\n";
            
            // 9. Clean up test notification
            echo "9. Cleaning up test notification...\n";
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
            $stmt->execute([$notificationId]);
            echo "   ✅ Test notification deleted\n\n";
            
        } else {
            echo "   ❌ Failed to create test notification\n\n";
        }
    } else {
        echo "   ❌ No test patient found in database\n\n";
    }
    
    echo "=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>