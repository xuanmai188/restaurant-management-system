<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Xác thực quyền
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}
$role = strtolower($_SESSION['user']['role_name'] ?? '');
if (!in_array($role, ['admin', 'quanly'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

$action = $_GET['action'] ?? '';

// Validate và lấy date param (mặc định hôm nay)
$dateParam = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tham số date không hợp lệ (YYYY-MM-DD)']);
    exit;
}
$date = $conn->real_escape_string($dateParam);

try {
    switch ($action) {
        case 'today':
            getTodayStats($conn, $date);
            break;
        case 'hourly':
            getHourlyStats($conn, $date);
            break;
        case 'breakdown':
            getRevenueBreakdown($conn, $date);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ── action=today ─────────────────────────────────────────────────────────────
function getTodayStats($conn, $date) {
    $dateRange = date_range('order_time', $date);
    $resRange  = date_range('r.reservation_time', $date);

    // Tổng đơn + phân loại walk-in / online
    $r1 = $conn->query("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN reservation_id IS NULL THEN 1 ELSE 0 END) AS walkin_orders,
            SUM(CASE WHEN reservation_id IS NOT NULL THEN 1 ELSE 0 END) AS online_orders
        FROM orders
        WHERE $dateRange
    ")->fetch_assoc();

    // Doanh thu walk-in
    $r2 = $conn->query("
        SELECT COALESCE(SUM(paid_amount), 0) AS revenue
        FROM orders
        WHERE reservation_id IS NULL
          AND status = 'da_thanh_toan'
          AND $dateRange
    ")->fetch_assoc();

    // Doanh thu online
    $r3 = $conn->query("
        SELECT COALESCE(SUM(paid_amount), 0) AS revenue
        FROM orders
        WHERE reservation_id IS NOT NULL
          AND status = 'da_thanh_toan'
          AND $dateRange
    ")->fetch_assoc();

    // Doanh thu không đến — tiền cọc giữ lại
    $r4 = $conn->query("
        SELECT COALESCE(SUM(rp.amount), 0) AS revenue
        FROM reservation_payments rp
        JOIN reservations r ON r.reservation_id = rp.reservation_id
        WHERE r.status = 'khong_den'
          AND rp.payment_status = 'thanh_cong'
          AND $resRange
    ")->fetch_assoc();

    $revenue_walkin   = (float)$r2['revenue'];
    $revenue_online   = (float)$r3['revenue'];
    $revenue_khong_den = (float)$r4['revenue'];
    $total_revenue    = $revenue_walkin + $revenue_online + $revenue_khong_den;

    echo json_encode([
        'success'          => true,
        'date'             => $date,
        'total_revenue'    => $total_revenue,
        'total_orders'     => (int)$r1['total_orders'],
        'walkin_orders'    => (int)$r1['walkin_orders'],
        'online_orders'    => (int)$r1['online_orders'],
        'revenue_walkin'   => $revenue_walkin,
        'revenue_online'   => $revenue_online,
        'revenue_khong_den' => $revenue_khong_den,
    ]);
}

// ── action=hourly ─────────────────────────────────────────────────────────────
function getHourlyStats($conn, $date) {
    $revenueByHour = array_fill(0, 24, 0.0);
    $walkinByHour  = array_fill(0, 24, 0);
    $onlineByHour  = array_fill(0, 24, 0);
    $dateRange = date_range('order_time', $date);

    $res = $conn->query("
        SELECT
            HOUR(order_time) AS hr,
            SUM(paid_amount) AS revenue,
            SUM(CASE WHEN reservation_id IS NULL THEN 1 ELSE 0 END) AS walkin_cnt,
            SUM(CASE WHEN reservation_id IS NOT NULL THEN 1 ELSE 0 END) AS online_cnt
        FROM orders
        WHERE status = 'da_thanh_toan'
          AND $dateRange
        GROUP BY HOUR(order_time)
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $h = (int)$row['hr'];
            $revenueByHour[$h] = (float)$row['revenue'];
            $walkinByHour[$h]  = (int)$row['walkin_cnt'];
            $onlineByHour[$h]  = (int)$row['online_cnt'];
        }
    }

    echo json_encode([
        'success'       => true,
        'date'          => $date,
        'hours'         => range(0, 23),
        'revenue'       => array_values($revenueByHour),
        'walkin_count'  => array_values($walkinByHour),
        'online_count'  => array_values($onlineByHour),
    ]);
}

// ── action=breakdown ─────────────────────────────────────────────────────────
function getRevenueBreakdown($conn, $date) {
    $dateRange = date_range('order_time', $date);
    $resRange  = date_range('r.reservation_time', $date);

    $r_walkin = (float)$conn->query("
        SELECT COALESCE(SUM(paid_amount), 0) AS v FROM orders
        WHERE reservation_id IS NULL AND status='da_thanh_toan' AND $dateRange
    ")->fetch_assoc()['v'];

    $r_online = (float)$conn->query("
        SELECT COALESCE(SUM(paid_amount), 0) AS v FROM orders
        WHERE reservation_id IS NOT NULL AND status='da_thanh_toan' AND $dateRange
    ")->fetch_assoc()['v'];

    $r_khong_den = (float)$conn->query("
        SELECT COALESCE(SUM(rp.amount), 0) AS v
        FROM reservation_payments rp
        JOIN reservations r ON r.reservation_id = rp.reservation_id
        WHERE r.status='khong_den' AND rp.payment_status='thanh_cong'
          AND $resRange
    ")->fetch_assoc()['v'];

    $total = $r_walkin + $r_online + $r_khong_den;

    echo json_encode([
        'success'              => true,
        'date'                 => $date,
        'total'                => $total,
        'walkin'               => $r_walkin,
        'online'               => $r_online,
        'khong_den'            => $r_khong_den,
        'pct_walkin'           => $total > 0 ? round($r_walkin / $total * 100, 1) : 0,
        'pct_online'           => $total > 0 ? round($r_online / $total * 100, 1) : 0,
        'pct_khong_den'        => $total > 0 ? round($r_khong_den / $total * 100, 1) : 0,
    ]);
}
