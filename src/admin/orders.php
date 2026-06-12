<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

function fmt($v) { return number_format((float)$v, 0, ',', '.') . ' đ'; }

// Cập nhật trạng thái đơn
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $st  = $conn->real_escape_string($_GET['status']);

    // Lấy table_id trước khi update để sync bàn sau
    $orderRow = $conn->query("SELECT table_id FROM orders WHERE order_id=$id")->fetch_assoc();
    $conn->query("UPDATE orders SET status='$st' WHERE order_id=$id");

    // Đồng bộ trạng thái bàn ngay sau khi đổi trạng thái đơn
    if ($orderRow) {
        sync_table_status();
    }

    $key = $_GET['key'] ?? '';
    $qs  = http_build_query(array_filter([
        'key'           => $key,
        'date_from'     => $_GET['date_from'] ?? '',
        'date_to'       => $_GET['date_to']   ?? '',
        'status_filter' => $_GET['status_filter'] ?? '',
        'keyword'       => $_GET['keyword'] ?? '',
    ]));
    header('Location: orders.php?' . $qs); exit;
}

$dateFrom      = $_GET['date_from']     ?? date('Y-m-d');
$dateTo        = $_GET['date_to']       ?? date('Y-m-d');
$filter_status = trim($_GET['status_filter'] ?? '');
$keyword       = trim($_GET['keyword']       ?? '');
$keyParam      = isset($_GET['key']) ? '&key=' . urlencode($_GET['key']) : '';

// ── Thống kê ────────────────────────────────────────────────────────────────
// Chỉ đếm orders có trạng thái thực sự (loại trừ đơn da_dat_coc vì đã tính trong reservation)
$totalOrdersRes = $conn->query("
    SELECT COUNT(*) AS cnt FROM orders
    WHERE status IN ('da_thanh_toan','hoan_thanh','da_huy','dang_xu_ly','dang_che_bien','dang_phuc_vu','moi')
      AND reservation_id IS NULL
      AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'
");
$totalOrdersCount = $totalOrdersRes ? (int)$totalOrdersRes->fetch_assoc()['cnt'] : 0;

// Đếm thêm orders có reservation (đặt bàn đến ăn) — tính riêng
$totalOrdersWithResRes = $conn->query("
    SELECT COUNT(*) AS cnt FROM orders
    WHERE status IN ('da_thanh_toan','hoan_thanh','da_huy','dang_xu_ly','dang_che_bien','dang_phuc_vu','moi')
      AND reservation_id IS NOT NULL
      AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'
");
$totalOrdersWithResCount = $totalOrdersWithResRes ? (int)$totalOrdersWithResRes->fetch_assoc()['cnt'] : 0;

// Reservations chưa có order (chỉ đặt cọc, chưa đến ăn)
$totalResRes = $conn->query("
    SELECT COUNT(*) AS cnt FROM reservations r
    WHERE DATE(reservation_time) BETWEEN '$dateFrom' AND '$dateTo'
      AND NOT EXISTS (
          SELECT 1 FROM orders o WHERE o.reservation_id = r.reservation_id
            AND o.status NOT IN ('da_huy','da_dat_coc')
      )
");
$totalResCount = $totalResRes ? (int)$totalResRes->fetch_assoc()['cnt'] : 0;

$paidRes = $conn->query("SELECT COUNT(*) AS cnt, IFNULL(SUM(total_amount),0) AS total FROM orders WHERE status='da_thanh_toan' AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'");
$paid = $paidRes ? $paidRes->fetch_assoc() : ['cnt'=>0,'total'=>0];

$cancelRes = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status='da_huy' AND DATE(order_time) BETWEEN '$dateFrom' AND '$dateTo'");
$cancelCount = $cancelRes ? (int)$cancelRes->fetch_assoc()['cnt'] : 0;

// Tiền thu quầy
$quayRes = $conn->query("SELECT IFNULL(SUM(amount_paid),0) AS total FROM payments WHERE payment_status='thanh_cong' AND DATE(payment_time) BETWEEN '$dateFrom' AND '$dateTo'");
$quayTotal = $quayRes ? (float)$quayRes->fetch_assoc()['total'] : 0;

// Tiền cọc đặt bàn
$cocRes = $conn->query("SELECT IFNULL(SUM(amount),0) AS total FROM reservation_payments WHERE payment_status IN ('thanh_cong','cho_xu_ly') AND DATE(payment_time) BETWEEN '$dateFrom' AND '$dateTo'");
$cocTotal = $cocRes ? (float)$cocRes->fetch_assoc()['total'] : 0;

// Cọc bị hủy giữ lại
$cancelDepRes = $conn->query("
    SELECT IFNULL(SUM(amount),0) AS total FROM (
        SELECT rp.amount FROM reservations r
        JOIN reservation_payments rp ON r.reservation_id = rp.reservation_id
        WHERE r.status='da_huy' AND DATE(r.created_at) BETWEEN '$dateFrom' AND '$dateTo'
        UNION ALL
        SELECT o.paid_amount FROM orders o
        WHERE o.status='da_huy' AND o.paid_amount>0 AND DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'
    ) x
");
$cancelDep = $cancelDepRes ? (float)$cancelDepRes->fetch_assoc()['total'] : 0;
$totalRevenue = $quayTotal + $cocTotal;

// ── Query chính: gộp orders + reservations giống cashier/history.php ────────
$whereOrder = "o.status IN ('da_thanh_toan','hoan_thanh','da_huy','da_dat_coc')
               AND DATE(o.order_time) BETWEEN '$dateFrom' AND '$dateTo'";
if ($filter_status) $whereOrder .= " AND o.status='" . $conn->real_escape_string($filter_status) . "'";
if ($keyword) {
    $kw = $conn->real_escape_string("%$keyword%");
    $whereOrder .= " AND (t.table_name LIKE '$kw' OR c.customer_name LIKE '$kw')";
}

$payments = $conn->query("
    SELECT
        o.order_id AS id,
        o.total_amount,
        o.paid_amount,
        o.status,
        COALESCE(p.payment_time, o.order_time) AS payment_time,
        t.table_name, f.floor_name,
        COALESCE(c.customer_name, u.full_name, 'Khách vãng lai') AS customer_name,
        'order' AS type,
        CASE
            WHEN p.payment_id IS NOT NULL THEN 'Thu quầy'
            WHEN o.status='da_huy' THEN 'Đã hủy'
            WHEN o.status='da_dat_coc' THEN 'Đã cọc'
            ELSE 'Cọc 100%'
        END AS payment_method_display,
        COALESCE(p.payment_method,'cash') AS payment_method,
        CASE
            WHEN o.status='da_huy' THEN 'da_huy'
            WHEN o.status='da_dat_coc' THEN 'da_dat_coc'
            WHEN p.payment_id IS NOT NULL THEN 'thu_quay'
            ELSE 'thu_quay'
        END AS loai,
        COALESCE((
            SELECT SUM(rp2.amount) FROM reservation_payments rp2
            WHERE rp2.reservation_id=o.reservation_id AND rp2.payment_status IN ('thanh_cong','cho_xu_ly')
        ),0) AS deposit_amount,
        o.reservation_id
    FROM orders o
    LEFT JOIN tables    t ON t.table_id    = o.table_id
    LEFT JOIN floors    f ON f.floor_id    = t.floor_id
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    LEFT JOIN users     u ON u.user_id     = o.waiter_id
    LEFT JOIN payments  p ON p.order_id    = o.order_id AND p.payment_status='thanh_cong'
    WHERE $whereOrder

    UNION ALL

    SELECT
        CONCAT('R', r.reservation_id) AS id,
        COALESCE((SELECT SUM(ri.quantity*ri.unit_price) FROM reservation_items ri WHERE ri.reservation_id=r.reservation_id), COALESCE(rp.amount,0)) AS total_amount,
        COALESCE(rp.amount,0) AS paid_amount,
        r.status,
        COALESCE(rp.payment_time, r.reservation_time) AS payment_time,
        t2.table_name, f2.floor_name,
        COALESCE(u2.full_name,'Khách đặt bàn') AS customer_name,
        'reservation' AS type,
        'Đặt bàn' AS payment_method_display,
        COALESCE(rp.payment_method,'bank_transfer') AS payment_method,
        'dat_ban' AS loai,
        COALESCE(rp.amount,0) AS deposit_amount,
        r.reservation_id
    FROM reservations r
    LEFT JOIN tables  t2 ON t2.table_id = r.table_id
    LEFT JOIN floors  f2 ON f2.floor_id = t2.floor_id
    LEFT JOIN users   u2 ON u2.user_id  = r.user_id
    LEFT JOIN reservation_payments rp ON rp.reservation_id=r.reservation_id AND rp.payment_status IN ('thanh_cong','cho_xu_ly')
    WHERE r.status IN ('hoan_thanh','da_thanh_toan','da_huy')
      AND DATE(COALESCE(rp.payment_time, r.created_at)) BETWEEN '$dateFrom' AND '$dateTo'
      -- Chỉ hiển thị reservation khi KHÔNG có order đã thanh toán liên kết
      -- (tránh hiển thị 2 lần khi khách đến ăn và đã thanh toán qua quầy)
      AND NOT EXISTS (
          SELECT 1 FROM orders o2
          WHERE o2.reservation_id = r.reservation_id
            AND o2.status = 'da_thanh_toan'
      )

    ORDER BY payment_time DESC
");

$methodLabel = ['cash'=>'Tiền mặt','bank_transfer'=>'Chuyển khoản','card'=>'Thẻ'];
$statusLabel = ['moi'=>'Mới','dang_xu_ly'=>'Đang xử lý','dang_che_bien'=>'Đang nấu','dang_phuc_vu'=>'Đang phục vụ','hoan_thanh'=>'Hoàn thành','da_thanh_toan'=>'Đã thanh toán','da_huy'=>'Đã hủy','da_dat_coc'=>'Đã cọc'];
$allStatusLabel = $statusLabel;

$pageTitle = 'Quản lý đơn hàng';
$activeMenu = 'orders'; $sidebarRole = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<!-- Bộ lọc -->
<form method="GET" class="card" style="padding:16px; margin-bottom:20px;">
    <?php if (isset($_GET['page'])): ?>
        <input type="hidden" name="page" value="<?= e($_GET['page']) ?>">
    <?php endif; ?>
    <?php if (isset($_GET['key'])): ?>
        <input type="hidden" name="key" value="<?= e($_GET['key']) ?>">
    <?php endif; ?>
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr auto; gap:12px; align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Từ ngày</label>
            <input class="input" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Đến ngày</label>
            <input class="input" type="date" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Trạng thái</label>
            <select class="select" name="status_filter">
                <option value="">Tất cả</option>
                <?php foreach ($statusLabel as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $filter_status===$val?'selected':'' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Tìm kiếm</label>
            <input class="input" type="text" name="keyword" placeholder="Bàn, khách hàng..." value="<?= e($keyword) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Lọc</button>
    </div>
</form>

<!-- Thống kê -->
<div class="stats" style="grid-template-columns:repeat(3,1fr); margin-bottom:20px;">
    <div class="stat-card">
        <p>Tổng đơn trong khoảng</p>
        <h3><?= $totalOrdersCount + $totalOrdersWithResCount + $totalResCount ?></h3>
        <span><?= $totalOrdersCount + $totalOrdersWithResCount ?> đơn hàng · <?= $totalResCount ?> đặt bàn chờ</span>
        <span style="display:block; margin-top:4px; font-size:13px; color:#6b7280;">Giá trị: <?= fmt((float)$paid['total'] + $cocTotal) ?></span>
    </div>
    <div class="stat-card">
        <p>Đã thanh toán</p>
        <h3 style="color:var(--success);"><?= fmt($quayTotal) ?></h3>
        <span><?= $paid['cnt'] ?> đơn hoàn thành · Hủy: <?= $cancelCount ?></span>
        <?php if ($cocTotal > 0): ?>
            <span style="display:block; margin-top:4px; font-size:12px; color:#6b7280;">Cọc đặt bàn: <?= fmt($cocTotal) ?></span>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <p>Tổng doanh thu thực thu</p>
        <h3><?= fmt($totalRevenue) ?></h3>
        <span style="color:var(--success);">Quầy: <?= fmt($quayTotal) ?></span>
        <?php if ($cocTotal > 0): ?>
            <span style="display:block; font-size:12px; color:#6b7280;">Cọc thu: <?= fmt($cocTotal) ?></span>
        <?php endif; ?>
        <?php if ($cancelDep > 0): ?>
            <span style="display:block; font-size:12px; color:#dc2626;">Cọc hủy giữ: <?= fmt($cancelDep) ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- Bảng gộp -->
<?php
$rows = [];
if ($payments && $payments->num_rows > 0) {
    while ($p = $payments->fetch_assoc()) $rows[] = $p;
}
$groups = [
    'thu_quay' => ['label'=>'Thu quầy',  'style'=>'background:#f0fdf4; color:#166534;', 'items'=>[]],
    'dat_ban'  => ['label'=>'Đặt bàn',   'style'=>'background:#eff6ff; color:#1e40af;', 'items'=>[]],
    'da_huy'   => ['label'=>'Đã hủy',    'style'=>'background:#fef2f2; color:#991b1b;', 'items'=>[]],
];
$sumTotal = 0; $sumPaid = 0;
foreach ($rows as $p) {
    if ($p['loai'] === 'da_huy')          $groups['da_huy']['items'][]   = $p;
    elseif ($p['type'] === 'reservation') $groups['dat_ban']['items'][]  = $p;
    else                                  $groups['thu_quay']['items'][] = $p;
    $sumTotal += (float)$p['total_amount'];
    $sumPaid  += (float)$p['paid_amount'];
}
?>

<div class="card panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Mã đơn</th><th>Loại</th><th>Bàn</th><th>Tổng tiền</th>
                    <th>Đã trả</th><th>Phương thức</th><th>Trạng thái</th>
                    <th>Khách hàng</th><th>Thời gian</th><th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $hasAny = false;
            foreach ($groups as $gKey => $group):
                if (empty($group['items'])) continue;
                $hasAny = true;
            ?>
                <tr>
                    <td colspan="10" style="padding:8px 16px; font-weight:700; font-size:13px; <?= $group['style'] ?>">
                        <?= $group['label'] ?> (<?= count($group['items']) ?>)
                    </td>
                </tr>
                <?php foreach ($group['items'] as $p):
                    $dep        = (float)($p['deposit_amount'] ?? 0);
                    $total      = (float)$p['total_amount'];
                    $paid_amt   = (float)$p['paid_amount'];
                    $isCancelled = ($p['loai'] === 'da_huy');
                    $isRes       = ($p['type'] === 'reservation');

                    if ($isRes)          $daTra = $dep;
                    elseif ($isCancelled) $daTra = $paid_amt;
                    else                  $daTra = $paid_amt > 0 ? $paid_amt : $total;

                    $rowStyle = $isCancelled ? 'background:#fff5f5;' : '';
                ?>
                <tr style="<?= $rowStyle ?>">
                    <td style="font-weight:600;">
                        <?= $isRes ? e($p['id']) : '#'.e($p['id']) ?>
                    </td>
                    <td>
                        <?php if ($isCancelled): ?>
                            <span class="badge" style="background:#fee2e2;color:#dc2626;">Hủy</span>
                        <?php elseif ($isRes): ?>
                            <span class="badge" style="background:#dbeafe;color:#1e40af;">Đặt bàn</span>
                        <?php elseif ($p['loai'] === 'da_dat_coc'): ?>
                            <span class="badge" style="background:#fef9c3;color:#854d0e;">Đặt cọc</span>
                        <?php else: ?>
                            <span class="badge" style="background:#d1fae5;color:#065f46;">Thu quầy</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(($p['floor_name']??'').' – '.($p['table_name']??'–')) ?></td>
                    <td style="font-weight:700; color:<?= $isCancelled?'#9ca3af':'var(--primary)' ?>;">
                        <?php if ($isCancelled && !$isRes && $dep == 0): ?>
                            <span style="text-decoration:line-through; color:#9ca3af;"><?= fmt($total) ?></span>
                        <?php else: ?>
                            <?= fmt($total) ?>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600;">
                        <?php if ($isCancelled && $dep > 0): ?>
                            <span style="color:#dc2626;"><?= fmt($dep) ?></span>
                            <br><small style="color:#dc2626; font-size:11px;">Mất cọc</small>
                        <?php elseif ($isCancelled): ?>
                            <span style="color:#9ca3af;">0 đ</span>
                        <?php else: ?>
                            <span style="color:#065f46;"><?= fmt($daTra) ?></span>
                            <?php if ($isRes && $dep > 0 && $total > $dep): ?>
                                <br><small style="color:#dc2626; font-size:11px;"><?= fmt($total - $dep) ?> còn lại</small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-role"><?= e($methodLabel[$p['payment_method']] ?? $p['payment_method']) ?></span>
                    </td>
                    <td>
                        <?php
                        $st = $p['status'];
                        $stLabel = $allStatusLabel[$st] ?? $st;
                        $stStyle = match($st) {
                            'da_thanh_toan','hoan_thanh' => 'background:#d1fae5;color:#065f46;',
                            'da_huy'                     => 'background:#fee2e2;color:#dc2626;',
                            'da_dat_coc'                 => 'background:#fef9c3;color:#854d0e;',
                            default                      => 'background:#e5e7eb;color:#374151;',
                        };
                        ?>
                        <span class="badge" style="<?= $stStyle ?>"><?= $stLabel ?></span>
                    </td>
                    <td><?= e($p['customer_name'] ?? 'Khách vãng lai') ?></td>
                    <td style="font-size:13px; color:#6b7280;"><?= e($p['payment_time']) ?></td>
                    <td>
                        <?php if (!$isRes && !in_array($p['status'], ['da_thanh_toan','da_huy'])): ?>
                            <?php $dateQs = '&date_from='.urlencode($dateFrom).'&date_to='.urlencode($dateTo); ?>
                            <select onchange="location='?id=<?= $p['id'] ?>&status='+this.value+'<?= $keyParam . $dateQs ?>'"
                                    style="border:1px solid var(--border);border-radius:8px;padding:5px 8px;font-size:12px;">
                                <option value="">Đổi trạng thái</option>
                                <?php foreach ($allStatusLabel as $val => $lbl): ?>
                                    <option value="<?= $val ?>"><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if (!$hasAny): ?>
                <tr><td colspan="10"><div class="empty-state">Không có đơn hàng nào trong khoảng thời gian này.</div></td></tr>
            <?php endif; ?>

            <?php if ($hasAny): ?>
                <tr style="background:#f9fafb; border-top:2px solid #e5e7eb; font-weight:700;">
                    <td colspan="3" style="padding:12px 16px; font-size:14px;">Tổng kết</td>
                    <td style="color:var(--primary);"><?= fmt($sumTotal) ?></td>
                    <td style="color:#065f46;"><?= fmt($quayTotal + $cocTotal + $cancelDep) ?></td>
                    <td colspan="5">
                        <span style="color:#065f46; font-size:13px;">Hoàn thành: <?= fmt($quayTotal) ?></span>
                        <?php if ($cocTotal > 0): ?>
                            <span style="color:#854d0e; font-size:13px; margin-left:12px;">Cọc thu: <?= fmt($cocTotal) ?></span>
                        <?php endif; ?>
                        <?php if ($cancelDep > 0): ?>
                            <span style="color:#dc2626; font-size:13px; margin-left:12px;">Cọc hủy giữ: <?= fmt($cancelDep) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>


