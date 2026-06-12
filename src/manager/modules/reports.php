<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

// ── Query 1: Đếm orders (không tính đơn hủy) ─────────
$stmt = $conn->prepare("
    SELECT
        COUNT(CASE WHEN o.status != 'da_huy' THEN 1 END) AS tong_don,
        SUM(CASE WHEN o.status != 'da_huy' THEN o.total_amount ELSE 0 END) AS tong_tat_ca_don,
        COUNT(CASE WHEN o.status = 'da_thanh_toan' THEN 1 END) AS count_paid,
        COUNT(CASE WHEN o.status = 'da_huy'        THEN 1 END) AS count_cancel,
        COUNT(CASE WHEN o.reservation_id IS NULL AND o.status != 'da_huy' THEN 1 END) AS count_walk_in,
        COUNT(CASE WHEN o.reservation_id IS NOT NULL AND o.status != 'da_huy' THEN 1 END) AS count_from_reservation,
        COALESCE(AVG(CASE WHEN o.status = 'da_thanh_toan' THEN o.total_amount END), 0) AS avg_val
    FROM orders o
    WHERE DATE(o.order_time) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$tong_tat_ca_don  = (float)($stats['tong_tat_ca_don']  ?? 0);
$total_orders     = (int)($stats['tong_don']           ?? 0);
$paid_orders      = (int)($stats['count_paid']         ?? 0);
$walk_in_orders   = (int)($stats['count_walk_in']      ?? 0);
$reservation_orders = (int)($stats['count_from_reservation'] ?? 0);
$avg_order_value  = (float)($stats['avg_val']          ?? 0);

// Tiền thực thu từ đơn đã thanh toán — payments + đơn admin/quản lý đổi trạng thái thủ công
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount_paid), 0) AS da_thanh_toan
    FROM (
        SELECT p.amount_paid
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE p.payment_status = 'thanh_cong'
          AND DATE(p.payment_time) BETWEEN ? AND ?

        UNION ALL

        SELECT o.total_amount AS amount_paid
        FROM orders o
        WHERE o.status = 'da_thanh_toan'
          AND DATE(o.order_time) BETWEEN ? AND ?
          AND NOT EXISTS (
              SELECT 1 FROM payments p2
              WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
          )
    ) combined
");
$stmt->bind_param('ssss', $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$da_thanh_toan = (float)$stmt->get_result()->fetch_assoc()['da_thanh_toan'];

// Đếm reservations CHƯA có order (chưa check-in, chưa chuyển thành order)
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM reservations r
    WHERE DATE(r.created_at) BETWEEN ? AND ?
      AND NOT EXISTS (
          SELECT 1 FROM orders o 
          WHERE o.reservation_id = r.reservation_id
      )
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$reservations_without_order = (int)$stmt->get_result()->fetch_assoc()['cnt'];

// Tổng đơn = chỉ đếm orders thực tế (không tính đơn hủy, không cộng reservations)
$grand_total = $total_orders;

// Đếm đơn đặt cọc từ orders (có reservation_id và status là da_dat_coc/da_coc)
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt, SUM(o.total_amount) AS total
    FROM orders o
    WHERE o.reservation_id IS NOT NULL
      AND o.status IN ('da_dat_coc', 'da_coc')
      AND DATE(o.order_time) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$deposit_stats = $stmt->get_result()->fetch_assoc();
$deposit_orders   = (int)($deposit_stats['cnt'] ?? 0);
$tong_don_dat_coc = (float)($deposit_stats['total'] ?? 0);

// ── Query 2: Cọc bị giữ lại — khách không đến HOẶC đã hủy ──────────────────
// Dùng r.reservation_time để đồng nhất với trang thu ngân
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(rp.amount), 0) AS coc_bi_huy
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('khong_den', 'da_huy')
      AND rp.payment_status = 'thanh_cong'
      AND DATE(r.reservation_time) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$coc_bi_huy = (float)($stmt->get_result()->fetch_assoc()['coc_bi_huy'] ?? 0);

// ── Query 3: Cọc đang giữ — chỉ hiển thị reservation CHƯA kết thúc ──────────
// KHÔNG cộng vào doanh thu, chỉ để thông tin
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(rp.amount), 0) AS coc_dang_giu
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('cho_xac_nhan', 'da_xac_nhan', 'da_checkin')
      AND rp.payment_status IN ('thanh_cong','cho_xu_ly')
      AND DATE(rp.payment_time) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$tien_coc_thu = (float)($stmt->get_result()->fetch_assoc()['coc_dang_giu'] ?? 0);

// ── Tổng doanh thu = đã thanh toán + cọc hủy giữ lại ───────────────────────
$total_revenue = $da_thanh_toan + $coc_bi_huy;

// Tổng tiền thu tại quầy (payments không tính deposit_consumed + đơn thủ công)
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount_paid), 0) AS thu_quay
    FROM (
        SELECT p.amount_paid
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE p.payment_status = 'thanh_cong'
          AND p.payment_method != 'deposit_consumed'
          AND DATE(p.payment_time) BETWEEN ? AND ?

        UNION ALL

        SELECT o.total_amount AS amount_paid
        FROM orders o
        WHERE o.status = 'da_thanh_toan'
          AND DATE(o.order_time) BETWEEN ? AND ?
          AND NOT EXISTS (
              SELECT 1 FROM payments p2
              WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
          )
    ) combined
");
$stmt->bind_param('ssss', $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$thu_quay = (float)$stmt->get_result()->fetch_assoc()['thu_quay'];

// Phần từ cọc = tổng deposit_consumed thực sự được dùng thanh toán đơn hàng
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(p.amount_paid), 0) AS tu_coc
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    WHERE p.payment_status = 'thanh_cong'
      AND p.payment_method = 'deposit_consumed'
      AND DATE(p.payment_time) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$tu_coc = (float)$stmt->get_result()->fetch_assoc()['tu_coc'];

// ── Tổng khách ───────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT customer_id) AS cnt
    FROM orders
    WHERE status = 'da_thanh_toan'
      AND customer_id IS NOT NULL
      AND DATE(order_time) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$total_customers = (int)$stmt->get_result()->fetch_assoc()['cnt'];

// ── Doanh thu theo ngày (tất cả orders) ───────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        DATE(o.order_time) AS date,
        SUM(CASE WHEN o.status = 'da_thanh_toan' THEN o.total_amount ELSE 0 END) AS da_thanh_toan,
        COUNT(*) AS orders
    FROM orders o
    WHERE DATE(o.order_time) BETWEEN ? AND ?
    GROUP BY DATE(o.order_time)
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$daily_orders = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $daily_orders[$r['date']] = $r;
}
$stmt = $conn->prepare("
    SELECT DATE(r.reservation_time) AS date, COALESCE(SUM(rp.amount), 0) AS coc_huy
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('khong_den', 'da_huy')
      AND rp.payment_status = 'thanh_cong'
      AND DATE(r.reservation_time) BETWEEN ? AND ?
    GROUP BY DATE(r.reservation_time)
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$daily_coc_huy_res = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $daily_coc_huy_res[$r['date']] = (float)$r['coc_huy'];
}

$all_dates = array_unique(array_merge(array_keys($daily_orders), array_keys($daily_coc_huy_res)));
rsort($all_dates);

// Đếm reservations CHƯA có order theo ngày (chưa check-in)
$stmt = $conn->prepare("
    SELECT DATE(r.created_at) AS date, COUNT(*) AS cnt
    FROM reservations r
    WHERE DATE(r.created_at) BETWEEN ? AND ?
      AND NOT EXISTS (
          SELECT 1 FROM orders o 
          WHERE o.reservation_id = r.reservation_id
      )
    GROUP BY DATE(r.created_at)
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$daily_res_count = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $daily_res_count[$r['date']] = (int)$r['cnt'];
}
$all_dates = array_unique(array_merge($all_dates, array_keys($daily_res_count)));
rsort($all_dates);

$daily_revenue = [];
foreach ($all_dates as $d) {
    $rev = ($daily_orders[$d]['da_thanh_toan'] ?? 0)
         + ($daily_coc_huy_res[$d] ?? 0);
    $daily_revenue[] = [
        'date'    => $d,
        'revenue' => $rev,
        'orders'  => ($daily_orders[$d]['orders'] ?? 0) + ($daily_res_count[$d] ?? 0),
    ];
}

// ── Top món ──────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT mi.item_name,
           SUM(od.quantity) AS total_quantity,
           SUM(od.quantity * od.unit_price) AS total_revenue
    FROM order_details od
    JOIN menu_items mi ON mi.item_id = od.item_id
    JOIN orders o ON o.order_id = od.order_id
    WHERE o.status = 'da_thanh_toan'
      AND DATE(o.order_time) BETWEEN ? AND ?
    GROUP BY mi.item_id, mi.item_name
    ORDER BY total_quantity DESC
    LIMIT 10
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$top_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="module-container">
    <div class="module-header">
        <h2>Báo cáo doanh thu</h2>        
    </div>

    <!-- Bộ lọc -->
    <div style="background:white; padding:20px; border-radius:8px; margin-bottom:20px;">
        <form method="GET" style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="module" value="reports">
            <div>
                <label style="display:block; margin-bottom:4px; font-size:13px; color:#666;">Từ ngày</label>
                <input type="date" name="start_date" value="<?= e($start_date) ?>" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div>
                <label style="display:block; margin-bottom:4px; font-size:13px; color:#666;">Đến ngày</label>
                <input type="date" name="end_date" value="<?= e($end_date) ?>" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <button type="submit" style="padding:8px 20px; background:#d32f2f; color:white; border:none; border-radius:4px; cursor:pointer;">Xem báo cáo</button>
        </form>
    </div>

    <!-- Thống kê tổng quan -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:24px;">
        <div style="background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,.08);">
            <div style="color:#666; font-size:13px;">Tổng đơn hàng</div>
            <div style="font-size:30px; font-weight:700; color:#d32f2f; margin-top:8px;"><?= number_format($grand_total) ?> đơn</div>
            <div style="font-size:12px; color:#888; margin-top:4px;">
                <?= $walk_in_orders ?> walk-in · <?= $reservation_orders ?> từ đặt bàn · <?= $reservations_without_order ?> chưa check-in<br>
                Đã thanh toán: <?= $paid_orders ?> đơn
            </div>
        </div>
        <div style="background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,.08);">
            <div style="color:#666; font-size:13px;">Doanh thu thực thu</div>
            <div style="font-size:28px; font-weight:700; color:#4caf50; margin-top:8px;"><?= number_format($total_revenue) ?>đ</div>
            <div style="font-size:12px; color:#888; margin-top:4px;">
                Thu tại quầy: <?= number_format($thu_quay) ?>đ
                <?php if ($tu_coc > 0): ?> · Từ cọc: <?= number_format($tu_coc) ?>đ<?php endif; ?>
                <?php if ($coc_bi_huy > 0): ?><br><span style="color:#dc2626;">Cọc giữ lại (hủy/không đến): <?= number_format($coc_bi_huy) ?>đ</span><?php endif; ?>
                <?php if ($tien_coc_thu > 0): ?><br><span style="color:#d97706;">Cọc đang giữ: <?= number_format($tien_coc_thu) ?>đ</span><?php endif; ?>
            </div>
        </div>        <div style="background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,.08);">
            <div style="color:#666; font-size:13px;">Khách hàng</div>
            <div style="font-size:30px; font-weight:700; color:#2196f3; margin-top:8px;"><?= number_format($total_customers) ?></div>
        </div>
    </div>

    <!-- Biểu đồ doanh thu -->
    <div style="background:white; padding:20px; border-radius:8px; margin-bottom:20px;">
        <h3 style="margin-bottom:16px;">Doanh thu theo ngày</h3>
        <div style="height:360px; margin-bottom:24px;">
            <canvas id="revenueChart"></canvas>
        </div>

        <!-- Bảng chi tiết -->
        <details open>
            <summary style="cursor:pointer; padding:10px; background:#f5f5f5; border-radius:4px; margin-bottom:10px; font-weight:600;">
                Xem dữ liệu chi tiết
            </summary>
            <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="padding:12px; text-align:left; border-bottom:2px solid #ddd;">Ngày</th>
                        <th style="padding:12px; text-align:right; border-bottom:2px solid #ddd;">Số đơn</th>
                        <th style="padding:12px; text-align:right; border-bottom:2px solid #ddd;">Doanh thu</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($daily_revenue)): ?>
                    <tr><td colspan="3" style="padding:20px; text-align:center; color:#999;">Không có dữ liệu</td></tr>
                <?php else: ?>
                    <?php foreach ($daily_revenue as $row): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:12px;"><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                        <td style="padding:12px; text-align:right;"><?= $row['orders'] ?></td>
                        <td style="padding:12px; text-align:right; font-weight:700; color:#4caf50;"><?= number_format($row['revenue']) ?>đ</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </details>
    </div>

    <!-- Top món ăn -->
    <div style="background:white; padding:20px; border-radius:8px;">
        <h3 style="margin-bottom:16px;">Top 10 món bán chạy</h3>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#f5f5f5;">
                    <th style="padding:12px; text-align:left; border-bottom:2px solid #ddd;">#</th>
                    <th style="padding:12px; text-align:left; border-bottom:2px solid #ddd;">Món ăn</th>
                    <th style="padding:12px; text-align:right; border-bottom:2px solid #ddd;">Số lượng</th>
                    <th style="padding:12px; text-align:right; border-bottom:2px solid #ddd;">Doanh thu</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($top_items)): ?>
                <tr><td colspan="4" style="padding:20px; text-align:center; color:#999;">Không có dữ liệu</td></tr>
            <?php else: ?>
                <?php foreach ($top_items as $i => $item): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; color:#999;"><?= $i+1 ?></td>
                    <td style="padding:12px; font-weight:500;"><?= e($item['item_name']) ?></td>
                    <td style="padding:12px; text-align:right;"><?= number_format($item['total_quantity']) ?></td>
                    <td style="padding:12px; text-align:right; font-weight:700; color:#4caf50;"><?= number_format($item['total_revenue']) ?>đ</td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function initReportsModule() {
    const dailyData = <?= json_encode(array_reverse($daily_revenue)) ?>;
    if (!dailyData || dailyData.length === 0) return;

    const labels      = dailyData.map(d => new Date(d.date).toLocaleDateString('vi-VN', {day:'2-digit',month:'2-digit'}));
    const revenueData = dailyData.map(d => parseFloat(d.revenue));
    const ordersData  = dailyData.map(d => parseInt(d.orders));

    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    const rg = ctx.getContext('2d').createLinearGradient(0,0,0,360);
    rg.addColorStop(0,'rgba(16,185,129,.35)');
    rg.addColorStop(1,'rgba(16,185,129,.01)');

    const og = ctx.getContext('2d').createLinearGradient(0,0,0,360);
    og.addColorStop(0,'rgba(59,130,246,.35)');
    og.addColorStop(1,'rgba(59,130,246,.01)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Doanh thu',
                    data: revenueData,
                    borderColor: '#10b981', backgroundColor: rg,
                    borderWidth: 2.5, fill: true, tension: 0,
                    pointRadius: 4, pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff', pointBorderWidth: 2,
                    yAxisID: 'y'
                },
                {
                    label: 'Số đơn',
                    data: ordersData,
                    borderColor: '#3b82f6', backgroundColor: og,
                    borderWidth: 2.5, fill: true, tension: 0,
                    pointRadius: 4, pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff', pointBorderWidth: 2,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', align: 'end' },
                tooltip: {
                    backgroundColor: 'rgba(17,24,39,.95)',
                    callbacks: {
                        label: ctx => {
                            const v = ctx.parsed.y;
                            return ctx.datasetIndex === 0
                                ? 'Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(v) + 'đ'
                                : 'Số đơn: ' + v;
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#6b7280' } },
                y: {
                    position: 'left',
                    title: { display: true, text: 'Doanh thu (VNĐ)', color: '#10b981' },
                    ticks: {
                        color: '#6b7280',
                        callback: v => v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v
                    },
                    grid: { color: 'rgba(0,0,0,.04)' }
                },
                y1: {
                    position: 'right',
                    title: { display: true, text: 'Số đơn', color: '#3b82f6' },
                    ticks: { color: '#6b7280', callback: v => Math.round(v) },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}
</script>
