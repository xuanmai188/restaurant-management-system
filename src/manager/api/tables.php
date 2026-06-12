<?php
// Tắt hiển thị lỗi PHP để đảm bảo JSON response sạch
error_reporting(0);
ini_set('display_errors', '0');

// Bắt đầu output buffering
ob_start();

require_once __DIR__ . '/../../config/database.php';

// Khởi động session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xóa mọi output trước đó
ob_end_clean();
ob_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

// Kiểm tra quyền (admin hoặc quanly)
$userRole = strtolower($_SESSION['user']['role_name'] ?? '');
if (!in_array($userRole, ['admin', 'quanly'])) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Xóa output buffer và set header
ob_end_clean();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            getTable();
            break;
        case 'create':
            createTable();
            break;
        case 'update':
            updateTable();
            break;
        case 'delete':
            deleteTable();
            break;
        case 'detail':
            getTableDetail();
            break;
        case 'createFloor':
            createFloor();
            break;
        case 'deleteFloor':
            deleteFloor();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getTable() {
    global $conn;
    
    $table_id = $_GET['table_id'] ?? 0;
    
    if (!$table_id) {
        throw new Exception('Table ID không hợp lệ');
    }
    
    $stmt = $conn->prepare("
        SELECT table_id, floor_id, table_name, capacity, status 
        FROM tables 
        WHERE table_id = ?
    ");
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception('Không tìm thấy bàn');
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function createTable() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $table_name = trim($data['table_name'] ?? '');
    $floor_id = $data['floor_id'] ?? 0;
    $capacity = $data['capacity'] ?? 0;
    $status = $data['status'] ?? 'trong';
    
    if (!$table_name || !$floor_id || $capacity <= 0) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO tables (floor_id, table_name, capacity, status) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('isis', $floor_id, $table_name, $capacity, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Thêm bàn thành công']);
    } else {
        throw new Exception('Lỗi thêm bàn');
    }
}

function updateTable() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $table_id = $data['table_id'] ?? 0;
    $table_name = trim($data['table_name'] ?? '');
    $floor_id = $data['floor_id'] ?? 0;
    $capacity = $data['capacity'] ?? 0;
    $status = $data['status'] ?? 'auto';
    
    if (!$table_id || !$table_name || !$floor_id || $capacity <= 0) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    
    // Nếu status = "auto", tính toán trạng thái tự động
    if ($status === 'auto') {
        // Kiểm tra có order active không
        $orderCheck = $conn->query("
            SELECT COUNT(*) as cnt FROM orders 
            WHERE table_id = $table_id 
              AND status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
              AND DATE(order_time) = CURDATE()
        ")->fetch_assoc();
        
        if ($orderCheck['cnt'] > 0) {
            $status = 'dang_su_dung';
        } else {
            // Kiểm tra có reservation hôm nay không
            $resCheck = $conn->query("
                SELECT COUNT(*) as cnt FROM reservations 
                WHERE table_id = $table_id AND status IN ('da_xac_nhan','cho_xac_nhan')
                AND DATE(reservation_time) = CURDATE()
            ")->fetch_assoc();
            
            if ($resCheck['cnt'] > 0) {
                $status = 'da_dat';
            } else {
                $status = 'trong';
            }
        }
    }
    
    $stmt = $conn->prepare("
        UPDATE tables 
        SET floor_id = ?, table_name = ?, capacity = ?, status = ?
        WHERE table_id = ?
    ");
    $stmt->bind_param('isisi', $floor_id, $table_name, $capacity, $status, $table_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật bàn thành công']);
    } else {
        throw new Exception('Lỗi cập nhật bàn');
    }
}

function deleteTable() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $table_id = $data['table_id'] ?? 0;
    
    if (!$table_id) {
        throw new Exception('Table ID không hợp lệ');
    }
    
    // Check if table has active orders
    $check = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE table_id = ? AND status IN ('moi', 'dang_xu_ly', 'dang_phuc_vu')
    ");
    $check->bind_param('i', $table_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        throw new Exception('Không thể xóa bàn đang có đơn hàng');
    }
    
    $stmt = $conn->prepare("DELETE FROM tables WHERE table_id = ?");
    $stmt->bind_param('i', $table_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Xóa bàn thành công']);
    } else {
        throw new Exception('Lỗi xóa bàn');
    }
}

function getTableDetail() {
    global $conn;
    
    $table_id = $_GET['table_id'] ?? 0;
    
    if (!$table_id) {
        throw new Exception('Table ID không hợp lệ');
    }
    
    // Get table info
    $table_stmt = $conn->prepare("
        SELECT t.*, f.floor_name 
        FROM tables t
        LEFT JOIN floors f ON f.floor_id = t.floor_id
        WHERE t.table_id = ?
    ");
    $table_stmt->bind_param('i', $table_id);
    $table_stmt->execute();
    $table = $table_stmt->get_result()->fetch_assoc();
    
    if (!$table) {
        throw new Exception('Không tìm thấy bàn');
    }
    
    // Get current order with customer info
    $order_stmt = $conn->prepare("
        SELECT o.*, 
               o.guest_count,
               CASE 
                   WHEN c.customer_id IS NOT NULL THEN c.customer_name
                   WHEN u.user_id IS NOT NULL THEN u.full_name
                   ELSE 'Khách vãng lai'
               END as customer_name
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        LEFT JOIN users u ON u.user_id = o.customer_id AND c.customer_id IS NULL
        WHERE o.table_id = ? AND o.status IN ('moi', 'dang_xu_ly', 'dang_phuc_vu', 'hoan_thanh')
        AND DATE(o.order_time) = CURDATE()
        ORDER BY o.order_time DESC
        LIMIT 1
    ");
    $order_stmt->bind_param('i', $table_id);
    $order_stmt->execute();
    $order = $order_stmt->get_result()->fetch_assoc();
    
    // Get order items if order exists
    $order_items = [];
    if ($order) {
        $items_stmt = $conn->prepare("
            SELECT od.order_detail_id, od.item_id, od.quantity, od.unit_price, od.note,
                   mi.item_name, mi.image_url
            FROM order_details od
            JOIN menu_items mi ON mi.item_id = od.item_id
            WHERE od.order_id = ?
            ORDER BY od.order_detail_id ASC
        ");
        $items_stmt->bind_param('i', $order['order_id']);
        $items_stmt->execute();
        $order_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get reservations
    $res_stmt = $conn->prepare("
        SELECT r.*, 
               COALESCE(u.full_name, 'Khách vãng lai') as customer_name,
               rp.amount AS deposit_amount,
               rp.payment_type,
               rp.payment_percent,
               rp.payment_status AS deposit_status
        FROM reservations r
        LEFT JOIN users u ON u.user_id = r.user_id
        LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
        WHERE r.table_id = ? AND r.status IN ('da_xac_nhan', 'cho_xac_nhan')
        AND DATE(r.reservation_time) = CURDATE()
        ORDER BY r.reservation_time ASC
    ");
    $res_stmt->bind_param('i', $table_id);
    $res_stmt->execute();
    $reservations = $res_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'table' => $table,
            'order' => $order,
            'order_items' => $order_items,
            'reservations' => $reservations
        ]
    ]);
}

function createFloor() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $floor_name = trim($data['floor_name'] ?? '');
    
    if (!$floor_name) {
        throw new Exception('Tên tầng không được để trống');
    }
    
    $stmt = $conn->prepare("INSERT INTO floors (floor_name) VALUES (?)");
    $stmt->bind_param('s', $floor_name);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tạo tầng thành công']);
    } else {
        throw new Exception('Lỗi tạo tầng');
    }
}

function deleteFloor() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $floor_id = $data['floor_id'] ?? 0;
    
    if (!$floor_id) {
        throw new Exception('Floor ID không hợp lệ');
    }
    
    // Kiểm tra xem tầng có bàn nào không
    $check = $conn->prepare("SELECT COUNT(*) as count FROM tables WHERE floor_id = ?");
    $check->bind_param('i', $floor_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        throw new Exception('Không thể xóa tầng đang có bàn. Vui lòng xóa hết bàn trước.');
    }
    
    $stmt = $conn->prepare("DELETE FROM floors WHERE floor_id = ?");
    $stmt->bind_param('i', $floor_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Xóa tầng thành công']);
    } else {
        throw new Exception('Lỗi xóa tầng');
    }
}
