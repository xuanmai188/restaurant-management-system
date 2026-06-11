<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

$message = '';

if ($_POST['action'] ?? '' === 'update_status') {
    // CHỈ cập nhật đơn hàng: 'hoan_thanh' -> 'da_thanh_toan'
    $updateOrders = "UPDATE orders SET status = 'da_thanh_toan' WHERE status = 'hoan_thanh'";
    $result1 = $conn->query($updateOrders);
    
    // RESERVATIONS giữ nguyên luồng trạng thái
    
    if ($result1) {
        $affectedRows = $conn->affected_rows;
        $message = "Đã cập nhật $affectedRows đơn hàng từ 'hoàn thành' → 'đã thanh toán'<br>";
        $message .= "Đặt bàn giữ nguyên luồng trạng thái hiện tại";
    } else {
        $message = "Lỗi: " . $conn->error;
    }
}

// Kiểm tra trạng thái hiện tại của đơn hàng
$ordersQuery = "SELECT status, COUNT(*) as count FROM orders WHERE DATE(order_time) = '2026-04-24' GROUP BY status";
$ordersResult = $conn->query($ordersQuery);

// Kiểm tra trạng thái hiện tại của đặt bàn
$reservationsQuery = "SELECT status, COUNT(*) as count FROM reservations WHERE DATE(reservation_time) = '2026-04-24' GROUP BY status";
$reservationsResult = $conn->query($reservationsQuery);

$pageTitle = 'Sửa trạng thái đơn hàng & đặt bàn';
$activeMenu = 'admin';
$sidebarRole = 'admin';
include __DIR__ . '/../includes/layout.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<div class="card" style="padding: 24px; margin-bottom: 24px;">
    <h3>Trạng thái đơn hàng ngày 24/04/2026</h3>
    
    <?php if ($ordersResult && $ordersResult->num_rows > 0): ?>
        <table class="table" style="margin: 16px 0;">
            <thead>
                <tr><th>Trạng thái</th><th>Số lượng</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $ordersResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($row['status']) ?></td>
                        <td><?= e($row['count']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card" style="padding: 24px; margin-bottom: 24px;">
    <h3>Trạng thái đặt bàn ngày 24/04/2026</h3>
    
    <?php if ($reservationsResult && $reservationsResult->num_rows > 0): ?>
        <table class="table" style="margin: 16px 0;">
            <thead>
                <tr><th>Trạng thái</th><th>Số lượng</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $reservationsResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($row['status']) ?></td>
                        <td><?= e($row['count']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <form method="POST" style="margin-top: 20px;">
        <input type="hidden" name="action" value="update_status">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Bạn có chắc muốn cập nhật đơn hàng từ hoàn thành thành đã thanh toán?')">
            Cập nhật đơn hàng: 'hoàn thành' → 'đã thanh toán'
        </button>
    </form>
</div>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>