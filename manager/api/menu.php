<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

// Kiểm tra quyền (admin hoặc quanly) - KHÔNG yêu cầu key cho API
$userRole = strtolower($_SESSION['user']['role_name'] ?? '');
if (!in_array($userRole, ['admin', 'quanly'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

header('Content-Type: application/json');

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/../../assets/images/menu/');
define('UPLOAD_URL_BASE', '/quanlynhahang/assets/images/menu/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);

// Helper function: Remove Vietnamese tones
function removeVietnameseTones($str) {
    $vietnamese = [
        'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
        'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
        'ì', 'í', 'ị', 'ỉ', 'ĩ',
        'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
        'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
        'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
        'đ',
        'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
        'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
        'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
        'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
        'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
        'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ',
        'Đ'
    ];
    
    $latin = [
        'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
        'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
        'i', 'i', 'i', 'i', 'i',
        'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
        'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
        'y', 'y', 'y', 'y', 'y',
        'd',
        'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A',
        'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
        'I', 'I', 'I', 'I', 'I',
        'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O',
        'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U',
        'Y', 'Y', 'Y', 'Y', 'Y',
        'D'
    ];
    
    return str_replace($vietnamese, $latin, $str);
}

// Helper function: Sanitize filename
function sanitizeFilename($filename) {
    // Chuyển về không dấu
    $filename = removeVietnameseTones($filename);
    
    // Chỉ giữ chữ cái, số, gạch ngang, gạch dưới
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    
    // Loại bỏ nhiều gạch dưới liên tiếp
    $filename = preg_replace('/_+/', '_', $filename);
    
    // Trim gạch dưới ở đầu và cuối
    $filename = trim($filename, '_');
    
    // Giới hạn độ dài
    if (strlen($filename) > 50) {
        $filename = substr($filename, 0, 50);
    }
    
    return $filename;
}

// Helper function: Generate unique filename
function generateUniqueFilename($originalName) {
    // Lấy extension
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Lấy tên file không có extension
    $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
    
    // Sanitize tên file
    $sanitized = sanitizeFilename($nameWithoutExt);
    
    // Tạo timestamp với microseconds
    $timestamp = microtime(true);
    $timestamp = str_replace('.', '', $timestamp);
    
    // Kết hợp
    return $timestamp . '_' . $sanitized . '.' . $ext;
}

// Helper function: Validate image file
function validateImageFile($file) {
    $result = ['valid' => true, 'error' => null];
    
    // Kiểm tra lỗi upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['valid'] = false;
        $result['error'] = 'Lỗi tải file lên server';
        return $result;
    }
    
    // Kiểm tra kích thước
    if ($file['size'] > MAX_FILE_SIZE) {
        $result['valid'] = false;
        $result['error'] = 'Kích thước file không được vượt quá 20MB';
        return $result;
    }
    
    // Kiểm tra MIME type thực tế
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        $result['valid'] = false;
        $result['error'] = 'Chỉ chấp nhận file ảnh định dạng JPG, JPEG, PNG, GIF';
        return $result;
    }
    
    // Kiểm tra extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $result['valid'] = false;
        $result['error'] = 'Chỉ chấp nhận file ảnh định dạng JPG, JPEG, PNG, GIF';
        return $result;
    }
    
    return $result;
}

// Helper function: Ensure upload directory exists
function ensureUploadDirectory() {
    if (!file_exists(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            throw new Exception('Không thể tạo thư mục lưu trữ');
        }
    }
    
    if (!is_writable(UPLOAD_DIR)) {
        throw new Exception('Thư mục lưu trữ không có quyền ghi');
    }
}

// Helper function: Delete image file
function deleteImageFile($imageUrl) {
    if (empty($imageUrl)) {
        return true;
    }
    
    // Chuyển URL thành đường dẫn file
    $filename = basename($imageUrl);
    $filePath = UPLOAD_DIR . $filename;
    
    // Xóa file nếu tồn tại
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    
    return true; // File không tồn tại, coi như đã xóa
}

// Helper function: Handle image upload
function handleImageUpload($fileInput, $oldImagePath = null) {
    // Kiểm tra file có tồn tại
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Không có file upload
    }
    
    // Validate file
    $validation = validateImageFile($_FILES[$fileInput]);
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Tạo tên file duy nhất
    $uniqueFilename = generateUniqueFilename($_FILES[$fileInput]['name']);
    
    // Đảm bảo thư mục tồn tại
    ensureUploadDirectory();
    
    // Xóa file cũ nếu có
    if ($oldImagePath) {
        deleteImageFile($oldImagePath);
    }
    
    // Lưu file mới
    $targetPath = UPLOAD_DIR . $uniqueFilename;
    if (!move_uploaded_file($_FILES[$fileInput]['tmp_name'], $targetPath)) {
        throw new Exception('Lỗi lưu file hình ảnh');
    }
    
    // Trả về đường dẫn tương đối
    return UPLOAD_URL_BASE . $uniqueFilename;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            getMenuItem();
            break;
        case 'create':
            createMenuItem();
            break;
        case 'update':
            updateMenuItem();
            break;
        case 'delete':
            deleteMenuItem();
            break;
        case 'toggleStatus':
            toggleMenuItemStatus();
            break;
        default:
            error_log("Invalid action received: " . $action);
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Menu API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getMenuItem() {
    global $conn;
    
    $item_id = $_GET['item_id'] ?? 0;
    
    if (!$item_id) {
        throw new Exception('Item ID không hợp lệ');
    }
    
    $stmt = $conn->prepare("
        SELECT item_id, item_name, category_id, price, description, image_url, status 
        FROM menu_items 
        WHERE item_id = ?
    ");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception('Không tìm thấy món ăn');
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function createMenuItem() {
    global $conn;
    
    // Đọc từ FormData thay vì JSON
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = $_POST['category_id'] ?? 0;
    $price = $_POST['price'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    
    if (!$item_name || !$category_id || $price <= 0) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    
    // Xử lý upload ảnh
    $imageUrl = null;
    try {
        $imageUrl = handleImageUpload('image');
    } catch (Exception $e) {
        throw new Exception($e->getMessage());
    }
    
    // Insert với image_url
    $stmt = $conn->prepare("
        INSERT INTO menu_items (item_name, category_id, price, description, image_url, status) 
        VALUES (?, ?, ?, ?, ?, 'con_hang')
    ");
    $stmt->bind_param('sidss', $item_name, $category_id, $price, $description, $imageUrl);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Thêm món thành công',
            'image_url' => $imageUrl
        ]);
    } else {
        // Rollback: xóa file đã upload nếu database insert thất bại
        if ($imageUrl) {
            deleteImageFile($imageUrl);
        }
        throw new Exception('Lỗi thêm món');
    }
}

function updateMenuItem() {
    global $conn;
    
    // Đọc từ FormData thay vì JSON
    $item_id = $_POST['item_id'] ?? 0;
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = $_POST['category_id'] ?? 0;
    $price = $_POST['price'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    $currentImage = $_POST['current_image'] ?? '';
    
    if (!$item_id || !$item_name || !$category_id || $price <= 0) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    
    // Xử lý upload ảnh mới (nếu có)
    $imageUrl = $currentImage; // Giữ nguyên ảnh cũ
    try {
        $newImageUrl = handleImageUpload('image', $currentImage);
        if ($newImageUrl !== null) {
            $imageUrl = $newImageUrl; // Cập nhật ảnh mới
        }
    } catch (Exception $e) {
        throw new Exception($e->getMessage());
    }
    
    // Update với image_url
    $stmt = $conn->prepare("
        UPDATE menu_items 
        SET item_name = ?, category_id = ?, price = ?, description = ?, image_url = ?
        WHERE item_id = ?
    ");
    $stmt->bind_param('sidssi', $item_name, $category_id, $price, $description, $imageUrl, $item_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Cập nhật món thành công',
            'image_url' => $imageUrl
        ]);
    } else {
        // Rollback: xóa file mới đã upload nếu database update thất bại
        if ($imageUrl !== $currentImage && $imageUrl) {
            deleteImageFile($imageUrl);
        }
        throw new Exception('Lỗi cập nhật món');
    }
}

function deleteMenuItem() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $item_id = $data['item_id'] ?? 0;
    
    if (!$item_id) {
        throw new Exception('Item ID không hợp lệ');
    }
    
    // Check if item is in any orders
    $check = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM order_details 
        WHERE item_id = ?
    ");
    $check->bind_param('i', $item_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        throw new Exception('Không thể xóa món đã có trong đơn hàng. Bạn có thể đánh dấu hết món thay vì xóa.');
    }
    
    // Lấy image_url trước khi xóa
    $getImage = $conn->prepare("SELECT image_url FROM menu_items WHERE item_id = ?");
    $getImage->bind_param('i', $item_id);
    $getImage->execute();
    $imageResult = $getImage->get_result()->fetch_assoc();
    $imageUrl = $imageResult['image_url'] ?? null;
    
    // Xóa món ăn
    $stmt = $conn->prepare("DELETE FROM menu_items WHERE item_id = ?");
    $stmt->bind_param('i', $item_id);
    
    if ($stmt->execute()) {
        // Xóa file ảnh nếu có
        if ($imageUrl) {
            deleteImageFile($imageUrl);
        }
        echo json_encode(['success' => true, 'message' => 'Xóa món thành công']);
    } else {
        throw new Exception('Lỗi xóa món');
    }
}

function toggleMenuItemStatus() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $item_id = $data['item_id'] ?? 0;
    $new_status = $data['status'] ?? '';
    
    if (!$item_id || !in_array($new_status, ['con_hang', 'het_hang'])) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    
    $stmt = $conn->prepare("UPDATE menu_items SET status = ? WHERE item_id = ?");
    $stmt->bind_param('si', $new_status, $item_id);
    
    if ($stmt->execute()) {
        $message = $new_status === 'con_hang' ? 'Đã đánh dấu còn món' : 'Đã đánh dấu hết món';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        throw new Exception('Lỗi cập nhật trạng thái');
    }
}
