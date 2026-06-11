<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly', 'admin']);

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
        'D:/saoluudulieu/' . $filename,
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
        'D:/saoluudulieu/' . $filename,
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

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
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
            
            $timestamp = date('Y-m-d_H-i-s');
            $backup_file = "backup_qlnhahang_$timestamp.sql";
            $backup_path = "$backup_dir/$backup_file";
            
            // Database credentials
            $host = '127.0.0.1';
            $dbname = 'qlnhahang';
            $username = 'root';
            $password = '';
            
            // Tìm mysqldump
            $mysqldump_path = 'C:/xampp/mysql/bin/mysqldump.exe';
            if (!file_exists($mysqldump_path)) {
                // Try alternative paths
                $alt_paths = [
                    'C:/wamp64/bin/mysql/mysql8.0.31/bin/mysqldump.exe',
                    'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe',
                    'mysqldump' // Try system PATH
                ];
                
                foreach ($alt_paths as $path) {
                    if (file_exists($path) || $path === 'mysqldump') {
                        $mysqldump_path = $path;
                        break;
                    }
                }
            }
            
            $command = "\"$mysqldump_path\" --host=$host --user=$username --single-transaction --routines --triggers $dbname > \"$backup_path\" 2>&1";
            
            // Execute backup
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($backup_path) && filesize($backup_path) > 0) {
                $file_size = filesize($backup_path);
                $file_size_mb = round($file_size / 1024 / 1024, 2);
                
                // Log the operation with filename
                $details = "File: $backup_file (${file_size_mb}MB) - Lưu tại: $backup_dir";
                $stmt = $conn->prepare("
                    INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details)
                    VALUES ('backup', 0, ?, ?)
                ");
                $stmt->bind_param('is', $userId, $details);
                
                if (!$stmt->execute()) {
                    error_log("Failed to insert backup log: " . $stmt->error);
                    throw new Exception("Sao lưu thành công nhưng không thể ghi log: " . $stmt->error);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Sao lưu dữ liệu thành công',
                    'filename' => $backup_file,
                    'size' => $file_size_mb,
                    'path' => $backup_dir,
                    'log_inserted' => true
                ]);
            } else {
                $error_msg = 'Không thể tạo file backup.';
                if (!empty($output)) {
                    $error_msg .= ' Lỗi: ' . implode("\n", $output);
                }
                if (!file_exists($mysqldump_path) && $mysqldump_path !== 'mysqldump') {
                    $error_msg .= ' (mysqldump không tìm thấy tại: ' . $mysqldump_path . ')';
                }
                throw new Exception($error_msg);
            }
            break;
            
        case 'check_health':
            // Check system health
            $checks = [];
            
            // Check database connection
            $checks[] = '✓ Kết nối CSDL: OK';
            
            // Check tables count
            $result = $conn->query("SELECT COUNT(*) as count FROM tables");
            $table_count = $result->fetch_assoc()['count'];
            $checks[] = "✓ Số bàn: $table_count";
            
            // Check active users
            $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'hoat_dong'");
            $user_count = $result->fetch_assoc()['count'];
            $checks[] = "✓ Người dùng hoạt động: $user_count";
            
            // Check orders today
            $todayStart = date('Y-m-d') . ' 00:00:00';
            $todayEnd   = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
            $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_time >= '$todayStart' AND order_time < '$todayEnd'");
            $orders_today = $result->fetch_assoc()['count'];
            $checks[] = "✓ Đơn hàng hôm nay: $orders_today";
            
            // Log the health check
            $details = implode(", ", $checks);
            $stmt = $conn->prepare("
                INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details)
                VALUES ('health_check', 0, ?, ?)
            ");
            $stmt->bind_param('is', $userId, $details);
            
            if (!$stmt->execute()) {
                error_log("Failed to insert health_check log: " . $stmt->error);
                throw new Exception("Kiểm tra thành công nhưng không thể ghi log: " . $stmt->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => implode("\n", $checks),
                'log_inserted' => true
            ]);
            break;
            
        case 'add_log':
            $details = $input['details'] ?? 'Ghi nhận log thủ công';
            
            $stmt = $conn->prepare("
                INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details)
                VALUES ('manual_log', 0, ?, ?)
            ");
            $stmt->bind_param('is', $userId, $details);
            
            if (!$stmt->execute()) {
                error_log("Failed to insert manual_log: " . $stmt->error);
                throw new Exception("Không thể ghi log: " . $stmt->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Ghi nhận log thành công',
                'log_inserted' => true
            ]);
            break;
            
        case 'clean_old_data':
            // Delete old completed orders (older than 6 months)
            $six_months_ago = date('Y-m-d', strtotime('-6 months'));
            
            // Count orders to delete
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM orders 
                WHERE status IN ('paid', 'cancelled') 
                AND order_time < ?
            ");
            $stmt->bind_param('s', $six_months_ago);
            $stmt->execute();
            $result = $stmt->get_result();
            $delete_count = $result->fetch_assoc()['count'];
            
            // Delete old orders
            $stmt = $conn->prepare("
                DELETE FROM orders 
                WHERE status IN ('paid', 'cancelled') 
                AND order_time < ?
            ");
            $stmt->bind_param('s', $six_months_ago);
            $stmt->execute();
            
            // Log the operation
            $details = "Đã xóa $delete_count đơn hàng cũ (trước $six_months_ago)";
            $stmt = $conn->prepare("
                INSERT INTO data_operation_logs (action_type, deleted_count, performed_by, details)
                VALUES ('delete_orders', ?, ?, ?)
            ");
            $stmt->bind_param('iis', $delete_count, $userId, $details);
            
            if (!$stmt->execute()) {
                error_log("Failed to insert delete_orders log: " . $stmt->error);
                throw new Exception("Dọn dẹp thành công nhưng không thể ghi log: " . $stmt->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Dọn dẹp dữ liệu thành công',
                'deleted_count' => $delete_count,
                'log_inserted' => true
            ]);
            break;
            
        case 'delete_log':
            $log_id = (int)($input['log_id'] ?? 0);
            
            if ($log_id <= 0) {
                throw new Exception('ID log không hợp lệ');
            }
            
            // Delete log entry
            $stmt = $conn->prepare("DELETE FROM data_operation_logs WHERE log_id = ?");
            $stmt->bind_param('i', $log_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Xóa log thành công'
                ]);
            } else {
                throw new Exception('Không tìm thấy log để xóa');
            }
            break;
            
        case 'delete_all_logs':
            // Delete all logs
            $conn->query("DELETE FROM data_operation_logs");
            
            // Reset AUTO_INCREMENT to 1
            $conn->query("ALTER TABLE data_operation_logs AUTO_INCREMENT = 1");
            
            echo json_encode([
                'success' => true,
                'message' => 'Đã xóa tất cả log và reset ID về 1'
            ]);
            break;
            
        default:
            throw new Exception('Hành động không hợp lệ');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
