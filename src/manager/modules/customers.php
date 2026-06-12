<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);

// Lấy danh sách khách hàng
$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
$types = '';

if ($search) {
    $where_clause = "WHERE c.customer_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

$query = "
    SELECT 
        c.customer_id,
        c.customer_name,
        c.phone,
        c.email,
        c.created_at,
        u.username,
        COUNT(DISTINCT o.order_id) as total_orders,
        COALESCE(
            (SELECT COUNT(DISTINCT r2.reservation_id)
             FROM reservations r2
             WHERE r2.user_id = c.user_id), 0
        ) as total_reservations,
        COALESCE(
            (SELECT SUM(p2.amount_paid)
             FROM payments p2
             JOIN orders o2 ON o2.order_id = p2.order_id
             WHERE o2.customer_id = c.customer_id
               AND p2.payment_status = 'thanh_cong'), 0
        ) +
        COALESCE(
            (SELECT SUM(rp.amount)
             FROM reservation_payments rp
             JOIN reservations r3 ON r3.reservation_id = rp.reservation_id
             WHERE r3.user_id = c.user_id
               AND rp.payment_status IN ('thanh_cong', 'cho_xu_ly')), 0
        ) as total_spent
    FROM customers c
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN orders o ON o.customer_id = c.customer_id AND o.status NOT IN ('da_huy')
    $where_clause
    GROUP BY c.customer_id
    ORDER BY c.customer_id ASC
";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Lấy đơn hàng khách vãng lai (customer_id IS NULL)
$walk_in_where = '';
if ($search) {
    $kw = $conn->real_escape_string("%$search%");
    $walk_in_where = "AND (t.table_name LIKE '$kw')";
}
$walkInOrders = $conn->query("
    SELECT o.order_id, o.order_time, o.total_amount, o.status, o.guest_count,
           t.table_name
    FROM orders o
    LEFT JOIN tables t ON t.table_id = o.table_id
    WHERE o.customer_id IS NULL AND o.status != 'da_huy'
    $walk_in_where
    ORDER BY o.order_id DESC
    LIMIT 50
");
$walkInList = $walkInOrders ? $walkInOrders->fetch_all(MYSQLI_ASSOC) : [];
$walkInTotal = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE customer_id IS NULL AND status != 'da_huy'")->fetch_assoc()['cnt'];
?>

<div class="module-container">
    <div class="module-header">
        <h2>Quản lý khách hàng</h2>
    </div>

    <!-- Thanh tìm kiếm -->
    <div class="search-section" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; margin-top: 20px;">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="hidden" name="module" value="customers">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Tìm theo tên, số điện thoại, email..." 
                   style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" style="padding: 10px 20px; background: #d32f2f; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Tìm kiếm
            </button>
            <?php if ($search): ?>
                <a href="?module=customers" style="padding: 10px 20px; background: #666; color: white; text-decoration: none; border-radius: 4px;">
                    Xóa lọc
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Danh sách khách hàng -->
    <div class="customers-list" style="background: white; border-radius: 8px; overflow: hidden; margin-bottom: 30px;">
        <div style="padding: 16px 20px; border-bottom: 2px solid #eee; background: #fafafa;">
            <h3 style="margin:0; font-size:16px; font-weight:700; color:#333;">👤 Khách hàng đã đăng ký</h3>
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #ddd;">ID</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #ddd;">Tên khách hàng</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #ddd;">Số điện thoại</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #ddd;">Email</th>
                    <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Tài khoản</th>
                    <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Đơn hàng</th>
                    <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Đặt bàn</th>
                    <th style="padding: 15px; text-align: right; border-bottom: 2px solid #ddd;">Tổng chi tiêu</th>
                    <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="9" style="padding: 40px; text-align: center; color: #999;">
                            <?= $search ? 'Không tìm thấy khách hàng nào' : 'Chưa có khách hàng nào' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 15px;"><?= $customer['customer_id'] ?></td>
                            <td style="padding: 15px; font-weight: 500;"><?= htmlspecialchars($customer['customer_name']) ?></td>
                            <td style="padding: 15px;"><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                            <td style="padding: 15px;"><?= htmlspecialchars($customer['email'] ?? '-') ?></td>
                            <td style="padding: 15px; text-align: center;">
                                <?php if ($customer['username']): ?>
                                    <span style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                        <?= htmlspecialchars($customer['username']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: center;"><?= number_format($customer['total_orders']) ?></td>
                            <td style="padding: 15px; text-align: center;"><?= number_format($customer['total_reservations']) ?></td>
                            <td style="padding: 15px; text-align: right; font-weight: bold; color: #4caf50;">
                                <?= number_format($customer['total_spent']) ?>đ
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <button onclick="viewCustomerDetail(<?= $customer['customer_id'] ?>)" 
                                        style="padding: 6px 12px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                                    Chi tiết
                                </button>
                                <button onclick="deleteCustomer(<?= $customer['customer_id'] ?>, '<?= htmlspecialchars($customer['customer_name']) ?>')" 
                                        style="padding: 6px 12px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Xóa
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Đơn hàng khách vãng lai -->
    <div style="background: white; border-radius: 8px; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 2px solid #eee; background: #fff8f0; display:flex; align-items:center; justify-content:space-between;">
            <h3 style="margin:0; font-size:16px; font-weight:700; color:#e65100;">🚶 Khách vãng lai</h3>
            <span style="background:#ff9800; color:white; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:700;"><?= $walkInTotal ?> đơn</span>
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #fff3e0;">
                    <th style="padding: 12px 15px; text-align: left; border-bottom: 2px solid #ffe0b2;">Mã đơn</th>
                    <th style="padding: 12px 15px; text-align: left; border-bottom: 2px solid #ffe0b2;">Thời gian</th>
                    <th style="padding: 12px 15px; text-align: left; border-bottom: 2px solid #ffe0b2;">Bàn</th>
                    <th style="padding: 12px 15px; text-align: center; border-bottom: 2px solid #ffe0b2;">Số khách</th>
                    <th style="padding: 12px 15px; text-align: right; border-bottom: 2px solid #ffe0b2;">Tổng tiền</th>
                    <th style="padding: 12px 15px; text-align: center; border-bottom: 2px solid #ffe0b2;">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($walkInList)): ?>
                    <tr><td colspan="6" style="padding: 30px; text-align: center; color: #999;">Không có đơn khách vãng lai</td></tr>
                <?php else: ?>
                    <?php
                    $statusLabels = [
                        'moi' => ['label'=>'Mới', 'color'=>'#2196f3'],
                        'dang_xu_ly' => ['label'=>'Đang xử lý', 'color'=>'#ff9800'],
                        'dang_che_bien' => ['label'=>'Đang chế biến', 'color'=>'#9c27b0'],
                        'dang_phuc_vu' => ['label'=>'Đang phục vụ', 'color'=>'#00bcd4'],
                        'hoan_thanh' => ['label'=>'Hoàn thành', 'color'=>'#4caf50'],
                        'da_thanh_toan' => ['label'=>'Đã thanh toán', 'color'=>'#4caf50'],
                        'da_huy' => ['label'=>'Đã hủy', 'color'=>'#f44336'],
                    ];
                    foreach ($walkInList as $wi):
                        $st = $statusLabels[$wi['status']] ?? ['label'=>$wi['status'], 'color'=>'#999'];
                    ?>
                    <tr style="border-bottom: 1px solid #fff3e0;">
                        <td style="padding: 12px 15px; font-weight:600;">#<?= $wi['order_id'] ?></td>
                        <td style="padding: 12px 15px; color:#666; font-size:13px;"><?= date('d/m/Y H:i', strtotime($wi['order_time'])) ?></td>
                        <td style="padding: 12px 15px;"><?= htmlspecialchars($wi['table_name'] ?? '-') ?></td>
                        <td style="padding: 12px 15px; text-align:center;"><?= $wi['guest_count'] ?></td>
                        <td style="padding: 12px 15px; text-align:right; font-weight:700; color:#e65100;"><?= number_format($wi['total_amount']) ?>đ</td>
                        <td style="padding: 12px 15px; text-align:center;">
                            <span style="background:<?= $st['color'] ?>; color:white; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">
                                <?= $st['label'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($walkInTotal > 50): ?>
            <div style="padding:12px 20px; text-align:center; color:#999; font-size:13px; border-top:1px solid #eee;">
                Hiển thị 50 đơn gần nhất / tổng <?= $walkInTotal ?> đơn
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal chi tiết khách hàng -->
<div id="customerDetailModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 1000px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Chi tiết khách hàng</h2>
            <button onclick="closeCustomerDetail()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div id="customerDetailContent"></div>
    </div>
</div>

<!-- Modal chỉnh sửa khách hàng -->
<div id="editCustomerModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="max-width: 500px; margin: 100px auto; background: white; border-radius: 8px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Chỉnh sửa thông tin khách hàng</h2>
            <button onclick="closeEditCustomer()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div id="editCustomerContent"></div>
    </div>
</div>

<script>
function initCustomersModule() {
    console.log('Module Khách hàng đã tải');
}

function viewCustomerDetail(customerId) {
    const modal = document.getElementById('customerDetailModal');
    modal.style.display = 'block';
    modal.dataset.customerId = customerId; // Lưu customer ID để reload sau
    document.getElementById('customerDetailContent').innerHTML = '<div style="text-align: center; padding: 40px;">Đang tải...</div>';
    
    fetch(`/quanlynhahang/manager/api/customers.php?action=detail&id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCustomerDetail(data.data);
            } else {
                showToast(data.message || 'Lỗi khi tải thông tin', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Lỗi kết nối', 'error');
        });
}

function displayCustomerDetail(data) {
    const { customer, orders, reservations } = data;
    
    let html = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div>
                <h3 style="margin-bottom: 15px;">Thông tin cơ bản</h3>
                <p><strong>Tên:</strong> ${customer.customer_name}</p>
                <p><strong>Số điện thoại:</strong> ${customer.phone || '-'}</p>
                <p><strong>Email:</strong> ${customer.email || '-'}</p>
                <p><strong>Tài khoản:</strong> ${customer.username || 'Chưa có'}</p>
                <p><strong>Ngày tạo:</strong> ${customer.created_at}</p>
            </div>
            <div>
                <h3 style="margin-bottom: 15px;">Thống kê</h3>
                <p><strong>Tổng đơn hàng:</strong> ${customer.total_orders}</p>
                <p><strong>Tổng đặt bàn:</strong> ${customer.total_reservations}</p>
                <p><strong>Tổng chi tiêu:</strong> <span style="color: #4caf50; font-weight: bold;">${parseInt(customer.total_spent).toLocaleString()}đ</span></p>
            </div>
        </div>
        
        <h3 style="margin-bottom: 15px;">Lịch sử đơn hàng (${orders.length})</h3>
        <div style="max-height: 300px; overflow-y: auto; margin-bottom: 30px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="position: sticky; top: 0; background: #f5f5f5;">
                    <tr>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Mã đơn</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Thời gian</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Bàn</th>
                        <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Tổng tiền</th>
                        <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Trạng thái</th>
                        <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (orders.length === 0) {
        html += '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #999;">Chưa có đơn hàng nào</td></tr>';
    } else {
        orders.forEach(order => {
            const statusColors = {
                'new': '#2196f3',
                'processing': '#ff9800',
                'serving': '#9c27b0',
                'completed': '#4caf50',
                'paid': '#4caf50',
                'cancelled': '#f44336'
            };
            const statusLabels = {
                'new': 'Mới',
                'processing': 'Đang xử lý',
                'serving': 'Đang phục vụ',
                'completed': 'Hoàn thành',
                'paid': 'Đã thanh toán',
                'cancelled': 'Đã hủy'
            };
            html += `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;">#${order.order_id}</td>
                    <td style="padding: 10px;">${order.order_time}</td>
                    <td style="padding: 10px;">${order.table_name}</td>
                    <td style="padding: 10px; text-align: right; font-weight: bold;">${parseInt(order.total_amount).toLocaleString()}đ</td>
                    <td style="padding: 10px; text-align: center;">
                        <span style="background: ${statusColors[order.status] || '#999'}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            ${statusLabels[order.status] || order.status}
                        </span>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <select onchange="changeOrderStatus(${order.order_id}, this.value)" 
                                style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                            <option value="">Đổi trạng thái</option>
                            <option value="new">Mới</option>
                            <option value="processing">Đang xử lý</option>
                            <option value="serving">Đang phục vụ</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="paid">Đã thanh toán</option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                    </td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
        
        <h3 style="margin-bottom: 15px;">Lịch sử đặt bàn (${reservations.length})</h3>
        <div style="max-height: 300px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="position: sticky; top: 0; background: #f5f5f5;">
                    <tr>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Mã đặt bàn</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Thời gian đặt</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Bàn</th>
                        <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Số người</th>
                        <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Trạng thái</th>
                        <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (reservations.length === 0) {
        html += '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #999;">Chưa có đặt bàn nào</td></tr>';
    } else {
        reservations.forEach(res => {
            const statusColors = {
                'cho_xac_nhan': '#ff9800',
                'da_xac_nhan':  '#2196f3',
                'hoan_thanh':   '#4caf50',
                'da_huy':       '#f44336'
            };
            const statusLabels = {
                'cho_xac_nhan': 'Chờ xác nhận',
                'da_xac_nhan':  'Đã xác nhận',
                'hoan_thanh':   'Hoàn thành',
                'da_huy':       'Đã hủy'
            };
            html += `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;">#${res.reservation_id}</td>
                    <td style="padding: 10px;">${res.reservation_time}</td>
                    <td style="padding: 10px;">${res.table_name}</td>
                    <td style="padding: 10px; text-align: center;">${res.number_of_people}</td>
                    <td style="padding: 10px; text-align: center;">
                        <span style="background: ${statusColors[res.status] || '#999'}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            ${statusLabels[res.status] || res.status}
                        </span>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <select onchange="changeReservationStatus(${res.reservation_id}, this.value)" 
                                style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                            <option value="">Đổi trạng thái</option>
                            <option value="cho_xac_nhan">Chờ xác nhận</option>
                            <option value="da_xac_nhan">Đã xác nhận</option>
                            <option value="hoan_thanh">Hoàn thành</option>
                            <option value="da_huy">Đã hủy</option>
                        </select>
                    </td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('customerDetailContent').innerHTML = html;
}

function closeCustomerDetail() {
    document.getElementById('customerDetailModal').style.display = 'none';
}

function deleteCustomer(customerId, customerName) {
    if (!confirm(`Bạn có chắc muốn xóa khách hàng "${customerName}"?\n\nHành động này không thể hoàn tác.`)) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', customerId);

    fetch('/quanlynhahang/manager/api/customers.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Đã xóa khách hàng thành công', 'success');
            location.reload();
        } else {
            showToast(data.message || 'Lỗi khi xóa', 'error');
        }
    })
    .catch(() => showToast('Lỗi kết nối', 'error'));
}

function displayEditForm(customer) {
    const html = `
        <form id="editCustomerForm" onsubmit="saveCustomer(event, ${customer.customer_id})">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Tên khách hàng *</label>
                <input type="text" name="customer_name" value="${customer.customer_name}" required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Số điện thoại</label>
                <input type="text" name="phone" value="${customer.phone || ''}"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                <input type="email" name="email" value="${customer.email || ''}"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEditCustomer()" 
                        style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Hủy
                </button>
                <button type="submit" 
                        style="padding: 10px 20px; background: #d32f2f; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Lưu thay đổi
                </button>
            </div>
        </form>
    `;
    document.getElementById('editCustomerContent').innerHTML = html;
}

function saveCustomer(event, customerId) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', 'update');
    formData.append('id', customerId);
    
    fetch('/quanlynhahang/manager/api/customers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Cập nhật thành công', 'success');
            closeEditCustomer();
            location.reload();
        } else {
            showToast(data.message || 'Lỗi khi cập nhật', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Lỗi kết nối', 'error');
    });
}

function closeEditCustomer() {
    document.getElementById('editCustomerModal').style.display = 'none';
}

function changeOrderStatus(orderId, newStatus) {
    if (!newStatus) return;
    
    if (!confirm(`Bạn có chắc muốn đổi trạng thái đơn hàng #${orderId}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_order_status');
    formData.append('order_id', orderId);
    formData.append('status', newStatus);
    
    fetch('/quanlynhahang/manager/api/customers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Cập nhật trạng thái thành công', 'success');
            // Reload chi tiết khách hàng
            const customerId = document.querySelector('#customerDetailModal').dataset.customerId;
            if (customerId) {
                viewCustomerDetail(customerId);
            }
        } else {
            showToast(data.message || 'Lỗi khi cập nhật', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Lỗi kết nối', 'error');
    });
}

function changeReservationStatus(reservationId, newStatus) {
    if (!newStatus) return;
    
    if (!confirm(`Bạn có chắc muốn đổi trạng thái đặt bàn #${reservationId}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_reservation_status');
    formData.append('reservation_id', reservationId);
    formData.append('status', newStatus);
    
    fetch('/quanlynhahang/manager/api/customers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Cập nhật trạng thái thành công', 'success');
            // Reload chi tiết khách hàng
            const customerId = document.querySelector('#customerDetailModal').dataset.customerId;
            if (customerId) {
                viewCustomerDetail(customerId);
            }
        } else {
            showToast(data.message || 'Lỗi khi cập nhật', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Lỗi kết nối', 'error');
    });
}
</script>
