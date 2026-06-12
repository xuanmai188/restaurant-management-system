<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);

// Get orders with details
$orders_query = "
    SELECT 
        o.order_id,
        o.order_time,
        o.guest_count,
        o.status,
        o.total_amount,
        t.table_name,
        f.floor_name,
        COALESCE(c.customer_name, u.full_name, 'Khách vãng lai') as customer_name
    FROM orders o
    LEFT JOIN tables t ON t.table_id = o.table_id
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    LEFT JOIN users u ON u.user_id = o.waiter_id
    ORDER BY o.order_time DESC
    LIMIT 50
";
$orders_result = $conn->query($orders_query);
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);
?>

<div class="module-container">
    <div class="module-header">
        <div>
            <h2>Quản lý đơn hàng</h2>
        </div>
        <button class="btn btn-primary" onclick="createNewOrder()">
            Tạo đơn hàng mới
        </button>
    </div>

    <div class="orders-list" id="orders-list">
        <?php if (count($orders) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Bàn</th>
                        <th>Khách hàng</th>
                        <th>Số người</th>
                        <th>Thời gian</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusLabels = [
                        'moi'          => 'Mới',
                        'dang_xu_ly'   => 'Đang xử lý',
                        'dang_che_bien'=> 'Đang nấu',
                        'dang_phuc_vu' => 'Đang phục vụ',
                        'hoan_thanh'   => 'Hoàn thành món',
                        'da_thanh_toan'=> 'Đã thanh toán',
                        'da_huy'       => 'Đã hủy',
                        'da_dat_coc'   => 'Đã cọc',
                    ];
                    $statusStyles = [
                        'moi'          => 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
                        'dang_xu_ly'   => 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d;',
                        'dang_che_bien'=> 'background:#fed7aa; color:#9a3412; border:1px solid #fdba74;',
                        'dang_phuc_vu' => 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d;',
                        'hoan_thanh'   => 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
                        'da_thanh_toan'=> 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
                        'da_huy'       => 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
                        'da_dat_coc'   => 'background:#e0e7ff; color:#3730a3; border:1px solid #a5b4fc;',
                    ];
                    foreach ($orders as $order):
                        $style = $statusStyles[$order['status']] ?? 'background:#e5e7eb; color:#374151;';
                        $label = $statusLabels[$order['status']] ?? ucfirst($order['status']);
                    ?>
                        <tr>
                            <td style="font-weight:600; color:#3b82f6;">#<?= $order['order_id'] ?></td>
                            <td><?= e($order['table_name'] ?? 'N/A') ?> (<?= e($order['floor_name'] ?? '') ?>)</td>
                            <td><?= e($order['customer_name'] ?? 'Khách vãng lai') ?></td>
                            <td><?= $order['guest_count'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($order['order_time'])) ?></td>
                            <td><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</td>
                            <td>
                                <span style="display:inline-block; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; <?= $style ?>">
                                    <?= $label ?>
                                </span>
                            </td>
                            <td>
                                <button onclick="viewOrderDetail(<?= $order['order_id'] ?>)" style="padding:8px 16px; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#3b82f6,#2563eb); color:white;">Chi tiết</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>Chưa có đơn hàng nào</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Order Modal -->
<div id="create-order-modal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>Tạo đơn hàng mới</h3>
            <button class="modal-close" onclick="closeCreateOrderModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="co-form" onsubmit="event.preventDefault(); submitCreateOrder();">
                <input type="hidden" id="co-customer-id">

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;font-size:14px;margin-bottom:6px;">Bàn <span style="color:#ef4444;">*</span></label>
                    <select id="co-table-select" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        <option value="">-- Chọn bàn --</option>
                    </select>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;font-size:14px;margin-bottom:6px;">Số người <span style="color:#ef4444;">*</span></label>
                    <input type="number" id="co-guest-count" min="1" max="50" placeholder="Nhập số người..."
                           style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                    <small id="co-guest-hint" style="color:#6b7280;font-size:12px;margin-top:4px;display:block;"></small>
                </div>

                <div style="margin-bottom:16px;position:relative;">
                    <label style="display:block;font-weight:600;font-size:14px;margin-bottom:6px;">Khách hàng</label>
                    <input type="text" id="co-customer-search" placeholder="Tìm theo tên hoặc SĐT..."
                           style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;" autocomplete="off">
                    <div id="co-customer-results"
                         style="position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:100;max-height:180px;overflow-y:auto;"></div>
                    <small id="co-customer-display" style="color:#6b7280;font-size:12px;margin-top:4px;display:block;"></small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeCreateOrderModal()" class="btn" style="background:#e5e7eb;color:#374151;">Hủy</button>
            <button type="button" onclick="submitCreateOrder()" class="btn btn-primary">Tạo đơn</button>
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
<div id="order-detail-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Chi tiết đơn hàng</h3>
            <button class="modal-close" onclick="closeOrderDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="order-detail-content">
            <div class="loading">Đang tải...</div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeOrderDetailModal()" class="btn btn-primary">Đóng</button>
        </div>
    </div>
</div>

<style>
.module-container {
    padding: 24px;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e5e7eb;
}

.module-header h2 {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
}

.orders-list {
    margin-top: 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}

.table th {
    padding: 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}

.table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background: #f9fafb;
}

.table tbody tr:last-child {
    border-bottom: none;
}

.table td {
    padding: 16px;
    font-size: 14px;
    color: #1f2937;
    vertical-align: middle;
}

.table td:first-child {
    font-weight: 600;
    color: #3b82f6;
}

.badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.badge-new {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: #1e40af;
    border: 1px solid #93c5fd;
}

.badge-processing {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border: 1px solid #fcd34d;
}

.badge-serving {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.badge-completed {
    background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
    color: #1f2937;
    border: 1px solid #9ca3af;
}

.badge-cancelled {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.badge-paid {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.btn-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.2);
}

.btn-icon:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #9ca3af;
}

.empty-state p {
    font-size: 16px;
    margin: 0;
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
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 2px solid #e5e7eb;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}

.modal-header h3 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
}

.modal-close {
    background: none;
    border: none;
    font-size: 32px;
    cursor: pointer;
    color: #9ca3af;
    line-height: 1;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 2px solid #e5e7eb;
    background: #fafafa;
}

.detail-section {
    margin-bottom: 28px;
}

.detail-section:last-child {
    margin-bottom: 0;
}

.detail-section h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 16px;
    color: #111827;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 14px;
}

.detail-value {
    color: #1f2937;
    text-align: right;
    font-size: 14px;
    font-weight: 500;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}

.items-table th {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    padding: 12px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.items-table td {
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
    color: #1f2937;
}

.items-table tr:last-child td {
    border-bottom: none;
}

.items-table .text-right {
    text-align: right;
}

.items-table .text-center {
    text-align: center;
}

.loading {
    text-align: center;
    padding: 60px;
    color: #9ca3af;
    font-size: 15px;
}
</style>

<script>
(function() {
    console.log('=== ORDERS MODULE SCRIPT LOADING ===');

    const API_URL = '/quanlynhahang/manager/api/orders.php';

    window.createNewOrder = function() {
        loadAvailableTables();
        document.getElementById('create-order-modal').classList.add('active');
    };

    window.viewOrderDetail = function(orderId) {
        console.log('View order detail called:', orderId);
        const modal = document.getElementById('order-detail-modal');
        const content = document.getElementById('order-detail-content');
        
        // Show modal with loading state
        content.innerHTML = '<div class="loading">Đang tải...</div>';
        modal.classList.add('active');
        
        fetch(`${API_URL}?action=detail&order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showOrderDetail(data.data);
                } else {
                    content.innerHTML = `<div style="color: red;">Lỗi: ${data.message || 'Không thể tải chi tiết'}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<div style="color: red;">Lỗi kết nối</div>';
            });
    };

    window.closeOrderDetailModal = function() {
        console.log('Close order detail modal called');
        document.getElementById('order-detail-modal').classList.remove('active');
    };

    function showOrderDetail(data) {
        const { order, items } = data;
        const content = document.getElementById('order-detail-content');
        
        const statusLabels = {
            'moi':           'Mới',
            'dang_xu_ly':    'Đang xử lý',
            'dang_che_bien': 'Đang nấu',
            'dang_phuc_vu':  'Đang phục vụ',
            'hoan_thanh':    'Hoàn thành món',
            'da_thanh_toan': 'Đã thanh toán',
            'da_huy':        'Đã hủy',
            'da_dat_coc':    'Đã cọc'
        };
        
        const statusLabels2 = {
            'moi':           'Mới',
            'dang_xu_ly':    'Đang xử lý',
            'dang_che_bien': 'Đang nấu',
            'dang_phuc_vu':  'Đang phục vụ',
            'hoan_thanh':    'Hoàn thành món',
            'da_thanh_toan': 'Đã thanh toán',
            'da_huy':        'Đã hủy',
            'da_dat_coc':    'Đã cọc'
        };
        
        let html = `
            <div class="detail-section">
                <h4>Thông tin đơn hàng</h4>
                <div class="detail-row">
                    <span class="detail-label">Mã đơn:</span>
                    <span class="detail-value">#${order.order_id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bàn:</span>
                    <span class="detail-value">${order.table_name || 'N/A'} (${order.floor_name || ''})</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Khách hàng:</span>
                    <span class="detail-value">${order.customer_name || 'Khách vãng lai'}</span>
                </div>
                ${order.customer_phone ? `
                <div class="detail-row">
                    <span class="detail-label">Số điện thoại:</span>
                    <span class="detail-value">${order.customer_phone}</span>
                </div>
                ` : ''}
                <div class="detail-row">
                    <span class="detail-label">Số người:</span>
                    <span class="detail-value">${order.guest_count}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Thời gian:</span>
                    <span class="detail-value">${new Date(order.order_time).toLocaleString('vi-VN')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Trạng thái:</span>
                    <span class="detail-value">
                        <span class="badge badge-${order.status}">${statusLabels2[order.status] || order.status}</span>
                    </span>
                </div>
            </div>
        `;
        
        if (items && items.length > 0) {
            html += `
                <div class="detail-section">
                    <h4>Món ăn (${items.length})</h4>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Món</th>
                                <th class="text-center">SL</th>
                                <th class="text-right">Đơn giá</th>
                                <th class="text-right">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            items.forEach(item => {
                const subtotal = item.quantity * item.unit_price;
                html += `
                    <tr>
                        <td>${item.item_name}</td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-right">${new Intl.NumberFormat('vi-VN').format(item.unit_price)}đ</td>
                        <td class="text-right">${new Intl.NumberFormat('vi-VN').format(subtotal)}đ</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        html += `
            <div class="detail-section">
                <div class="detail-row" style="font-size: 16px; font-weight: 700;">
                    <span class="detail-label">Tổng cộng:</span>
                    <span class="detail-value" style="color: #e74c3c;">${new Intl.NumberFormat('vi-VN').format(order.total_amount)}đ</span>
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    }

    function initOrdersModule() {
        console.log('Orders module initialized');
    }

    initOrdersModule();
    console.log('=== ORDERS MODULE SCRIPT LOADED ===');

    // ── Tạo đơn hàng mới ────────────────────────────────────────────────────

    function loadAvailableTables() {
        fetch(`${API_URL}?action=get_tables`)
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('co-table-select');
                sel.innerHTML = '<option value="">-- Chọn bàn --</option>';
                (data.data || []).forEach(t => {
                    sel.innerHTML += `<option value="${t.table_id}" data-capacity="${t.capacity}">${t.floor_name} – ${t.table_name} (${t.capacity} chỗ)</option>`;
                });
            });
    }

    window.closeCreateOrderModal = function() {
        document.getElementById('create-order-modal').classList.remove('active');
        document.getElementById('co-form').reset();
        document.getElementById('co-customer-results').innerHTML = '';
        document.getElementById('co-customer-id').value = '';
        document.getElementById('co-customer-display').textContent = '';
    };

    // Tìm khách hàng
    let _coSearchTimer = null;
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('co-customer-search');
        if (!searchInput) return;
        searchInput.addEventListener('input', function() {
            clearTimeout(_coSearchTimer);
            const q = this.value.trim();
            if (q.length < 2) { document.getElementById('co-customer-results').innerHTML = ''; return; }
            _coSearchTimer = setTimeout(() => {
                fetch(`${API_URL}?action=get_customers&q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(data => {
                        const box = document.getElementById('co-customer-results');
                        if (!data.data || data.data.length === 0) {
                            box.innerHTML = '<div style="padding:10px;color:#6b7280;font-size:13px;">Không tìm thấy</div>';
                            return;
                        }
                        box.innerHTML = data.data.map(c => `
                            <div onclick="selectCustomer(${c.customer_id},'${c.customer_name}','${c.phone}')"
                                 style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:13px;"
                                 onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
                                <strong>${c.customer_name}</strong> — ${c.phone}
                            </div>`).join('');
                    });
            }, 300);
        });

        // Cập nhật max guests khi chọn bàn
        document.getElementById('co-table-select').addEventListener('change', function() {
            const cap = this.options[this.selectedIndex]?.getAttribute('data-capacity');
            const hint = document.getElementById('co-guest-hint');
            if (cap) {
                hint.textContent = `Tối đa ${cap} người`;
                document.getElementById('co-guest-count').max = cap;
            } else {
                hint.textContent = '';
            }
        });
    });

    window.selectCustomer = function(id, name, phone) {
        document.getElementById('co-customer-id').value = id;
        document.getElementById('co-customer-search').value = `${name} — ${phone}`;
        document.getElementById('co-customer-display').textContent = '';
        document.getElementById('co-customer-results').innerHTML = '';
    };

    window.submitCreateOrder = function() {
        const table_id    = document.getElementById('co-table-select').value;
        const customer_id = document.getElementById('co-customer-id').value || null;
        const guest_count = document.getElementById('co-guest-count').value;

        if (!table_id) { alert('Vui lòng chọn bàn'); return; }
        if (!guest_count || guest_count < 1) { alert('Vui lòng nhập số người'); return; }

        fetch(`${API_URL}?action=create`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ table_id: parseInt(table_id), customer_id, guest_count: parseInt(guest_count) })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeCreateOrderModal();
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Lỗi tạo đơn');
            }
        });
    };
})();
</script>
