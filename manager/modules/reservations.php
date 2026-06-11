<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);

// Cập nhật trạng thái (dùng hàm chung với admin)
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $st = $_GET['status'];
    $result = update_reservation_status($id, $st);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

$filter_status = trim($_GET['status_filter'] ?? '');
$raw_date = trim($_GET['date_filter'] ?? date('Y-m-d'));

// Convert nếu nhận format d/m/Y từ browser cũ
if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw_date)) {
    $d = DateTime::createFromFormat('d/m/Y', $raw_date);
    $filter_date = $d ? $d->format('Y-m-d') : date('Y-m-d');
} else {
    $filter_date = $raw_date;
}

// Đồng bộ: reservation liên kết với order bị hủy → cập nhật thành da_huy
$conn->query("
    UPDATE reservations r
    JOIN orders o ON o.reservation_id = r.reservation_id
    SET r.status = 'da_huy'
    WHERE o.status = 'da_huy'
      AND r.status NOT IN ('da_huy', 'khong_den')
");
$where = '1';
if ($filter_status) $where .= " AND r.status='" . $conn->real_escape_string($filter_status) . "'";
if ($filter_date) $where .= " AND DATE(r.reservation_time)='" . $conn->real_escape_string($filter_date) . "'";

$reservations = $conn->query("
    SELECT r.*, t.table_name, f.floor_name,
           COALESCE(u.full_name, c.customer_name, 'Khách vãng lai') as customer_name,
           COALESCE(u.phone, c.phone, '-') as phone,
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
    LIMIT  100
");

$statusLabel = ['cho_xac_nhan'=>'Chờ xác nhận','da_xac_nhan'=>'Đã xác nhận','da_checkin'=>'Đã check-in','khong_den'=>'Không đến','da_huy'=>'Đã hủy','hoan_thanh'=>'Hoàn thành'];
$statusColors = [
    'cho_xac_nhan' => 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d;',
    'da_xac_nhan'  => 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
    'da_checkin'   => 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
    'khong_den'    => 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
    'da_huy'       => 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
    'hoan_thanh'   => 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
];
?>

<style>
.res-module { padding: 24px; }

.res-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e5e7eb;
}
.res-header h2 {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px;
}
.res-header p {
    margin: 0;
    font-size: 14px;
    color: #6b7280;
}

/* Filter card */
.res-filter-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.res-filter-card label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}
.res-filter-card input[type=date],
.res-filter-card select {
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    color: #1f2937;
    background: #f9fafb;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}
.res-filter-card input[type=date]:focus,
.res-filter-card select:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
    background: white;
}
.res-btn-filter {
    padding: 10px 24px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(239,68,68,0.3);
    transition: all 0.2s ease;
}
.res-btn-filter:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239,68,68,0.4);
}

/* Table */
.res-table-wrap {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    overflow: hidden;
}
.res-table {
    width: 100%;
    border-collapse: collapse;
}
.res-table thead {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}
.res-table th {
    padding: 14px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}
.res-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.15s ease;
}
.res-table tbody tr:hover { background: #fafafa; }
.res-table tbody tr:last-child { border-bottom: none; }
.res-table td {
    padding: 14px 16px;
    font-size: 14px;
    color: #1f2937;
    vertical-align: middle;
}

/* ID link */
.res-id-link {
    font-weight: 700;
    color: #ef4444;
    text-decoration: none;
    font-size: 14px;
}
.res-id-link:hover { color: #dc2626; }

/* Badge */
.res-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.res-badge-cho_xac_nhan  { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
.res-badge-da_xac_nhan   { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.res-badge-da_checkin    { background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; }
.res-badge-khong_den     { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.res-badge-da_huy        { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.res-badge-hoan_thanh    { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.res-badge-co_coc        { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.res-badge-khong_coc     { background:#f3f4f6; color:#6b7280; border:1px solid #d1d5db; }

/* Action buttons */
.res-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.res-btn-action {
    padding: 7px 14px;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
}
.res-btn-accept  { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color:#065f46; }
.res-btn-accept:hover  { background: linear-gradient(135deg,#a7f3d0,#6ee7b7); transform:translateY(-1px); }
.res-btn-cancel  { background: linear-gradient(135deg,#fee2e2,#fecaca); color:#dc2626; }
.res-btn-cancel:hover  { background: linear-gradient(135deg,#fecaca,#fca5a5); transform:translateY(-1px); }
.res-btn-checkin { background: linear-gradient(135deg,#dbeafe,#bfdbfe); color:#1e40af; }
.res-btn-checkin:hover { background: linear-gradient(135deg,#bfdbfe,#93c5fd); transform:translateY(-1px); }
.res-btn-done    { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color:#065f46; }
.res-btn-done:hover    { background: linear-gradient(135deg,#a7f3d0,#6ee7b7); transform:translateY(-1px); }

/* Empty state */
.res-empty {
    text-align: center;
    padding: 80px 20px;
    color: #9ca3af;
    font-size: 15px;
}
</style>

<div class="res-module">
    <div class="res-header">
        <div>
            <h2>Quản lý đặt bàn</h2>
            <p>Quản lý đặt bàn của khách hàng</p>
        </div>
    </div>

    <div class="res-filter-card">
        <form id="reservation-filter-form" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; width:100%;">
            <div>
                <label>Ngày</label>
                <input type="date" id="res-date-filter" value="<?= e($filter_date) ?>">
            </div>
            <div>
                <label>Trạng thái</label>
                <select id="res-status-filter">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach($statusLabel as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $filter_status===$k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="res-btn-filter">Lọc</button>
        </form>
    </div>

    <script>
    (function() {
        var form = document.getElementById('reservation-filter-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var date   = document.getElementById('res-date-filter').value;
                var status = document.getElementById('res-status-filter').value;
                var url    = '/quanlynhahang/manager/modules/reservations.php?date_filter=' + encodeURIComponent(date) + '&status_filter=' + encodeURIComponent(status);
                var contentArea = document.getElementById('content-area');
                if (contentArea) {
                    contentArea.innerHTML = '<div class="loading">Đang tải</div>';
                    fetch(url)
                        .then(function(r) { return r.text(); })
                        .then(function(html) {
                            contentArea.innerHTML = html;
                            contentArea.querySelectorAll('script').forEach(function(old) {
                                var s = document.createElement('script');
                                s.textContent = old.textContent;
                                old.parentNode.replaceChild(s, old);
                            });
                        });
                } else {
                    window.location.href = url;
                }
            });
        }
    })();
    </script>

    <div class="res-table-wrap">
        <table class="res-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Khách hàng</th>
                    <th>SĐT</th>
                    <th>Bàn</th>
                    <th>Số người</th>
                    <th>Thời gian đặt</th>
                    <th>Loại</th>
                    <th>Tiền cọc</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reservations && $reservations->num_rows > 0): ?>
                    <?php while ($r = $reservations->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="?module=reservations&id=<?= $r['reservation_id'] ?>" class="res-id-link">
                                    #<?= $r['reservation_id'] ?>
                                </a>
                            </td>
                            <td style="font-weight:500;"><?= e($r['customer_name']) ?></td>
                            <td style="color:#6b7280;"><?= e($r['phone']) ?></td>
                            <td>
                                <span style="font-weight:600;"><?= e($r['table_name'] ?? 'N/A') ?></span>
                                <?php if ($r['floor_name']): ?>
                                    <br><small style="color:#9ca3af;"><?= e($r['floor_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:500;"><?= $r['number_of_people'] ?> người</td>
                            <td>
                                <span style="font-weight:500;"><?= date('d/m/Y', strtotime($r['reservation_time'])) ?></span>
                                <br><small style="color:#9ca3af;"><?= date('H:i', strtotime($r['reservation_time'])) ?></small>
                            </td>
                            <td>
                                <?php if ($r['deposit_amount'] > 0): ?>
                                    <span class="res-badge res-badge-co_coc">Có cọc</span>
                                <?php else: ?>
                                    <span class="res-badge res-badge-khong_coc">Không cọc</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['deposit_amount'] > 0): ?>
                                    <strong style="color:#065f46;"><?= number_format($r['deposit_amount']) ?>đ</strong>
                                    <br><small style="color:#9ca3af;">
                                        <?= $r['payment_type'] === 'percent' ? $r['payment_percent'].'%' : 'Cố định' ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color:#d1d5db;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="res-badge res-badge-<?= $r['status'] ?>">
                                    <?= $statusLabel[$r['status']] ?? $r['status'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="res-actions">
                                    <?php if ($r['status'] === 'cho_xac_nhan'): ?>
                                        <button onclick="updateReservationStatus(<?= $r['reservation_id'] ?>, 'da_xac_nhan')" class="res-btn-action res-btn-accept">Chấp nhận</button>
                                        <button onclick="updateReservationStatus(<?= $r['reservation_id'] ?>, 'da_huy')" class="res-btn-action res-btn-cancel">Hủy bỏ</button>
                                    <?php elseif ($r['status'] === 'da_xac_nhan'): ?>
                                        <button onclick="updateReservationStatus(<?= $r['reservation_id'] ?>, 'da_checkin')" class="res-btn-action res-btn-checkin">Check-in</button>
                                        <button onclick="updateReservationStatus(<?= $r['reservation_id'] ?>, 'khong_den')" class="res-btn-action res-btn-cancel">Không đến</button>
                                    <?php elseif ($r['status'] === 'da_checkin'): ?>
                                        <button onclick="updateReservationStatus(<?= $r['reservation_id'] ?>, 'hoan_thanh')" class="res-btn-action res-btn-done">Hoàn thành</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="res-empty">Không có đặt bàn nào</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateReservationStatus(id, status) {
    if (!confirm('Xác nhận thay đổi trạng thái?')) return;
    
    var url = '/quanlynhahang/manager/modules/reservations.php?status=' + encodeURIComponent(status) + '&id=' + id;
    
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                alert(data.message || 'Không thể cập nhật');
                return;
            }
            // Reload module
            var contentArea = document.getElementById('content-area');
            if (contentArea) {
                fetch('/quanlynhahang/manager/modules/reservations.php')
                    .then(function(r) { return r.text(); })
                    .then(function(html) {
                        contentArea.innerHTML = html;
                        contentArea.querySelectorAll('script').forEach(function(old) {
                            var s = document.createElement('script');
                            s.textContent = old.textContent;
                            old.parentNode.replaceChild(s, old);
                        });
                    });
            }
        })
        .catch(function(err) {
            alert('Lỗi cập nhật: ' + err.message);
        });
}
</script>