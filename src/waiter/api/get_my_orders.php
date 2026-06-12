<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Kiểm tra quyền
if (empty($_SESSION['user']) || !in_array(strtolower($_SESSION['user']['role_name'] ?? ''), ['phucvu', 'admin', 'quanly'])) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

try {
    $waiter_id = $_SESSION['user']['user_id'];
    $date = $_GET['date'] ?? date('Y-m-d');
    $view = $_GET['view'] ?? 'orders';
    
    $dateStart = $date . ' 00:00:00';
    $dateEnd = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
    
    if ($view === 'orders') {
        // Đơn hàng
        $query = "
            SELECT 
                o.order_id,
                o.order_time,
                o.status,
                o.total_amount,
                o.reservation_id,
                t.table_name,
                f.floor_name,
                c.customer_name,
                u.full_name as waiter_name
            FROM orders o
            LEFT JOIN tables t ON t.table_id = o.table_id
            LEFT JOIN floors f ON f.floor_id = t.floor_id
            LEFT JOIN customers c ON c.customer_id = o.customer_id
            LEFT JOIN users u ON u.user_id = o.waiter_id
            WHERE o.order_time >= '$dateStart' 
              AND o.order_time < '$dateEnd'
              AND o.reservation_id IS NULL
            ORDER BY o.order_time DESC
        ";
    } else {
        // Đặt bàn
        $query = "
            SELECT 
                r.reservation_id,
                r.table_id,
                r.reservation_time,
                r.number_of_people,
                r.note,
                r.status,
                t.table_name,
                f.floor_name,
                COALESCE(u.full_name, u.username, 'Khách') as customer_name,
                u.phone,
                o.order_id,
                o.total_amount
            FROM reservations r
            LEFT JOIN tables t ON t.table_id = r.table_id
            LEFT JOIN floors f ON f.floor_id = t.floor_id
            LEFT JOIN users u ON u.user_id = r.user_id
            LEFT JOIN orders o ON o.reservation_id = r.reservation_id
            WHERE r.reservation_time >= '$dateStart' 
              AND r.reservation_time < '$dateEnd'
            ORDER BY r.reservation_time DESC
        ";
    }
    
    $result = $conn->query($query);
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    // Thống kê
    $stats_query = "
        SELECT 
            COUNT(*) as my_orders_count,
            SUM(CASE WHEN status NOT IN ('da_thanh_toan', 'da_huy') THEN 1 ELSE 0 END) as active_count
        FROM orders 
        WHERE waiter_id = $waiter_id 
          AND order_time >= '$dateStart' 
          AND order_time < '$dateEnd'
    ";
    
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result ? $stats_result->fetch_assoc() : ['my_orders_count' => 0, 'active_count' => 0];
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'stats' => $stats,
        'view' => $view,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
