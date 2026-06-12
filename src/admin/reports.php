<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

// Xử lý chọn nhanh theo tháng
if (isset($_GET['quick'])) {
    switch ($_GET['quick']) {
        case 'this_month':
            $dateFrom = date('Y-m-01');
            $dateTo   = date('Y-m-d');
            break;
        case 'last_month':
            $dateFrom = date('Y-m-01', strtotime('first day of last month'));
            $dateTo   = date('Y-m-t', strtotime('last day of last month'));
            break;
        case '3_months':
            $dateFrom = date('Y-m-01', strtotime('-2 months'));
            $dateTo   = date('Y-m-d');
            break;
        case '6_months':
            $dateFrom = date('Y-m-01', strtotime('-5 months'));
            $dateTo   = date('Y-m-d');
            break;
        case 'this_year':
            $dateFrom = date('Y-01-01');
            $dateTo   = date('Y-m-d');
            break;
    }
} else {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
}

// ── Doanh thu thực thu — payments + đơn admin đổi trạng thái thủ công
// Dùng subquery gộp cả 2 nguồn để tránh double-count
$r1 = $conn->query("
    SELECT IFNULL(SUM(amount_paid), 0) AS total, COUNT(DISTINCT order_id) AS cnt
    FROM (
        -- Đơn thu qua quầy (có payment record)
        SELECT p.order_id, p.amount_paid
        FROM payments p
        WHERE p.payment_status = 'thanh_cong'
          AND DATE(p.payment_time) BETWEEN '$dateFrom' AND '$dateTo'

        UNION ALL

        -- Đơn admin/quản lý đổi trạng thái thủ công (không có payment record)
        SELECT o.order_id, o.total_amount AS amount_paid
        FROM orders o
        WHERE o.status = 'da_thanh_toan'
          AND DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'
          AND NOT EXISTS (
              SELECT 1 FROM payments p2
              WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
          )
    ) combined
");
$rev_quay = $r1 ? $r1->fetch_assoc() : ['total'=>0,'cnt'=>0];

// ── Cọc giữ lại (khong_den + da_huy) — dùng reservation_time
$r_cancel = $conn->query("
    SELECT COALESCE(SUM(rp.amount), 0) AS total
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('khong_den', 'da_huy')
      AND rp.payment_status = 'thanh_cong'
      AND DATE(r.reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
");
$cancelDep = $r_cancel ? (float)$r_cancel->fetch_assoc()['total'] : 0;

// ── Tổng doanh thu = tiền thực thu từ đơn + cọc giữ lại
$totalRevenue = (float)$rev_quay['total'] + $cancelDep;

// ── Thu tại quầy (không tính deposit_consumed) — để hiển thị breakdown
$r1b = $conn->query("
    SELECT IFNULL(SUM(amount_paid), 0) AS total
    FROM (
        SELECT p.amount_paid
        FROM payments p
        WHERE p.payment_status = 'thanh_cong'
          AND p.payment_method != 'deposit_consumed'
          AND DATE(p.payment_time) BETWEEN '$dateFrom' AND '$dateTo'

        UNION ALL

        SELECT o.total_amount AS amount_paid
        FROM orders o
        WHERE o.status = 'da_thanh_toan'
          AND DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'
          AND NOT EXISTS (
              SELECT 1 FROM payments p2
              WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
          )
    ) combined
");
$thu_quay_row = $r1b ? $r1b->fetch_assoc() : ['total'=>0];
$thu_quay_val = (float)$thu_quay_row['total'];

// ── Từ cọc (deposit_consumed) — để hiển thị breakdown
$r1c = $conn->query("
    SELECT IFNULL(SUM(p.amount_paid), 0) AS total
    FROM payments p
    WHERE p.payment_status = 'thanh_cong'
      AND p.payment_method = 'deposit_consumed'
      AND DATE(p.payment_time) BETWEEN '$dateFrom' AND '$dateTo'
");
$tu_coc_val = $r1c ? (float)$r1c->fetch_assoc()['total'] : 0;

// Orders walk-in (không có reservation_id) đã thanh toán
$r2 = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status='da_thanh_toan' AND reservation_id IS NULL AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'");
$totalWalkinPaid = $r2 ? (int)$r2->fetch_assoc()['cnt'] : 0;

// Orders walk-in bị hủy
$r2_huy = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status='da_huy' AND reservation_id IS NULL AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'");
$totalOrdersHuy = $r2_huy ? (int)$r2_huy->fetch_assoc()['cnt'] : 0;

// Reservations đã hoàn thành
$r2_res_done = $conn->query("SELECT COUNT(*) AS cnt FROM reservations WHERE status='hoan_thanh' AND DATE(reservation_time) BETWEEN '$dateFrom' AND '$dateTo'");
$totalResDone = $r2_res_done ? (int)$r2_res_done->fetch_assoc()['cnt'] : 0;

// Reservations bị hủy hoặc không đến
$r2_res_huy = $conn->query("SELECT COUNT(*) AS cnt FROM reservations WHERE status IN ('da_huy','khong_den') AND DATE(reservation_time) BETWEEN '$dateFrom' AND '$dateTo'");
$totalResHuy = $r2_res_huy ? (int)$r2_res_huy->fetch_assoc()['cnt'] : 0;

// Reservations đang chờ (chưa kết thúc)
$r2_res_pending = $conn->query("SELECT COUNT(*) AS cnt FROM reservations WHERE status IN ('cho_xac_nhan','da_xac_nhan','da_checkin') AND DATE(reservation_time) BETWEEN '$dateFrom' AND '$dateTo'");
$totalResPending = $r2_res_pending ? (int)$r2_res_pending->fetch_assoc()['cnt'] : 0;

// Tổng đặt bàn
$totalReservations = $totalResDone + $totalResHuy + $totalResPending;

// Tổng đơn = chỉ đếm orders thực tế (không tính đơn hủy)
$r_all_orders = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status != 'da_huy' AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'");
$totalAllOrders = $r_all_orders ? (int)$r_all_orders->fetch_assoc()['cnt'] : 0;

$grandTotal = $totalAllOrders;

// Hoàn tất = walk-in đã thanh toán + reservations hoàn thành
$totalDone      = $totalWalkinPaid + $totalResDone;
// Hủy = orders hủy + reservations hủy/không đến
$totalCancelled = $totalOrdersHuy + $totalResHuy;

// Cọc bị hủy — đã tính ở trên ($cancelDep)

// Doanh thu theo ngày — payments + đơn thủ công
$daily_paid_res = $conn->query("
    SELECT day, SUM(amount_paid) AS total, COUNT(DISTINCT order_id) AS cnt
    FROM (
        SELECT DATE(p.payment_time) AS day, p.order_id, p.amount_paid
        FROM payments p
        WHERE p.payment_status = 'thanh_cong'
          AND DATE(p.payment_time) BETWEEN '$dateFrom' AND '$dateTo'

        UNION ALL

        SELECT DATE(o.order_time) AS day, o.order_id, o.total_amount AS amount_paid
        FROM orders o
        WHERE o.status = 'da_thanh_toan'
          AND DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'
          AND NOT EXISTS (
              SELECT 1 FROM payments p2
              WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
          )
    ) combined
    GROUP BY day
    ORDER BY day ASC
");
$daily_map = [];
if ($daily_paid_res) {
    while ($row = $daily_paid_res->fetch_assoc()) {
        $daily_map[$row['day']] = ['total' => (float)$row['total'], 'cnt' => (int)$row['cnt']];
    }
}

// Đếm orders ĐÃ THANH TOÁN theo ngày (để hiển thị số giao dịch)
$daily_paid_orders = $conn->query("
    SELECT DATE(order_time) AS day, COUNT(*) AS cnt
    FROM orders
    WHERE status = 'da_thanh_toan'
      AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(order_time)
");
if ($daily_paid_orders) {
    while ($row = $daily_paid_orders->fetch_assoc()) {
        $d = $row['day'];
        if (!isset($daily_map[$d])) {
            $daily_map[$d] = ['total' => 0, 'cnt' => 0];
        }
        $daily_map[$d]['paid_orders'] = (int)$row['cnt'];
    }
}

// Đếm orders TẠI QUÁN (không có reservation_id) theo ngày
$daily_walkin_orders = $conn->query("
    SELECT DATE(order_time) AS day, COUNT(*) AS cnt
    FROM orders
    WHERE status = 'da_thanh_toan'
      AND reservation_id IS NULL
      AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(order_time)
");
if ($daily_walkin_orders) {
    while ($row = $daily_walkin_orders->fetch_assoc()) {
        $d = $row['day'];
        if (!isset($daily_map[$d])) {
            $daily_map[$d] = ['total' => 0, 'cnt' => 0, 'paid_orders' => 0];
        }
        $daily_map[$d]['walkin_orders'] = (int)$row['cnt'];
    }
}

// Đếm orders BỊ HỦY theo ngày
$daily_cancelled_orders = $conn->query("
    SELECT DATE(order_time) AS day, COUNT(*) AS cnt
    FROM orders
    WHERE status = 'da_huy'
      AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(order_time)
");
if ($daily_cancelled_orders) {
    while ($row = $daily_cancelled_orders->fetch_assoc()) {
        $d = $row['day'];
        if (!isset($daily_map[$d])) {
            $daily_map[$d] = ['total' => 0, 'cnt' => 0, 'paid_orders' => 0, 'walkin_orders' => 0];
        }
        $daily_map[$d]['cancelled_orders'] = (int)$row['cnt'];
    }
}

// Đếm reservations BỊ HỦY theo ngày
$daily_cancelled_reservations = $conn->query("
    SELECT DATE(reservation_time) AS day, COUNT(*) AS cnt
    FROM reservations
    WHERE status = 'da_huy'
      AND DATE(reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(reservation_time)
");
if ($daily_cancelled_reservations) {
    while ($row = $daily_cancelled_reservations->fetch_assoc()) {
        $d = $row['day'];
        if (!isset($daily_map[$d])) {
            $daily_map[$d] = ['total' => 0, 'cnt' => 0, 'paid_orders' => 0, 'walkin_orders' => 0, 'cancelled_orders' => 0];
        }
        $daily_map[$d]['cancelled_reservations'] = (int)$row['cnt'];
    }
}

// Tính tổng tiền cọc đã thu theo ngày
$daily_deposit = $conn->query("
    SELECT DATE(rp.payment_time) AS day, SUM(rp.amount) AS total
    FROM reservation_payments rp
    WHERE rp.payment_status IN ('thanh_cong','cho_xu_ly')
      AND DATE(rp.payment_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(rp.payment_time)
");
if ($daily_deposit) {
    while ($row = $daily_deposit->fetch_assoc()) {
        $d = $row['day'];
        if (!isset($daily_map[$d])) {
            $daily_map[$d] = ['total' => 0, 'cnt' => 0, 'paid_orders' => 0, 'walkin_orders' => 0, 'cancelled_orders' => 0, 'cancelled_reservations' => 0];
        }
        $daily_map[$d]['deposit'] = (float)$row['total'];
    }
}

// Đếm reservations theo ngày
$daily_reservations = $conn->query("
    SELECT DATE(reservation_time) AS day, COUNT(*) AS cnt
    FROM reservations
    WHERE DATE(reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(reservation_time)
");
if ($daily_reservations) {
    while ($row = $daily_reservations->fetch_assoc()) {
        $d = $row['day'];
        if (!isset($daily_map[$d])) {
            $daily_map[$d] = ['total' => 0, 'cnt' => 0, 'all_orders' => 0];
        }
        $daily_map[$d]['reservations'] = (int)$row['cnt'];
    }
}

// Cộng thêm cọc giữ lại theo ngày (khong_den + da_huy), dùng reservation_time
$daily_cancel_res = $conn->query("
    SELECT DATE(r.reservation_time) AS day, SUM(rp.amount) AS total
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('khong_den', 'da_huy')
      AND rp.payment_status = 'thanh_cong'
      AND DATE(r.reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(r.reservation_time)
");
if ($daily_cancel_res) {
    while ($row = $daily_cancel_res->fetch_assoc()) {
        $d = $row['day'];
        $daily_map[$d]['total'] = ($daily_map[$d]['total'] ?? 0) + (float)$row['total'];
    }
}

ksort($daily_map);
$chartLabels = array_keys($daily_map);
$chartData   = array_column(array_values($daily_map), 'total');
$dailyRows   = [];
foreach ($daily_map as $day => $d) {
    $done      = ($d['paid_orders'] ?? 0);   // đơn đã thanh toán
    $cancelled = ($d['cancelled_orders'] ?? 0) + ($d['cancelled_reservations'] ?? 0);
    $walkin    = $d['walkin_orders'] ?? 0;
    $reservations = $d['reservations'] ?? 0;
    $deposit   = $d['deposit'] ?? 0;
    $revenue   = $d['total'] ?? 0;

    $dailyRows[] = [
        'day'         => $day,
        'total'       => $revenue,
        'done'        => $done,
        'walkin'      => $walkin,
        'reservations'=> $reservations,
        'cancelled'   => $cancelled,
        'deposit'     => $deposit,
    ];
}

// Doanh thu theo tháng — payments + đơn thủ công
$monthly_paid = $conn->query("
    SELECT month, SUM(amount_paid) AS total, COUNT(DISTINCT order_id) AS cnt
    FROM (
        SELECT DATE_FORMAT(p.payment_time, '%Y-%m') AS month, p.order_id, p.amount_paid
        FROM payments p
        WHERE p.payment_status = 'thanh_cong'
          AND DATE(p.payment_time) BETWEEN '$dateFrom' AND '$dateTo'

        UNION ALL

        SELECT DATE_FORMAT(o.order_time, '%Y-%m') AS month, o.order_id, o.total_amount AS amount_paid
        FROM orders o
        WHERE o.status = 'da_thanh_toan'
          AND DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'
          AND NOT EXISTS (
              SELECT 1 FROM payments p2
              WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
          )
    ) combined
    GROUP BY month
");
$monthly_map = [];
if ($monthly_paid) {
    while ($row = $monthly_paid->fetch_assoc()) {
        $monthly_map[$row['month']] = ['total' => (float)$row['total'], 'cnt' => (int)$row['cnt']];
    }
}

// Đếm orders ĐÃ THANH TOÁN theo tháng
$monthly_paid_orders = $conn->query("
    SELECT DATE_FORMAT(order_time, '%Y-%m') AS month, COUNT(*) AS cnt
    FROM orders
    WHERE status = 'da_thanh_toan'
      AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE_FORMAT(order_time, '%Y-%m')
");
if ($monthly_paid_orders) {
    while ($row = $monthly_paid_orders->fetch_assoc()) {
        $m = $row['month'];
        if (!isset($monthly_map[$m])) {
            $monthly_map[$m] = ['total' => 0, 'cnt' => 0];
        }
        $monthly_map[$m]['paid_orders'] = (int)$row['cnt'];
    }
}

// Đếm reservations theo tháng
$monthly_reservations = $conn->query("
    SELECT DATE_FORMAT(reservation_time, '%Y-%m') AS month, COUNT(*) AS cnt
    FROM reservations
    WHERE DATE(reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE_FORMAT(reservation_time, '%Y-%m')
");
if ($monthly_reservations) {
    while ($row = $monthly_reservations->fetch_assoc()) {
        $m = $row['month'];
        if (!isset($monthly_map[$m])) {
            $monthly_map[$m] = ['total' => 0, 'cnt' => 0, 'paid_orders' => 0];
        }
        $monthly_map[$m]['reservations'] = (int)$row['cnt'];
    }
}

$monthly_cancel = $conn->query("
    SELECT DATE_FORMAT(r.reservation_time, '%Y-%m') AS month, SUM(rp.amount) AS total
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('khong_den', 'da_huy')
      AND rp.payment_status = 'thanh_cong'
      AND DATE(r.reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE_FORMAT(r.reservation_time, '%Y-%m')
");
if ($monthly_cancel) {
    while ($row = $monthly_cancel->fetch_assoc()) {
        $m = $row['month'];
        $monthly_map[$m]['total'] = ($monthly_map[$m]['total'] ?? 0) + (float)$row['total'];
    }
}

krsort($monthly_map);
$monthlyRows   = [];
$monthlyLabels = [];
$monthlyData   = [];
foreach ($monthly_map as $month => $d) {
    $total_gd = ($d['paid_orders'] ?? 0) + ($d['reservations'] ?? 0);
    $monthlyRows[]   = ['month' => $month, 'total' => $d['total'], 'cnt' => $total_gd];
    $monthlyLabels[] = $month;
    $monthlyData[]   = $d['total'];
}

// Top 10 món bán chạy — gộp cả order đã thanh toán + reservation_items
$topItems = $conn->query("
    SELECT item_name, SUM(qty) AS qty, SUM(revenue) AS revenue
    FROM (
        SELECT mi.item_name, SUM(od.quantity) AS qty, SUM(od.quantity * od.unit_price) AS revenue
        FROM   order_details od
        JOIN   menu_items mi ON mi.item_id = od.item_id
        JOIN   orders o ON o.order_id = od.order_id
        WHERE  o.status = 'da_thanh_toan'
          AND  DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'
        GROUP  BY od.item_id

        UNION ALL

        SELECT mi2.item_name, SUM(ri.quantity) AS qty, SUM(ri.quantity * ri.unit_price) AS revenue
        FROM   reservation_items ri
        JOIN   menu_items mi2 ON mi2.item_id = ri.item_id
        JOIN   reservations r ON r.reservation_id = ri.reservation_id
        WHERE  r.status IN ('hoan_thanh','da_checkin')
          AND  DATE(r.reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
          AND  NOT EXISTS (
              SELECT 1 FROM orders o2
              WHERE o2.reservation_id = r.reservation_id
                AND o2.status = 'da_thanh_toan'
          )
        GROUP  BY ri.item_id
    ) combined
    GROUP BY item_name
    ORDER BY qty DESC
    LIMIT 10
");

$pageTitle    = 'Tổng quan & Báo cáo';
$activeMenu   = 'dashboard';
$sidebarRole  = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<!-- Bộ lọc thời gian -->
<form method="GET" class="card" style="padding:12px; margin-bottom:16px;">
    <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:10px; align-items:end; margin-bottom:10px;">
        <div class="form-group" style="margin:0;">
            <label style="font-size:13px;">Từ ngày</label>
            <input class="input" type="date" name="date_from" value="<?= e($dateFrom) ?>" style="padding:6px 8px; font-size:13px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:13px;">Đến ngày</label>
            <input class="input" type="date" name="date_to" value="<?= e($dateTo) ?>" style="padding:6px 8px; font-size:13px;">
        </div>
        <button class="btn btn-primary" type="submit" style="padding:6px 12px; font-size:13px;">Xem</button>
    </div>
    
    <!-- Chọn nhanh theo tháng -->
    <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <span style="color:#666; font-size:12px; align-self:center;">Nhanh:</span>
        <button type="submit" name="quick" value="this_month" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">Tháng này</button>
        <button type="submit" name="quick" value="last_month" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">Tháng trước</button>
        <button type="submit" name="quick" value="3_months" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">3 tháng</button>
        <button type="submit" name="quick" value="6_months" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">6 tháng</button>
        <button type="submit" name="quick" value="this_year" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;">Năm nay</button>
    </div>
</form>

<!-- Thống kê nhanh -->
<div class="stats" style="grid-template-columns:repeat(3,1fr); margin-bottom:16px;">
    <div class="stat-card" style="padding:12px; text-align:center;">
        <p style="font-size:12px; margin-bottom:4px;">Tổng doanh thu</p>
        <h3 style="font-size:18px; margin-bottom:4px;"><?= format_currency($totalRevenue) ?></h3>
        <span style="color:var(--success); font-size:11px;">Thu tại quầy: <?= format_currency($thu_quay_val) ?></span>
        <?php if ($tu_coc_val > 0): ?>
            <span style="display:block; font-size:11px; color:#6b7280;">Từ cọc: <?= format_currency($tu_coc_val) ?></span>
        <?php endif; ?>
        <?php if ($cancelDep > 0): ?>
            <span style="display:block; font-size:11px; color:#dc2626;">Cọc giữ lại (hủy/không đến): <?= format_currency($cancelDep) ?></span>
        <?php endif; ?>
    </div>
    <div class="stat-card" style="padding:12px; text-align:center;">
        <p style="font-size:12px; margin-bottom:4px;">Tổng đơn hàng</p>
        <h3 style="font-size:18px; margin-bottom:4px;"><?= $grandTotal ?> đơn</h3>
        <?php if ($totalResPending > 0): ?>
            <span style="display:block; font-size:11px; color:#d97706;">⏳ Đang chờ: <?= $totalResPending ?></span>
        <?php endif; ?>
    </div>    <div class="stat-card" style="padding:12px; text-align:center;">
        <p style="font-size:12px; margin-bottom:4px;">Doanh thu TB/ngày</p>
        <h3 style="font-size:18px; margin-bottom:4px;"><?= (!empty($chartData) && count($chartData) > 0) ? format_currency(array_sum($chartData) / count($chartData)) : '0 đ' ?></h3>
    </div>
</div>



<!-- Biểu đồ doanh thu theo ngày - Responsive -->
<div class="card panel" style="margin-bottom:16px;">
    <div class="panel-header" style="padding:8px 12px;">
        <h3 style="font-size:13px; margin:0;">📈 Doanh thu 7 ngày gần nhất</h3>
    </div>
    <?php if (!empty($chartLabels) && !empty($chartData)): ?>
        <div style="padding:12px;">
            <div style="display:flex; justify-content:space-between; align-items:end; height:100px; border-bottom:1px solid #eee; margin-bottom:8px;">
                <?php 
                $recentData = array_slice($chartData, -7); // Chỉ lấy 7 ngày gần nhất
                $recentLabels = array_slice($chartLabels, -7);
                $maxValue = !empty($recentData) ? max($recentData) : 1;
                if ($maxValue <= 0) $maxValue = 1;
                
                for($i = 0; $i < count($recentData); $i++): 
                    $height = ($recentData[$i] / $maxValue) * 80;
                    $date = date('d/m', strtotime($recentLabels[$i]));
                ?>
                <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                    <div style="background:#22c55e; width:20px; height:<?= $height ?>px; border-radius:2px 2px 0 0; margin-bottom:4px;" title="<?= format_currency($recentData[$i]) ?>"></div>
                    <span style="font-size:10px; color:#666;"><?= $date ?></span>
                </div>
                <?php endfor; ?>
            </div>
            <div style="text-align:center; font-size:11px; color:#888;">
                Tổng 7 ngày: <?= format_currency(array_sum($recentData)) ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state" style="padding:15px; font-size:12px;">Không có dữ liệu trong khoảng thời gian này.</div>
    <?php endif; ?>
</div>

<!-- Bảng thống kê theo tháng -->


<!-- Bảng chi tiết + Top món -->
<div class="content-grid" style="gap:12px;">
    <div class="card panel">
        <div class="panel-header" style="padding:10px 16px;"><h3 style="font-size:14px; margin:0;">Doanh thu theo ngày (Chi tiết)</h3></div>
        <div class="table-wrap" style="max-height:400px; overflow-y:auto;">
            <table class="table" style="font-size:12px;">
                <thead>
                    <tr style="font-size:11px;">
                        <th>Ngày</th>
                        <th>✓ Hoàn tất</th>
                        <th>✗ Hủy/KĐ</th>
                        <th>Doanh thu</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($dailyRows)): ?>
                    <?php foreach (array_slice(array_reverse($dailyRows), 0, 15) as $d): ?>
                    <tr>
                        <td style="font-weight:600;"><?= date('d/m', strtotime($d['day'])) ?></td>
                        <td style="color:#16a34a; font-weight:600;"><?= $d['done'] > 0 ? $d['done'] : '-' ?></td>
                        <td><?= $d['cancelled'] > 0 ? '<span style="color:#dc2626; font-weight:600;">' . $d['cancelled'] . '</span>' : '-' ?></td>
                        <td style="font-weight:700;color:var(--primary)"><?= format_currency($d['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4"><div class="empty-state" style="padding:15px;">Không có dữ liệu.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card panel">
        <div class="panel-header" style="padding:10px 16px;"><h3 style="font-size:14px; margin:0;">Top 5 món bán chạy</h3></div>
        <div class="table-wrap">
            <table class="table" style="font-size:12px;">
                <thead><tr style="font-size:11px;"><th>Món ăn</th><th>SL</th><th>Doanh thu</th></tr></thead>
                <tbody>
                <?php if ($topItems && $topItems->num_rows > 0): ?>
                    <?php $count = 0; while (($t = $topItems->fetch_assoc()) && $count < 5): $count++; ?>
                    <tr>
                        <td style="max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($t['item_name']) ?></td>
                        <td><span class="badge badge-role" style="font-size:10px; padding:2px 6px;"><?= $t['qty'] ?></span></td>
                        <td style="font-weight:700;color:var(--primary)"><?= format_currency($t['revenue']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3"><div class="empty-state" style="padding:15px;">Không có dữ liệu.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CSS cho biểu đồ tùy chỉnh -->
<style>
.chart-bar:hover {
    opacity: 0.8;
    cursor: pointer;
}
</style>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>

