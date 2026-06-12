<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Kiểm tra quyền
if (empty($_SESSION['user']) || !in_array(strtolower($_SESSION['user']['role_name'] ?? ''), ['thungan', 'admin', 'quanly'])) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

try {
    // Lấy đơn chờ thanh toán
    $query = "
        SELECT 
            o.order_id,
            o.table_id,
            o.order_time,
            o.total_amount,
            o.paid_amount,
            COALESCE(o.total_amount - o.paid_amount, o.total_amount) as remaining_amount,
            t.table_name,
            f.floor_name,
            c.customer_name,
            u.full_name as waiter_name
        FROM orders o
        LEFT JOIN tables t ON t.table_id = o.table_id
        LEFT JOIN floors f ON f.floor_id = t.floor_id
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        LEFT JOIN users u ON u.user_id = o.waiter_id
        WHERE o.status = 'hoan_thanh'
        ORDER BY o.order_time ASC
    ";
    
    $result = $conn->query($query);
    $orders = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    // Thống kê
    $stats_query = "
        SELECT 
            COUNT(*) as total_pending,
            COALESCE(SUM(total_amount - paid_amount), 0) as total_remaining
        FROM orders
        WHERE status = 'hoan_thanh'
    ";
    
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result ? $stats_result->fetch_assoc() : ['total_pending' => 0, 'total_remaining' => 0];
    
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
