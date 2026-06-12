<?php
// Bắt đầu output buffering để tránh output không mong muốn
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Kiểm tra quyền truy cập
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role_name'], ['Admin', 'admin'])) {
    ob_end_clean(); // Xóa buffer
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Xóa buffer và set header
ob_end_clean();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

// Handle GET request for file download
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $filename = $_GET['filename'] ?? '';
    if (!preg_match('/^backup_qlnhahang_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
        die('Tên file không hợp lệ');
    }
    
    // Tìm file trong các thư mục có thể
    $possible_paths = [
        __DIR__ . '/../../backups/' . $filename,
        'D:/backups/' . $filename,
        'E:/saoluudulieu/' . $filename,
        'C:/backups/' . $filename
    ];
    
    $backup_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $backup_path = $path;
            break;
        }
    }
    
    if (!$backup_path) {
        die('File backup không tồn tại');
    }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($backup_path));
    readfile($backup_path);
    exit;
}

// Handle form POST for file download (legacy support)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_backup') {
    $filename = $_POST['filename'] ?? '';
    if (!preg_match('/^backup_qlnhahang_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
        die('Tên file không hợp lệ');
    }
    
    // Tìm file trong các thư mục có thể
    $possible_paths = [
        __DIR__ . '/../../backups/' . $filename,
        'D:/backups/' . $filename,
        'E:/saoluudulieu/' . $filename,
        'C:/backups/' . $filename
    ];
    
    $backup_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $backup_path = $path;
            break;
        }
    }
    
    if (!$backup_path) {
        die('File backup không tồn tại');
    }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($backup_path));
    readfile($backup_path);
    exit;
}

$action = $input['action'] ?? '';
$userId = (int)$_SESSION['user']['user_id'];

try {
    switch ($action) {
        case 'backup':
            // Lấy đường dẫn từ request hoặc dùng mặc định
            $backup_dir = $input['backup_path'] ?? __DIR__ . '/../../backups';
            
            // Tạo thư mục nếu chưa tồn tại
            if (!file_exists($backup_dir)) {
                if (!mkdir($backup_dir, 0777, true)) {
                    throw new Exception('Không thể tạo thư mục backup: ' . $backup_dir);
                }
            }
            
            $timestamp   = date('Y-m-d_H-i-s');
            $backup_file = "backup_qlnhahang_$timestamp.sql";
            $backup_path = "$backup_dir/$backup_file";
            
            // Tìm mysqldump
            $mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';
            if (!file_exists($mysqldump)) {
                $mysqldump = 'mysqldump'; // Thử dùng mysqldump trong PATH
            }
            
            // Thực hiện backup
            $command = "\"$mysqldump\" --host=127.0.0.1 --user=root --single-transaction qlnhahang > \"$backup_path\" 2>&1";
            exec($command, $out, $ret);
            
            if ($ret === 0 && file_exists($backup_path) && filesize($backup_path) > 0) {
                $size = round(filesize($backup_path)/1024/1024, 2);
                $details = "File: $backup_file ({$size}MB) - Lưu tại: $backup_dir";
                $stmt = $conn->prepare("INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details) VALUES ('backup', 0, ?, ?)");
                $stmt->bind_param('is', $userId, $details);
                $stmt->execute();
                echo json_encode(['success' => true, 'filename' => $backup_file, 'size' => $size, 'path' => $backup_dir]);
            } else {
                throw new Exception('Không thể tạo file backup. Output: ' . implode(' ', $out));
            }
            break;

        case 'check_health':
            $checks = ['✓ Kết nối CSDL: OK'];
            $checks[] = '✓ Số bàn: ' . $conn->query("SELECT COUNT(*) as c FROM tables")->fetch_assoc()['c'];
            $checks[] = '✓ Người dùng hoạt động: ' . $conn->query("SELECT COUNT(*) as c FROM users WHERE status='hoat_dong'")->fetch_assoc()['c'];
            $checks[] = '✓ Đơn hàng hôm nay: ' . $conn->query("SELECT COUNT(*) as c FROM orders WHERE DATE(order_time)=CURDATE()")->fetch_assoc()['c'];
            $details = implode(', ', $checks);
            $stmt = $conn->prepare("INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details) VALUES ('health_check', 0, ?, ?)");
            $stmt->bind_param('is', $userId, $details);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => implode("\n", $checks)]);
            break;

        case 'add_log':
            $details = $input['details'] ?? 'Ghi nhận log thủ công';
            $stmt = $conn->prepare("INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details) VALUES ('manual_log', 0, ?, ?)");
            $stmt->bind_param('is', $userId, $details);
            $stmt->execute();
            echo json_encode(['success' => true]);
            break;

        case 'clean_old_data':
            $six_months = date('Y-m-d', strtotime('-6 months'));
            $count = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status IN ('da_thanh_toan','da_huy') AND order_time < '$six_months'")->fetch_assoc()['c'];
            $conn->query("DELETE FROM orders WHERE status IN ('da_thanh_toan','da_huy') AND order_time < '$six_months'");
            $details = "Đã xóa $count đơn hàng cũ (trước $six_months)";
            $stmt = $conn->prepare("INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details) VALUES ('delete_orders', ?, ?, ?)");
            $stmt->bind_param('iis', $count, $userId, $details);
            $stmt->execute();
            echo json_encode(['success' => true, 'deleted_count' => $count]);
            break;

        case 'delete_all_logs':
            $conn->query("DELETE FROM data_operation_logs");
            $conn->query("ALTER TABLE data_operation_logs AUTO_INCREMENT = 1");
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Hành động không hợp lệ');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
