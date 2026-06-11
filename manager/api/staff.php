<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly', 'admin']);

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Lấy thông tin nhân viên
            $userId = (int)($_GET['id'] ?? 0);
            
            if ($userId <= 0) {
                throw new Exception('ID nhân viên không hợp lệ');
            }
            
            $stmt = $conn->prepare("
                SELECT u.user_id, u.full_name, u.username, u.email, u.phone, u.ngay_sinh, u.gioi_tinh, u.role_id, r.role_name
                FROM users u
                LEFT JOIN roles r ON r.role_id = u.role_id
                WHERE u.user_id = ?
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            
            if (!$staff) {
                throw new Exception('Không tìm thấy nhân viên');
            }
            
            echo json_encode([
                'success' => true,
                'staff' => $staff
            ]);
            break;
            
        case 'create':
            // Thêm nhân viên mới
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $roleId = (int)($_POST['role_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $ngaySinh = trim($_POST['ngay_sinh'] ?? '') ?: null;
            $gioiTinh = trim($_POST['gioi_tinh'] ?? '') ?: null;
            
            // Validate
            if (empty($fullName) || empty($username) || empty($password)) {
                throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc');
            }
            
            if ($roleId <= 0) {
                throw new Exception('Vui lòng chọn vai trò');
            }
            
            // Validate số điện thoại
            if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
                throw new Exception('Số điện thoại phải có đúng 10 chữ số');
            }
            
            // Kiểm tra username đã tồn tại
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('Tên đăng nhập đã tồn tại');
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert
            $stmt = $conn->prepare("
                INSERT INTO users (full_name, username, password, role_id, email, phone, ngay_sinh, gioi_tinh, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'hoat_dong')
            ");
            $stmt->bind_param('sssissss', $fullName, $username, $hashedPassword, $roleId, $email, $phone, $ngaySinh, $gioiTinh);
            
            if (!$stmt->execute()) {
                throw new Exception('Không thể thêm nhân viên: ' . $conn->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Thêm nhân viên thành công'
            ]);
            break;
            
        case 'update':
            // Cập nhật nhân viên
            $userId = (int)($_POST['staff_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $roleId = (int)($_POST['role_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $ngaySinh = trim($_POST['ngay_sinh'] ?? '') ?: null;
            $gioiTinh = trim($_POST['gioi_tinh'] ?? '') ?: null;
            
            // Validate
            if ($userId <= 0) {
                throw new Exception('ID nhân viên không hợp lệ');
            }
            
            if (empty($fullName) || empty($username)) {
                throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc');
            }
            
            if ($roleId <= 0) {
                throw new Exception('Vui lòng chọn vai trò');
            }
            
            // Validate số điện thoại
            if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
                throw new Exception('Số điện thoại phải có đúng 10 chữ số');
            }
            
            // Kiểm tra username đã tồn tại (trừ user hiện tại)
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->bind_param('si', $username, $userId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('Tên đăng nhập đã tồn tại');
            }
            
            // Lấy role_id cũ để ghi log
            $stmt = $conn->prepare("SELECT role_id FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $oldData = $result->fetch_assoc();
            $oldRoleId = $oldData['role_id'] ?? 0;
            
            // Update
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, password = ?, role_id = ?, email = ?, phone = ?, ngay_sinh = ?, gioi_tinh = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param('sssissssi', $fullName, $username, $hashedPassword, $roleId, $email, $phone, $ngaySinh, $gioiTinh, $userId);
            } else {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, role_id = ?, email = ?, phone = ?, ngay_sinh = ?, gioi_tinh = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param('ssissssi', $fullName, $username, $roleId, $email, $phone, $ngaySinh, $gioiTinh, $userId);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Không thể cập nhật nhân viên: ' . $conn->error);
            }
            
            // Ghi log nếu role thay đổi
            if ($oldRoleId != $roleId) {
                log_role_change($userId, $oldRoleId, $roleId, "Cập nhật vai trò từ quản lý");
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Cập nhật nhân viên thành công'
            ]);
            break;
            
        case 'delete':
            // Xóa nhân viên
            $userId = (int)($_POST['staff_id'] ?? 0);
            
            if ($userId <= 0) {
                throw new Exception('ID nhân viên không hợp lệ');
            }
            
            // Kiểm tra không được xóa admin hoặc quản lý
            $stmt = $conn->prepare("
                SELECT r.role_name 
                FROM users u
                LEFT JOIN roles r ON r.role_id = u.role_id
                WHERE u.user_id = ?
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user) {
                throw new Exception('Không tìm thấy nhân viên');
            }
            
            if (in_array(strtolower($user['role_name']), ['admin', 'quanly'])) {
                throw new Exception('Không thể xóa tài khoản Admin hoặc Quản lý');
            }
            
            // Xóa (hoặc set status = 'inactive')
            $stmt = $conn->prepare("UPDATE users SET status = 'khoa' WHERE user_id = ?");
            $stmt->bind_param('i', $userId);
            
            if (!$stmt->execute()) {
                throw new Exception('Không thể xóa nhân viên: ' . $conn->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Xóa nhân viên thành công'
            ]);
            break;
            
        case 'get_schedule':
            // Lấy lịch làm việc và ngày nghỉ
            $userId = (int)($_GET['id'] ?? 0);
            $weekStartDate = $_GET['week_start_date'] ?? null;
            
            if ($userId <= 0) {
                throw new Exception('ID nhân viên không hợp lệ');
            }
            
            // Lấy ca làm việc
            // Ưu tiên ca của tuần cụ thể, nếu không có thì lấy ca chung
            if ($weekStartDate !== null && !empty($weekStartDate)) {
                $stmt = $conn->prepare("
                    SELECT day_of_week, start_time, end_time, week_start_date
                    FROM staff_shifts
                    WHERE user_id = ? AND week_start_date = ?
                ");
                $stmt->bind_param('is', $userId, $weekStartDate);
            } else {
                $stmt = $conn->prepare("
                    SELECT day_of_week, start_time, end_time, week_start_date
                    FROM staff_shifts
                    WHERE user_id = ? AND week_start_date IS NULL
                ");
                $stmt->bind_param('i', $userId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $shifts = [];
            while ($row = $result->fetch_assoc()) {
                $shifts[$row['day_of_week']] = [
                    'start' => substr($row['start_time'], 0, 5),
                    'end' => substr($row['end_time'], 0, 5)
                ];
            }
            
            // Lấy ngày nghỉ
            $stmt = $conn->prepare("
                SELECT day_off_id as id, start_date, end_date, reason, status
                FROM staff_days_off
                WHERE user_id = ?
                ORDER BY start_date DESC
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $daysOff = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode([
                'success' => true,
                'shifts' => $shifts,
                'daysOff' => $daysOff
            ]);
            break;
            
        case 'save_shifts':
            // Lưu ca làm việc
            $userId = (int)($_POST['staff_id'] ?? 0);
            $shiftsJson = $_POST['shifts'] ?? '{}';
            $shifts = json_decode($shiftsJson, true);
            $weekStartDate = $_POST['week_start_date'] ?? null;
            
            if ($userId <= 0) {
                throw new Exception('ID nhân viên không hợp lệ');
            }
            
            // Validate week_start_date if provided
            if ($weekStartDate !== null && !empty($weekStartDate)) {
                $date = new DateTime($weekStartDate);
                // Check if it's Monday (1 = Monday in PHP)
                if ($date->format('N') != 1) {
                    throw new Exception('Ngày bắt đầu tuần phải là thứ 2');
                }
            } else {
                $weekStartDate = null;
            }
            
            // Xóa tất cả ca cũ cho tuần này (hoặc tất cả nếu weekStartDate = null)
            if ($weekStartDate === null) {
                // Xóa tất cả ca chung (week_start_date IS NULL)
                $stmt = $conn->prepare("DELETE FROM staff_shifts WHERE user_id = ? AND week_start_date IS NULL");
                $stmt->bind_param('i', $userId);
            } else {
                // Xóa ca của tuần cụ thể
                $stmt = $conn->prepare("DELETE FROM staff_shifts WHERE user_id = ? AND week_start_date = ?");
                $stmt->bind_param('is', $userId, $weekStartDate);
            }
            $stmt->execute();
            
            // Thêm ca mới
            if (!empty($shifts)) {
                $stmt = $conn->prepare("
                    INSERT INTO staff_shifts (user_id, day_of_week, start_time, end_time, week_start_date)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($shifts as $day => $times) {
                    $stmt->bind_param('issss', $userId, $day, $times['start'], $times['end'], $weekStartDate);
                    $stmt->execute();
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Lưu lịch làm việc thành công'
            ]);
            break;
            
        case 'add_day_off':
            // Thêm ngày nghỉ
            $userId = (int)($_POST['staff_id'] ?? 0);
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            
            if ($userId <= 0) {
                throw new Exception('ID nhân viên không hợp lệ');
            }
            
            if (empty($startDate) || empty($endDate)) {
                throw new Exception('Vui lòng chọn ngày bắt đầu và kết thúc');
            }
            
            // Kiểm tra ngày kết thúc >= ngày bắt đầu
            if (strtotime($endDate) < strtotime($startDate)) {
                throw new Exception('Ngày kết thúc phải sau hoặc bằng ngày bắt đầu');
            }
            
            $stmt = $conn->prepare("
                INSERT INTO staff_days_off (user_id, start_date, end_date, reason)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('isss', $userId, $startDate, $endDate, $reason);
            
            if (!$stmt->execute()) {
                throw new Exception('Không thể thêm ngày nghỉ: ' . $conn->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Thêm ngày nghỉ thành công'
            ]);
            break;
            
        case 'delete_day_off':
            // Xóa ngày nghỉ
            $dayOffId = (int)($_POST['day_off_id'] ?? 0);
            
            if ($dayOffId <= 0) {
                throw new Exception('ID ngày nghỉ không hợp lệ');
            }
            
            $stmt = $conn->prepare("DELETE FROM staff_days_off WHERE day_off_id = ?");
            $stmt->bind_param('i', $dayOffId);
            
            if (!$stmt->execute()) {
                throw new Exception('Không thể xóa ngày nghỉ: ' . $conn->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Xóa ngày nghỉ thành công'
            ]);
            break;
            
        case 'get_work_history':
            // Lấy lịch sử làm việc
            $userId = (int)($_GET['id'] ?? 0);
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';
            
            if ($userId <= 0) {
                throw new Exception('ID nhân viên không hợp lệ');
            }
            
            if (empty($startDate) || empty($endDate)) {
                throw new Exception('Vui lòng chọn khoảng thời gian');
            }
            
            // Tạo danh sách các ngày trong khoảng thời gian
            $history = [];
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
            
            // Lấy tất cả ca làm việc của nhân viên (cả chung và cụ thể)
            $stmt = $conn->prepare("
                SELECT day_of_week, start_time, end_time, week_start_date
                FROM staff_shifts
                WHERE user_id = ?
                ORDER BY week_start_date DESC
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Tổ chức ca làm việc theo tuần
            $generalShifts = [];
            $weeklyShifts = [];
            
            while ($row = $result->fetch_assoc()) {
                if ($row['week_start_date'] === null) {
                    // Ca chung cho tất cả các tuần
                    $generalShifts[$row['day_of_week']] = [
                        'start' => $row['start_time'],
                        'end' => $row['end_time']
                    ];
                } else {
                    // Ca cho tuần cụ thể
                    $weekKey = $row['week_start_date'];
                    if (!isset($weeklyShifts[$weekKey])) {
                        $weeklyShifts[$weekKey] = [];
                    }
                    $weeklyShifts[$weekKey][$row['day_of_week']] = [
                        'start' => $row['start_time'],
                        'end' => $row['end_time']
                    ];
                }
            }
            
            // Lấy danh sách ngày nghỉ
            $stmt = $conn->prepare("
                SELECT start_date, end_date
                FROM staff_days_off
                WHERE user_id = ?
                AND status = 'da_duyet'
                AND start_date <= ?
                AND end_date >= ?
            ");
            $stmt->bind_param('iss', $userId, $endDate, $startDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $daysOff = [];
            while ($row = $result->fetch_assoc()) {
                $offStart = new DateTime($row['start_date']);
                $offEnd = new DateTime($row['end_date']);
                $offPeriod = new DatePeriod($offStart, $interval, $offEnd->modify('+1 day'));
                
                foreach ($offPeriod as $date) {
                    $daysOff[$date->format('Y-m-d')] = true;
                }
            }
            
            // Map day of week
            $dayMap = [
                0 => 'sun',
                1 => 'mon',
                2 => 'tue',
                3 => 'wed',
                4 => 'thu',
                5 => 'fri',
                6 => 'sat'
            ];
            
            $totalShifts = 0;
            $totalMinutes = 0;
            
            // Tạo lịch sử làm việc
            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $dayOfWeek = $dayMap[$date->format('w')];
                
                // Kiểm tra có nghỉ không
                if (isset($daysOff[$dateStr])) {
                    continue;
                }
                
                // Tìm tuần bắt đầu của ngày này (thứ 2 của tuần)
                $dayNum = $date->format('N'); // 1 = Monday, 7 = Sunday
                $daysFromMonday = $dayNum - 1;
                $weekStart = clone $date;
                $weekStart->modify("-{$daysFromMonday} days");
                $weekStartStr = $weekStart->format('Y-m-d');
                
                // Ưu tiên ca của tuần cụ thể, nếu không có thì dùng ca chung
                $shift = null;
                if (isset($weeklyShifts[$weekStartStr][$dayOfWeek])) {
                    $shift = $weeklyShifts[$weekStartStr][$dayOfWeek];
                } elseif (isset($generalShifts[$dayOfWeek])) {
                    $shift = $generalShifts[$dayOfWeek];
                }
                
                if ($shift) {
                    // Calculate duration in minutes
                    $start = explode(':', $shift['start']);
                    $end = explode(':', $shift['end']);
                    $startMinutes = (int)$start[0] * 60 + (int)$start[1];
                    $endMinutes = (int)$end[0] * 60 + (int)$end[1];
                    $duration = $endMinutes - $startMinutes;
                    
                    $history[] = [
                        'work_date' => $dateStr,
                        'start_time' => substr($shift['start'], 0, 5),
                        'end_time' => substr($shift['end'], 0, 5)
                    ];
                    
                    $totalShifts++;
                    $totalMinutes += $duration;
                }
            }
            
            // Đếm số ngày nghỉ trong khoảng thời gian
            $totalDaysOff = count(array_filter(array_keys($daysOff), function($date) use ($startDate, $endDate) {
                return $date >= $startDate && $date <= $endDate;
            }));
            
            echo json_encode([
                'success' => true,
                'history' => array_reverse($history), // Newest first
                'stats' => [
                    'total_shifts' => $totalShifts,
                    'total_hours' => round($totalMinutes / 60, 1),
                    'total_days_off' => $totalDaysOff
                ]
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
