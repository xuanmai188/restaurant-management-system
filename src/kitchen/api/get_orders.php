<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Kiểm tra quyền
if (empty($_SESSION['user']) || !in_array(strtolower($_SESSION['user']['role_name'] ?? ''), ['bep', 'admin', 'quanly'])) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

try {
    $status_filter = $_GET['status'] ?? 'active';
    
    if ($status_filter === 'active') {
        // Đơn đang hoạt động (mới, đang xử lý, đang chế biến)
        $query = "
            SELECT 
                od.order_detail_id,
                od.order_id,
                od.item_id,
                od.quantity,
                od.note,
                od.item_status,
                mi.item_name,
                o.order_time,
                o.status as order_status,
                t.table_name,
                f.floor_name
            FROM order_details od
            JOIN orders o ON o.order_id = od.order_id
            JOIN menu_items mi ON mi.item_id = od.item_id
            LEFT JOIN tables t ON t.table_id = o.table_id
            LEFT JOIN floors f ON f.floor_id = t.floor_id
            WHERE o.status IN ('moi', 'dang_xu_ly', 'dang_che_bien')
              AND od.item_status IN ('moi', 'dang_che_bien')
            ORDER BY o.order_time ASC, od.order_detail_id ASC
        ";
    } else {
        // Đơn đã hoàn thành hôm nay
        $today_start = date('Y-m-d') . ' 00:00:00';
        $query = "
            SELECT 
                od.order_detail_id,
                od.order_id,
                od.item_id,
                od.quantity,
                od.note,
                od.item_status,
                mi.item_name,
                o.order_time,
                o.status as order_status,
                t.table_name,
                f.floor_name
            FROM order_details od
            JOIN orders o ON o.order_id = od.order_id
            JOIN menu_items mi ON mi.item_id = od.item_id
            LEFT JOIN tables t ON t.table_id = o.table_id
            LEFT JOIN floors f ON f.floor_id = t.floor_id
            WHERE o.order_time >= '$today_start'
              AND od.item_status = 'hoan_thanh'
            ORDER BY o.order_time DESC
            LIMIT 50
        ";
    }
    
    $result = $conn->query($query);
    $orders = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    // Đếm số món đang chờ và đang nấu
    $stats_query = "
        SELECT 
            COUNT(CASE WHEN od.item_status = 'moi' THEN 1 END) as pending_count,
            COUNT(CASE WHEN od.item_status = 'dang_che_bien' THEN 1 END) as cooking_count
        FROM order_details od
        JOIN orders o ON o.order_id = od.order_id
        WHERE o.status IN ('moi', 'dang_xu_ly', 'dang_che_bien')
          AND od.item_status IN ('moi', 'dang_che_bien')
    ";
    
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result ? $stats_result->fetch_assoc() : ['pending_count' => 0, 'cooking_count' => 0];
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
