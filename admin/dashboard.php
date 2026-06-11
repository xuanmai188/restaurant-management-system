<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

// Tiền thu quầy (payments table — giống cashier/index.php)
$r1 = $conn->query("
    SELECT COALESCE(SUM(p.amount_paid), 0) AS total, COUNT(DISTINCT p.order_id) AS cnt
    FROM payments p
    WHERE p.payment_status = 'thanh_cong'
      AND DATE(p.payment_time) BETWEEN '$dateFrom' AND '$dateTo'
");
$rev_quay = $r1 ? $r1->fetch_assoc() : ['total'=>0,'cnt'=>0];

// Tiền cọc đã thu (reservation_payments — giống cashier/history.php)
$r1b = $conn->query("
    SELECT COALESCE(SUM(rp.amount), 0) AS total, COUNT(*) AS cnt
    FROM reservation_payments rp
    WHERE rp.payment_status IN ('thanh_cong','cho_xu_ly')
      AND DATE(rp.payment_time) BETWEEN '$dateFrom' AND '$dateTo'
");
$rev_coc = $r1b ? $r1b->fetch_assoc() : ['total'=>0,'cnt'=>0];

$total_revenue = (float)$rev_quay['total'] + (float)$rev_coc['total'];

// Đơn đã thanh toán
$r2 = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status='da_thanh_toan' AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'");
$orders_paid = $r2 ? (int)$r2->fetch_assoc()['cnt'] : 0;

// Đơn đang cọc
$r2b = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status='da_dat_coc' AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'");
$orders_coc = $r2b ? (int)$r2b->fetch_assoc()['cnt'] : 0;

// Đơn chờ thanh toán hôm nay
$r2c = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status='hoan_thanh'");
$orders_pending = $r2c ? (int)$r2c->fetch_assoc()['cnt'] : 0;

// Doanh thu theo ngày
$daily_quay_res = $conn->query("
    SELECT DATE(p.payment_time) AS day, SUM(p.amount_paid) AS total, COUNT(DISTINCT p.order_id) AS cnt
    FROM payments p
    WHERE p.payment_status = 'thanh_cong'
      AND DATE(p.payment_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(p.payment_time)
    ORDER BY day DESC
");
$daily_map = [];
if ($daily_quay_res) {
    while ($row = $daily_quay_res->fetch_assoc()) {
        $daily_map[$row['day']] = ['total' => (float)$row['total'], 'cnt' => (int)$row['cnt']];
    }
}
$daily_coc_res = $conn->query("
    SELECT DATE(rp.payment_time) AS day, SUM(rp.amount) AS total
    FROM reservation_payments rp
    WHERE rp.payment_status IN ('thanh_cong','cho_xu_ly')
      AND DATE(rp.payment_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(rp.payment_time)
");
if ($daily_coc_res) {
    while ($row = $daily_coc_res->fetch_assoc()) {
        $d = $row['day'];
        $daily_map[$d]['total'] = ($daily_map[$d]['total'] ?? 0) + (float)$row['total'];
        $daily_map[$d]['cnt']   = $daily_map[$d]['cnt'] ?? 0;
    }
}
krsort($daily_map);

// Top món bán chạy
$topItems = $conn->query("
    SELECT mi.item_name, SUM(od.quantity) AS qty, SUM(od.quantity * od.unit_price) AS revenue
    FROM   order_details od
    JOIN   menu_items mi ON mi.item_id = od.item_id
    JOIN   orders o ON o.order_id = od.order_id
    WHERE  o.status = 'da_thanh_toan'
      AND  DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP  BY od.item_id
    ORDER  BY qty DESC
    LIMIT  10
");

$pageTitle = 'Báo cáo doanh thu'; $pageSubtitle = 'Thống kê theo khoảng thời gian';
$activeMenu = 'reports'; $sidebarRole = 'admin';
include __DIR__ . '/../includes/layout.php';
?>

<form method="GET" class="card" style="padding:18px; margin-bottom:20px;">
    <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:14px; align-items:end;">
        <div class="form-group" style="margin:0;"><label>Từ ngày</label><input class="input" type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div class="form-group" style="margin:0;"><label>Đến ngày</label><input class="input" type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        <button class="btn btn-primary" type="submit">Xem báo cáo</button>
    </div>
</form>

<div class="stats" style="grid-template-columns:repeat(3,1fr); margin-bottom:24px;">
    <div class="stat-card">
        <p>Tổng doanh thu</p>
        <h3><?= format_currency($total_revenue) ?></h3>
        <span style="color:var(--success);">Quầy: <?= format_currency($rev_quay['total']) ?> · Cọc: <?= format_currency($rev_coc['total']) ?></span>
    </div>
    <div class="stat-card">
        <p>Đơn đã thanh toán</p>
        <h3><?= $orders_paid ?> đơn<?= $orders_coc > 0 ? ' + ' . $orders_coc . ' cọc' : '' ?></h3>
        <span>Đã thanh toán: <?= $orders_paid ?> · Đang cọc: <?= $orders_coc ?></span>
    </div>
    <div class="stat-card">
        <p>Chờ thanh toán</p>
        <h3 style="color:#d97706;"><?= $orders_pending ?></h3>
        <span>Đơn hoàn thành chưa thu tiền</span>
    </div>
</div>

<div class="content-grid">
    <div class="card panel">
        <div class="panel-header"><h3>Doanh thu theo ngày</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Ngày</th><th>Số giao dịch</th><th>Doanh thu</th></tr></thead>
                <tbody>
                <?php if (!empty($daily_map)): ?>
                    <?php foreach ($daily_map as $day => $d): ?>
                    <tr>
                        <td><?= e($day) ?></td>
                        <td><?= $d['cnt'] ?></td>
                        <td style="font-weight:700;color:var(--primary)"><?= format_currency($d['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3"><div class="empty-state">Không có dữ liệu.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card panel">
        <div class="panel-header"><h3>Top 10 món bán chạy</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Món ăn</th><th>SL</th><th>Doanh thu</th></tr></thead>
                <tbody>
                <?php if ($topItems && $topItems->num_rows > 0): ?>
                    <?php while ($t = $topItems->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($t['item_name']) ?></td>
                        <td><span class="badge badge-role"><?= $t['qty'] ?></span></td>
                        <td style="font-weight:700;color:var(--primary)"><?= format_currency($t['revenue']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3"><div class="empty-state">Không có dữ liệu.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>
