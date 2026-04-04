<?php
// File: api/notifications/get_notifications.php
// Get user notifications with pagination

header('Content-Type: application/json');
require_once '../../config/config.php';
requireLogin();

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $userId = $_SESSION['user_id'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    $unread_only = isset($_GET['unread']) && $_GET['unread'] === '1';
    
    $query = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$userId];
    
    if ($unread_only) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    $countParams = [$userId];
    if ($unread_only) {
        $countQuery .= " AND is_read = 0";
        $countParams = [$userId];
    }
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];

    // Get total unread count for badge display
    $unreadCountStmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $unreadCountStmt->execute([$userId]);
    $unreadResult = $unreadCountStmt->fetch(PDO::FETCH_ASSOC);
    $unreadCount = $unreadResult['unread_count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => $total,
        'unread_count' => $unreadCount,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}
?>