<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/status_constants.php';

// ================= FORMAT =================
function format_currency($amount): string {
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ================= REDIRECT =================
function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

// ================= CSRF =================
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(?string $token = null): void {
    $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    if (!verify_csrf($token)) {
        http_response_code(403);
        die('Yeu cau khong hop le. Vui long tai lai trang va thu lai.');
    }
}

function require_post_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Phuong thuc khong hop le.');
    }

    require_csrf($_POST['csrf_token'] ?? '');
}

// ================= AUTH =================
function require_login(): void {
    if (empty($_SESSION['user'])) {
        redirect('/quanlynhahang/auth/login.php');
    }
}

function require_role(array $roles): void {
    require_login();
    $role = strtolower($_SESSION['user']['role_name'] ?? '');
    $allowed = array_map('strtolower', $roles);

    if (!in_array($role, $allowed)) {
        redirect('/quanlynhahang/index.php');
    }

    // Nếu là admin, chỉ yêu cầu key khi đang truy cập trang admin
    if ($role === 'admin' && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
        if (!isset($_SESSION['admin_key'])) {
            session_destroy();
            die('Phiên làm việc không hợp lệ. Vui lòng <a href="/quanlynhahang/auth/admin-access.php">đăng nhập lại</a>.');
        }

        if (!isset($_GET['key']) || $_GET['key'] !== $_SESSION['admin_key']) {
            die('Link admin không hợp lệ! Vui lòng truy cập từ menu hoặc <a href="/quanlynhahang/auth/admin-access.php">đăng nhập lại</a>.');
        }
    }
}

// ================= ADMIN SECRET KEY =================

// tạo key khi login (gọi sau login thành công)
function generate_admin_key(): void {
    if (!isset($_SESSION['admin_key'])) {
        $_SESSION['admin_key'] = bin2hex(random_bytes(32));
    }
}

// kiểm tra key admin
function require_admin_key(): void {
    require_login();

    // phải là admin
    if (($_SESSION['user']['role_name'] ?? '') !== 'admin') {
        redirect('/quanlynhahang/index.php');
    }

    // chưa có key
    if (!isset($_SESSION['admin_key'])) {
        die("Không có key admin!");
    }

    // sai key URL
    if (!isset($_GET['key']) || $_GET['key'] !== $_SESSION['admin_key']) {
        die("Link admin không hợp lệ!");
    }
}

// ================= REDIRECT ROLE =================
function redirect_by_role(): void {
    $role = strtolower($_SESSION['user']['role_name'] ?? '');

    $map = [
        'admin'     => '/quanlynhahang/admin.php',
        'quanly'    => '/quanlynhahang/manager/index.php',
        'thungan'   => '/quanlynhahang/cashier/index.php',
        'phucvu'    => '/quanlynhahang/waiter/index.php',
        'bep'       => '/quanlynhahang/kitchen/index.php',
        'khachhang' => '/quanlynhahang/index.php',
    ];

    redirect($map[$role] ?? '/quanlynhahang/index.php');
}

// ================= STATUS =================
function get_status_badge_class(string $status): string {
    return match ($status) {
        'available', 'da_xac_nhan', 'success', 'hoan_thanh', 'paid' => 'status-success',
        'cho_xac_nhan', 'processing', 'reserved'                    => 'status-warning',
        'da_huy', 'failed', 'maintenance', 'out_of_stock'           => 'status-danger',
        default                                                      => 'status-muted',
    };
}

// ================= SYSTEM CONFIG =================

// Lấy giá trị config từ database (với caching)
function get_config(string $key): ?string {
    global $conn;
    
    // Check cache first
    if (isset($_SESSION['config_cache'][$key])) {
        return $_SESSION['config_cache'][$key];
    }
    
    // Query database
    $stmt = $conn->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $_SESSION['config_cache'][$key] = $result['config_value'];
        return $result['config_value'];
    }
    
    return null;
}

// Lưu giá trị config vào database
function set_config(string $key, string $value, ?string $description = null): bool {
    global $conn;
    $user_id = $_SESSION['user']['user_id'] ?? 1;
    
    $stmt = $conn->prepare("
        INSERT INTO system_config (config_key, config_value, description, updated_by) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            config_value = VALUES(config_value),
            description = COALESCE(VALUES(description), description),
            updated_by = VALUES(updated_by)
    ");
    
    $stmt->bind_param('sssi', $key, $value, $description, $user_id);
    $success = $stmt->execute();
    
    // Clear cache
    if ($success && isset($_SESSION['config_cache'][$key])) {
        unset($_SESSION['config_cache'][$key]);
    }
    
    return $success;
}

// Xóa toàn bộ config cache
function clear_config_cache(): void {
    unset($_SESSION['config_cache']);
}

// ================= DATA OPERATION LOGGING =================

// Ghi log thao tác quản lý dữ liệu
function log_data_operation(string $action_type, int $deleted_count = 0, ?string $details = null): bool {
    global $conn;
    $user_id = $_SESSION['user']['user_id'] ?? 1;
    
    $stmt = $conn->prepare("
        INSERT INTO data_operation_logs 
        (action_type, deleted_count, performed_by, details) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->bind_param('siis', $action_type, $deleted_count, $user_id, $details);
    return $stmt->execute();
}

// ================= ROLE CHANGE LOGGING =================

// Ghi log thay đổi role
function log_role_change(int $user_id, int $old_role_id, int $new_role_id, ?string $note = null): bool {
    global $conn;
    $changed_by = $_SESSION['user']['user_id'] ?? 1;
    
    $stmt = $conn->prepare("
        INSERT INTO role_change_logs 
        (user_id, old_role_id, new_role_id, changed_by, note) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('iiiis', $user_id, $old_role_id, $new_role_id, $changed_by, $note);
    return $stmt->execute();
}

// ================= DATE RANGE HELPER =================
// Thay thế DATE(column) = '$date' bằng range query để dùng được index
// Trả về: ["col >= 'YYYY-MM-DD 00:00:00' AND col < 'YYYY-MM-DD 00:00:00'", ...]
function date_range(string $column, string $date): string {
    $next = date('Y-m-d', strtotime($date . ' +1 day'));
    return "$column >= '{$date} 00:00:00' AND $column < '{$next} 00:00:00'";
}

// Dùng cho BETWEEN (date_from → date_to)
function date_between(string $column, string $dateFrom, string $dateTo): string {
    $next = date('Y-m-d', strtotime($dateTo . ' +1 day'));
    return "$column >= '{$dateFrom} 00:00:00' AND $column < '{$next} 00:00:00'";
}


// Đồng bộ trạng thái bàn dựa trên orders và reservations thực tế
// Gọi hàm này khi load bất kỳ trang nào cần hiển thị trạng thái bàn chính xác
function sync_table_status(): void {
    global $conn;
    if (!$conn) return;

    $conn->query("SET time_zone = '+07:00'");

    $conn->begin_transaction();
    try {
        // Bàn đang có order THỰC SỰ active (không tính hoan_thanh vì đó là chờ thu ngân)
        $conn->query("
            UPDATE tables t SET t.status = 'dang_su_dung'
            WHERE EXISTS (
                SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                  AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
                  AND DATE(o.order_time) = CURDATE()
            ) AND t.status != 'bao_tri'
        ");

        // Bàn chờ thanh toán (order hoan_thanh, không có reservation hoặc reservation chưa xong)
        $conn->query("
            UPDATE tables t SET t.status = 'dang_su_dung'
            WHERE NOT EXISTS (
                SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                  AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
                  AND DATE(o.order_time) = CURDATE()
            )
            AND EXISTS (
                SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                  AND o.status = 'hoan_thanh'
                  AND DATE(o.order_time) = CURDATE()
                  AND (
                      o.reservation_id IS NULL
                      OR EXISTS (
                          SELECT 1 FROM reservations r
                          WHERE r.reservation_id = o.reservation_id
                            AND r.status NOT IN ('hoan_thanh','da_huy','khong_den')
                      )
                  )
            )
            AND t.status != 'bao_tri'
        ");

        // Bàn đã đặt: có reservation hôm nay chưa check-in HOẶC order da_dat_coc
        // mà reservation chưa hoàn thành/hủy
        $conn->query("
            UPDATE tables t SET t.status = 'da_dat'
            WHERE NOT EXISTS (
                SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                  AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
                  AND DATE(o.order_time) = CURDATE()
            )
            AND (
                EXISTS (
                    SELECT 1 FROM reservations r WHERE r.table_id = t.table_id
                      AND r.status IN ('da_xac_nhan','cho_xac_nhan')
                      AND DATE(r.reservation_time) = CURDATE()
                )
                OR EXISTS (
                    SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                      AND o.status IN ('da_dat_coc','da_coc')
                      AND EXISTS (
                          SELECT 1 FROM reservations r
                          WHERE r.reservation_id = o.reservation_id
                            AND r.status NOT IN ('hoan_thanh','da_huy','khong_den')
                      )
                )
            )
            AND t.status != 'bao_tri'
        ");

        // Bàn trống: không có order active, không có reservation hôm nay còn hiệu lực
        // Chạy cuối cùng, ghi đè mọi trạng thái sai (trừ bao_tri)
        $conn->query("
            UPDATE tables t SET t.status = 'trong'
            WHERE t.status != 'bao_tri'
            AND NOT EXISTS (
                SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                  AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
                  AND DATE(o.order_time) = CURDATE()
            )
            AND NOT EXISTS (
                SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                  AND o.status = 'hoan_thanh'
                  AND DATE(o.order_time) = CURDATE()
                  AND (
                      o.reservation_id IS NULL
                      OR NOT EXISTS (
                          SELECT 1 FROM reservations r
                          WHERE r.reservation_id = o.reservation_id
                            AND r.status IN ('hoan_thanh','da_huy','khong_den')
                      )
                  )
            )
            AND NOT EXISTS (
                SELECT 1 FROM reservations r WHERE r.table_id = t.table_id
                  AND r.status IN ('da_xac_nhan','cho_xac_nhan')
                  AND DATE(r.reservation_time) = CURDATE()
            )
            AND NOT EXISTS (
                SELECT 1 FROM orders o WHERE o.table_id = t.table_id
                  AND o.status IN ('da_dat_coc','da_coc')
                  AND NOT EXISTS (
                      SELECT 1 FROM reservations r
                      WHERE r.reservation_id = o.reservation_id
                        AND r.status IN ('hoan_thanh','da_huy','khong_den')
                  )
            )
        ");

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}

// ================= RESERVATION HELPERS =================

function reservation_duration_minutes(int $numberOfPeople): int {
    if ($numberOfPeople >= 5) {
        return 150;
    }
    if ($numberOfPeople >= 3) {
        return 120;
    }
    return 90;
}

function reservation_compute_end_time(string $startTime, int $numberOfPeople): string {
    $mins = reservation_duration_minutes($numberOfPeople);
    return date('Y-m-d H:i:s', strtotime($startTime) + ($mins * 60));
}

/**
 * Kiểm tra trùng khung giờ đặt bàn (dùng start_time/end_time hoặc fallback reservation_time).
 * @return array|null Hàng conflict hoặc null
 */
function reservation_find_time_conflict(
    mysqli $conn,
    int $tableId,
    string $startTime,
    string $endTime,
    int $excludeReservationId = 0
): ?array {
    $sql = "
        SELECT reservation_id, reservation_time, start_time, end_time
        FROM reservations
        WHERE table_id = ?
          AND status IN ('cho_xac_nhan', 'da_xac_nhan', 'da_checkin')
          AND (
              (start_time IS NOT NULL AND end_time IS NOT NULL AND start_time < ? AND end_time > ?)
              OR (start_time IS NULL AND end_time IS NULL AND reservation_time < ? AND DATE_ADD(reservation_time, INTERVAL 2 HOUR) > ?)
          )
    ";
    if ($excludeReservationId > 0) {
        $sql .= ' AND reservation_id != ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($excludeReservationId > 0) {
        $stmt->bind_param('issssi', $tableId, $endTime, $startTime, $endTime, $startTime, $excludeReservationId);
    } else {
        $stmt->bind_param('issss', $tableId, $endTime, $startTime, $endTime, $startTime);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Cập nhật trạng thái reservation và đồng bộ order/bàn (admin + quản lý).
 */
function update_reservation_status(int $reservationId, string $newStatus): array {
    global $conn;

    if (!$conn) {
        return ['success' => false, 'message' => 'Không kết nối CSDL'];
    }

    $allowed = defined('ALLOWED_RESERVATION_STATUSES')
        ? ALLOWED_RESERVATION_STATUSES
        : ['cho_xac_nhan', 'da_xac_nhan', 'da_checkin', 'khong_den', 'da_huy', 'hoan_thanh'];

    if (!in_array($newStatus, $allowed, true)) {
        return ['success' => false, 'message' => 'Trạng thái không hợp lệ'];
    }

    $check = $conn->prepare('SELECT reservation_id FROM reservations WHERE reservation_id = ? LIMIT 1');
    $check->bind_param('i', $reservationId);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $check->close();
        return ['success' => false, 'message' => 'Không tìm thấy đặt bàn'];
    }
    $check->close();

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('UPDATE reservations SET status=? WHERE reservation_id=?');
        $stmt->bind_param('si', $newStatus, $reservationId);
        $stmt->execute();
        $stmt->close();

        if ($newStatus === 'da_checkin') {
            $stmt = $conn->prepare("
                UPDATE orders
                SET status = 'dang_phuc_vu'
                WHERE reservation_id = ?
                  AND status IN ('da_dat_coc', 'da_coc')
            ");
            $stmt->bind_param('i', $reservationId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                UPDATE tables t
                JOIN reservations r ON r.table_id = t.table_id
                SET t.status = 'dang_su_dung'
                WHERE r.reservation_id = ?
            ");
            $stmt->bind_param('i', $reservationId);
            $stmt->execute();
            $stmt->close();
        } elseif ($newStatus === 'hoan_thanh') {
            $info = $conn->query("SELECT table_id FROM reservations WHERE reservation_id = $reservationId LIMIT 1")->fetch_assoc();
            $tableId = $info ? (int)$info['table_id'] : 0;

            $stmt = $conn->prepare("
                UPDATE orders
                SET status = 'hoan_thanh'
                WHERE reservation_id = ?
                  AND status IN ('da_dat_coc', 'da_coc', 'moi', 'dang_xu_ly', 'dang_che_bien', 'dang_phuc_vu')
            ");
            $stmt->bind_param('i', $reservationId);
            $stmt->execute();
            $stmt->close();

            if ($tableId > 0) {
                $conn->query("
                    UPDATE tables
                    SET status = 'trong'
                    WHERE table_id = $tableId
                      AND NOT EXISTS (
                          SELECT 1 FROM orders o2
                          WHERE o2.table_id = $tableId
                            AND o2.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
                      )
                ");
            }
        } elseif ($newStatus === 'da_huy' || $newStatus === 'khong_den') {
            $stmt = $conn->prepare("
                UPDATE orders
                SET status = 'da_huy'
                WHERE reservation_id = ?
                  AND status IN ('da_dat_coc', 'da_coc')
            ");
            $stmt->bind_param('i', $reservationId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                UPDATE tables t
                JOIN reservations r ON r.table_id = t.table_id
                SET t.status = 'trong'
                WHERE r.reservation_id = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM orders o2
                      WHERE o2.table_id = t.table_id
                        AND o2.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
                  )
            ");
            $stmt->bind_param('i', $reservationId);
            $stmt->execute();
            $stmt->close();
        }

        sync_table_status();
        $conn->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ================= SYNC RESERVATION STATUS =================
// Gọi ở đầu các trang: waiter/index.php, waiter/my_orders.php, cashier/history.php
// Tự động cập nhật trạng thái reservation theo thời gian thực
function syncReservationStatus(): void {
    global $conn;
    if (!$conn) return;

    $now = date('Y-m-d H:i:s');
    $noShowMins = defined('NO_SHOW_THRESHOLD_MINUTES') ? (int)NO_SHOW_THRESHOLD_MINUTES : 30;

    // 1. NO-SHOW: quá NO_SHOW_THRESHOLD_MINUTES chưa check-in → khong_den
    $noShowRes = $conn->query("
        SELECT r.reservation_id, r.table_id, o.order_id
        FROM reservations r
        LEFT JOIN orders o ON o.reservation_id = r.reservation_id
                          AND o.status IN ('da_dat_coc','da_coc')
        WHERE r.status IN ('cho_xac_nhan','da_xac_nhan')
          AND DATE_ADD(r.reservation_time, INTERVAL $noShowMins MINUTE) < '$now'
    ");

    if ($noShowRes) {
        while ($row = $noShowRes->fetch_assoc()) {
            $res_id   = (int)$row['reservation_id'];
            $table_id = (int)$row['table_id'];
            $order_id = $row['order_id'] ? (int)$row['order_id'] : null;

            $conn->begin_transaction();
            try {
                // Reservation → khong_den
                $s = $conn->prepare("UPDATE reservations SET status='khong_den' WHERE reservation_id=? AND status IN ('cho_xac_nhan','da_xac_nhan')");
                $s->bind_param('i', $res_id); $s->execute(); $s->close();

                // Order da_dat_coc liên kết → da_huy (sync lifecycle)
                if ($order_id) {
                    $s = $conn->prepare("UPDATE orders SET status='da_huy' WHERE order_id=? AND status IN ('da_dat_coc','da_coc')");
                    $s->bind_param('i', $order_id); $s->execute(); $s->close();
                }

                // Trả bàn về trống nếu không có đơn active khác
                $chk = $conn->prepare("SELECT order_id FROM orders WHERE table_id=? AND status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu') LIMIT 1");
                $chk->bind_param('i', $table_id); $chk->execute();
                if ($chk->get_result()->num_rows === 0) {
                    $conn->query("UPDATE tables SET status='trong' WHERE table_id=$table_id");
                }
                $chk->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }

    // 2. RESERVATION HOAN_THANH nhưng order vẫn da_dat_coc → chuyển sang da_thanh_toan
    //    Xảy ra khi cashier thanh toán order nhưng reservation chưa được sync
    $conn->query("
        UPDATE orders o
        JOIN reservations r ON r.reservation_id = o.reservation_id
        SET o.status = 'da_thanh_toan'
        WHERE o.status = 'da_dat_coc'
          AND r.status = 'hoan_thanh'
    ");

    // 3. AUTO-COMPLETE: đã check-in và end_time đã qua → hoan_thanh
    $conn->query("
        UPDATE reservations
        SET status = 'hoan_thanh'
        WHERE status = 'da_checkin'
          AND end_time IS NOT NULL
          AND end_time < '$now'
    ");
}

// Alias cũ để không vỡ các trang đang gọi auto_cancel_expired_reservations()
function auto_cancel_expired_reservations(): void {
    syncReservationStatus();
}

// ═══════════════════════════════════════════════════════════════════
// GIAI ĐOẠN 2: REVENUE REPORTING HELPERS (Payments-based)
// ═══════════════════════════════════════════════════════════════════
// Source of truth: payments table, NOT orders.total_amount
// Mục đích: Đảm bảo audit trail sạch, không double-count
// ═══════════════════════════════════════════════════════════════════

/**
 * Tính doanh thu by date range từ PAYMENTS table
 * @param mysqli $conn
 * @param string $start_date YYYY-MM-DD
 * @param string $end_date YYYY-MM-DD
 * @param array $exclude_types Loại payment cần loại trừ (vd: ['refund', 'adjustment'])
 * @return array ['total', 'walk_in', 'online', 'count']
 */
function get_revenue_by_date_range($conn, $start_date, $end_date, $exclude_types = []): array {
    $exclude_sql = "";
    if (!empty($exclude_types)) {
        $placeholders = implode(",", array_fill(0, count($exclude_types), "?"));
        $exclude_sql = " AND p.payment_type NOT IN ({$placeholders})";
    }

    $sql = "
        SELECT
            COALESCE(SUM(p.amount_paid), 0) as total,
            COALESCE(SUM(CASE WHEN o.reservation_id IS NULL THEN p.amount_paid ELSE 0 END), 0) as walk_in,
            COALESCE(SUM(CASE WHEN o.reservation_id IS NOT NULL THEN p.amount_paid ELSE 0 END), 0) as online,
            COUNT(DISTINCT o.order_id) as count
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE p.payment_status = 'thanh_cong'
            AND DATE(p.payment_time) BETWEEN ? AND ?
            {$exclude_sql}
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['total' => 0, 'walk_in' => 0, 'online' => 0, 'count' => 0];
    }

    $types = "ss";
    $params = [$start_date, $end_date];

    if (!empty($exclude_types)) {
        $types .= str_repeat("s", count($exclude_types));
        $params = array_merge($params, $exclude_types);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'total'    => (float)($result['total'] ?? 0),
        'walk_in'  => (float)($result['walk_in'] ?? 0),
        'online'   => (float)($result['online'] ?? 0),
        'count'    => (int)($result['count'] ?? 0)
    ];
}

/**
 * Tính doanh thu by payment type (chi tiết)
 * @param mysqli $conn
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function get_revenue_breakdown_by_type($conn, $start_date, $end_date): array {
    $sql = "
        SELECT
            p.payment_type,
            p.payment_method,
            COUNT(DISTINCT p.payment_id) as count,
            COALESCE(SUM(p.amount_paid), 0) as total
        FROM payments p
        WHERE p.payment_status = 'thanh_cong'
            AND DATE(p.payment_time) BETWEEN ? AND ?
            AND p.payment_type NOT IN ('refund', 'adjustment')
        GROUP BY p.payment_type, p.payment_method
        ORDER BY p.payment_type, total DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    return $data;
}

/**
 * Kiểm tra consistency: SUM(payments) vs orders.paid_amount
 * @param mysqli $conn
 * @return array ['order_id' => difference, ...]
 */
function check_payment_consistency($conn): array {
    $sql = "
        SELECT
            o.order_id,
            o.paid_amount as orders_paid,
            COALESCE(SUM(p.amount_paid), 0) as payments_sum,
            ABS(o.paid_amount - COALESCE(SUM(p.amount_paid), 0)) as diff
        FROM orders o
        LEFT JOIN payments p ON p.order_id = o.order_id AND p.payment_status = 'thanh_cong'
        GROUP BY o.order_id
        HAVING diff > 0.01
        ORDER BY diff DESC
    ";

    $result = $conn->query($sql);
    if (!$result) return [];

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[$row['order_id']] = $row;
    }

    return $data;
}
