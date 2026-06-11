<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Kiểm tra quyền
$_role = strtolower($_SESSION['user']['role_name'] ?? '');
if (!isset($_SESSION['user']) || !in_array($_role, ['quanly', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'detail':
        getCustomerDetail();
        break;
    case 'get':
        getCustomer();
        break;
    case 'update':
        updateCustomer();
        break;
    case 'update_order_status':
        updateOrderStatus();
        break;
    case 'update_reservation_status':
        updateReservationStatus();
        break;
    case 'delete':
        deleteCustomer();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}

function getCustomerDetail() {
    global $conn;
    $customer_id = $_GET['id'] ?? 0;
    
    // Lấy thông tin khách hàng
    $customer_query = "
        SELECT 
            c.customer_id,
            c.customer_name,
            c.phone,
            c.email,
            c.created_at,
            u.username,
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT r.reservation_id) as total_reservations,
            COALESCE(SUM(CASE WHEN o.status != 'da_huy' THEN p.amount_paid ELSE 0 END), 0) as total_spent
        FROM customers c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN orders o ON c.customer_id = o.customer_id
        LEFT JOIN payments p ON o.order_id = p.order_id AND p.payment_status = 'thanh_cong'
        LEFT JOIN reservations r ON r.user_id = c.user_id
        WHERE c.customer_id = ?
        GROUP BY c.customer_id
    ";
    $stmt = $conn->prepare($customer_query);
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng']);
        return;
    }
    
    // Lấy lịch sử đơn hàng
    $orders_query = "
        SELECT 
            o.order_id,
            o.order_time,
            o.status,
            o.total_amount,
            t.table_name
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.table_id
        WHERE o.customer_id = ?
        ORDER BY o.order_time DESC
        LIMIT 20
    ";
    $stmt = $conn->prepare($orders_query);
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Lấy lịch sử đặt bàn
    $reservations_query = "
        SELECT 
            r.reservation_id,
            r.reservation_time,
            r.number_of_people,
            r.status,
            t.table_name
        FROM reservations r
        LEFT JOIN tables t ON r.table_id = t.table_id
        LEFT JOIN customers c ON r.user_id = c.user_id
        WHERE c.customer_id = ?
        ORDER BY r.reservation_time DESC
        LIMIT 20
    ";
    $stmt = $conn->prepare($reservations_query);
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'customer' => $customer,
            'orders' => $orders,
            'reservations' => $reservations
        ]
    ]);
}

function getCustomer() {
    global $conn;
    $customer_id = $_GET['id'] ?? 0;
    
    $query = "SELECT * FROM customers WHERE customer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $customer]);
}

function updateCustomer() {
    global $conn;
    $customer_id = $_POST['id'] ?? 0;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($customer_name)) {
        echo json_encode(['success' => false, 'message' => 'Tên khách hàng không được để trống']);
        return;
    }
    
    // Kiểm tra trùng số điện thoại
    if ($phone) {
        $check_query = "SELECT customer_id FROM customers WHERE phone = ? AND customer_id != ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('si', $phone, $customer_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Số điện thoại đã tồn tại']);
            return;
        }
    }
    
    // Kiểm tra trùng email
    if ($email) {
        $check_query = "SELECT customer_id FROM customers WHERE email = ? AND customer_id != ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('si', $email, $customer_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email đã tồn tại']);
            return;
        }
    }
    
    // Cập nhật thông tin
    $update_query = "UPDATE customers SET customer_name = ?, phone = ?, email = ? WHERE customer_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('sssi', $customer_name, $phone, $email, $customer_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật: ' . $conn->error]);
    }
}


function updateOrderStatus() {
    global $conn;
    $order_id = $_POST['order_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    $valid_statuses = ['new', 'processing', 'serving', 'completed', 'paid', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
        return;
    }
    
    $query = "UPDATE orders SET status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $status, $order_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật: ' . $conn->error]);
    }
}

function updateReservationStatus() {
    global $conn;
    $reservation_id = $_POST['reservation_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    $valid_statuses = ['cho_xac_nhan', 'da_xac_nhan', 'hoan_thanh', 'da_huy'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
        return;
    }
    
    $query = "UPDATE reservations SET status = ? WHERE reservation_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $status, $reservation_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật: ' . $conn->error]);
    }
}

function deleteCustomer() {
    global $conn;
    $customer_id = (int)($_POST['id'] ?? 0);

    if (!$customer_id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }

    // Kiểm tra còn đơn hàng active không
    $check = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE customer_id=$customer_id AND status NOT IN ('da_thanh_toan','da_huy')");
    if ($check && (int)$check->fetch_assoc()['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Khách hàng còn đơn hàng chưa hoàn tất, không thể xóa']);
        return;
    }

    // Lấy user_id liên kết để xóa tài khoản
    $row = $conn->query("SELECT user_id FROM customers WHERE customer_id=$customer_id LIMIT 1")->fetch_assoc();

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM customers WHERE customer_id=$customer_id");
        if (!empty($row['user_id'])) {
            $conn->query("DELETE FROM users WHERE user_id={$row['user_id']}");
        }
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Đã xóa khách hàng thành công']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa: ' . $e->getMessage()]);
    }
}
