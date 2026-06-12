<?php
// Tắt hiển thị lỗi PHP
error_reporting(0);
ini_set('display_errors', '0');

// Bắt đầu output buffering
ob_start();

require_once __DIR__ . '/../../config/database.php';

// Khởi động session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xóa output buffer
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
            getEmployee();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getEmployee() {
    global $conn;
    
    $user_id = $_GET['user_id'] ?? 0;
    
    if (!$user_id) {
        throw new Exception('User ID không hợp lệ');
    }
    
    $stmt = $conn->prepare("
        SELECT user_id, full_name, phone, email, role_id, ngay_sinh, gioi_tinh
        FROM users 
        WHERE user_id = ? AND role_id IN (2,3,4,5)
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception('Không tìm thấy nhân viên');
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}
