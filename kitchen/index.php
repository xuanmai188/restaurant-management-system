<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['bep', 'admin', 'quanly']);

// ── POST: state mutation ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $action   = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($order_id > 0) {
        if ($action === 'processing') {
            $conn->query("UPDATE orders SET status='dang_che_bien' WHERE order_id=$order_id AND status IN ('moi','dang_xu_ly','dang_phuc_vu')");
        } elseif ($action === 'done') {
            $conn->query("UPDATE orders SET status='dang_phuc_vu' WHERE order_id=$order_id AND status='dang_che_bien'");
            $conn->query("UPDATE order_details SET item_status='hoan_thanh' WHERE order_id=$order_id");
        }
    }

    // AJAX POST → trả JSON thay vì redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header('Location: index.php'); exit;
}

// ── AJAX GET: trả dữ liệu JSON cho polling ───────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(getKitchenData($conn));
    exit;
}

// ── Hàm lấy dữ liệu bếp ─────────────────────────────────────────────────────
function getKitchenData($conn): array {
    $todayStart = date('Y-m-d') . ' 00:00:00';
    $todayEnd   = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';

    $rawRows = $conn->query("
        SELECT
            o.order_id, o.status, o.order_time,
            t.table_name, f.floor_name,
            c.customer_name,
            u.full_name AS waiter_name,
            od.order_detail_id, od.quantity, od.note, od.item_status,
            mi.item_name
        FROM orders o
        LEFT JOIN tables        t  ON t.table_id    = o.table_id
        LEFT JOIN floors        f  ON f.floor_id    = t.floor_id
        LEFT JOIN customers     c  ON c.customer_id = o.customer_id
        LEFT JOIN users         u  ON u.user_id     = o.waiter_id
        LEFT JOIN order_details od ON od.order_id   = o.order_id
                                   AND od.item_status IN ('moi','dang_che_bien')
        LEFT JOIN menu_items    mi ON mi.item_id     = od.item_id
        WHERE o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
        ORDER BY o.order_time ASC, od.order_detail_id ASC
    ");

    $orderMap = [];
    if ($rawRows) {
        while ($row = $rawRows->fetch_assoc()) {
            $oid = $row['order_id'];
            if (!isset($orderMap[$oid])) {
                $orderMap[$oid] = [
                    'order_id'      => $oid,
                    'status'        => $row['status'],
                    'order_time'    => $row['order_time'],
                    'table_name'    => $row['table_name'],
                    'floor_name'    => $row['floor_name'],
                    'customer_name' => $row['customer_name'],
                    'waiter_name'   => $row['waiter_name'],
                    'items'         => [],
                ];
            }
            if ($row['order_detail_id']) {
                $orderMap[$oid]['items'][] = [
                    'item_name'   => $row['item_name'],
                    'quantity'    => $row['quantity'],
                    'note'        => $row['note'],
                    'item_status' => $row['item_status'],
                ];
            }
        }
    }

    $orderList = $newCount = $processingCount = $totalPendingItems = 0;
    $orders = [];
    foreach ($orderMap as $o) {
        if (empty($o['items'])) continue;
        if ($o['status'] === 'moi' || $o['status'] === 'dang_phuc_vu') $newCount++;
        elseif ($o['status'] === 'dang_che_bien') $processingCount++;
        $totalPendingItems += count($o['items']);
        $orders[] = $o;
    }

    $doneToday = $conn->query("
        SELECT COUNT(*) AS cnt FROM orders
        WHERE status IN ('hoan_thanh','da_thanh_toan')
          AND order_time >= '$todayStart' AND order_time < '$todayEnd'
    ");
    $doneCount = $doneToday ? (int)$doneToday->fetch_assoc()['cnt'] : 0;

    $overdueCount = 0;
    foreach ($orders as $o) {
        if ((time() - strtotime($o['order_time'])) / 60 > 10) $overdueCount++;
    }

    return [
        'orders'            => $orders,
        'newCount'          => $newCount,
        'processingCount'   => $processingCount,
        'totalPendingItems' => $totalPendingItems,
        'doneCount'         => $doneCount,
        'overdueCount'      => $overdueCount,
        'serverTime'        => time(),
    ];
}

// ── Dữ liệu cho lần load đầu ─────────────────────────────────────────────────
$data         = getKitchenData($conn);
$orderList    = $data['orders'];
$newCount     = $data['newCount'];
$processingCount   = $data['processingCount'];
$totalPendingItems = $data['totalPendingItems'];
$doneCount    = $data['doneCount'];
$overdueCount = $data['overdueCount'];

$pageTitle    = 'Bếp - Bill món ăn';
$pageSubtitle = 'Danh sách món cần nấu';
$activeMenu   = 'kitchen';
$sidebarRole  = 'bep';
include __DIR__ . '/../includes/layout.php';
?>

<!-- Stats -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px;">
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #dc2626; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Đơn mới</div>
        <div id="stat-newCount" style="font-size:28px; font-weight:800; color:#dc2626; line-height:1.2;"><?= $newCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Chờ nấu</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #d97706; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Đang nấu</div>
        <div id="stat-processingCount" style="font-size:28px; font-weight:800; color:#d97706; line-height:1.2;"><?= $processingCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Đang thực hiện</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #8b5cf6; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Món chờ nấu</div>
        <div id="stat-totalPendingItems" style="font-size:28px; font-weight:800; color:#8b5cf6; line-height:1.2;"><?= $totalPendingItems ?></div>
        <div style="font-size:11px; color:#9ca3af;">Tổng món</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid <?= $overdueCount > 0 ? '#dc2626' : '#9ca3af' ?>; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Đơn trễ (>10p)</div>
        <div id="stat-overdueCount" style="font-size:28px; font-weight:800; color:<?= $overdueCount > 0 ? '#dc2626' : '#9ca3af' ?>; line-height:1.2;"><?= $overdueCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Cần ưu tiên</div>
    </div>
</div>

<!-- Danh sách bill -->
<?php if (empty($orderList)): ?>
    <div id="empty-box" class="card" style="padding:60px; text-align:center; color:var(--muted);">
        <p style="font-size:18px; font-weight:700;">Không có món nào cần nấu</p>
        <p style="margin-top:8px;">Tất cả đơn đã được xử lý</p>
    </div>
<?php else: ?>
    <div id="empty-box" class="card" style="padding:60px; text-align:center; color:var(--muted); display:none;">
        <p style="font-size:18px; font-weight:700;">Không có món nào cần nấu</p>
        <p style="margin-top:8px;">Tất cả đơn đã được xử lý</p>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px;" id="orders-container">
        <?php foreach ($orderList as $o):
            $isNew     = $o['status'] === 'moi' || $o['status'] === 'dang_phuc_vu';
            $isCooking = $o['status'] === 'dang_che_bien';
            $borderColor = $isNew ? '#dc2626' : ($isCooking ? '#d97706' : '#6b7280');
            $bgColor     = $isNew ? '#fff5f5' : ($isCooking ? '#fffbeb' : '#f9fafb');

            // Tính thời gian chờ
            $waitSecs = time() - strtotime($o['order_time']);
            $waitMins = floor($waitSecs / 60);
            $waitH    = floor($waitMins / 60);
            $waitM    = $waitMins % 60;
            $waitStr  = $waitH > 0 ? "{$waitH}h{$waitM}p" : "{$waitMins}p";

            // Embed JSON cho print
            $printData = json_encode([
                'order_id' => $o['order_id'],
                'table'    => $o['floor_name'] . ' – ' . $o['table_name'],
                'time'     => date('H:i', strtotime($o['order_time'])),
                'waiter'   => $o['waiter_name'] ?? '–',
                'items'    => $o['items'],
            ], JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
            <div class="card"
                 style="border-top:6px solid <?= $borderColor ?>; background:<?= $bgColor ?>; padding:0; overflow:hidden;"
                 data-order-id="<?= $o['order_id'] ?>"
                 data-status="<?= $o['status'] ?>"
                 data-order='<?= $printData ?>'>

                <!-- Header -->
                <div style="padding:14px 18px; border-bottom:2px solid rgba(0,0,0,0.08); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="font-size:22px; font-weight:900; letter-spacing:-0.5px;">Đơn #<?= $o['order_id'] ?></strong>
                        <p style="font-size:14px; color:#374151; font-weight:600; margin-top:2px;">
                            <?= e($o['floor_name'] . ' – ' . $o['table_name']) ?>
                        </p>
                    </div>
                    <div style="text-align:right;">
                        <span style="display:inline-block; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700;
                            background:<?= $isNew ? '#fee2e2' : ($isCooking ? '#fef3c7' : '#f3f4f6') ?>;
                            color:<?= $isNew ? '#dc2626' : ($isCooking ? '#d97706' : '#6b7280') ?>;">
                            <?= $isNew ? 'Mới' : ($isCooking ? 'Đang nấu' : 'Đang xử lý') ?>
                        </span>
                        <p style="font-size:15px; font-weight:800; margin-top:6px; color:#6b7280;">
                            <?= date('H:i', strtotime($o['order_time'])) ?>
                            <span style="font-size:13px;">(<?= $waitStr ?>)</span>
                        </p>
                    </div>
                </div>

                <!-- Danh sách món -->
                <div style="padding:12px 18px;">
                    <?php foreach ($o['items'] as $item): ?>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:10px 0; border-bottom:1px dashed #e5e7eb;">
                            <div style="flex:1; padding-right:12px;">
                                <strong style="font-size:17px; font-weight:800; color:#111827;"><?= e($item['item_name']) ?></strong>
                                <?php if ($item['note']): ?>
                                    <div style="margin-top:5px; padding:4px 10px; background:#fef2f2; border-left:3px solid #dc2626; border-radius:0 6px 6px 0; display:inline-block;">
                                        <span style="font-size:12px; font-weight:800; color:#b91c1c; letter-spacing:0.3px;">
                                            ! <?= strtoupper(e($item['note'])) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span style="font-size:28px; font-weight:900; color:<?= $borderColor ?>; min-width:48px; text-align:center; line-height:1;">
                                ×<?= $item['quantity'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Phục vụ -->
                <div style="padding:8px 18px; font-size:13px; color:#6b7280; border-top:1px solid rgba(0,0,0,0.06);">
                    PV: <?= e($o['waiter_name'] ?? '–') ?>
                    <?php if ($o['customer_name']): ?>
                        &nbsp;·&nbsp; <?= e($o['customer_name']) ?>
                    <?php endif; ?>
                </div>

                <!-- Nút hành động — POST form -->
                <div style="padding:12px 18px; display:flex; gap:8px;">
                    <button onclick="printKitchenOrder(this)" class="btn btn-secondary" style="flex:0.4; justify-content:center; font-size:13px; padding:10px 0;">
                        In
                    </button>
                    <?php if ($isNew): ?>
                        <form method="POST" style="flex:1; display:flex;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="processing">
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <button type="submit" class="btn btn-secondary" style="flex:1; justify-content:center; padding:10px 0; font-size:14px; font-weight:700;">
                                Bắt đầu nấu
                            </button>
                        </form>
                        <form method="POST" style="flex:1; display:flex;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="done">
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <button type="submit" class="btn" style="flex:1; justify-content:center; background:#16a34a; color:white; padding:10px 0; font-size:14px; font-weight:700;"
                                    onclick="return confirm('Xác nhận đã nấu xong đơn #<?= $o['order_id'] ?>?')">
                                Nấu xong
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="flex:1; display:flex;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="done">
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <button type="submit" class="btn" style="flex:1; justify-content:center; background:#16a34a; color:white; padding:12px 0; font-size:15px; font-weight:800;">
                                Nấu xong
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
/* Không còn animation */
</style>

<script>
// ── AJAX Polling — thay thế full page reload ──────────────────────────────────
let _isSubmitting = false; // tránh poll khi đang submit

function renderStats(data) {
    const colors = { newCount: '#dc2626', processingCount: '#d97706', totalPendingItems: '#8b5cf6' };
    ['newCount','processingCount','totalPendingItems'].forEach((k, i) => {
        const el = document.getElementById('stat-' + k);
        if (el) el.textContent = data[k];
    });
    const overdueEl = document.getElementById('stat-overdueCount');
    if (overdueEl) {
        overdueEl.textContent = data.overdueCount;
        overdueEl.style.color = data.overdueCount > 0 ? '#dc2626' : '#9ca3af';
        overdueEl.closest('div').style.borderLeftColor = data.overdueCount > 0 ? '#dc2626' : '#9ca3af';
    }
}

function renderOrders(orders) {
    const container = document.getElementById('orders-container');
    const emptyBox  = document.getElementById('empty-box');
    if (!container) return;

    if (!orders || orders.length === 0) {
        container.innerHTML = '';
        if (emptyBox) emptyBox.style.display = '';
        return;
    }
    if (emptyBox) emptyBox.style.display = 'none';

    const now = Math.floor(Date.now() / 1000);

    // Giữ lại các card đang có, thêm mới, xóa cũ — tránh flicker
    const existingIds = new Set([...container.querySelectorAll('[data-order-id]')].map(el => el.dataset.orderId));
    const newIds = new Set(orders.map(o => String(o.order_id)));

    // Xóa card không còn trong data
    existingIds.forEach(id => {
        if (!newIds.has(id)) {
            const el = container.querySelector(`[data-order-id="${id}"]`);
            if (el) el.remove();
        }
    });

    orders.forEach(o => {
        const isNew     = o.status === 'moi' || o.status === 'dang_phuc_vu';
        const isCooking = o.status === 'dang_che_bien';
        const borderColor = isNew ? '#dc2626' : (isCooking ? '#d97706' : '#6b7280');
        const bgColor     = isNew ? '#fff5f5' : (isCooking ? '#fffbeb' : '#f9fafb');
        const statusText  = isNew ? 'Mới' : (isCooking ? 'Đang nấu' : 'Đang xử lý');
        const statusBg    = isNew ? '#fee2e2' : (isCooking ? '#fef3c7' : '#f3f4f6');
        const statusColor = isNew ? '#dc2626' : (isCooking ? '#d97706' : '#6b7280');

        const waitSecs = now - Math.floor(new Date(o.order_time).getTime() / 1000);
        const waitMins = Math.floor(waitSecs / 60);
        const waitH = Math.floor(waitMins / 60);
        const waitM = waitMins % 60;
        const waitStr = waitH > 0 ? `${waitH}h${waitM}p` : `${waitMins}p`;
        const orderTime = new Date(o.order_time).toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});

        const itemsHTML = o.items.map(item => `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px dashed #e5e7eb;">
                <div style="flex:1;padding-right:12px;">
                    <strong style="font-size:17px;font-weight:800;color:#111827;">${item.item_name}</strong>
                    ${item.note ? `<div style="margin-top:5px;padding:4px 10px;background:#fef2f2;border-left:3px solid #dc2626;border-radius:0 6px 6px 0;display:inline-block;"><span style="font-size:12px;font-weight:800;color:#b91c1c;letter-spacing:0.3px;">! ${item.note.toUpperCase()}</span></div>` : ''}
                </div>
                <span style="font-size:28px;font-weight:900;color:${borderColor};min-width:48px;text-align:center;line-height:1;">×${item.quantity}</span>
            </div>`).join('');

        const actionBtns = isNew ? `
            <form method="POST" style="flex:1;display:flex;" onsubmit="setSubmitting(this)">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="processing">
                <input type="hidden" name="order_id" value="${o.order_id}">
                <button type="submit" class="btn btn-secondary" style="flex:1;justify-content:center;padding:10px 0;font-size:14px;font-weight:700;">Bắt đầu nấu</button>
            </form>
            <form method="POST" style="flex:1;display:flex;" onsubmit="setSubmitting(this)">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="done">
                <input type="hidden" name="order_id" value="${o.order_id}">
                <button type="submit" class="btn" style="flex:1;justify-content:center;background:#16a34a;color:white;padding:10px 0;font-size:14px;font-weight:700;" onclick="return confirm('Xác nhận đã nấu xong đơn #${o.order_id}?')">Nấu xong</button>
            </form>` : `
            <form method="POST" style="flex:1;display:flex;" onsubmit="setSubmitting(this)">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="done">
                <input type="hidden" name="order_id" value="${o.order_id}">
                <button type="submit" class="btn" style="flex:1;justify-content:center;background:#16a34a;color:white;padding:12px 0;font-size:15px;font-weight:800;">Nấu xong</button>
            </form>`;

        const printData = JSON.stringify({
            order_id: o.order_id,
            table: `${o.floor_name} – ${o.table_name}`,
            time: orderTime,
            waiter: o.waiter_name || '–',
            items: o.items
        }).replace(/'/g, '&#39;');

        const html = `
            <div class="card" style="border-top:6px solid ${borderColor};background:${bgColor};padding:0;overflow:hidden;" data-order-id="${o.order_id}" data-order='${printData}'>
                <div style="padding:14px 18px;border-bottom:2px solid rgba(0,0,0,0.08);display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <strong style="font-size:22px;font-weight:900;letter-spacing:-0.5px;">Đơn #${o.order_id}</strong>
                        <p style="font-size:14px;color:#374151;font-weight:600;margin-top:2px;">${o.floor_name} – ${o.table_name}</p>
                    </div>
                    <div style="text-align:right;">
                        <span style="display:inline-block;padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700;background:${statusBg};color:${statusColor};">${statusText}</span>
                        <p style="font-size:15px;font-weight:800;margin-top:6px;color:#6b7280;">${orderTime} <span style="font-size:13px;">(${waitStr})</span></p>
                    </div>
                </div>
                <div style="padding:12px 18px;">${itemsHTML}</div>
                <div style="padding:8px 18px;font-size:13px;color:#6b7280;border-top:1px solid rgba(0,0,0,0.06);">
                    PV: ${o.waiter_name || '–'}${o.customer_name ? ' &nbsp;·&nbsp; ' + o.customer_name : ''}
                </div>
                <div style="padding:12px 18px;display:flex;gap:8px;">
                    <button onclick="printKitchenOrder(this)" class="btn btn-secondary" style="flex:0.4;justify-content:center;font-size:13px;padding:10px 0;">In</button>
                    ${actionBtns}
                </div>
            </div>`;

        const existing = container.querySelector(`[data-order-id="${o.order_id}"]`);
        if (existing) {
            // Chỉ update nếu status thay đổi để tránh flicker
            if (existing.dataset.status !== o.status) {
                existing.outerHTML = html;
            }
        } else {
            container.insertAdjacentHTML('beforeend', html);
        }

        // Lưu status vào dataset để so sánh lần sau
        const card = container.querySelector(`[data-order-id="${o.order_id}"]`);
        if (card) card.dataset.status = o.status;
    });
}

function setSubmitting(form) {
    _isSubmitting = true;
    const btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = '...'; }
    // Reset sau 5s phòng trường hợp lỗi
    setTimeout(() => { _isSubmitting = false; }, 5000);
}

async function pollKitchen() {
    if (_isSubmitting) return; // đang submit, bỏ qua poll
    try {
        const res = await fetch('index.php?ajax=1');
        if (!res.ok) return;
        const data = await res.json();
        renderStats(data);
        renderOrders(data.orders);
    } catch (e) {
        // Lỗi mạng — bỏ qua, poll lần sau
    }
}

// Poll mỗi 15 giây — không reload trang
setInterval(pollKitchen, 15000);

function printKitchenOrder(btn) {
    const card = btn.closest('.card');
    const data = JSON.parse(card.dataset.order);

    let itemsHTML = '';
    data.items.forEach(item => {
        itemsHTML += `
            <div class="item">
                <div class="item-header">
                    <span>${item.item_name}</span>
                    <span>×${item.quantity}</span>
                </div>
                ${item.note ? `<div class="item-note">! ${item.note.toUpperCase()}</div>` : ''}
            </div>
        `;
    });

    const printWindow = window.open('', '_blank', 'width=300,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Phiếu bếp #${data.order_id}</title>
            <style>
                * { margin:0; padding:0; box-sizing:border-box; font-family:'Courier New',monospace; }
                body { padding:10px; font-size:12px; }
                .header { text-align:center; border-bottom:2px dashed #000; padding-bottom:10px; margin-bottom:10px; }
                .header h2 { font-size:18px; margin-bottom:5px; }
                .info { margin-bottom:10px; }
                .info-row { display:flex; justify-content:space-between; margin:3px 0; }
                .items { border-top:1px dashed #000; border-bottom:1px dashed #000; padding:10px 0; margin:10px 0; }
                .item { margin:8px 0; }
                .item-header { display:flex; justify-content:space-between; font-weight:bold; font-size:14px; }
                .item-note { font-weight:700; color:#000; margin-top:2px; }
                .footer { text-align:center; margin-top:15px; font-size:10px; }
                @media print { body { padding:0; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>PHIẾU BẾP</h2>
                <div>Đơn #${data.order_id}</div>
            </div>
            <div class="info">
                <div class="info-row"><strong>Bàn:</strong> <span>${data.table}</span></div>
                <div class="info-row"><strong>Giờ:</strong> <span>${data.time}</span></div>
                <div class="info-row"><strong>Phục vụ:</strong> <span>${data.waiter}</span></div>
            </div>
            <div class="items">${itemsHTML}</div>
            <div class="footer">
                <p>--- HẾT ---</p>
                <p>${new Date().toLocaleString('vi-VN')}</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => { printWindow.print(); printWindow.close(); }, 300);
}
</script>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>
