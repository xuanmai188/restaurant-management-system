<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['thungan', 'admin', 'quanly']);

$msg     = '';
$msgType = 'alert-success';

// ── FIX 7: Thêm deposit_consumed vào methodLabel ────────────────────────────
$methodLabel = [
    'cash'             => 'Tiền mặt',
    'bank_transfer'    => 'Chuyển khoản',
    'card'             => 'Thẻ',
    'deposit_consumed' => 'Cấn cọc',
];

function normalize_money($value): float { return (float)$value; }
function format_money_local($amount): string {
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

// ── FIX 1: Transaction + Race condition ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_order'])) {
    require_post_csrf();
    $order_id       = (int)($_POST['order_id'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $cashier_id     = (int)($_SESSION['user']['user_id'] ?? 0);

    if ($order_id <= 0) {
        $msg = 'Thiếu mã đơn hàng.';
        $msgType = 'alert-error';
    } elseif (!array_key_exists($payment_method, $methodLabel)) {
        $msg = 'Phương thức thanh toán không hợp lệ.';
        $msgType = 'alert-error';
    } else {
        $conn->begin_transaction();
        try {
            // Lock order để tránh race condition (2 cashier click cùng lúc)
            $lockStmt = $conn->prepare("SELECT order_id, status, total_amount, reservation_id, table_id FROM orders WHERE order_id = ? FOR UPDATE");
            $lockStmt->bind_param('i', $order_id);
            $lockStmt->execute();
            $order = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();

            if (!$order) {
                throw new Exception('Không tìm thấy đơn hàng.');
            }
            if ($order['status'] === 'da_thanh_toan') {
                throw new Exception('Đơn hàng này đã thanh toán rồi.');
            }
            if ($order['status'] !== 'hoan_thanh') {
                throw new Exception('Đơn hàng chưa hoàn thành, không thể thanh toán.');
            }

            $totalAmount = normalize_money($order['total_amount']);

            // Lấy tổng cọc đã thu (chỉ 1 query, không correlated subquery)
            $depStmt = $conn->prepare("
                SELECT IFNULL(SUM(amount), 0) AS deposit, IFNULL(MAX(payment_percent), 0) AS pct
                FROM reservation_payments
                WHERE reservation_id = ? AND payment_status IN ('thanh_cong','cho_xu_ly')
            ");
            $depStmt->bind_param('i', $order['reservation_id']);
            $depStmt->execute();
            $depRow = $depStmt->get_result()->fetch_assoc();
            $depStmt->close();

            $depositAmount  = normalize_money($depRow['deposit']);
            $prepaidPercent = (int)$depRow['pct'];
            $remaining      = max(0, $totalAmount - $depositAmount);

            if ($remaining <= 0) {
                // Đã cọc đủ — chốt đơn VÀ insert payment row với amount = totalAmount
                // để revenue tracking chính xác (payments là source of truth)
                $upd = $conn->prepare("UPDATE orders SET status='da_thanh_toan' WHERE order_id=? AND status='hoan_thanh'");
                $upd->bind_param('i', $order_id);
                if (!$upd->execute()) throw new Exception('Lỗi cập nhật đơn: ' . $upd->error);
                $upd->close();

                // Insert payment row với amount = totalAmount (không phải 0)
                // Đây là revenue realization — cọc đã được consume thành doanh thu
                // payment_type='deposit_consumed' để tracking rõ ràng
                $ins = $conn->prepare("INSERT INTO payments (order_id, cashier_id, payment_method, payment_type, amount_paid, payment_status) VALUES (?, ?, 'deposit_consumed', 'deposit_consumed', ?, 'thanh_cong')");
                $ins->bind_param('iid', $order_id, $cashier_id, $totalAmount);
                if (!$ins->execute()) throw new Exception('Lỗi ghi nhận cấn cọc: ' . $ins->error);
                $ins->close();
            } else {
                // Thu tiền tại quầy
                // payment_type='order_payment' để phân biệt với deposit_consumed
                $ins = $conn->prepare("INSERT INTO payments (order_id, cashier_id, payment_method, payment_type, amount_paid, payment_status) VALUES (?, ?, ?, 'order_payment', ?, 'thanh_cong')");
                $ins->bind_param('iisd', $order_id, $cashier_id, $payment_method, $remaining);
                if (!$ins->execute()) throw new Exception('Lỗi lưu thanh toán: ' . $ins->error);
                $ins->close();

                $upd = $conn->prepare("UPDATE orders SET paid_amount=?, status='da_thanh_toan' WHERE order_id=? AND status='hoan_thanh'");
                $upd->bind_param('di', $remaining, $order_id);
                if (!$upd->execute()) throw new Exception('Lỗi cập nhật đơn: ' . $upd->error);
                $upd->close();
            }

            // Trả bàn về trống
            $conn->query("UPDATE tables SET status='trong' WHERE table_id={$order['table_id']}");

            // Hoàn thành reservation nếu có + đánh dấu cọc đã dùng
            if ($order['reservation_id']) {
                $conn->query("UPDATE reservations SET status='hoan_thanh' WHERE reservation_id={$order['reservation_id']}");
                // Đánh dấu cọc đã được dùng để thanh toán
                $conn->query("
                    UPDATE reservation_payments
                    SET deposit_status = 'da_su_dung'
                    WHERE reservation_id = {$order['reservation_id']}
                      AND payment_status IN ('thanh_cong','cho_xu_ly')
                ");
            }

            $conn->commit();

            if ($remaining <= 0) {
                $msg = "Đơn #{$order_id} đã thanh toán đủ từ cọc, đã chốt thành công.";
            } else {
                $msg = "Thanh toán đơn #{$order_id} thành công. Thu thêm: " . format_money_local($remaining);
            }

        } catch (Exception $e) {
            $conn->rollback();
            $msg     = $e->getMessage();
            $msgType = 'alert-error';
        }
    }
}

// ── Dữ liệu thống kê ────────────────────────────────────────────────────────
$today      = date('Y-m-d');
$todayStart = $today . ' 00:00:00';
$todayEnd   = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';

// FIX: Revenue lấy từ payments ONLY (source of truth)
// deposit_consumed đã có amount = totalAmount nên không cần cộng thêm reservation_payments
$statRes = $conn->query("
    SELECT IFNULL(SUM(amount_paid), 0) AS total
    FROM payments
    WHERE payment_time >= '$todayStart' AND payment_time < '$todayEnd'
      AND payment_status = 'thanh_cong'
      AND payment_method != 'deposit_consumed'
");
$stat = $statRes ? $statRes->fetch_assoc() : ['total' => 0];
$revenueFromCashier = (float)$stat['total'];

// Doanh thu từ cấn cọc (deposit_consumed) hôm nay
$depositConsumedRes = $conn->query("
    SELECT IFNULL(SUM(amount_paid), 0) AS total
    FROM payments
    WHERE payment_time >= '$todayStart' AND payment_time < '$todayEnd'
      AND payment_status = 'thanh_cong'
      AND payment_method = 'deposit_consumed'
");
$revenueFromDeposit = $depositConsumedRes ? (float)$depositConsumedRes->fetch_assoc()['total'] : 0;

// Cọc hủy giữ lại (reservation bị hủy — nhà hàng giữ)
$cancelledDepRes = $conn->query("
    SELECT IFNULL(SUM(rp.amount), 0) AS total
    FROM reservations r
    JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE r.status = 'da_huy'
      AND rp.payment_status IN ('thanh_cong','cho_xu_ly')
      AND r.reservation_time >= '$todayStart' AND r.reservation_time < '$todayEnd'
");
$cancelledDep = $cancelledDepRes ? (float)$cancelledDepRes->fetch_assoc()['total'] : 0;

// Tổng tiền thu = thu trực tiếp + cấn cọc + cọc hủy giữ lại
$totalRevenue = $revenueFromCashier + $revenueFromDeposit + $cancelledDep;

// Đơn chờ thanh toán — KHÔNG filter ngày, lấy tất cả đơn hoan_thanh chưa thu tiền
// (bao gồm đơn do admin/quản lý đổi trạng thái từ bất kỳ lúc nào)
$pendingRes   = $conn->query("
    SELECT COUNT(*) AS cnt FROM orders
    WHERE status = 'hoan_thanh'
");
$pendingCount = $pendingRes ? (int)$pendingRes->fetch_assoc()['cnt'] : 0;

// FIX 1: paidCount dùng payment_time (đúng nghiệp vụ)
$paidRes = $conn->query("
    SELECT COUNT(DISTINCT p.order_id) AS cnt
    FROM payments p
    WHERE p.payment_status = 'thanh_cong'
      AND p.payment_time >= '$todayStart' AND p.payment_time < '$todayEnd'
");
$paidCount = $paidRes ? (int)$paidRes->fetch_assoc()['cnt'] : 0;

// ── FIX N+1: Load orders + items bằng JOIN một lần ──────────────────────────
$rawOrders = $conn->query("
    SELECT
        o.order_id, o.total_amount, o.paid_amount, o.status, o.order_time, o.reservation_id,
        t.table_name, f.floor_name, c.customer_name,
        IFNULL(rp_agg.deposit_amount, 0) AS prepaid_amount,
        IFNULL(rp_agg.prepaid_percent, 0) AS prepaid_percent,
        GREATEST(o.total_amount - IFNULL(rp_agg.deposit_amount, 0), 0) AS remaining_amount,
        od.order_detail_id, od.quantity, od.unit_price,
        mi.item_name
    FROM orders o
    LEFT JOIN tables    t ON t.table_id    = o.table_id
    LEFT JOIN floors    f ON f.floor_id    = t.floor_id
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    LEFT JOIN (
        SELECT reservation_id,
               SUM(amount) AS deposit_amount,
               MAX(payment_percent) AS prepaid_percent
        FROM reservation_payments
        WHERE payment_status IN ('thanh_cong','cho_xu_ly')
        GROUP BY reservation_id
    ) rp_agg ON rp_agg.reservation_id = o.reservation_id
    LEFT JOIN order_details od ON od.order_id = o.order_id
    LEFT JOIN menu_items    mi ON mi.item_id  = od.item_id
    WHERE o.status = 'hoan_thanh'
    ORDER BY o.order_time ASC, od.order_detail_id ASC
");

// Group theo order
$orderMap = [];
if ($rawOrders) {
    while ($row = $rawOrders->fetch_assoc()) {
        $oid = $row['order_id'];
        if (!isset($orderMap[$oid])) {
            $orderMap[$oid] = [
                'order_id'       => $oid,
                'total_amount'   => $row['total_amount'],
                'status'         => $row['status'],
                'order_time'     => $row['order_time'],
                'reservation_id' => $row['reservation_id'],
                'table_name'     => $row['table_name'],
                'floor_name'     => $row['floor_name'],
                'customer_name'  => $row['customer_name'],
                'prepaid_amount' => $row['prepaid_amount'],
                'prepaid_percent'=> $row['prepaid_percent'],
                'remaining_amount'=> $row['remaining_amount'],
                'items'          => [],
            ];
        }
        if ($row['order_detail_id']) {
            $orderMap[$oid]['items'][] = [
                'item_name'  => $row['item_name'],
                'quantity'   => $row['quantity'],
                'unit_price' => $row['unit_price'],
            ];
        }
    }
}
$orderList = array_values($orderMap);

// Lịch sử thanh toán hôm nay
$history = $conn->query("
    SELECT
        p.payment_id AS id,
        p.amount_paid AS amount,
        p.payment_method,
        p.payment_time,
        o.order_id,
        t.table_name,
        f.floor_name
    FROM payments p
    LEFT JOIN orders o ON o.order_id = p.order_id
    LEFT JOIN tables t ON t.table_id = o.table_id
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    WHERE p.payment_time >= '$todayStart' AND p.payment_time < '$todayEnd'
      AND p.payment_status = 'thanh_cong'
    ORDER BY p.payment_time DESC
    LIMIT 30
");

$pageTitle   = 'Thanh toán đơn hàng';
$activeMenu  = 'cashier';
$sidebarRole = 'thungan';
include __DIR__ . '/../includes/layout.php';
?>

<?php if ($msg): ?>
    <div class="alert <?= $msgType ?>"><?= e($msg) ?></div>
<?php endif; ?>

<!-- Stats -->
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:24px;">
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #d97706; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Chờ thanh toán</div>
        <div style="font-size:28px; font-weight:800; color:<?= $pendingCount > 0 ? '#d97706' : '#9ca3af' ?>; line-height:1.2;"><?= $pendingCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Đơn đang chờ</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #16a34a; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Đã thanh toán</div>
        <div style="font-size:28px; font-weight:800; color:#16a34a; line-height:1.2;"><?= $paidCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Hôm nay</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #3b82f6; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Tiền thu hôm nay</div>
        <div style="font-size:22px; font-weight:800; color:#3b82f6; line-height:1.2;"><?= format_money_local($totalRevenue) ?></div>
        <div style="font-size:11px; color:#9ca3af;">Thu trực tiếp: <?= format_money_local($revenueFromCashier) ?> · Cấn cọc: <?= format_money_local($revenueFromDeposit) ?> · Cọc hủy: <?= format_money_local($cancelledDep) ?></div>
    </div>
</div>

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
    <h3 style="font-size:18px;">Đơn hàng chờ thanh toán</h3>
    <?php if ($pendingCount > 0): ?>
        <span class="badge badge-inactive"><?= $pendingCount ?> đơn</span>
    <?php endif; ?>
</div>

<?php if (!empty($orderList)): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; margin-bottom:32px;">
        <?php foreach ($orderList as $o):
            $totalAmount     = normalize_money($o['total_amount']);
            $prepaidAmount   = normalize_money($o['prepaid_amount']);
            $remainingAmount = normalize_money($o['remaining_amount']);
            $prepaidPercent  = (int)$o['prepaid_percent'];
            $isFullyPrepaid  = $remainingAmount <= 0;

            // Serialize items cho print
            $printItems = json_encode($o['items'], JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
            <div class="card" style="padding:22px;"
                 data-order-id="<?= $o['order_id'] ?>"
                 data-order-items='<?= $printItems ?>'>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <strong style="font-size:18px;">Đơn #<?= $o['order_id'] ?></strong>
                    <span style="display:inline-block; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700; background:#dcfce7; color:#16a34a;">
                        Chờ thanh toán
                    </span>
                </div>

                <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:14px;">
                    <p style="font-size:15px; color:#111827; font-weight:600;">
                        <?= e(($o['floor_name'] ?? '') . ' – ' . ($o['table_name'] ?? '')) ?>
                    </p>
                    <p style="font-size:14px; color:#374151;"><?= e($o['customer_name'] ?? 'Khách vãng lai') ?></p>
                    <p style="font-size:13px; color:#6b7280;"><?= date('H:i', strtotime($o['order_time'])) ?></p>
                </div>

                <!-- Breakdown tiền -->
                <div style="padding:14px 16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:16px; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#374151;">Tổng tiền món</span>
                        <strong><?= format_money_local($totalAmount) ?></strong>
                    </div>
                    <?php if ($prepaidAmount > 0): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; color:#166534;">
                        <span>Đã cọc<?= $prepaidPercent > 0 ? " ({$prepaidPercent}%)" : '' ?></span>
                        <strong>- <?= format_money_local($prepaidAmount) ?></strong>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:18px; border-top:1px dashed #d1d5db; padding-top:8px;">
                        <span style="font-weight:600;">Còn lại</span>
                        <strong style="color:<?= $isFullyPrepaid ? '#166534' : '#b45309' ?>;">
                            <?= format_money_local($remainingAmount) ?>
                        </strong>
                    </div>
                </div>

                <?php if ($isFullyPrepaid): ?>
                    <div style="padding:10px 14px; background:#ecfdf5; border:1px solid #bbf7d0; color:#166534; border-radius:10px; margin-bottom:12px; font-size:13px;">
                        Khách đã cọc đủ. Chỉ cần xác nhận để chốt đơn.
                    </div>
                <?php endif; ?>

                <form method="POST" onsubmit="setTimeout(()=>{var b=this.querySelector('.btn-pay');b.disabled=true;b.textContent='Đang xử lý...';},0);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                    <input type="hidden" name="pay_order" value="1">
                    <?php if (!$isFullyPrepaid): ?>
                        <select name="payment_method" class="select" style="margin-bottom:10px;">
                            <?php foreach ($methodLabel as $val => $lbl): ?>
                                <?php if ($val === 'deposit_consumed') continue; ?>
                                <option value="<?= e($val) ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="payment_method" value="deposit_consumed">
                    <?php endif; ?>

                    <div style="display:flex; gap:10px;">
                        <button type="button" onclick="printCashierInvoice(<?= $o['order_id'] ?>)"
                                class="btn btn-secondary" style="flex:0.3; justify-content:center; font-size:13px;">
                            In
                        </button>
                        <button type="submit" class="btn btn-primary btn-pay" style="flex:1; height:48px;">
                            <?= $isFullyPrepaid ? 'Xác nhận đã thanh toán đủ' : 'Xác nhận thanh toán' ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card" style="padding:48px; text-align:center; color:var(--muted); margin-bottom:32px;">
        <p style="font-size:16px;">Không có đơn hàng nào đang chờ thanh toán</p>
    </div>
<?php endif; ?>

<!-- Lịch sử hôm nay -->
<div class="panel-header" style="margin-bottom:14px;">
    <h3 style="font-size:18px;">Lịch sử thanh toán hôm nay</h3>
    <a href="/quanlynhahang/cashier/history.php" class="btn btn-secondary" style="font-size:13px; padding:8px 14px;">Xem tất cả</a>
</div>
<div class="card panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>#</th><th>Đơn hàng</th><th>Bàn</th><th>Số tiền</th><th>Phương thức</th><th>Thời gian</th></tr>
            </thead>
            <tbody>
            <?php if ($history && $history->num_rows > 0): ?>
                <?php while ($h = $history->fetch_assoc()): ?>
                    <tr>
                        <td><?= $h['id'] ?></td>
                        <td><?= $h['order_id'] ? '#'.$h['order_id'] : '–' ?></td>
                        <td><?= e(($h['floor_name'] ?? '') . ' – ' . ($h['table_name'] ?? '–')) ?></td>
                        <td style="font-weight:700; color:var(--primary);"><?= format_money_local($h['amount']) ?></td>
                        <td><span class="badge badge-role"><?= e($methodLabel[$h['payment_method']] ?? $h['payment_method']) ?></span></td>
                        <td style="font-size:13px;"><?= date('H:i', strtotime($h['payment_time'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6"><div class="empty-state">Chưa có giao dịch nào hôm nay</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>

<script>
function disablePayBtn(btn) {
    if (!btn) return;
    setTimeout(() => {
        btn.disabled = true;
        btn.textContent = 'Đang xử lý...';
        btn.style.opacity = '0.7';
    }, 0);
    // Fallback: re-enable sau 10s phòng trường hợp lỗi mạng
    setTimeout(() => {
        btn.disabled = false;
        btn.style.opacity = '';
    }, 10000);
}

function printCashierInvoice(orderId) {
    const card = document.querySelector(`[data-order-id="${orderId}"]`);
    if (!card) { alert('Không tìm thấy đơn hàng'); return; }

    const items = JSON.parse(card.getAttribute('data-order-items') || '[]');
    const ps    = card.querySelectorAll('p');
    const table    = ps[0]?.textContent.trim() || '';
    const customer = ps[1]?.textContent.trim() || 'Khách vãng lai';
    const time     = ps[2]?.textContent.trim() || '';

    const strongs = card.querySelectorAll('strong');
    const total    = strongs[1]?.textContent || '0đ';
    const prepaid  = strongs[2]?.textContent.replace('- ','') || '';
    const remaining= strongs[3]?.textContent || '0đ';

    let itemsHTML = '';
    items.forEach(item => {
        const subtotal = item.quantity * item.unit_price;
        itemsHTML += `<div class="item">
            <span class="item-name">${item.item_name}</span>
            <span class="item-qty">x${item.quantity}</span>
            <span class="item-price">${fmt(subtotal)}</span>
        </div>`;
    });

    const w = window.open('', '_blank', 'width=300,height=700');
    w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Hóa đơn #${orderId}</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Courier New',monospace}
        body{padding:15px;font-size:12px}
        .header{text-align:center;border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:12px}
        .header h1{font-size:18px}.header h2{font-size:14px;margin-top:4px}
        .info{margin-bottom:12px}.info-row{display:flex;justify-content:space-between;margin:4px 0}
        .items{border-top:1px solid #000;border-bottom:1px solid #000;padding:8px 0;margin:10px 0}
        .item{display:flex;justify-content:space-between;margin:5px 0}
        .item-name{flex:1}.item-qty{width:35px;text-align:center}.item-price{width:75px;text-align:right}
        .summary{margin-top:12px}.summary-row{display:flex;justify-content:space-between;margin:6px 0}
        .summary-row.total{font-size:15px;font-weight:bold;border-top:2px solid #000;padding-top:8px;margin-top:8px}
        .footer{text-align:center;margin-top:16px;border-top:1px dashed #000;padding-top:12px;font-size:10px}
        @media print{body{padding:0}}
    </style></head><body>
    <div class="header"><h1>NHÀ HÀNG</h1><h2>HÓA ĐƠN THANH TOÁN</h2><div>Đơn #${orderId}</div></div>
    <div class="info">
        <div class="info-row"><strong>Bàn:</strong><span>${table}</span></div>
        <div class="info-row"><strong>Khách:</strong><span>${customer}</span></div>
        <div class="info-row"><strong>Giờ:</strong><span>${time}</span></div>
    </div>
    <div class="items">${itemsHTML}</div>
    <div class="summary">
        <div class="summary-row"><span>Tổng tiền:</span><strong>${total}</strong></div>
        ${prepaid ? `<div class="summary-row" style="color:#166534"><span>Đã cọc:</span><strong>- ${prepaid}</strong></div>` : ''}
        <div class="summary-row total"><span>THANH TOÁN:</span><strong>${remaining}</strong></div>
    </div>
    <div class="footer"><p>Cảm ơn quý khách!</p><p>${new Date().toLocaleString('vi-VN')}</p></div>
    </body></html>`);
    w.document.close();
    setTimeout(() => { w.print(); }, 400);

    function fmt(n) {
        return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(n);
    }
}
</script>
