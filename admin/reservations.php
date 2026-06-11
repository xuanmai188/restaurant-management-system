<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

// Cập nhật trạng thái — đồng bộ order/bàn giống quản lý
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $st = $_GET['status'];
    $result = update_reservation_status($id, $st);
    if (!$result['success']) {
        http_response_code(400);
        die(e($result['message'] ?? 'Không thể cập nhật trạng thái'));
    }

    $key         = $_GET['key']         ?? '';
    $date_filter = $_GET['date_filter'] ?? '';
    $sf          = $_GET['status_filter'] ?? '';

    // Nếu đang nhúng trong admin.php thì redirect về admin.php, không phải reservations.php
    $isEmbedded = isset($_GET['page']) || defined('ADMIN_EMBEDDED');
    if ($isEmbedded || isset($_GET['page'])) {
        $base = 'admin.php?page=reservations';
        if ($key)         $base .= '&key=' . urlencode($key);
        if ($date_filter) $base .= '&date_filter=' . urlencode($date_filter);
        if ($sf)          $base .= '&status_filter=' . urlencode($sf);
        header('Location: ' . $base);
    } else {
        header('Location: reservations.php?key=' . $key
            . ($date_filter ? '&date_filter=' . urlencode($date_filter) : '')
            . ($sf          ? '&status_filter=' . urlencode($sf)        : '')
        );
    }
    exit;
}

// Đồng bộ reservation bị hủy theo order
$conn->query("
    UPDATE reservations r
    JOIN orders o ON o.reservation_id = r.reservation_id
    SET r.status = 'da_huy'
    WHERE o.status = 'da_huy'
      AND r.status NOT IN ('da_huy','khong_den')
");

$filter_status = trim($_GET['status_filter'] ?? '');
$filter_date   = trim($_GET['date_filter']   ?? '');

$where = '1';
if ($filter_status) $where .= " AND r.status='" . $conn->real_escape_string($filter_status) . "'";
if ($filter_date)   $where .= " AND DATE(r.reservation_time)='" . $conn->real_escape_string($filter_date) . "'";

$reservations = $conn->query("
    SELECT r.*, t.table_name, f.floor_name,
           COALESCE(u.full_name, c.customer_name, 'Khách vãng lai') AS customer_name,
           COALESCE(u.phone, c.phone, '-') AS phone,
           rp.amount AS deposit_amount,
           rp.payment_type,
           rp.payment_percent,
           rp.payment_status AS deposit_status
    FROM   reservations r
    LEFT JOIN tables    t  ON t.table_id    = r.table_id
    LEFT JOIN floors    f  ON f.floor_id    = t.floor_id
    LEFT JOIN users     u  ON u.user_id     = r.user_id
    LEFT JOIN orders    o  ON o.reservation_id = r.reservation_id
    LEFT JOIN customers c  ON c.customer_id = o.customer_id
    LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
    WHERE  $where
    GROUP BY r.reservation_id
    ORDER  BY r.reservation_time DESC
    LIMIT  200
");

$statusLabel = [
    'cho_xac_nhan' => 'Chờ xác nhận',
    'da_xac_nhan'  => 'Đã xác nhận',
    'da_checkin'   => 'Đã check-in',
    'khong_den'    => 'Không đến',
    'da_huy'       => 'Đã hủy',
    'hoan_thanh'   => 'Hoàn thành',
];
$statusColors = [
    'cho_xac_nhan' => 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d;',
    'da_xac_nhan'  => 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
    'da_checkin'   => 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
    'khong_den'    => 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
    'da_huy'       => 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
    'hoan_thanh'   => 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
];

$keyParam = isset($_GET['key']) ? '&key=' . urlencode($_GET['key']) : '';

$pageTitle = 'Quản lý đặt bàn';
$activeMenu = 'reservations'; $sidebarRole = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:2px solid #e5e7eb;">
    <h2 style="margin:0; font-size:28px; font-weight:700; color:#111827;">Quản lý đặt bàn</h2>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:24px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <?php if (isset($_GET['page'])): ?>
        <input type="hidden" name="page" value="<?= e($_GET['page']) ?>">
    <?php endif; ?>
    <?php if (isset($_GET['key'])): ?>
        <input type="hidden" name="key" value="<?= e($_GET['key']) ?>">
    <?php endif; ?>
    <div>
        <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:4px;">Ngày</label>
        <input class="input" type="date" name="date_filter" value="<?= e($filter_date) ?>" style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:10px; font-size:14px;">
    </div>
    <div>
        <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:4px;">Trạng thái</label>
        <select name="status_filter" style="padding:8px 16px; border:1px solid #e5e7eb; border-radius:10px; font-size:14px; font-weight:600; color:#374151; background:white; cursor:pointer;">
            <option value="">Tất cả trạng thái</option>
            <?php foreach ($statusLabel as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filter_status===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="align-self:flex-end;">
        <button type="submit" style="padding:10px 20px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; box-shadow:0 4px 12px rgba(239,68,68,0.3);">Lọc</button>
    </div>
</form>

<!-- Table -->
<div style="background:white; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,0.04); border:1px solid #e5e7eb; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse;">
        <thead style="background:linear-gradient(135deg,#f9fafb,#f3f4f6);">
            <tr>
                <?php foreach (['#','Khách hàng','SĐT','Bàn','Số người','Thời gian đặt','Loại','Tiền cọc','Trạng thái','Thao tác'] as $h): ?>
                <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid #e5e7eb;"><?= $h ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php if ($reservations && $reservations->num_rows > 0): ?>
            <?php while ($r = $reservations->fetch_assoc()): ?>
            <tr style="border-bottom:1px solid #f3f4f6; transition:background 0.15s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
                <td style="padding:14px 16px; font-size:14px; font-weight:700; color:#ef4444;">#<?= $r['reservation_id'] ?></td>
                <td style="padding:14px 16px; font-size:14px; font-weight:500;"><?= e($r['customer_name']) ?></td>
                <td style="padding:14px 16px; font-size:14px; color:#6b7280;"><?= e($r['phone']) ?></td>
                <td style="padding:14px 16px; font-size:14px;">
                    <span style="font-weight:600;"><?= e($r['table_name']) ?></span>
                    <br><small style="color:#9ca3af;"><?= e($r['floor_name']) ?></small>
                </td>
                <td style="padding:14px 16px; font-size:14px; font-weight:500;"><?= $r['number_of_people'] ?> người</td>
                <td style="padding:14px 16px; font-size:14px;">
                    <span style="font-weight:500;"><?= date('d/m/Y', strtotime($r['reservation_time'])) ?></span>
                    <br><small style="color:#9ca3af;"><?= date('H:i', strtotime($r['reservation_time'])) ?></small>
                </td>
                <td style="padding:14px 16px;">
                    <?php if ($r['deposit_amount'] > 0): ?>
                        <span style="display:inline-block; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;">Có cọc</span>
                    <?php else: ?>
                        <span style="display:inline-block; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; background:#f3f4f6; color:#6b7280; border:1px solid #d1d5db;">Không cọc</span>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px; font-size:14px;">
                    <?php if ($r['deposit_amount'] > 0): ?>
                        <strong style="color:#065f46;"><?= format_currency($r['deposit_amount']) ?></strong>
                        <br><small style="color:#9ca3af;"><?= $r['payment_type'] === 'percent' ? $r['payment_percent'].'%' : 'Cố định' ?></small>
                    <?php else: ?>
                        <span style="color:#d1d5db;">—</span>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px;">
                    <span style="display:inline-block; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; <?= $statusColors[$r['status']] ?? 'background:#e5e7eb; color:#374151;' ?>">
                        <?= $statusLabel[$r['status']] ?? $r['status'] ?>
                    </span>
                </td>
                <td style="padding:14px 16px;">
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button onclick="viewDetail(<?= $r['reservation_id'] ?>, <?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)"
                            style="padding:7px 12px; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#3b82f6,#2563eb); color:white;">
                            Chi tiết
                        </button>
                        <?php
                        $dateParam = $filter_date ? '&date_filter=' . urlencode($filter_date) : '';
                        $sfParam   = $filter_status ? '&status_filter=' . urlencode($filter_status) : '';
                        // Nếu đang nhúng trong admin.php thì dùng base admin.php?page=reservations
                        $pageParam = isset($_GET['page']) ? '&page=' . urlencode($_GET['page']) : '';
                        $baseUrl   = isset($_GET['page']) ? 'admin.php?page=reservations' : 'reservations.php';
                        $baseUrl  .= $keyParam . $dateParam . $sfParam;
                        ?>
                        <?php if ($r['status'] === 'cho_xac_nhan'): ?>
                            <a href="<?= $baseUrl ?>&id=<?= $r['reservation_id'] ?>&status=da_xac_nhan"
                               style="display:inline-block; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; background:linear-gradient(135deg,#10b981,#059669); color:white;">
                                Xác nhận
                            </a>
                            <a href="<?= $baseUrl ?>&id=<?= $r['reservation_id'] ?>&status=da_huy"
                               style="display:inline-block; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; background:linear-gradient(135deg,#ef4444,#dc2626); color:white;"
                               onclick="return confirm('Hủy đặt bàn này?')">
                                Hủy
                            </a>
                        <?php elseif ($r['status'] === 'da_xac_nhan'): ?>
                            <a href="<?= $baseUrl ?>&id=<?= $r['reservation_id'] ?>&status=da_checkin"
                               style="display:inline-block; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; background:linear-gradient(135deg,#3b82f6,#2563eb); color:white;">
                                Check-in
                            </a>
                            <a href="<?= $baseUrl ?>&id=<?= $r['reservation_id'] ?>&status=khong_den"
                               style="display:inline-block; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; background:linear-gradient(135deg,#f59e0b,#d97706); color:white;"
                               onclick="return confirm('Đánh dấu khách không đến?')">
                                Không đến
                            </a>
                        <?php elseif ($r['status'] === 'da_checkin'): ?>
                            <a href="<?= $baseUrl ?>&id=<?= $r['reservation_id'] ?>&status=hoan_thanh"
                               style="display:inline-block; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; background:linear-gradient(135deg,#6b7280,#4b5563); color:white;">
                                Hoàn thành
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="10" style="padding:80px 20px; text-align:center; color:#9ca3af; font-size:15px;">Không có lịch đặt bàn nào.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Detail Modal -->
<div id="detail-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:white; border-radius:16px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:24px; border-bottom:2px solid #e5e7eb; background:linear-gradient(135deg,#f9fafb,#f3f4f6);">
            <h3 style="margin:0; font-size:22px; font-weight:700; color:#111827;">Chi tiết đặt bàn</h3>
            <button onclick="closeDetail()" style="background:none; border:none; font-size:32px; cursor:pointer; color:#9ca3af; line-height:1;">&times;</button>
        </div>
        <div id="detail-content" style="padding:24px;"></div>
        <div style="display:flex; justify-content:flex-end; padding:16px 24px; border-top:2px solid #e5e7eb; background:#fafafa;">
            <button onclick="closeDetail()" style="padding:10px 24px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#3b82f6,#2563eb); color:white;">Đóng</button>
        </div>
    </div>
</div>

<script>
const statusLabels = {cho_xac_nhan:'Chờ xác nhận', da_xac_nhan:'Đã xác nhận', da_checkin:'Đã check-in', khong_den:'Không đến', da_huy:'Đã hủy', hoan_thanh:'Hoàn thành'};
const statusStyles = {
    cho_xac_nhan: 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d;',
    da_xac_nhan:  'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
    da_checkin:   'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
    khong_den:    'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
    da_huy:       'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
    hoan_thanh:   'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
};

function viewDetail(id, data) {
    const style = statusStyles[data.status] || 'background:#e5e7eb; color:#374151;';
    const label = statusLabels[data.status] || data.status;
    const isOnline = data.deposit_amount > 0;
    const typeBadgeStyle = isOnline
        ? 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;'
        : 'background:#f3f4f6; color:#374151; border:1px solid #d1d5db;';

    let depositSection = '';
    if (isOnline) {
        const amount = new Intl.NumberFormat('vi-VN').format(data.deposit_amount) + ' đ';
        const percent = data.payment_percent ? data.payment_percent + '%' : 'Cố định';
        depositSection = row('Tiền cọc', amount, '#059669') + row('Phần trăm cọc', percent);
    }

    document.getElementById('detail-content').innerHTML = `
        <div>
            ${row('Mã đặt bàn', '#' + data.reservation_id, '#ef4444', true)}
            ${row('Khách hàng', data.customer_name || '-')}
            ${row('Số điện thoại', data.phone || '-')}
            ${row('Bàn', (data.floor_name || '') + ' – ' + (data.table_name || ''))}
            ${row('Số người', data.number_of_people + ' người')}
            ${row('Thời gian đặt', data.reservation_time)}
            ${row('Ghi chú', data.note || '-')}
            ${row('Loại', '<span style="display:inline-block; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; ' + typeBadgeStyle + '">' + (isOnline ? 'Có cọc' : 'Không cọc') + '</span>', null, false, true)}
            ${depositSection}
            ${row('Trạng thái', '<span style="display:inline-block; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; ' + style + '">' + label + '</span>', null, false, true)}
        </div>
    `;
    document.getElementById('detail-modal').style.display = 'flex';
}

function row(label, value, color = null, bold = false, html = false) {
    const valStyle = color ? `color:${color}; font-weight:700;` : (bold ? 'font-weight:700;' : '');
    const val = html ? value : `<span style="${valStyle}">${value}</span>`;
    return `<div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f3f4f6;">
        <span style="font-weight:600; color:#6b7280; font-size:14px;">${label}</span>
        <span style="font-size:14px; color:#1f2937; text-align:right;">${val}</span>
    </div>`;
}

function closeDetail() {
    document.getElementById('detail-modal').style.display = 'none';
}
</script>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>


