    <?php
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_role(['quanly']);

    // Get system statistics
    $stats = [];

    // Count tables
    $result = $conn->query("SELECT COUNT(*) as count FROM tables");
    $stats['tables'] = $result->fetch_assoc()['count'];

    // Count orders
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $stats['orders'] = $result->fetch_assoc()['count'];

    // Count reservations
    $result = $conn->query("SELECT COUNT(*) as count FROM reservations");
    $stats['reservations'] = $result->fetch_assoc()['count'];

    // Count users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'hoat_dong'");
    $stats['users'] = $result->fetch_assoc()['count'];

    // Get recent logs
    $logs_query = "
        SELECT l.log_id, l.action_type, l.deleted_count, l.performed_by, l.performed_at, l.details,
            u.full_name as performed_by_name
        FROM data_operation_logs l
        LEFT JOIN users u ON u.user_id = l.performed_by
        ORDER BY l.log_id DESC
        LIMIT 100
    ";
    $logs_result = $conn->query($logs_query);
    $logs = $logs_result ? $logs_result->fetch_all(MYSQLI_ASSOC) : [];

    // Extract backup filenames from details
    foreach ($logs as &$log) {
        if ($log['action_type'] === 'backup' && preg_match('/File: (backup_qlnhahang_[\d\-_]+\.sql)/', $log['details'], $matches)) {
            $log['backup_file'] = $matches[1];
        } else {
            $log['backup_file'] = null;
        }
    }

    // Debug: Check if we have any logs
    $total_logs = count($logs);

    // Debug output
    error_log("Total logs in array: " . $total_logs);
    foreach ($logs as $log) {
        error_log("Log ID: {$log['log_id']}, Type: {$log['action_type']}");
    }

    // Get database size
    $db_name = 'qlnhahang';
    $size_query = "
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.TABLES
        WHERE table_schema = '$db_name'
    ";
    $size_result = $conn->query($size_query);
    $db_size = $size_result->fetch_assoc()['size_mb'] ?? 0;
    ?>

    <div class="module-container">
        <div class="module-header">
            <div>
                <h2>Bảo trì hệ thống</h2>
                <p>Hỗ trợ các chức năng bảo trì và đảm bảo hoạt động ổn định</p>
            </div>
        </div>

        <!-- System Status Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="--accent: linear-gradient(90deg,#3b82f6,#2563eb);">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Kích thước CSDL</div>
                    <div class="stat-value"><?= $db_size ?> <span style="font-size:16px;font-weight:600;color:#64748b;">MB</span></div>
                </div>
            </div>

            <div class="stat-card" style="--accent: linear-gradient(90deg,#10b981,#059669);">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Tổng đơn hàng</div>
                    <div class="stat-value"><?= number_format($stats['orders']) ?></div>
                </div>
            </div>

            <div class="stat-card" style="--accent: linear-gradient(90deg,#f59e0b,#d97706);">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Tổng đặt bàn</div>
                    <div class="stat-value"><?= number_format($stats['reservations']) ?></div>
                </div>
            </div>

            <div class="stat-card" style="--accent: linear-gradient(90deg,#8b5cf6,#7c3aed);">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Người dùng</div>
                    <div class="stat-value"><?= number_format($stats['users']) ?></div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-section">
            <h3 class="section-title">
                <span class="section-title-icon"><i class="fas fa-tools"></i></span>
                Thao tác bảo trì
            </h3>
            <div class="action-buttons">
                <button class="action-btn btn-backup" onclick="performBackup(event)" title="Tạo bản sao lưu toàn bộ dữ liệu hệ thống">
                    <div class="btn-icon-wrap"><i class="fas fa-download"></i></div>
                    <span>Sao lưu dữ liệu</span>
                    <small>Tạo bản backup CSDL</small>
                </button>
                <button class="action-btn btn-check" onclick="checkSystemHealth(event)" title="Kiểm tra tình trạng hoạt động của hệ thống">
                    <div class="btn-icon-wrap"><i class="fas fa-heartbeat"></i></div>
                    <span>Kiểm tra trạng thái</span>
                    <small>Xem tình trạng hệ thống</small>
                </button>
                <button class="action-btn btn-log" onclick="addTestLog(event)" title="Ghi nhận log thủ công">
                    <div class="btn-icon-wrap"><i class="fas fa-file-alt"></i></div>
                    <span>Ghi nhận log lỗi</span>
                    <small>Lưu log thủ công</small>
                </button>
                <button class="action-btn btn-clean" onclick="cleanOldData(event)" title="Xóa dữ liệu cũ hơn 6 tháng">
                    <div class="btn-icon-wrap"><i class="fas fa-broom"></i></div>
                    <span>Dọn dẹp dữ liệu cũ</span>
                    <small>Xóa dữ liệu cũ hơn 6 tháng</small>
                </button>
            </div>
        </div>

        <!-- Logs Section -->
        <div class="logs-section">
            <div class="logs-header">
                <div class="logs-header-left">
                    <h3 class="section-title" style="margin:0;">
                        <span class="section-title-icon" style="background:linear-gradient(135deg,#64748b,#475569);"><i class="fas fa-list-alt"></i></span>
                        Lịch sử thao tác
                    </h3>
                    <span class="logs-count-badge"><?= $total_logs ?></span>
                </div>
                <div class="logs-header-right">
                    <select id="logFilter" class="log-filter" onchange="filterLogs()">
                        <option value="all">Tất cả loại</option>
                        <option value="backup">Sao lưu dữ liệu</option>
                        <option value="health_check">Kiểm tra trạng thái</option>
                        <option value="manual_log">Ghi nhận log lỗi</option>
                        <option value="delete_orders">Dọn dẹp dữ liệu cũ</option>
                    </select>
                    <button class="btn btn-sm btn-danger" onclick="deleteAllLogs()">
                        <i class="fas fa-trash-alt"></i> Xóa tất cả
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="refreshLogs()">
                        <i class="fas fa-sync-alt"></i> Làm mới
                    </button>
                </div>
            </div>

            <div class="logs-table-container">
                <?php if (count($logs) > 0): ?>
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 200px;">Loại thao tác</th>
                                <th style="width: 150px;">Người thực hiện</th>
                                <th style="width: 150px;">Thời gian</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <?php foreach ($logs as $log): ?>
                                <tr data-action-type="<?= $log['action_type'] ?>">
                                    <td class="log-id-cell">#<?= $log['log_id'] ?></td>
                                    <td>
                                        <?php
                                        $action_labels = [
                                            'delete_orders' => 'Dọn dẹp dữ liệu cũ',
                                            'backup' => 'Sao lưu dữ liệu',
                                            'health_check' => 'Kiểm tra trạng thái',
                                            'manual_log' => 'Ghi nhận log lỗi',
                                            'delete_reservations' => 'Xóa đặt bàn',
                                            'reset_all' => 'Reset toàn bộ',
                                            'restore' => 'Khôi phục'
                                        ];
                                        $action_colors = [
                                            'delete_orders' => '#ef4444',
                                            'backup' => '#10b981',
                                            'health_check' => '#3b82f6',
                                            'manual_log' => '#f59e0b',
                                            'delete_reservations' => '#f59e0b',
                                            'reset_all' => '#dc2626',
                                            'restore' => '#3b82f6'
                                        ];
                                        $action = $log['action_type'] ?? 'unknown';
                                        $label = $action_labels[$action] ?? ($action ? ucfirst(str_replace('_', ' ', $action)) : 'Không xác định');
                                        $color = $action_colors[$action] ?? '#6b7280';
                                        ?>
                                        <span class="action-badge" style="background: <?= $color ?>;">
                                            <?= $label ?>
                                        </span>
                                    </td>
                                    <td class="log-user-cell"><?= e($log['performed_by_name'] ?? 'N/A') ?></td>
                                    <td class="log-time-cell"><?= date('d/m/Y H:i', strtotime($log['performed_at'])) ?></td>
                                    <td class="details-cell">
                                        <?php if ($log['action_type'] === 'backup' && preg_match('/File: (backup_qlnhahang_[\d\-_]+\.sql)/', $log['details'], $matches)): ?>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <span style="flex: 1; color: #64748b; font-size: 13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($log['details'] ?? '-') ?></span>
                                                <button class="btn-download-mini" onclick="downloadBackup('<?= $matches[1] ?>')" title="Tải xuống file backup">
                                                    <i class="fas fa-download"></i> Tải xuống
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <?= e($log['details'] ?? '-') ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Chưa có log nào được ghi nhận</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    * {
        font-family: 'Be Vietnam Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    }
    
    .module-container {
        padding: 28px;
        background: #f1f5f9;
        min-height: 100%;
    }

    /* ── Header ── */
    .module-header {
        margin-bottom: 28px;
    }
    .module-header h2 {
        margin: 0 0 6px;
        font-size: 26px;
        color: #0f172a;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    .module-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    /* ── Stats Grid ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 500px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: #fff;
        border-radius: 18px;
        padding: 22px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 8px rgba(15,23,42,.06);
        border: 1px solid #e2e8f0;
        transition: transform .2s, box-shadow .2s;
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: var(--accent);
        border-radius: 18px 18px 0 0;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(15,23,42,.10);
    }
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        flex-shrink: 0;
    }
    .stat-info { flex: 1; min-width: 0; }
    .stat-label {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
        margin-bottom: 4px;
    }
    .stat-value {
        font-size: 30px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
    }

    /* ── Action Section ── */
    .action-section {
        background: #fff;
        border-radius: 18px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(15,23,42,.06);
        border: 1px solid #e2e8f0;
    }
    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 20px;
        font-size: 16px;
        color: #0f172a;
        font-weight: 700;
    }
    .section-title-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: white;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
    }

    .action-buttons {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
    }
    @media (max-width: 900px) { .action-buttons { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 500px) { .action-buttons { grid-template-columns: 1fr; } }

    .action-btn {
        padding: 22px 16px;
        border: none;
        border-radius: 16px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        font-size: 14px;
        transition: transform .2s, box-shadow .2s, opacity .15s;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .action-btn::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0);
        transition: background .2s;
    }
    .action-btn:hover::after { background: rgba(255,255,255,.12); }
    .action-btn:hover { transform: translateY(-3px); }
    .action-btn:active { transform: translateY(0); opacity: .85; }
    .action-btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    .action-btn .btn-icon-wrap {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: rgba(255,255,255,.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    .action-btn span { font-size: 14px; font-weight: 700; }
    .action-btn small {
        font-size: 11px;
        font-weight: 500;
        opacity: .85;
        text-align: center;
        line-height: 1.4;
    }

    .btn-backup {
        background: linear-gradient(145deg, #10b981, #059669);
        box-shadow: 0 6px 20px rgba(16,185,129,.35);
    }
    .btn-check {
        background: linear-gradient(145deg, #3b82f6, #2563eb);
        box-shadow: 0 6px 20px rgba(59,130,246,.35);
    }
    .btn-log {
        background: linear-gradient(145deg, #f59e0b, #d97706);
        box-shadow: 0 6px 20px rgba(245,158,11,.35);
    }
    .btn-clean {
        background: linear-gradient(145deg, #ef4444, #dc2626);
        box-shadow: 0 6px 20px rgba(239,68,68,.35);
    }

    /* ── Logs Section ── */
    .logs-section {
        background: #fff;
        border-radius: 18px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(15,23,42,.06);
        border: 1px solid #e2e8f0;
    }
    .logs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .logs-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .logs-count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 26px;
        height: 26px;
        padding: 0 8px;
        background: #f1f5f9;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }
    .logs-header-right {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .log-filter {
        padding: 8px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        background: #f8fafc;
        cursor: pointer;
        transition: border-color .2s, box-shadow .2s;
        outline: none;
    }
    .log-filter:hover { border-color: #94a3b8; }
    .log-filter:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all .2s;
        white-space: nowrap;
    }
    .btn-sm { padding: 7px 13px; font-size: 12px; }
    .btn-secondary { background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; }
    .btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
    .btn-danger { background: linear-gradient(135deg,#ef4444,#dc2626); color: white; box-shadow: 0 2px 8px rgba(239,68,68,.25); }
    .btn-danger:hover { box-shadow: 0 4px 14px rgba(239,68,68,.35); transform: translateY(-1px); }

    /* ── Table ── */
    .logs-table-container {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    .logs-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }
    .logs-table thead tr {
        background: #f8fafc;
    }
    .logs-table th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 700;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .7px;
        border-bottom: 1.5px solid #e2e8f0;
        white-space: nowrap;
    }
    .logs-table td {
        padding: 13px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        vertical-align: middle;
    }
    .logs-table tbody tr:last-child td { border-bottom: none; }
    .logs-table tbody tr:hover { background: #f8fafc; }

    .log-id-cell {
        font-weight: 700;
        color: #94a3b8;
        font-size: 13px;
        font-variant-numeric: tabular-nums;
    }
    .log-time-cell {
        color: #64748b;
        font-size: 13px;
        white-space: nowrap;
    }
    .log-user-cell {
        font-weight: 600;
        color: #334155;
        white-space: nowrap;
    }

    .action-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 700;
        color: white;
        white-space: nowrap;
    }
    .action-badge::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: rgba(255,255,255,.6);
        flex-shrink: 0;
    }

    .details-cell {
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #64748b;
        font-size: 13px;
    }

    .btn-download-mini {
        padding: 5px 12px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        transition: all .2s;
        white-space: nowrap;
        box-shadow: 0 2px 6px rgba(16,185,129,.25);
    }
    .btn-download-mini:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16,185,129,.35);
    }

    .empty-state {
        text-align: center;
        padding: 64px 20px;
        color: #94a3b8;
    }
    .empty-state i { font-size: 44px; margin-bottom: 14px; opacity: .4; display: block; }
    .empty-state p { margin: 0; font-size: 15px; font-weight: 500; }
    </style>

    <script>
    let isProcessing = false;

    async function performBackup(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (isProcessing) return;
        if (!confirm('Bạn có chắc chắn muốn sao lưu dữ liệu?')) return;
        
        isProcessing = true;
        const btn = event.currentTarget;
        btn.disabled = true;
        btn.style.opacity = '0.6';
        
        try {
            const response = await fetch('/quanlynhahang/manager/api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'backup' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Sao lưu dữ liệu thành công!\n\nFile: ' + data.filename + '\nKích thước: ' + data.size + ' MB');
                location.reload();
            } else {
                alert('Lỗi: ' + data.message);
                isProcessing = false;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi sao lưu dữ liệu');
            isProcessing = false;
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }

    async function checkSystemHealth(event) {
        event.preventDefault();
        event.stopPropagation();
        
        console.log('checkSystemHealth called, isProcessing:', isProcessing);
        
        if (isProcessing) {
            console.log('Already processing, returning');
            return;
        }
        
        isProcessing = true;
        const btn = event.currentTarget;
        btn.disabled = true;
        btn.style.opacity = '0.6';
        
        console.log('Sending check_health request...');
        
        try {
            const response = await fetch('/quanlynhahang/manager/api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'check_health' })
            });
            
            const data = await response.json();
            console.log('Response:', data);
            
            if (data.success) {
                alert('Trạng thái hệ thống:\n\n' + data.message);
                location.reload();
            } else {
                alert('Lỗi: ' + data.message);
                isProcessing = false;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi kiểm tra trạng thái');
            isProcessing = false;
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }

    async function addTestLog(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (isProcessing) return;
        
        const reason = prompt('Nhập lý do ghi log:');
        if (!reason) return;
        
        isProcessing = true;
        const btn = event.currentTarget;
        btn.disabled = true;
        btn.style.opacity = '0.6';
        
        try {
            const response = await fetch('/quanlynhahang/manager/api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'add_log',
                    details: reason
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Ghi nhận log thành công!');
                location.reload();
            } else {
                alert('Lỗi: ' + data.message);
                isProcessing = false;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi ghi log');
            isProcessing = false;
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }

    async function cleanOldData(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (isProcessing) return;
        if (!confirm('Bạn có chắc chắn muốn dọn dẹp dữ liệu cũ?\n\nThao tác này sẽ xóa các đơn hàng và đặt bàn đã hoàn thành cách đây hơn 6 tháng.')) return;
        
        isProcessing = true;
        const btn = event.currentTarget;
        btn.disabled = true;
        btn.style.opacity = '0.6';
        
        try {
            const response = await fetch('/quanlynhahang/manager/api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clean_old_data' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Dọn dẹp dữ liệu thành công!\n\nĐã xóa: ' + data.deleted_count + ' bản ghi');
                location.reload();
            } else {
                alert('Lỗi: ' + data.message);
                isProcessing = false;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi dọn dẹp dữ liệu');
            isProcessing = false;
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }

    function refreshLogs() {
        location.reload();
    }

    function downloadBackup(filename) {
        // Simple GET request download - same as admin
        window.location.href = '/quanlynhahang/manager/api/maintenance.php?action=download_backup&filename=' + encodeURIComponent(filename);
    }

    async function deleteLog(logId) {
        if (!confirm('Bạn có chắc chắn muốn xóa log này?')) return;
        
        try {
            const response = await fetch('/quanlynhahang/manager/api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'delete_log',
                    log_id: logId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Xóa log thành công!');
                refreshLogs();
            } else {
                alert('Lỗi: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi xóa log');
        }
    }

    async function deleteAllLogs() {
        if (!confirm('Bạn có chắc chắn muốn xóa TẤT CẢ log?\n\nThao tác này sẽ xóa toàn bộ lịch sử và reset ID về 1.')) return;
        
        try {
            const response = await fetch('/quanlynhahang/manager/api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_all_logs' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Đã xóa tất cả log thành công!');
                refreshLogs();
            } else {
                alert('Lỗi: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi xóa log');
        }
    }

    function filterLogs() {
        const filterValue = document.getElementById('logFilter').value;
        const rows = document.querySelectorAll('#logsTableBody tr');
        
        rows.forEach(row => {
            const actionType = row.getAttribute('data-action-type');
            
            if (filterValue === 'all' || actionType === filterValue) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>
