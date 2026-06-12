<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin']);

// System stats
$stats = [];
$stats['tables']       = $conn->query("SELECT COUNT(*) as c FROM tables")->fetch_assoc()['c'];
$stats['orders']       = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$stats['reservations'] = $conn->query("SELECT COUNT(*) as c FROM reservations")->fetch_assoc()['c'];
$stats['users']        = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='hoat_dong'")->fetch_assoc()['c'];

// Logs
$logs_result = $conn->query("
    SELECT l.log_id, l.action_type, l.deleted_count, l.performed_by, l.performed_at, l.details,
           u.full_name as performed_by_name
    FROM data_operation_logs l
    LEFT JOIN users u ON u.user_id = l.performed_by
    ORDER BY l.log_id DESC
    LIMIT 100
");
$logs = $logs_result ? $logs_result->fetch_all(MYSQLI_ASSOC) : [];
foreach ($logs as &$log) {
    $log['backup_file'] = (
        $log['action_type'] === 'backup' &&
        preg_match('/File: (backup_qlnhahang_[\d\-_]+\.sql)/', $log['details'], $m)
    ) ? $m[1] : null;
}
$total_logs = count($logs);

// DB size
$db_size = $conn->query("
    SELECT ROUND(SUM(data_length + index_length)/1024/1024, 2) AS size_mb
    FROM information_schema.TABLES WHERE table_schema='qlnhahang'
")->fetch_assoc()['size_mb'] ?? 0;

$pageTitle    = 'Bảo trì hệ thống';
$pageSubtitle = 'Hỗ trợ các chức năng bảo trì và đảm bảo hoạt động ổn định';
$activeMenu   = 'maintenance';
$sidebarRole  = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<div class="module-container" style="padding:24px;">

    <!-- Stats -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:32px;">
        <div style="background:white; border-radius:16px; padding:24px; display:flex; align-items:center; gap:16px; box-shadow:0 4px 12px rgba(0,0,0,0.08); border:1px solid #e5e7eb;">
            <div><div style="font-size:13px;color:#6b7280;font-weight:600;text-transform:uppercase;">Kích thước CSDL</div><div style="font-size:28px;font-weight:800;"><?= $db_size ?> MB</div></div>
        </div>
        <div style="background:white; border-radius:16px; padding:24px; display:flex; align-items:center; gap:16px; box-shadow:0 4px 12px rgba(0,0,0,0.08); border:1px solid #e5e7eb;">
            <div><div style="font-size:13px;color:#6b7280;font-weight:600;text-transform:uppercase;">Tổng đơn hàng</div><div style="font-size:28px;font-weight:800;"><?= number_format($stats['orders']) ?></div></div>
        </div>
        <div style="background:white; border-radius:16px; padding:24px; display:flex; align-items:center; gap:16px; box-shadow:0 4px 12px rgba(0,0,0,0.08); border:1px solid #e5e7eb;">
            <div><div style="font-size:13px;color:#6b7280;font-weight:600;text-transform:uppercase;">Tổng đặt bàn</div><div style="font-size:28px;font-weight:800;"><?= number_format($stats['reservations']) ?></div></div>
        </div>
        <div style="background:white; border-radius:16px; padding:24px; display:flex; align-items:center; gap:16px; box-shadow:0 4px 12px rgba(0,0,0,0.08); border:1px solid #e5e7eb;">
            <div><div style="font-size:13px;color:#6b7280;font-weight:600;text-transform:uppercase;">Người dùng</div><div style="font-size:28px;font-weight:800;"><?= number_format($stats['users']) ?></div></div>
        </div>
    </div>

    <!-- Actions -->
    <div style="background:white; border-radius:16px; padding:24px; margin-bottom:32px; box-shadow:0 4px 12px rgba(0,0,0,0.08); border:1px solid #e5e7eb;">
        <h3 style="margin:0 0 20px; font-size:20px; font-weight:700;">Thao tác bảo trì</h3>
        
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">
            <button onclick="performBackup(event)" style="padding:20px; border:none; border-radius:12px; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; font-weight:700; font-size:15px; color:white; background:linear-gradient(135deg,#10b981,#059669); box-shadow:0 4px 12px rgba(16,185,129,0.3);">
                <span>Sao lưu dữ liệu</span>
                <small style="font-size:12px;opacity:0.9;">Tạo bản backup CSDL</small>
            </button>
            <button onclick="checkSystemHealth(event)" style="padding:20px; border:none; border-radius:12px; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; font-weight:700; font-size:15px; color:white; background:linear-gradient(135deg,#3b82f6,#2563eb); box-shadow:0 4px 12px rgba(59,130,246,0.3);">
                <span>Kiểm tra trạng thái</span>
                <small style="font-size:12px;opacity:0.9;">Xem tình trạng hệ thống</small>
            </button>
            <button onclick="addTestLog(event)" style="padding:20px; border:none; border-radius:12px; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; font-weight:700; font-size:15px; color:white; background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 12px rgba(245,158,11,0.3);">
                <span>Ghi nhận log lỗi</span>
                <small style="font-size:12px;opacity:0.9;">Lưu log thủ công</small>
            </button>
            <button onclick="cleanOldData(event)" style="padding:20px; border:none; border-radius:12px; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; font-weight:700; font-size:15px; color:white; background:linear-gradient(135deg,#ef4444,#dc2626); box-shadow:0 4px 12px rgba(239,68,68,0.3);">
                <span>Dọn dẹp dữ liệu cũ</span>
                <small style="font-size:12px;opacity:0.9;">Xóa dữ liệu cũ hơn 6 tháng</small>
            </button>
        </div>
    </div>

    <!-- Logs -->
    <div style="background:white; border-radius:16px; padding:24px; box-shadow:0 4px 12px rgba(0,0,0,0.08); border:1px solid #e5e7eb;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; font-size:20px; font-weight:700;">Lịch sử thao tác (<?= $total_logs ?> log)</h3>
            <div style="display:flex; gap:12px; align-items:center;">
                <select id="logFilter" onchange="filterLogs()" style="padding:8px 16px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; font-weight:600; color:#374151;">
                    <option value="all">Tất cả</option>
                    <option value="backup">Sao lưu dữ liệu</option>
                    <option value="health_check">Kiểm tra trạng thái</option>
                    <option value="manual_log">Ghi nhận log lỗi</option>
                    <option value="delete_orders">Dọn dẹp dữ liệu cũ</option>
                </select>
                <button onclick="deleteAllLogs()" style="padding:6px 12px; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; background:#ef4444; color:white; display:inline-flex; align-items:center; gap:6px;">
                    Xóa tất cả
                </button>
                <button onclick="location.reload()" style="padding:6px 12px; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; background:#f3f4f6; color:#374151;">
                    Làm mới
                </button>
            </div>
        </div>

        <?php if ($total_logs > 0): ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead style="background:#f9fafb;">
                    <tr>
                        <th style="padding:12px; text-align:left; font-weight:700; font-size:13px; color:#374151; border-bottom:2px solid #e5e7eb; width:60px;">ID</th>
                        <th style="padding:12px; text-align:left; font-weight:700; font-size:13px; color:#374151; border-bottom:2px solid #e5e7eb; width:200px;">Loại thao tác</th>
                        <th style="padding:12px; text-align:left; font-weight:700; font-size:13px; color:#374151; border-bottom:2px solid #e5e7eb; width:150px;">Người thực hiện</th>
                        <th style="padding:12px; text-align:left; font-weight:700; font-size:13px; color:#374151; border-bottom:2px solid #e5e7eb; width:150px;">Thời gian</th>
                        <th style="padding:12px; text-align:left; font-weight:700; font-size:13px; color:#374151; border-bottom:2px solid #e5e7eb;">Chi tiết</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <?php
                    $action_labels = [
                        'delete_orders' => 'Dọn dẹp dữ liệu cũ',
                        'backup'        => 'Sao lưu dữ liệu',
                        'health_check'  => 'Kiểm tra trạng thái',
                        'manual_log'    => 'Ghi nhận log lỗi',
                        'delete_reservations' => 'Xóa đặt bàn',
                        'reset_all'     => 'Reset toàn bộ',
                        'restore'       => 'Khôi phục'
                    ];
                    $action_colors = [
                        'delete_orders' => '#ef4444',
                        'backup'        => '#10b981',
                        'health_check'  => '#3b82f6',
                        'manual_log'    => '#f59e0b',
                        'delete_reservations' => '#f59e0b',
                        'reset_all'     => '#dc2626',
                        'restore'       => '#3b82f6'
                    ];
                    foreach ($logs as $log):
                        $action = $log['action_type'] ?? 'unknown';
                        $label  = $action_labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
                        $color  = $action_colors[$action] ?? '#6b7280';
                    ?>
                    <tr data-action-type="<?= $action ?>" style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:14px 12px; font-weight:700; color:#6b7280;"><?= $log['log_id'] ?></td>
                        <td style="padding:14px 12px;">
                            <span style="display:inline-block; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:700; color:white; background:<?= $color ?>;">
                                <?= $label ?>
                            </span>
                        </td>
                        <td style="padding:14px 12px;"><?= e($log['performed_by_name'] ?? 'N/A') ?></td>
                        <td style="padding:14px 12px; color:#6b7280;"><?= date('d/m/Y H:i', strtotime($log['performed_at'])) ?></td>
                        <td style="padding:14px 12px; max-width:260px; color:#6b7280; font-size:13px;">
                            <?php if ($log['backup_file']): 
                                // Rút gọn: chỉ lấy tên file + size
                                preg_match('/File: (backup_[^\s]+)/', $log['details'], $mf);
                                preg_match('/\(([^)]+)\)/', $log['details'], $ms);
                                $shortDetail = ($mf[1] ?? $log['backup_file']) . ' ' . ($ms[0] ?? '');
                            ?>
                                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= e($log['details']) ?>">
                                    <?= e($shortDetail) ?>
                                </div>
                                <button onclick="downloadBackup('<?= $log['backup_file'] ?>')" style="margin-top:6px; padding:5px 10px; border:none; border-radius:6px; cursor:pointer; background:linear-gradient(135deg,#10b981,#059669); color:white; font-size:12px; font-weight:600;">
                                    ↓ Tải xuống
                                </button>
                            <?php else: ?>
                                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block;" title="<?= e($log['details']) ?>">
                                    <?= e(mb_strimwidth($log['details'] ?? '-', 0, 60, '...')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div style="text-align:center; padding:60px 20px; color:#9ca3af;">
                <div style="font-size:48px; margin-bottom:16px; opacity:0.5;">Trống</div>
                <p style="margin:0; font-size:15px;">Chưa có log nào được ghi nhận</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let isProcessing = false;

async function performBackup(event) {
    event.preventDefault();
    if (isProcessing) return;
    
    if (!confirm('Bạn có chắc chắn muốn sao lưu dữ liệu?')) return;
    
    isProcessing = true;
    const btn = event.currentTarget; btn.disabled = true; btn.style.opacity = '0.6';
    
    try {
        const requestBody = { action: 'backup' };
        
        const res = await fetch('admin/api/maintenance.php', {
            method: 'POST', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(requestBody)
        });
        
        const data = await res.json();
        
        if (data.success) { 
            alert('Sao lưu thành công!\n\nFile: ' + data.filename + '\nKích thước: ' + data.size + ' MB\nĐường dẫn: ' + data.path); 
            location.reload(); 
        } else { 
            alert('Lỗi: ' + data.message); 
            isProcessing = false; 
            btn.disabled = false; 
            btn.style.opacity = '1'; 
        }
    } catch(e) { 
        alert('Có lỗi xảy ra: ' + e.message); 
        isProcessing = false; 
        btn.disabled = false; 
        btn.style.opacity = '1'; 
    }
}

async function checkSystemHealth(event) {
    event.preventDefault();
    if (isProcessing) return;
    isProcessing = true;
    const btn = event.currentTarget; btn.disabled = true; btn.style.opacity = '0.6';
    try {
        const res = await fetch('admin/api/maintenance.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'check_health'})
        });
        const data = await res.json();
        if (data.success) { alert('Trạng thái hệ thống:\n\n' + data.message); location.reload(); }
        else { alert('Lỗi: ' + data.message); isProcessing = false; btn.disabled = false; btn.style.opacity = '1'; }
    } catch(e) { alert('Có lỗi xảy ra: ' + e.message); isProcessing = false; btn.disabled = false; btn.style.opacity = '1'; }
}

async function addTestLog(event) {
    event.preventDefault();
    if (isProcessing) return;
    const reason = prompt('Nhập lý do ghi log:');
    if (!reason) return;
    isProcessing = true;
    const btn = event.currentTarget; btn.disabled = true; btn.style.opacity = '0.6';
    try {
        const res = await fetch('admin/api/maintenance.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'add_log', details: reason})
        });
        const data = await res.json();
        if (data.success) { alert('Ghi nhận log thành công!'); location.reload(); }
        else { alert('Lỗi: ' + data.message); isProcessing = false; btn.disabled = false; btn.style.opacity = '1'; }
    } catch(e) { alert('Có lỗi xảy ra: ' + e.message); isProcessing = false; btn.disabled = false; btn.style.opacity = '1'; }
}

async function cleanOldData(event) {
    event.preventDefault();
    if (isProcessing) return;
    if (!confirm('Xóa các đơn hàng và đặt bàn đã hoàn thành cách đây hơn 6 tháng?')) return;
    isProcessing = true;
    const btn = event.currentTarget; btn.disabled = true; btn.style.opacity = '0.6';
    try {
        const res = await fetch('admin/api/maintenance.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'clean_old_data'})
        });
        const data = await res.json();
        if (data.success) { alert('Dọn dẹp thành công!\nĐã xóa: ' + data.deleted_count + ' bản ghi'); location.reload(); }
        else { alert('Lỗi: ' + data.message); isProcessing = false; btn.disabled = false; btn.style.opacity = '1'; }
    } catch(e) { alert('Có lỗi xảy ra: ' + e.message); isProcessing = false; btn.disabled = false; btn.style.opacity = '1'; }
}

async function deleteAllLogs() {
    if (!confirm('Xóa TẤT CẢ log và reset ID về 1?')) return;
    if (!confirm('Bạn chắc chắn muốn xóa? Hành động này không thể hoàn tác!')) return;
    try {
        const res = await fetch('admin/api/maintenance.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete_all_logs'})
        });
        const data = await res.json();
        if (data.success) { alert('Đã xóa tất cả log!'); location.reload(); }
        else alert('Lỗi: ' + data.message);
    } catch(e) { alert('Có lỗi xảy ra: ' + e.message); }
}

function downloadBackup(filename) {
    // Tạo link download ẩn
    const link = document.createElement('a');
    link.href = 'admin/api/maintenance.php?action=download_backup&filename=' + encodeURIComponent(filename);
    link.download = filename;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function filterLogs() {
    const val = document.getElementById('logFilter').value;
    document.querySelectorAll('#logsTableBody tr').forEach(row => {
        row.style.display = (val === 'all' || row.dataset.actionType === val) ? '' : 'none';
    });
}
</script>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>


