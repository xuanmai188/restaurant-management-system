<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['thungan', 'admin', 'quanly']);

function format_money_local($amount): string {
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

// ── Input validation ─────────────────────────────────────────────────────────
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$method   = $_GET['method']    ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');

// Sync trạng thái reservation theo thời gian thực
syncReservationStatus();

$fromStart = $dateFrom . ' 00:00:00';
$toEnd     = date('Y-m-d', strtotime($dateTo . ' +1 day')) . ' 00:00:00';

$methodLabel = [
    'cash'             => 'Tiền mặt',
    'bank_transfer'    => 'Chuyển khoản',
    'card'             => 'Thẻ',
    'deposit_consumed' => 'Cấn cọc',
    'manual'           => 'Đổi trạng thái',
];

// ── CARD 1: Đơn đã thanh toán — từ payments + orders da_thanh_toan không có payment ──
$paidStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT order_id) AS total_orders,
        IFNULL(SUM(amount_paid), 0) AS total_paid
    FROM (
        -- Đơn thu qua quầy (có payment record)
        SELECT p.order_id, p.amount_paid
        FROM payments p
        WHERE p.payment_status = 'thanh_cong'
          AND p.payment_time >= ? AND p.payment_time < ?

        UNION ALL

        -- Đơn admin/quản lý đổi trạng thái thủ công (không có payment record)
        SELECT o.order_id, o.total_amount AS amount_paid
        FROM orders o
        WHERE o.status = 'da_thanh_toan'
          AND o.order_time >= ? AND o.order_time < ?
          AND NOT EXISTS (
              SELECT 1 FROM payments p2
              WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
          )
    ) combined
");
$paidStmt->bind_param('ssss', $fromStart, $toEnd, $fromStart, $toEnd);
$paidStmt->execute();
$paidRow      = $paidStmt->get_result()->fetch_assoc();
$paidStmt->close();
$totalPaid    = (float)($paidRow['total_paid']   ?? 0);
$totalOrders  = (int)($paidRow['total_orders']   ?? 0);

// ── CARD 2: Cọc đang giữ — reservation chưa kết thúc lifecycle ──────────────
// Chỉ hiển thị: cho_xac_nhan, da_xac_nhan, da_checkin
// KHÔNG hiển thị: hoan_thanh, khong_den, da_huy (đã kết thúc)
$depositStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT r.reservation_id) AS total_res,
        IFNULL(SUM(rp.amount), 0) AS total_deposit
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('cho_xac_nhan', 'da_xac_nhan', 'da_checkin')
      AND rp.deposit_status != 'da_su_dung'
      AND rp.payment_status = 'thanh_cong'
      AND rp.payment_time >= ? AND rp.payment_time < ?
");
$depositStmt->bind_param('ss', $fromStart, $toEnd);
$depositStmt->execute();
$depositRow    = $depositStmt->get_result()->fetch_assoc();
$depositStmt->close();
$activeDeposit = (float)($depositRow['total_deposit'] ?? 0);
$activeResCount= (int)($depositRow['total_res']       ?? 0);

// Cọc bị giữ lại do khách không đến / hủy
$forfeitedStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT r.reservation_id) AS total_res,
        IFNULL(SUM(rp.amount), 0) AS total_deposit
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status IN ('khong_den', 'da_huy')
      AND rp.payment_status = 'thanh_cong'
      AND r.reservation_time >= ? AND r.reservation_time < ?
");
$forfeitedStmt->bind_param('ss', $fromStart, $toEnd);
$forfeitedStmt->execute();
$forfeitedRow    = $forfeitedStmt->get_result()->fetch_assoc();
$forfeitedStmt->close();
$forfeitedDeposit = (float)($forfeitedRow['total_deposit'] ?? 0);
$forfeitedResCount= (int)($forfeitedRow['total_res']       ?? 0);

// ── CARD 3: Tổng dòng tiền = đơn đã thanh toán + cọc đang giữ + cọc bị giữ lại ──
$totalCashFlow = $totalPaid + $activeDeposit + $forfeitedDeposit;

// ── BẢNG 1: Đơn hàng đã thanh toán ─────────────────────────────────────────
$methodClause = '';
$bindTypes    = 'ss';
$bindParams   = [$fromStart, $toEnd];

if ($method && array_key_exists($method, $methodLabel)) {
    $methodClause = 'AND p.payment_method = ?';
    $bindTypes   .= 's';
    $bindParams[] = $method;
}

$payStmt = $conn->prepare("
    SELECT
        p.payment_id, p.amount_paid, p.payment_method, p.payment_time,
        o.order_id, o.total_amount,
        t.table_name, f.floor_name,
        c.customer_name
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    LEFT JOIN tables    t ON t.table_id    = o.table_id
    LEFT JOIN floors    f ON f.floor_id    = t.floor_id
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    WHERE p.payment_status = 'thanh_cong'
      AND p.payment_time >= ? AND p.payment_time < ?
      $methodClause

    UNION ALL

    -- Đơn admin/quản lý đổi trạng thái thủ công (không có payment record)
    SELECT
        NULL AS payment_id, o.total_amount AS amount_paid, 'manual' AS payment_method,
        o.order_time AS payment_time,
        o.order_id, o.total_amount,
        t.table_name, f.floor_name,
        c.customer_name
    FROM orders o
    LEFT JOIN tables    t ON t.table_id    = o.table_id
    LEFT JOIN floors    f ON f.floor_id    = t.floor_id
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    WHERE o.status = 'da_thanh_toan'
      AND o.order_time >= ? AND o.order_time < ?
      AND NOT EXISTS (
          SELECT 1 FROM payments p2
          WHERE p2.order_id = o.order_id AND p2.payment_status = 'thanh_cong'
      )
      AND (? = '' OR 'manual' = ?)

    ORDER BY payment_time DESC
    LIMIT 200
");
// Bind params: fromStart, toEnd (payments), [method nếu có], fromStart, toEnd (orders manual), method, method
if ($method && array_key_exists($method, $methodLabel)) {
    $bindTypes  = 'sss' . 'ss' . 'ss';
    $bindParams = [$fromStart, $toEnd, $method, $fromStart, $toEnd, $method, $method];
} else {
    $bindTypes  = 'ss' . 'ss' . 'ss';
    $bindParams = [$fromStart, $toEnd, $fromStart, $toEnd, '', ''];
}
$payStmt->bind_param($bindTypes, ...$bindParams);
$payStmt->execute();
$paymentsResult = $payStmt->get_result();
$payStmt->close();

// ── BẢNG 2: Reservation còn giữ cọc (active liabilities) ────────────────────
// CHỈ hiển thị reservation chưa kết thúc lifecycle
$resStmt = $conn->prepare("
    SELECT
        r.reservation_id, r.status AS res_status, r.reservation_time,
        r.number_of_people,
        t.table_name, f.floor_name,
        COALESCE(u.full_name, u.username, 'Khách') AS customer_name,
        rp_agg.deposit_amount,
        rp_agg.payment_method AS deposit_method
    FROM reservations r
    LEFT JOIN tables t ON t.table_id = r.table_id
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    LEFT JOIN users u ON u.user_id = r.user_id
    LEFT JOIN (
        SELECT reservation_id,
               SUM(amount) AS deposit_amount,
               MAX(payment_method) AS payment_method
        FROM reservation_payments
        WHERE payment_status = 'thanh_cong'
          AND deposit_status != 'da_su_dung'
        GROUP BY reservation_id
    ) rp_agg ON rp_agg.reservation_id = r.reservation_id
    WHERE r.status IN ('cho_xac_nhan', 'da_xac_nhan', 'da_checkin')
      AND rp_agg.deposit_amount > 0
      AND r.reservation_time >= ? AND r.reservation_time < ?
    ORDER BY r.reservation_time ASC
    LIMIT 200
");
$resStmt->bind_param('ss', $fromStart, $toEnd);
$resStmt->execute();
$reservationsResult = $resStmt->get_result();
$resStmt->close();

$pageTitle   = 'Lịch sử thanh toán';
$activeMenu  = 'cashier_history';
$sidebarRole = 'thungan';
include __DIR__ . '/../includes/layout.php';
?>

<!-- Bộ lọc -->
<form method="GET" class="card" style="padding:18px; margin-bottom:20px;">
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:14px; align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Từ ngày</label>
            <input class="input" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Đến ngày</label>
            <input class="input" type="date" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Phương thức</label>
            <select class="select" name="method">
                <option value="">Tất cả</option>
                <?php foreach ($methodLabel as $val => $lbl): ?>
                    <?php if ($val === 'deposit_consumed') continue; // Ẩn khỏi filter ?>
                    <option value="<?= $val ?>" <?= $method===$val?'selected':'' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Lọc</button>
    </div>
</form>

<!-- 3 Cards đúng nghiệp vụ -->
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px;">

    <!-- Card 1: Đơn đã thanh toán (revenue realized) -->
    <div style="background:white; border-radius:12px; padding:16px 18px; border-left:4px solid #16a34a; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Đơn đã thanh toán</div>
        <div style="font-size:26px; font-weight:800; color:#16a34a; line-height:1.2; margin:4px 0;"><?= format_money_local($totalPaid) ?></div>
        <div style="font-size:12px; color:#9ca3af;"><?= $totalOrders ?> đơn hoàn tất</div>
    </div>

    <!-- Card 2: Cọc đang giữ (active liability) -->
    <div style="background:white; border-radius:12px; padding:16px 18px; border-left:4px solid <?= $activeDeposit > 0 ? '#f59e0b' : '#9ca3af' ?>; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Cọc đang giữ</div>
        <div style="font-size:26px; font-weight:800; color:<?= $activeDeposit > 0 ? '#d97706' : '#9ca3af' ?>; line-height:1.2; margin:4px 0;"><?= format_money_local($activeDeposit) ?></div>
        <div style="font-size:12px; color:#9ca3af;"><?= $activeResCount ?> reservation chưa hoàn tất</div>
    </div>

    <!-- Card 3: Cọc bị giữ lại (forfeited) -->
    <div style="background:white; border-radius:12px; padding:16px 18px; border-left:4px solid <?= $forfeitedDeposit > 0 ? '#dc2626' : '#9ca3af' ?>; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Cọc giữ lại (không đến)</div>
        <div style="font-size:26px; font-weight:800; color:<?= $forfeitedDeposit > 0 ? '#dc2626' : '#9ca3af' ?>; line-height:1.2; margin:4px 0;"><?= format_money_local($forfeitedDeposit) ?></div>
        <div style="font-size:12px; color:#9ca3af;"><?= $forfeitedResCount ?> reservation hủy/không đến</div>
    </div>
</div>

<!-- Bảng 1: Đơn hàng đã thanh toán (completed sales) -->
<div class="card panel" style="margin-bottom:16px;">
    <h3 style="margin:0 0 14px; font-size:16px; font-weight:700; color:#065f46;">
        Đơn hàng đã thanh toán
        <span style="font-size:13px; font-weight:500; color:#9ca3af; margin-left:8px;"><?= $totalOrders ?> đơn</span>
    </h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Đơn</th><th>Bàn</th><th>Khách</th>
                    <th>Số tiền</th><th>Phương thức</th><th>Thời gian</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $hasPayments = false;
            while ($p = $paymentsResult->fetch_assoc()):
                $hasPayments = true;
                $methodDisplay = $methodLabel[$p['payment_method']] ?? $p['payment_method'];
                $isDeposit = $p['payment_method'] === 'deposit_consumed';
            ?>
                <tr>
                    <td><strong>#<?= $p['order_id'] ?></strong></td>
                    <td><?= e(($p['floor_name'] ?? '') . ' – ' . ($p['table_name'] ?? '–')) ?></td>
                    <td><?= e($p['customer_name'] ?? 'Khách vãng lai') ?></td>
                    <td style="font-weight:700; color:#065f46;"><?= format_money_local($p['amount_paid']) ?></td>
                    <td>
                        <span style="display:inline-block; padding:3px 10px; border-radius:8px; font-size:12px; font-weight:700;
                            background:<?= $isDeposit ? '#fef3c7' : '#dcfce7' ?>;
                            color:<?= $isDeposit ? '#d97706' : '#065f46' ?>;">
                            <?= $methodDisplay ?>
                        </span>
                    </td>
                    <td style="font-size:13px; color:#6b7280;"><?= date('H:i d/m', strtotime($p['payment_time'])) ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if (!$hasPayments): ?>
                <tr><td colspan="6"><div class="empty-state">Không có giao dịch nào</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bảng 2: Reservation còn giữ cọc (active liabilities) -->
<div class="card panel" style="margin-bottom:16px;">
    <h3 style="margin:0 0 14px; font-size:16px; font-weight:700; color:#d97706;">
        Reservation còn giữ cọc
        <span style="font-size:13px; font-weight:500; color:#9ca3af; margin-left:8px;"><?= $activeResCount ?> reservation</span>
    </h3>
    <?php if ($activeResCount === 0): ?>
        <div style="padding:24px; text-align:center; color:#9ca3af; font-size:14px;">
            Không có reservation nào đang giữ cọc trong khoảng này
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Reservation</th><th>Bàn</th><th>Khách</th>
                    <th>Số người</th><th>Cọc</th><th>Trạng thái</th><th>Giờ đặt</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $resStatusMap = [
                'cho_xac_nhan' => ['label' => 'Chờ xác nhận', 'bg' => '#fef3c7', 'color' => '#d97706'],
                'da_xac_nhan'  => ['label' => 'Đã xác nhận',  'bg' => '#dbeafe', 'color' => '#1e40af'],
                'da_checkin'   => ['label' => 'Đã check-in',  'bg' => '#dcfce7', 'color' => '#166534'],
            ];
            while ($r = $reservationsResult->fetch_assoc()):
                $rs = $resStatusMap[$r['res_status']] ?? ['label' => $r['res_status'], 'bg' => '#f3f4f6', 'color' => '#6b7280'];
            ?>
                <tr>
                    <td><strong>#<?= $r['reservation_id'] ?></strong></td>
                    <td><?= e(($r['floor_name'] ?? '') . ' – ' . ($r['table_name'] ?? '–')) ?></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td><?= $r['number_of_people'] ?> người</td>
                    <td style="font-weight:700; color:#d97706;"><?= format_money_local($r['deposit_amount']) ?></td>
                    <td>
                        <span style="display:inline-block; padding:3px 10px; border-radius:8px; font-size:12px; font-weight:700; background:<?= $rs['bg'] ?>; color:<?= $rs['color'] ?>;">
                            <?= $rs['label'] ?>
                        </span>
                    </td>
                    <td style="font-size:13px; color:#6b7280;"><?= date('H:i d/m', strtotime($r['reservation_time'])) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Bảng 3: Cọc bị giữ lại (forfeited) -->
<div class="card panel">
    <h3 style="margin:0 0 14px; font-size:16px; font-weight:700; color:#dc2626;">
        Cọc giữ lại (khách không đến / hủy)
        <span style="font-size:13px; font-weight:500; color:#9ca3af; margin-left:8px;"><?= $forfeitedResCount ?> reservation</span>
    </h3>
    <?php if ($forfeitedResCount === 0): ?>
        <div style="padding:24px; text-align:center; color:#9ca3af; font-size:14px;">
            Không có reservation nào bị giữ cọc trong khoảng này
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Reservation</th><th>Bàn</th><th>Khách</th>
                    <th>Số người</th><th>Cọc giữ lại</th><th>Lý do</th><th>Giờ đặt</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $forfeitedResStmt = $conn->prepare("
                SELECT
                    r.reservation_id, r.status AS res_status, r.reservation_time,
                    r.number_of_people,
                    t.table_name, f.floor_name,
                    COALESCE(u.full_name, u.username, 'Khách') AS customer_name,
                    rp_agg.deposit_amount
                FROM reservations r
                LEFT JOIN tables t ON t.table_id = r.table_id
                LEFT JOIN floors f ON f.floor_id = t.floor_id
                LEFT JOIN users u ON u.user_id = r.user_id
                LEFT JOIN (
                    SELECT reservation_id, SUM(amount) AS deposit_amount
                    FROM reservation_payments
                    WHERE payment_status = 'thanh_cong'
                    GROUP BY reservation_id
                ) rp_agg ON rp_agg.reservation_id = r.reservation_id
                WHERE r.status IN ('khong_den', 'da_huy')
                  AND rp_agg.deposit_amount > 0
                  AND r.reservation_time >= ? AND r.reservation_time < ?
                ORDER BY r.reservation_time DESC
                LIMIT 200
            ");
            $forfeitedResStmt->bind_param('ss', $fromStart, $toEnd);
            $forfeitedResStmt->execute();
            $forfeitedResResult = $forfeitedResStmt->get_result();
            $forfeitedResStmt->close();

            while ($r = $forfeitedResResult->fetch_assoc()):
            ?>
                <tr style="background:#fff5f5;">
                    <td><strong>#<?= $r['reservation_id'] ?></strong></td>
                    <td><?= e(($r['floor_name'] ?? '') . ' – ' . ($r['table_name'] ?? '–')) ?></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td><?= $r['number_of_people'] ?> người</td>
                    <td style="font-weight:700; color:#dc2626;"><?= format_money_local($r['deposit_amount']) ?></td>
                    <td>
                        <?php if ($r['res_status'] === 'khong_den'): ?>
                            <span style="display:inline-block; padding:3px 10px; border-radius:8px; font-size:12px; font-weight:700; background:#fef3c7; color:#d97706;">Không đến</span>
                        <?php else: ?>
                            <span style="display:inline-block; padding:3px 10px; border-radius:8px; font-size:12px; font-weight:700; background:#fee2e2; color:#dc2626;">Đã hủy</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px; color:#6b7280;"><?= date('H:i d/m', strtotime($r['reservation_time'])) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>
