<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

// Xóa khách hàng
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Kiểm tra còn đơn hàng chưa hoàn thành không
    $chk = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE customer_id=$id AND status NOT IN ('da_thanh_toan','da_huy')");
    $active = $chk ? (int)$chk->fetch_assoc()['cnt'] : 0;
    
    if ($active > 0) {
        $deleteError = 'Không thể xóa: khách hàng còn đơn hàng đang xử lý.';
    } else {
        // Kiểm tra còn đặt bàn chưa hoàn thành không
        $chkRes = $conn->query("SELECT COUNT(*) AS cnt FROM reservations r 
                                INNER JOIN customers c ON c.user_id = r.user_id 
                                WHERE c.customer_id=$id 
                                AND r.status NOT IN ('hoan_thanh','da_huy','khong_den')");
        $activeRes = $chkRes ? (int)$chkRes->fetch_assoc()['cnt'] : 0;
        
        if ($activeRes > 0) {
            $deleteError = 'Không thể xóa: khách hàng còn đặt bàn đang xử lý.';
        } else {
            // Lấy user_id liên kết để xóa tài khoản (nếu có)
            $userRow = $conn->query("SELECT user_id FROM customers WHERE customer_id=$id LIMIT 1");
            $userData = $userRow ? $userRow->fetch_assoc() : null;
            
            // Bắt đầu transaction
            $conn->begin_transaction();
            try {
                // Xóa khách hàng
                $result = $conn->query("DELETE FROM customers WHERE customer_id=$id");
                
                if (!$result) {
                    throw new Exception($conn->error);
                }
                
                if ($conn->affected_rows === 0) {
                    throw new Exception('Không tìm thấy khách hàng hoặc đã bị xóa');
                }
                
                // Xóa tài khoản user liên kết (nếu có)
                if (!empty($userData['user_id'])) {
                    $conn->query("DELETE FROM users WHERE user_id={$userData['user_id']}");
                }
                
                // Commit transaction
                $conn->commit();
                
                $keyword = $_GET['keyword'] ?? '';
                header('Location: admin.php?page=customers&keyword=' . urlencode($keyword) . '&msg=deleted'); 
                exit;
                
            } catch (Exception $e) {
                // Rollback nếu có lỗi
                $conn->rollback();
                $deleteError = 'Không thể xóa khách hàng: ' . $e->getMessage();
            }
        }
    }
}

$keyword = trim($_GET['keyword'] ?? '');
$where = '1';
if ($keyword) {
    $kw = $conn->real_escape_string("%$keyword%");
    $where .= " AND (c.customer_name LIKE '$kw' OR c.phone LIKE '$kw' OR c.email LIKE '$kw')";
}

$customers = $conn->query("
    SELECT c.*,
           COUNT(DISTINCT o.order_id) AS total_orders,
           COALESCE(
               (SELECT COUNT(DISTINCT r.reservation_id)
                FROM reservations r
                WHERE r.user_id = c.user_id), 0
           ) as total_reservations,
           COALESCE(
               (SELECT SUM(p.amount_paid)
                FROM payments p
                JOIN orders o2 ON o2.order_id = p.order_id
                WHERE o2.customer_id = c.customer_id
                  AND p.payment_status = 'thanh_cong'), 0
           ) AS total_spent
    FROM   customers c
    LEFT JOIN orders o ON o.customer_id = c.customer_id AND o.status NOT IN ('da_huy')
    WHERE  $where
    GROUP  BY c.customer_id
    ORDER  BY c.created_at DESC
");

// Lấy đơn hàng khách vãng lai (customer_id IS NULL, không tính đơn hủy)
$walkInOrders = $conn->query("
    SELECT o.order_id, o.order_time, o.total_amount, o.status, o.guest_count,
           t.table_name
    FROM orders o
    LEFT JOIN tables t ON t.table_id = o.table_id
    WHERE o.customer_id IS NULL AND o.status NOT IN ('da_huy')
    ORDER BY o.order_id DESC
    LIMIT 50
");
$walkInList = $walkInOrders ? $walkInOrders->fetch_all(MYSQLI_ASSOC) : [];
$walkInTotal = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE customer_id IS NULL AND status NOT IN ('da_huy')")->fetch_assoc()['cnt'];

$pageTitle = 'Khách hàng';
$activeMenu = 'customers'; $sidebarRole = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<?php if (!empty($deleteError)): ?>
    <div class="alert alert-error"><?= e($deleteError) ?></div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success">Đã xóa khách hàng.</div>
<?php endif; ?>

<form method="GET" class="filters" style="grid-template-columns:1fr auto;">
    <input type="hidden" name="page" value="customers">
    <input class="input" type="text" name="keyword" placeholder="Tìm tên, SĐT, email..." value="<?= e($keyword) ?>">
    <button class="btn btn-secondary" type="submit">Tìm</button>
</form>

<div class="card panel" style="margin-bottom:20px;">
    <div style="padding:14px 18px; border-bottom:1px solid #eee; background:#fafafa; display:flex; align-items:center; justify-content:space-between;">
        <h3 style="margin:0; font-size:15px; font-weight:700; color:#333;">👤 Khách hàng đã đăng ký</h3>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tên khách</th><th>SĐT</th><th>Email</th><th>Đơn hàng</th><th>Đặt bàn</th><th>Tổng chi tiêu</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php if ($customers && $customers->num_rows > 0): ?>
                <?php while ($r = $customers->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:500;"><?= e($r['customer_name']) ?></td>
                    <td><?= e($r['phone'] ?? '–') ?></td>
                    <td><?= e($r['email'] ?? '–') ?></td>
                    <td><span class="badge badge-active"><?= $r['total_orders'] ?></span></td>
                    <td><span class="badge badge-info"><?= $r['total_reservations'] ?></span></td>
                    <td style="font-weight:700; color:var(--primary);"><?= $r['total_spent'] > 0 ? format_currency($r['total_spent']) : '–' ?></td>
                    <td style="font-size:13px; color:#6b7280;"><?= e($r['created_at']) ?></td>
                    <td>
                        <a href="admin.php?page=customers&delete=<?= $r['customer_id'] ?><?= $keyword ? '&keyword=' . urlencode($keyword) : '' ?>"
                           class="btn btn-danger"
                           style="padding:6px 12px; font-size:13px;"
                           onclick="return confirm('⚠️ XÁC NHẬN XÓA KHÁCH HÀNG\n\nBạn có chắc muốn xóa khách hàng:\n<?= e(addslashes($r['customer_name'])) ?>?\n\nHành động này KHÔNG THỂ HOÀN TÁC!')">
                            Xóa
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8"><div class="empty-state">Không tìm thấy khách hàng nào.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Đơn hàng khách vãng lai -->
<div class="card panel">
    <div style="padding:14px 18px; border-bottom:1px solid #ffe0b2; background:#fff8f0; display:flex; align-items:center; justify-content:space-between;">
        <h3 style="margin:0; font-size:15px; font-weight:700; color:#e65100;">🚶 Đơn hàng khách vãng lai</h3>
        <span style="background:#ff9800; color:white; padding:3px 12px; border-radius:20px; font-size:13px; font-weight:700;"><?= $walkInTotal ?> đơn</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mã đơn</th><th>Thời gian</th><th>Bàn</th><th>Số khách</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php if (empty($walkInList)): ?>
                <tr><td colspan="6"><div class="empty-state">Không có đơn khách vãng lai</div></td></tr>
            <?php else: ?>
                <?php
                $stLabels = [
                    'moi'=>['Mới','#2196f3'], 'dang_xu_ly'=>['Đang xử lý','#ff9800'],
                    'dang_che_bien'=>['Đang chế biến','#9c27b0'], 'dang_phuc_vu'=>['Đang phục vụ','#00bcd4'],
                    'hoan_thanh'=>['Hoàn thành','#4caf50'], 'da_thanh_toan'=>['Đã thanh toán','#4caf50'],
                    'da_huy'=>['Đã hủy','#f44336'],
                ];
                foreach ($walkInList as $wi):
                    $st = $stLabels[$wi['status']] ?? [$wi['status'], '#999'];
                ?>
                <tr>
                    <td style="font-weight:600;">#<?= $wi['order_id'] ?></td>
                    <td style="font-size:13px; color:#6b7280;"><?= date('d/m/Y H:i', strtotime($wi['order_time'])) ?></td>
                    <td><?= e($wi['table_name'] ?? '–') ?></td>
                    <td style="text-align:center;"><?= $wi['guest_count'] ?></td>
                    <td style="font-weight:700; color:#e65100;"><?= format_currency($wi['total_amount']) ?></td>
                    <td><span style="background:<?= $st[1] ?>; color:white; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600;"><?= $st[0] ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if ($walkInTotal > 50): ?>
            <div style="padding:10px 18px; text-align:center; color:#999; font-size:13px; border-top:1px solid #eee;">
                Hiển thị 50 đơn gần nhất / tổng <?= $walkInTotal ?> đơn
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>


