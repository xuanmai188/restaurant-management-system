<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);

// Đồng bộ trạng thái bàn với thực tế
sync_table_status();

// Get tables with order information
$tables_query = "
    SELECT 
        t.table_id,
        t.table_name,
        t.capacity,
        t.status,
        f.floor_name,
        o.order_id,
        o.order_time,
        o.guest_count,
        c.customer_name,
        (SELECT COUNT(*) FROM reservations r
         LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
         WHERE r.table_id = t.table_id
           AND r.status IN ('da_xac_nhan', 'cho_xac_nhan')
           AND DATE(r.reservation_time) = CURDATE()
           AND rp.reservation_payment_id IS NULL
        ) as walkin_count,
        (SELECT COUNT(*) FROM reservations r
         LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
         WHERE r.table_id = t.table_id
           AND r.status IN ('da_xac_nhan', 'cho_xac_nhan')
           AND DATE(r.reservation_time) = CURDATE()
           AND rp.reservation_payment_id IS NOT NULL
        ) as online_count
    FROM tables t
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    LEFT JOIN orders o ON o.order_id = (
        SELECT order_id FROM orders
        WHERE table_id = t.table_id
          AND status IN ('moi', 'dang_xu_ly', 'dang_che_bien', 'dang_phuc_vu', 'hoan_thanh')
          AND (DATE(order_time) = CURDATE() OR status = 'dang_phuc_vu')
        ORDER BY
          CASE WHEN DATE(order_time) = CURDATE() THEN 0 ELSE 1 END,
          order_id DESC
        LIMIT 1
    )
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    ORDER BY f.floor_name, t.table_name
";
$tables_result = $conn->query($tables_query);
$tables = $tables_result->fetch_all(MYSQLI_ASSOC);

// Get floors for add/edit form
$floors = $conn->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="module-container">
    <div class="module-header">
        <div>
            <h2>Quản lý bàn</h2>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-secondary" onclick="window.openAddFloorModal(); return false;">
                Tạo tầng mới
            </button>
            <button class="btn btn-primary" onclick="window.openAddTableModal(); return false;">
                Thêm bàn mới
            </button>
        </div>
    </div>

    <?php if (count($tables) > 0): ?>
        <?php 
        // Group tables by floor
        $tablesByFloor = [];
        foreach ($tables as $table) {
            $floorName = $table['floor_name'] ?? 'Không rõ';
            if (!isset($tablesByFloor[$floorName])) {
                $tablesByFloor[$floorName] = [];
            }
            $tablesByFloor[$floorName][] = $table;
        }
        
        // Tạo map floor_name => floor_id
        $floorIdMap = [];
        foreach ($floors as $floor) {
            $floorIdMap[$floor['floor_name']] = $floor['floor_id'];
        }
        
        $statusLabels = [
            'trong'        => 'Trống',
            'dang_su_dung' => 'Có khách',
            'da_dat'       => 'Đã đặt',
            'bao_tri'      => 'Bảo trì'
        ];
        
        foreach ($tablesByFloor as $floorName => $floorTables): 
            $floorId = $floorIdMap[$floorName] ?? 0;
        ?>
            <div class="floor-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid #e0e0e0;">
                    <h3 style="font-size:24px; font-weight:700; margin:0; color:#333;"><?= e($floorName) ?></h3>
                    <button onclick="deleteFloor('<?= e($floorName) ?>', <?= $floorId ?>)" style="padding:8px 16px; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s ease;">
                        Xóa tầng
                    </button>
                </div>
                <div class="tables-grid">
                    <?php foreach ($floorTables as $table): ?>
                        <div class="table-card <?= $table['status'] ?>">
                            <div class="table-card-header">
                                <div>
                                    <div class="table-number"><?= e($table['table_name']) ?></div>
                                    <div class="table-floor"><?= e($table['floor_name'] ?? 'Không rõ') ?></div>
                                </div>
                                <div class="table-actions">
                                    <button class="btn-icon" onclick="editTable(<?= $table['table_id'] ?>)" title="Sửa">Sửa</button>
                                    <button class="btn-icon" onclick="deleteTable(<?= $table['table_id'] ?>)" title="Xóa">Xóa</button>
                                </div>
                            </div>
                            
                            <div class="table-card-body">
                                <div class="table-info">
                                    <span>Sức chứa: <?= $table['capacity'] ?> người</span>
                                </div>
                                
                                <div class="table-status status-<?= $table['status'] ?>">
                                    <?= $statusLabels[$table['status']] ?? $table['status'] ?>
                                </div>
                                
                                <?php if ($table['status'] === 'dang_su_dung' && $table['order_id']): ?>
                                    <div class="order-info">
                                        <div class="order-detail">
                                            <strong>Mã đơn:</strong> #<?= $table['order_id'] ?>
                                        </div>
                                        <div class="order-detail">
                                            <strong>Khách:</strong> <?= e($table['customer_name'] ?? 'Khách vãng lai') ?>
                                        </div>
                                        <div class="order-detail">
                                            <strong>Số người:</strong> <?= $table['guest_count'] ?? 0 ?>
                                        </div>
                                        <div class="order-detail">
                                            <strong>Giờ vào:</strong> <?= date('H:i', strtotime($table['order_time'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php 
                                $walkin = (int)$table['walkin_count'];
                                $online = (int)$table['online_count'];
                                if ($walkin + $online > 0):
                                    if ($walkin > 0 && $online > 0) {
                                        $badgeText = $walkin . ' walk-in, ' . $online . ' online';
                                    } elseif ($online > 0) {
                                        $badgeText = $online . ' online';
                                    } else {
                                        $badgeText = $walkin . ' walk-in';
                                    }
                                ?>
                                    <div class="reservation-info">
                                        <span class="reservation-badge"><?= $badgeText ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="table-card-footer">
                                <button class="btn-detail" onclick="viewTableDetail(<?= $table['table_id'] ?>)">
                                    Xem chi tiết
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <p>Chưa có bàn nào trong hệ thống</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Table Modal -->
<div id="table-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Thêm bàn mới</h3>
            <button class="modal-close" onclick="closeTableModal()">&times;</button>
        </div>
        <form id="table-form" class="modal-body">
            <input type="hidden" id="table-id">
            
            <div class="form-group">
                <label>Tên bàn</label>
                <input type="text" id="table-name" class="input-text" placeholder="VD: Bàn 1, Bàn VIP 1" required>
            </div>
            
            <div class="form-group">
                <label>Tầng</label>
                <select id="table-floor" class="input-select" required>
                    <option value="">Chọn tầng</option>
                    <?php foreach ($floors as $floor): ?>
                        <option value="<?= $floor['floor_id'] ?>"><?= e($floor['floor_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Sức chứa (số người)</label>
                <input type="number" id="table-capacity" class="input-text" min="1" max="20" required>
            </div>
            
            <div class="form-group">
                <label>Trạng thái</label>
                <select id="table-status" class="input-select" required>
                    <option value="trong">Trống</option>
                    <option value="bao_tri">Bảo trì</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeTableModal()" class="btn btn-secondary">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Floor Modal -->
<div id="floor-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tạo tầng mới</h3>
            <button class="modal-close" onclick="closeFloorModal()">&times;</button>
        </div>
        <form id="floor-form" class="modal-body">
            <div class="form-group">
                <label>Tên tầng</label>
                <input type="text" id="floor-name" class="input-text" placeholder="VD: Tầng 1, Tầng 2" required>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeFloorModal()" class="btn btn-secondary">Hủy</button>
                <button type="submit" class="btn btn-primary">Tạo tầng</button>
            </div>
        </form>
    </div>
</div>

<!-- Table Detail Modal -->
<div id="detail-modal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Chi tiết bàn</h3>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="detail-content">
            <div class="loading">Đang tải...</div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeDetailModal()" class="btn btn-primary">Đóng</button>
        </div>
    </div>
</div>

<style>
.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.floor-section {
    margin-bottom: 40px;
    clear: both;
}

.floor-title {
    font-size: 24px;
    font-weight: 700;
    margin-top: 20px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
    color: #333;
}

.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.table-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.table-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    border-color: #d1d5db;
}

.table-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    padding: 18px;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border-bottom: 1px solid #e5e7eb;
}

.table-number {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 4px;
    color: #111827;
}

.table-floor {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
}

.table-actions {
    display: flex;
    gap: 6px;
}

.btn-icon {
    background: white;
    border: 1px solid #e5e7eb;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 8px;
    transition: all 0.2s ease;
    color: #374151;
}

.btn-icon:first-child {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border-color: #3b82f6;
}

.btn-icon:first-child:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-icon:last-child {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border-color: #ef4444;
}

.btn-icon:last-child:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.table-card-body {
    padding: 18px;
}

.table-info {
    margin-bottom: 14px;
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.table-status {
    font-size: 13px;
    padding: 8px 14px;
    border-radius: 20px;
    display: inline-block;
    margin-bottom: 14px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.status-trong {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.status-dang_su_dung {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.status-da_dat {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border: 1px solid #fcd34d;
}

.status-bao_tri {
    background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
    color: #1f2937;
    border: 1px solid #9ca3af;
}

.order-info {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    padding: 14px;
    border-radius: 12px;
    margin-top: 14px;
    border: 1px solid #bae6fd;
}

.order-detail {
    font-size: 13px;
    margin-bottom: 8px;
    color: #0c4a6e;
    display: flex;
    justify-content: space-between;
}

.order-detail:last-child {
    margin-bottom: 0;
}

.order-detail strong {
    color: #075985;
    font-weight: 600;
}

.reservation-info {
    margin-top: 14px;
}

.reservation-badge {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid #fcd34d;
}

.table-card-footer {
    padding: 14px 18px;
    border-top: 1px solid #f3f4f6;
    background: #fafafa;
}

.btn-detail {
    width: 100%;
    padding: 10px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}

.btn-detail:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #999;
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 14px;
}

.input-text, .input-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 20px;
    border-top: 1px solid #e0e0e0;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.detail-section {
    margin-bottom: 24px;
}

.detail-section h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 8px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #666;
}

.detail-value {
    color: #333;
}

.reservation-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
}

.reservation-item:last-child {
    margin-bottom: 0;
}
</style>

<script>
(function() {
    console.log('=== TABLES MODULE SCRIPT LOADING ===');
    
    const API_URL = '/quanlynhahang/manager/api/tables.php';

    // Define functions immediately on window object
    window.openAddTableModal = function() {
        console.log('=== OPEN MODAL CALLED ===');
        try {
            const modal = document.getElementById('table-modal');
            console.log('Modal element found:', modal);
            
            if (!modal) {
                alert('ERROR: Modal element not found!');
                return;
            }
            
            document.getElementById('modal-title').textContent = 'Thêm bàn mới';
            document.getElementById('table-form').reset();
            document.getElementById('table-id').value = '';
            
            modal.classList.add('active');
            console.log('Modal classes after add:', modal.className);
            console.log('Modal display style:', window.getComputedStyle(modal).display);
        } catch (error) {
            console.error('Error in openAddTableModal:', error);
            alert('Lỗi: ' + error.message);
        }
    };
    
    console.log('openAddTableModal defined:', typeof window.openAddTableModal);

    window.openAddFloorModal = function() {
        console.log('=== OPEN FLOOR MODAL CALLED ===');
        try {
            const modal = document.getElementById('floor-modal');
            if (!modal) {
                alert('ERROR: Floor modal not found!');
                return;
            }
            document.getElementById('floor-form').reset();
            modal.classList.add('active');
        } catch (error) {
            console.error('Error in openAddFloorModal:', error);
            alert('Lỗi: ' + error.message);
        }
    };

    window.closeFloorModal = function() {
        console.log('Close floor modal called');
        document.getElementById('floor-modal').classList.remove('active');
    };

    window.editTable = function(tableId) {
        console.log('Edit table called:', tableId);
        document.getElementById('modal-title').textContent = 'Sửa thông tin bàn';
        
        fetch(`${API_URL}?action=get&table_id=${tableId}`, { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const table = data.data;
                    document.getElementById('table-id').value = table.table_id;
                    document.getElementById('table-name').value = table.table_name;
                    document.getElementById('table-floor').value = table.floor_id;
                    document.getElementById('table-capacity').value = table.capacity;
                    document.getElementById('table-status').value = table.status;
                    document.getElementById('table-modal').classList.add('active');
                } else {
                    alert(data.message || 'Lỗi tải thông tin bàn');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Lỗi kết nối');
            });
    };

    window.deleteTable = function(tableId) {
        console.log('Delete table called:', tableId);
        if (!confirm('Bạn có chắc muốn xóa bàn này?')) {
            return;
        }
        
        fetch(`${API_URL}?action=delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ table_id: tableId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                const base = window.location.origin + window.location.pathname;
                window.location.replace(base + '?module=tables&t=' + Date.now());
            } else {
                alert(data.message || 'Lỗi xóa bàn');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Lỗi kết nối');
        });
    };

    window.viewTableDetail = function(tableId) {
        console.log('View detail called:', tableId);
        const modal = document.getElementById('detail-modal');
        const content = document.getElementById('detail-content');
        
        // Show modal with loading state
        content.innerHTML = '<div class="loading">Đang tải...</div>';
        modal.classList.add('active');
        
        fetch(`${API_URL}?action=detail&table_id=${tableId}`, { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showDetailInfo(data.data);
                } else {
                    content.innerHTML = `<div style="color: red;">Lỗi: ${data.message || 'Không thể tải chi tiết'}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<div style="color: red;">Lỗi kết nối</div>';
            });
    };

    window.closeTableModal = function() {
        console.log('Close modal called');
        document.getElementById('table-modal').classList.remove('active');
    };

    window.closeDetailModal = function() {
        console.log('Close detail modal called');
        document.getElementById('detail-modal').classList.remove('active');
    };

    function showDetailInfo(data) {
        const { table, order, reservations } = data;
        const content = document.getElementById('detail-content');
        
        const statusLabels = {
            'trong':        'Trống',
            'dang_su_dung': 'Có khách',
            'da_dat':       'Đã đặt',
            'bao_tri':      'Bảo trì'
        };
        
        let html = `
            <div class="detail-section">
                <h4>Thông tin bàn</h4>
                <div class="detail-row">
                    <span class="detail-label">Tên bàn:</span>
                    <span class="detail-value">${table.table_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tầng:</span>
                    <span class="detail-value">${table.floor_name || 'Không rõ'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Sức chứa:</span>
                    <span class="detail-value">${table.capacity} người</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Trạng thái:</span>
                    <span class="detail-value">${statusLabels[table.status] || table.status}</span>
                </div>
            </div>
        `;
        
        if (order) {
            html += `
                <div class="detail-section">
                    <h4>Đơn hàng hiện tại</h4>
                    <div class="detail-row">
                        <span class="detail-label">Mã đơn:</span>
                        <span class="detail-value">#${order.order_id}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Khách hàng:</span>
                        <span class="detail-value">${order.customer_name || 'Khách vãng lai'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Số người:</span>
                        <span class="detail-value">${order.guest_count}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Giờ vào:</span>
                        <span class="detail-value">${new Date(order.order_time).toLocaleTimeString('vi-VN')}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tổng tiền:</span>
                        <span class="detail-value">${new Intl.NumberFormat('vi-VN').format(order.total_amount)}đ</span>
                    </div>
                </div>
            `;
        }
        
        if (reservations.length > 0) {
            html += `
                <div class="detail-section">
                    <h4>Đặt bàn (${reservations.length})</h4>
            `;
            
            reservations.forEach((res, i) => {
                const isOnline = res.deposit_amount !== null && res.deposit_amount !== undefined && res.deposit_amount !== '';
                const typeLabel = isOnline ? 'Online' : 'Walk-in';
                const typeBadgeStyle = isOnline
                    ? 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;'
                    : 'background:#f3f4f6; color:#374151; border:1px solid #d1d5db; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;';

                let depositHtml = '';
                if (isOnline) {
                    const amount = new Intl.NumberFormat('vi-VN').format(res.deposit_amount) + ' đ';
                    const ptLabels = { deposit: 'Đặt cọc', full_payment: 'Thanh toán đủ' };
                    const dsLabels = { pending: 'Chờ xử lý', success: 'Thành công', failed: 'Thất bại' };
                    const dsStyles = {
                        pending: 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;',
                        success: 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;',
                        failed:  'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;',
                    };
                    depositHtml = `
                        <div class="detail-row">
                            <span class="detail-label">Tiền cọc:</span>
                            <span class="detail-value" style="color:#059669; font-weight:700;">${amount}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Loại thanh toán:</span>
                            <span class="detail-value">${ptLabels[res.payment_type] || res.payment_type || '-'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Trạng thái cọc:</span>
                            <span class="detail-value"><span style="${dsStyles[res.deposit_status] || ''}">${dsLabels[res.deposit_status] || res.deposit_status || '-'}</span></span>
                        </div>
                    `;
                }

                html += `
                    <div class="reservation-item">
                        <div class="detail-row">
                            <span class="detail-label">Mã đặt bàn:</span>
                            <span class="detail-value" style="font-weight:700; color:#3b82f6;">#${res.reservation_id}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Loại:</span>
                            <span class="detail-value"><span style="${typeBadgeStyle}">${typeLabel}</span></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Khách hàng:</span>
                            <span class="detail-value">${res.customer_name || 'Khách vãng lai'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Thời gian:</span>
                            <span class="detail-value">${new Date(res.reservation_time).toLocaleString('vi-VN')}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Số người:</span>
                            <span class="detail-value">${res.number_of_people}</span>
                        </div>
                        ${depositHtml}
                    </div>
                `;
            });
            
            html += `</div>`;
        }
        
        content.innerHTML = html;
    }

function handleFormSubmit(e) {
    console.log('Form submit called');
    e.preventDefault();
    
    const tableId = document.getElementById('table-id').value;
    const action = tableId ? 'update' : 'create';
    
    const data = {
        table_id: tableId || undefined,
        table_name: document.getElementById('table-name').value,
        floor_id: document.getElementById('table-floor').value,
        capacity: document.getElementById('table-capacity').value,
        status: document.getElementById('table-status').value
    };
    
    console.log('Submitting:', action, data);
    
    fetch(`${API_URL}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data);
        if (data.success) {
            alert(data.message);
            window.closeTableModal();
            const base = window.location.origin + window.location.pathname;
            window.location.replace(base + '?module=tables&t=' + Date.now());
        } else {
            alert(data.message || 'Lỗi lưu bàn');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Lỗi kết nối');
    });
}

// Setup form submit handler
const form = document.getElementById('table-form');
if (form) {
    form.addEventListener('submit', handleFormSubmit);
    console.log('Form submit handler added');
}

// Setup floor form submit handler
const floorForm = document.getElementById('floor-form');
if (floorForm) {
    floorForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const floorName = document.getElementById('floor-name').value;
        
        fetch('/quanlynhahang/manager/api/tables.php?action=createFloor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ floor_name: floorName })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.closeFloorModal();
                const base = window.location.origin + window.location.pathname;
                window.location.replace(base + '?module=tables&t=' + Date.now());
            } else {
                alert(data.message || 'Lỗi tạo tầng');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Lỗi kết nối');
        });
    });
    console.log('Floor form submit handler added');
}

// Hàm xóa tầng
window.deleteFloor = function(floorName, floorId) {
    if (!floorId) {
        alert('Không tìm thấy ID tầng');
        return;
    }
    
    // Thông báo cảnh báo chi tiết
    const confirmMessage = `⚠️ CẢNH BÁO: BẠN SẮP XÓA TẦNG!\n\n` +
                          `Tầng: ${floorName}\n\n` +
                          `LƯU Ý QUAN TRỌNG:\n` +
                          `• Chỉ có thể xóa tầng KHÔNG CÓ BÀN nào\n` +
                          `• Nếu tầng có bàn, vui lòng xóa hết bàn trước\n` +
                          `• Hành động này KHÔNG THỂ HOÀN TÁC\n\n` +
                          `Bạn có chắc chắn muốn xóa tầng "${floorName}" không?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch('/quanlynhahang/manager/api/tables.php?action=deleteFloor', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({floor_id: floorId})
    })
    .then(r => r.text().then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response was:', text);
            throw new Error('API trả về không phải JSON');
        }
    }))
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            const base = window.location.origin + window.location.pathname;
            window.location.replace(base + '?module=tables&t=' + Date.now());
        } else {
            alert('❌ ' + (data.message || 'Lỗi xóa tầng'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Lỗi kết nối: ' + err.message);
    });
};

console.log('=== TABLES MODULE SCRIPT LOADED ===');
console.log('Functions defined:', {
    openAddTableModal: typeof window.openAddTableModal,
    openAddFloorModal: typeof window.openAddFloorModal,
    editTable: typeof window.editTable,
    deleteTable: typeof window.deleteTable,
    viewTableDetail: typeof window.viewTableDetail,
    closeTableModal: typeof window.closeTableModal,
    closeFloorModal: typeof window.closeFloorModal,
    closeDetailModal: typeof window.closeDetailModal
});

})();
</script>