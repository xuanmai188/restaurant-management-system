<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['bep', 'admin', 'quanly']);

$date      = $_GET['date'] ?? date('Y-m-d');
$dateStart = $date . ' 00:00:00';
$dateEnd   = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';

// ── Query: chỉ lấy status liên quan bếp, bỏ da_dat_coc/da_huy/reservation status
// ── Đồng thời fix N+1: JOIN một lần, group bằng PHP
$rawRows = $conn->query("
    SELECT
        o.order_id, o.status, o.order_time,
        t.table_name, f.floor_name,
        u.full_name AS waiter_name,
        od.order_detail_id, od.quantity, od.note,
        mi.item_name
    FROM orders o
    LEFT JOIN tables        t  ON t.table_id    = o.table_id
    LEFT JOIN floors        f  ON f.floor_id    = t.floor_id
    LEFT JOIN users         u  ON u.user_id     = o.waiter_id
    LEFT JOIN order_details od ON od.order_id   = o.order_id
    LEFT JOIN menu_items    mi ON mi.item_id     = od.item_id
    WHERE o.status IN ('moi','dang_che_bien','dang_phuc_vu','hoan_thanh','da_thanh_toan')
      AND o.order_time >= '$dateStart' AND o.order_time < '$dateEnd'
    ORDER BY o.order_time DESC, od.order_detail_id ASC
");

// Group theo order
$orderMap = [];
if ($rawRows) {
    while ($row = $rawRows->fetch_assoc()) {
        $oid = $row['order_id'];
        if (!isset($orderMap[$oid])) {
            $orderMap[$oid] = [
                'order_id'   => $oid,
                'status'     => $row['status'],
                'order_time' => $row['order_time'],
                'table_name' => $row['table_name'],
                'floor_name' => $row['floor_name'],
                'waiter_name'=> $row['waiter_name'],
                'items'      => [],
            ];
        }
        if ($row['order_detail_id']) {
            $orderMap[$oid]['items'][] = [
                'item_name' => $row['item_name'],
                'quantity'  => $row['quantity'],
                'note'      => $row['note'],
            ];
        }
    }
}
$orderList = array_values($orderMap);

// ── Kitchen status mapping — chỉ hiển thị trạng thái bếp quan tâm
$kitchenStatusMap = [
    'moi'           => ['label' => 'Chờ nấu',    'color' => '#dc2626', 'bg' => '#fee2e2'],
    'dang_che_bien' => ['label' => 'Đang nấu',   'color' => '#d97706', 'bg' => '#fef3c7'],
    'dang_phuc_vu'  => ['label' => 'Hoàn thành', 'color' => '#16a34a', 'bg' => '#dcfce7'],
    'hoan_thanh'    => ['label' => 'Hoàn thành', 'color' => '#16a34a', 'bg' => '#dcfce7'],
    'da_thanh_toan' => ['label' => 'Hoàn thành', 'color' => '#16a34a', 'bg' => '#dcfce7'],
];

$pageTitle    = 'Lịch sử bếp';
$pageSubtitle = 'Danh sách đơn hàng đã xử lý';
$activeMenu   = 'kitchen_history';
$sidebarRole  = 'bep';
include __DIR__ . '/../includes/layout.php';
?>

<!-- Bộ lọc -->
<form method="GET" style="display:flex; gap:12px; align-items:end; margin-bottom:20px;">
    <div class="form-group" style="margin:0;">
        <label>Ngày</label>
        <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <button class="btn btn-secondary" type="submit">Xem</button>
</form>

<!-- Danh sách đơn hàng -->
<div class="card panel">
    <h3 style="margin:0 0 16px;">Đơn hàng ngày <?= date('d/m/Y', strtotime($date)) ?> (<?= count($orderList) ?> đơn)</h3>

    <?php if (empty($orderList)): ?>
        <div style="padding:60px; text-align:center; color:var(--muted);">
            <p style="font-size:18px; font-weight:700;">Không có đơn hàng nào</p>
            <p style="margin-top:8px;">Chưa có đơn hàng nào trong ngày này</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bàn</th>
                        <th>Món ăn</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                        <th>Phục vụ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderList as $o):
                        $ks = $kitchenStatusMap[$o['status']] ?? null;
                    ?>
                        <tr>
                            <td><strong>#<?= $o['order_id'] ?></strong></td>
                            <td><?= e(($o['floor_name'] ?? '') . ' - ' . ($o['table_name'] ?? '')) ?></td>
                            <td>
                                <?php if (!empty($o['items'])): ?>
                                    <div style="max-height:150px; overflow-y:auto;">
                                        <?php foreach ($o['items'] as $item): ?>
                                            <div style="padding:6px 0; border-bottom:1px dashed #e5e7eb;">
                                                <strong><?= e($item['item_name']) ?></strong>
                                                <span style="color:#dc2626; font-weight:700; margin-left:8px;">×<?= $item['quantity'] ?></span>
                                                <?php if ($item['note']): ?>
                                                    <div style="margin-top:3px; padding:2px 8px; background:#fef2f2; border-left:3px solid #dc2626; border-radius:0 4px 4px 0; display:inline-block;">
                                                        <span style="font-size:11px; font-weight:700; color:#b91c1c;">! <?= strtoupper(e($item['note'])) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--muted);">Chưa có món</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ks): ?>
                                    <span style="display:inline-block; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700; background:<?= $ks['bg'] ?>; color:<?= $ks['color'] ?>;">
                                        <?= $ks['label'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700; background:#f3f4f6; color:#6b7280;">
                                        <?= e($o['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px;"><?= date('H:i', strtotime($o['order_time'])) ?></td>
                            <td style="font-size:13px;"><?= e($o['waiter_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>
