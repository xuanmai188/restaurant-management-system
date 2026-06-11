<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Xác thực quyền Manager hoặc Admin
require_role(['quanly', 'admin']);

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'detail':
            getOrderDetail();
            break;
        case 'create':
            createOrder();
            break;
        case 'get_tables':
            getAvailableTables();
            break;
        case 'get_customers':
            getCustomers();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getOrderDetail() {
    global $conn;
    
    $order_id = $_GET['order_id'] ?? 0;
    
    if (!$order_id) {
        throw new Exception('Order ID không hợp lệ');
    }
    
    // Get order info
    $order_stmt = $conn->prepare("
        SELECT 
            o.*,
            t.table_name,
            f.floor_name,
            c.customer_name
        FROM orders o
        LEFT JOIN tables t ON t.table_id = o.table_id
        LEFT JOIN floors f ON f.floor_id = t.floor_id
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        WHERE o.order_id = ?
    ");
    $order_stmt->bind_param('i', $order_id);
    $order_stmt->execute();
    $order = $order_stmt->get_result()->fetch_assoc();
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Get order items
    $items_stmt = $conn->prepare("
        SELECT 
            od.order_detail_id,
            od.order_id,
            od.item_id,
            od.quantity,
            od.unit_price,
            od.note,
            od.item_status,
            m.item_name
        FROM order_details od
        LEFT JOIN menu_items m ON m.item_id = od.item_id
        WHERE od.order_id = ?
        ORDER BY od.order_detail_id
    ");
    $items_stmt->bind_param('i', $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'order' => $order,
            'items' => $items
        ]
    ]);
}

function getAvailableTables() {
    global $conn;
    $result = $conn->query("
        SELECT t.table_id, t.table_name, t.capacity, f.floor_name
        FROM tables t
        LEFT JOIN floors f ON f.floor_id = t.floor_id
        LEFT JOIN orders o ON o.table_id = t.table_id
            AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
        WHERE o.order_id IS NULL AND t.status NOT IN ('bao_tri')
        ORDER BY f.floor_name, t.table_name
    ");
    $tables = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $tables]);
}

function getCustomers() {
    global $conn;
    $search = $conn->real_escape_string($_GET['q'] ?? '');
    $where = $search ? "WHERE customer_name LIKE '%$search%' OR phone LIKE '%$search%'" : '';
    $result = $conn->query("SELECT customer_id, customer_name, phone FROM customers $where ORDER BY customer_name LIMIT 30");
    $customers = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $customers]);
}

function createOrder() {
    global $conn;
    $data = json_decode(file_get_contents('php://input'), true);

    $table_id    = (int)($data['table_id'] ?? 0);
    $customer_id = $data['customer_id'] ? (int)$data['customer_id'] : null;
    $guest_count = (int)($data['guest_count'] ?? 1);
    $manager_id  = (int)($_SESSION['user']['user_id'] ?? 0);

    if ($table_id <= 0) throw new Exception('Vui lòng chọn bàn');
    if ($guest_count <= 0) throw new Exception('Số người không hợp lệ');

    // Kiểm tra bàn còn trống không
    $conn->begin_transaction();
    $lock = $conn->prepare("SELECT table_id FROM tables WHERE table_id = ? LIMIT 1 FOR UPDATE");
    $lock->bind_param('i', $table_id);
    $lock->execute();
    if ($lock->get_result()->num_rows === 0) {
        $conn->rollback();
        throw new Exception('Ban khong ton tai');
    }
    $lock->close();

    $chk = $conn->query("SELECT order_id FROM orders WHERE table_id=$table_id AND status IN ('moi','dang_xu_ly','dang_phuc_vu') LIMIT 1");
    if ($chk && $chk->num_rows > 0) {
        $conn->rollback();
        throw new Exception('Bàn này đang có đơn hàng chưa hoàn thành');
    }

    $stmt = $conn->prepare("INSERT INTO orders (table_id, customer_id, waiter_id, status, total_amount, guest_count) VALUES (?, ?, ?, 'moi', 0, ?)");
    $stmt->bind_param('iiii', $table_id, $customer_id, $manager_id, $guest_count);
    if (!$stmt->execute()) throw new Exception('Lỗi tạo đơn: ' . $conn->error);

    $new_order_id = $conn->insert_id;
    $conn->query("UPDATE tables SET status='dang_su_dung' WHERE table_id=$table_id");
    $conn->commit();

    echo json_encode(['success' => true, 'order_id' => $new_order_id, 'message' => "Tạo đơn #$new_order_id thành công"]);
}
