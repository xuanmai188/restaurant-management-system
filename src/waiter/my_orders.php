<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['phucvu', 'admin', 'quanly']);

$msg      = '';
$msgType  = 'alert-success';
$waiter_id = $_SESSION['user']['user_id'];
$date      = $_GET['date'] ?? date('Y-m-d');
$view      = $_GET['view'] ?? 'orders';

// Sync trạng thái reservation theo thời gian thực
syncReservationStatus();

// ── Tạo đặt bàn trước ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_reservation'])) {
    $customer_name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone']);
    $table_id = (int)$_POST['table_id'];
    $reservation_time = trim($_POST['reservation_time']);
    $number_of_people = (int)$_POST['number_of_people'];
    $note = trim($_POST['note']);
    
    if (!$customer_name || !$phone || !$table_id || !$reservation_time || !$number_of_people) {
        $msg = 'Vui lòng nhập đầy đủ thông tin';
        $msgType = 'alert-error';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $msg = 'Số điện thoại phải có đúng 10 chữ số';
        $msgType = 'alert-error';
    } else {
        $reservationDateTime = str_replace('T', ' ', $reservation_time) . ':00';
        
        $duration = reservation_duration_minutes($number_of_people);
        $newStart = $reservationDateTime;
        $newEnd   = reservation_compute_end_time($reservationDateTime, $number_of_people);

        $conflictRow = reservation_find_time_conflict($conn, $table_id, $newStart, $newEnd);

        if ($conflictRow) {
            $msg = 'Bàn này đã có người đặt trong khung giờ này (dựa trên thời lượng ăn dự kiến)';
            $msgType = 'alert-error';
        } else {
            $conn->begin_transaction();
            try {
                // Tạo/cập nhật customer
                $customer_id = null;
                $stmt_check = $conn->prepare("SELECT customer_id FROM customers WHERE phone=? LIMIT 1");
                $stmt_check->bind_param('s', $phone);
                $stmt_check->execute();
                $existingCustomer = $stmt_check->get_result();
                $stmt_check->close();
                
                if ($existingCustomer && $existingCustomer->num_rows > 0) {
                    $customer_id = $existingCustomer->fetch_assoc()['customer_id'];
                    $stmt_upd = $conn->prepare("UPDATE customers SET customer_name=? WHERE customer_id=?");
                    $stmt_upd->bind_param('si', $customer_name, $customer_id);
                    if (!$stmt_upd->execute()) throw new Exception('Lỗi cập nhật khách hàng: ' . $stmt_upd->error);
                    $stmt_upd->close();
                } else {
                    $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, created_by) VALUES (?, ?, ?)");
                    $stmt->bind_param('ssi', $customer_name, $phone, $waiter_id);
                    if (!$stmt->execute()) throw new Exception('Lỗi tạo khách hàng: ' . $stmt->error);
                    $customer_id = $conn->insert_id;
                    $stmt->close();
                }
                
                // Tạo reservation — lưu end_time để auto-complete hoạt động đúng
                $endDateTime = reservation_compute_end_time($reservationDateTime, $number_of_people);
                $stmt = $conn->prepare("INSERT INTO reservations (user_id, table_id, reservation_time, start_time, end_time, number_of_people, note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'da_xac_nhan', NOW())");
                $stmt->bind_param('iisssis', $waiter_id, $table_id, $reservationDateTime, $reservationDateTime, $endDateTime, $number_of_people, $note);
                if (!$stmt->execute()) throw new Exception('Lỗi tạo đặt bàn: ' . $stmt->error);
                $res_id = $conn->insert_id;
                $stmt->close();

                // Xử lý món đặt trước
                $menuItems = $_POST['menu_items'] ?? [];
                $totalAmount = 0;

                // Load giá tất cả món 1 lần (tránh N+1 query)
                $item_ids = array_keys(array_filter($menuItems, fn($q) => (int)$q > 0));
                $priceMap = [];
                if (!empty($item_ids)) {
                    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                    $stmt_prices = $conn->prepare("SELECT item_id, price FROM menu_items WHERE item_id IN ($placeholders) AND status='con_hang'");
                    $types = str_repeat('i', count($item_ids));
                    $stmt_prices->bind_param($types, ...$item_ids);
                    $stmt_prices->execute();
                    $res_prices = $stmt_prices->get_result();
                    while ($row = $res_prices->fetch_assoc()) {
                        $priceMap[$row['item_id']] = (float)$row['price'];
                    }
                    $stmt_prices->close();
                }

                foreach ($menuItems as $item_id => $qty) {
                    $qty = (int)$qty;
                    $item_id = (int)$item_id;
                    if ($qty <= 0 || !isset($priceMap[$item_id])) continue;
                    $price = $priceMap[$item_id];
                    $totalAmount += $qty * $price;
                    $s = $conn->prepare("INSERT INTO reservation_items (reservation_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    $s->bind_param('iiid', $res_id, $item_id, $qty, $price);
                    if (!$s->execute()) throw new Exception('Lỗi thêm món: ' . $s->error);
                    $s->close();
                }

                // Xử lý cọc
                $depositPct = (int)($_POST['deposit_percent'] ?? 0);
                $depositMethod = $_POST['deposit_method'] ?? 'cash';
                if ($depositPct > 0 && $totalAmount > 0) {
                    $depositAmount = round($totalAmount * $depositPct / 100, 2);

                    // Tạo order da_dat_coc
                    $os = $conn->prepare("INSERT INTO orders (table_id, customer_id, reservation_id, waiter_id, status, total_amount, paid_amount, guest_count) VALUES (?, ?, ?, ?, 'da_dat_coc', ?, 0, ?)");
                    $os->bind_param('iiiidi', $table_id, $customer_id, $res_id, $waiter_id, $totalAmount, $number_of_people);
                    if (!$os->execute()) throw new Exception('Lỗi tạo đơn cọc: ' . $os->error);
                    $order_id = $conn->insert_id;
                    $os->close();

                    // Thêm order_details (dùng priceMap đã load)
                    foreach ($menuItems as $item_id => $qty) {
                        $qty = (int)$qty;
                        $item_id = (int)$item_id;
                        if ($qty <= 0 || !isset($priceMap[$item_id])) continue;
                        $price = $priceMap[$item_id];
                        $od = $conn->prepare("INSERT INTO order_details (order_id, item_id, quantity, unit_price, item_status) VALUES (?, ?, ?, ?, 'moi')");
                        $od->bind_param('iiid', $order_id, $item_id, $qty, $price);
                        if (!$od->execute()) throw new Exception('Lỗi thêm chi tiết đơn: ' . $od->error);
                        $od->close();
                    }

                    // Ghi reservation_payment
                    $rp = $conn->prepare("INSERT INTO reservation_payments (reservation_id, cashier_id, payment_type, payment_percent, amount, payment_method, payment_time, payment_status) VALUES (?, ?, 'deposit', ?, ?, ?, NOW(), 'thanh_cong')");
                    $rp->bind_param('iiids', $res_id, $waiter_id, $depositPct, $depositAmount, $depositMethod);
                    if (!$rp->execute()) throw new Exception('Lỗi ghi thanh toán cọc: ' . $rp->error);
                    $rp->close();

                    $conn->query("UPDATE tables SET status='da_dat' WHERE table_id=$table_id");
                }

                $conn->commit();
                sync_table_status();
                $msg = 'Đặt bàn thành công!' . ($depositPct > 0 ? ' Đã ghi nhận cọc.' : '');
                header("Location: my_orders.php?view=reservations&date=$date&msg=" . urlencode($msg));
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $msg = 'Lỗi: ' . $e->getMessage();
                $msgType = 'alert-error';
            }
        }
    }
}

// ── Tạo đơn hàng mới ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $table_id        = (int)$_POST['table_id'];
    $raw_customer_id = $_POST['customer_id'] ?? '';
    $number_of_people = (int)($_POST['number_of_people'] ?? 0);
    $guest_name      = trim($_POST['guest_name'] ?? '');
    $guest_phone     = trim($_POST['guest_phone'] ?? '');

    // Xử lý prefix user_ hoặc customer id thường
    $customer_id = null;
    if ($raw_customer_id && strpos($raw_customer_id, 'user_') === 0) {
        $user_id = (int)str_replace('user_', '', $raw_customer_id);
        $userInfo = $conn->query("SELECT full_name, username, phone, email FROM users WHERE user_id=$user_id")->fetch_assoc();
        if ($userInfo) {
            $uphone = $conn->real_escape_string($userInfo['phone'] ?? '');
            $uemail = $conn->real_escape_string($userInfo['email'] ?? '');
            $existCust = $conn->query("SELECT customer_id FROM customers WHERE " . ($uphone ? "phone='$uphone'" : "email='$uemail'") . " LIMIT 1");
            if ($existCust && $existCust->num_rows > 0) {
                $customer_id = (int)$existCust->fetch_assoc()['customer_id'];
            } else {
                $uname = $userInfo['full_name'] ?: $userInfo['username'];
                $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, email, created_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('sssi', $uname, $userInfo['phone'], $userInfo['email'], $waiter_id);
                $stmt->execute();
                $customer_id = $conn->insert_id;
            }
        }
    } elseif ($raw_customer_id) {
        $customer_id = (int)$raw_customer_id;
    }

    // Nếu là khách vãng lai và có nhập tên/SĐT thì tạo/tìm customer
    if (!$customer_id && $guest_name && $guest_phone) {
        if (!preg_match('/^[0-9]{10}$/', $guest_phone)) {
            $msg = 'Số điện thoại phải có đúng 10 chữ số';
            $msgType = 'alert-error';
            goto skip_order;
        }
        $escaped_phone = $conn->real_escape_string($guest_phone);
        $escaped_name  = $conn->real_escape_string($guest_name);
        $existCust = $conn->query("SELECT customer_id FROM customers WHERE phone='$escaped_phone' LIMIT 1");
        if ($existCust && $existCust->num_rows > 0) {
            $customer_id = (int)$existCust->fetch_assoc()['customer_id'];
        } else {
            $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param('ssi', $guest_name, $guest_phone, $waiter_id);
            $stmt->execute();
            $customer_id = $conn->insert_id;
        }
    }

    $conn->begin_transaction();
    try {
        // Lock bàn trước để tránh race condition
        $lockStmt = $conn->prepare("SELECT table_id FROM tables WHERE table_id=? FOR UPDATE");
        $lockStmt->bind_param('i', $table_id);
        $lockStmt->execute();
        $lockStmt->close();

        // Kiểm tra lại sau khi lock
        $chkStmt = $conn->prepare("SELECT order_id FROM orders WHERE table_id=? AND status IN ('moi','dang_xu_ly','dang_phuc_vu') LIMIT 1");
        $chkStmt->bind_param('i', $table_id);
        $chkStmt->execute();
        $chkResult = $chkStmt->get_result();
        $chkStmt->close();
        if ($chkResult->num_rows > 0) throw new Exception('Bàn này đang có đơn hàng chưa hoàn thành.');

        // Kiểm tra sức chứa
        $capStmt = $conn->prepare("SELECT capacity FROM tables WHERE table_id=? LIMIT 1");
        $capStmt->bind_param('i', $table_id);
        $capStmt->execute();
        $tableInfo = $capStmt->get_result()->fetch_assoc();
        $capStmt->close();
        if ($tableInfo && $number_of_people > (int)$tableInfo['capacity']) {
            throw new Exception('Số người (' . $number_of_people . ') vượt quá sức chứa của bàn (' . $tableInfo['capacity'] . ' người).');
        }

        // Kiểm tra xung đột với đặt bàn
        $now = date('Y-m-d H:i:s');
        $duration = reservation_duration_minutes($number_of_people > 0 ? $number_of_people : 1);
        $order_end_time = date('Y-m-d H:i:s', strtotime($now) + ($duration * 60));
        
        $reservationConflict = reservation_find_time_conflict($conn, $table_id, $now, $order_end_time);
        if ($reservationConflict) {
            $conflictStart = $reservationConflict['start_time'] ?? $reservationConflict['reservation_time'];
            $conflictEnd = $reservationConflict['end_time'] ?? date('Y-m-d H:i:s', strtotime($reservationConflict['reservation_time'] . ' +2 hours'));
            throw new Exception('Bàn này đã được đặt trước từ ' . date('H:i d/m/Y', strtotime($conflictStart)) . ' đến ' . date('H:i d/m/Y', strtotime($conflictEnd)) . '. Bạn không thể tạo đơn hàng vào khung giờ này.');
        }

        $stmt = $conn->prepare("INSERT INTO orders (table_id, customer_id, waiter_id, status, total_amount, guest_count) VALUES (?, ?, ?, 'moi', 0, ?)");
        $stmt->bind_param('iiii', $table_id, $customer_id, $waiter_id, $number_of_people);
        if (!$stmt->execute()) throw new Exception('Lỗi tạo đơn hàng: ' . $stmt->error);
        $new_order_id = $conn->insert_id;
        $stmt->close();

        $conn->query("UPDATE tables SET status='dang_su_dung' WHERE table_id=$table_id");
        $conn->commit();

        header("Location: order.php?id=$new_order_id&msg=" . urlencode("Tạo đơn #$new_order_id thành công!"));
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $msg = $e->getMessage();
        $msgType = 'alert-error';
    }
    skip_order:
}

$dateStart = $date . ' 00:00:00';
$dateEnd   = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';

$orders = $conn->query("
    SELECT o.order_id, o.total_amount, o.status, o.order_time, o.reservation_id,
           t.table_name, f.floor_name, c.customer_name,
           u.full_name as waiter_name
    FROM   orders o
    LEFT JOIN tables    t ON t.table_id    = o.table_id
    LEFT JOIN floors    f ON f.floor_id    = t.floor_id
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    LEFT JOIN users     u ON u.user_id     = o.waiter_id
    WHERE  o.order_time >= '$dateStart' AND o.order_time < '$dateEnd'
      AND o.reservation_id IS NULL
    ORDER  BY o.order_time DESC
");

// Lấy danh sách đặt bàn — lọc theo reservation_time (ngày khách đến), bỏ GROUP BY sai
$reservations = $conn->query("
    SELECT r.reservation_id, r.table_id, r.reservation_time, r.number_of_people, r.note, r.status,
           t.table_name, f.floor_name, 
           COALESCE(u.full_name, u.username, 'Khách') as customer_name, 
           u.phone,
           o.order_id, o.total_amount
    FROM reservations r
    LEFT JOIN tables t ON t.table_id = r.table_id
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    LEFT JOIN users u ON u.user_id = r.user_id
    LEFT JOIN orders o ON o.reservation_id = r.reservation_id
    WHERE r.reservation_time >= '$dateStart' AND r.reservation_time < '$dateEnd'
    ORDER BY r.reservation_time DESC
");

// Lấy danh sách bàn cho dropdown (query 2 lần để tránh conflict với data_seek)
// Chỉ lấy bàn trống hoặc đã đặt, KHÔNG lấy bàn đang có khách ăn
$allTablesForDropdown = $conn->query("
    SELECT t.table_id, t.table_name, t.capacity, f.floor_name
    FROM tables t
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    LEFT JOIN orders o ON o.table_id = t.table_id 
        AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
    WHERE o.order_id IS NULL
    ORDER BY f.floor_name, t.table_name
");

// Lấy lịch đặt bàn của TẤT CẢ các bàn trong ngày để hiển thị timeline
$allTableSchedules = $conn->query("
    SELECT r.table_id, r.reservation_time, r.number_of_people, r.note,
           u.username, u.full_name
    FROM reservations r
    LEFT JOIN users u ON u.user_id = r.user_id
    WHERE r.reservation_time >= '$dateStart' AND r.reservation_time < '$dateEnd'
        AND r.status IN ('da_xac_nhan', 'cho_xac_nhan')
    ORDER BY r.table_id, r.reservation_time ASC
");

// Group schedules by table_id
$tableSchedules = [];
if ($allTableSchedules) {
    while ($sched = $allTableSchedules->fetch_assoc()) {
        $startTime = strtotime($sched['reservation_time']);
        
        // Tính thời lượng
        $duration = 90;
        if ($sched['number_of_people'] <= 2) {
            $duration = 90;
        } elseif ($sched['number_of_people'] <= 4) {
            $duration = 120;
        } else {
            $duration = 180;
        }
        
        $endTime = $startTime + ($duration * 60);
        
        $tableSchedules[$sched['table_id']][] = [
            'start' => date('H:i', $startTime),
            'end' => date('H:i', $endTime),
            'start_timestamp' => $startTime,
            'end_timestamp' => $endTime,
            'people' => $sched['number_of_people'],
            'duration' => $duration,
            'customer' => $sched['username'] ?? $sched['full_name'] ?? 'Khách',
            'note' => $sched['note']
        ];
    }
}

$customers = $conn->query("
    SELECT 'user' AS src, user_id AS id, COALESCE(full_name, username) AS customer_name, phone
    FROM users WHERE role_id = 6 AND status = 'hoat_dong'
    UNION
    SELECT 'customer' AS src, customer_id AS id, customer_name, phone
    FROM customers
    WHERE phone NOT IN (SELECT phone FROM users WHERE role_id = 6 AND phone IS NOT NULL AND phone != '')
       OR phone IS NULL OR phone = ''
    ORDER BY customer_name
");

$statusBadge = ['da_dat_coc'=>'badge-role','moi'=>'badge-role','dang_xu_ly'=>'badge-role','dang_che_bien'=>'badge-role','dang_phuc_vu'=>'badge-role','hoan_thanh'=>'badge-active','da_thanh_toan'=>'badge-active','da_huy'=>'badge-inactive'];
$statusLabel = ['da_dat_coc'=>'Đã cọc','moi'=>'Mới','dang_xu_ly'=>'Đang xử lý','dang_che_bien'=>'Đang nấu','dang_phuc_vu'=>'Đang phục vụ','hoan_thanh'=>'Hoàn thành món','da_thanh_toan'=>'Đã thanh toán','da_huy'=>'Đã hủy'];

// Mapping trạng thái reservation
$reservationStatusMap = [
    'cho_xac_nhan' => ['label' => 'Chờ xác nhận', 'class' => 'badge-role'],
    'da_xac_nhan'  => ['label' => 'Đã xác nhận',  'class' => 'badge-active'],
    'da_checkin'   => ['label' => 'Đã check-in',  'class' => 'badge-active'],
    'hoan_thanh'   => ['label' => 'Hoàn thành',   'class' => 'badge-active'],
    'da_huy'       => ['label' => 'Đã hủy',       'class' => 'badge-inactive'],
    'khong_den'    => ['label' => 'Không đến',     'class' => 'badge-inactive'],
];

$pageTitle    = 'Đơn hôm nay';
$activeMenu   = 'w_orders';
$sidebarRole  = 'phucvu';
include __DIR__ . '/../includes/layout.php';
?>

<?php if ($msg): ?>
    <div class="alert <?= $msgType ?>"><?= e($msg) ?></div>
<?php endif; ?>

<?php 
// Hiển thị message từ URL (sau redirect)
if (isset($_GET['msg'])): ?>
    <div class="alert alert-success"><?= e($_GET['msg']) ?></div>
<?php endif; ?>

<form method="GET" style="display:flex; gap:12px; align-items:end; margin-bottom:20px;">
    <div class="form-group" style="margin:0;">
        <label>Ngày</label>
        <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <button class="btn btn-secondary" type="submit">Xem</button>
    
    <div style="margin-left:auto; display:flex; gap:8px;">
        <a href="?view=orders&date=<?= e($date) ?>" class="btn <?= $view === 'orders' ? 'btn-primary' : 'btn-secondary' ?>">Đơn hàng</a>
        <a href="?view=reservations&date=<?= e($date) ?>" class="btn <?= $view === 'reservations' ? 'btn-primary' : 'btn-secondary' ?>">Đặt bàn</a>
    </div>
</form>

<?php if ($view === 'orders'): ?>
    <button type="button" class="btn btn-primary" onclick="openCreateOrder(0, '')" style="margin-bottom:16px;">+ Tạo đơn mới</button>
<?php else: ?>
    <button type="button" class="btn btn-primary" onclick="openReservationModal()" style="margin-bottom:16px;">+ Đặt bàn trước</button>
<?php endif; ?>

<?php if ($view === 'orders'): ?>
    <div class="card panel">
    <h3 style="margin:0 0 16px;">Tất cả đơn hàng ngày <?= date('d/m/Y', strtotime($date)) ?></h3>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Bàn</th><th>Khách</th><th>Phục vụ</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thời gian</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php if ($orders && $orders->num_rows > 0): ?>
                <?php while ($r = $orders->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $r['order_id'] ?></td>
                    <td><?= e($r['floor_name'].' – '.$r['table_name']) ?></td>
                    <td><?= e($r['customer_name'] ?? 'Vãng lai') ?></td>
                    <td><?= e($r['waiter_name'] ?? 'Online') ?></td>
                    <td style="font-weight:700; color:var(--primary);"><?= format_currency($r['total_amount']) ?></td>
                    <td><span class="badge <?= $statusBadge[$r['status']] ?? 'badge-role' ?>"><?= $statusLabel[$r['status']] ?? $r['status'] ?></span></td>
                    <td style="font-size:13px;"><?= e($r['order_time']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'da_dat_coc'): ?>
                            <a href="order.php?id=<?= $r['order_id'] ?>&view_only=1" class="btn btn-secondary" style="padding:7px 12px; font-size:13px;">Chi tiết</a>
                        <?php elseif (!in_array($r['status'],['hoan_thanh','da_thanh_toan','da_huy'])): ?>
                            <a href="order.php?id=<?= $r['order_id'] ?>" class="btn btn-primary" style="padding:7px 12px; font-size:13px;">Đặt món</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8"><div class="empty-state">Không có đơn tại nhà hàng trong ngày này.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card panel">
    <h3 style="margin:0 0 16px;">Đặt bàn ngày <?= date('d/m/Y', strtotime($date)) ?></h3>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Đơn</th><th>Bàn</th><th>Khách hàng</th><th>SĐT</th><th>Thời gian</th><th>Số người</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php if ($reservations && $reservations->num_rows > 0): ?>
                <?php while ($r = $reservations->fetch_assoc()): 
                    // Tính thời lượng và thời gian kết thúc
                    $startTime = strtotime($r['reservation_time']);
                    $duration = 90;
                    if ($r['number_of_people'] <= 2) $duration = 90;
                    elseif ($r['number_of_people'] <= 4) $duration = 120;
                    else $duration = 180;
                    $endTime = $startTime + ($duration * 60);
                ?>
                <tr>
                    <td>#<?= $r['reservation_id'] ?></td>
                    <td><?= $r['order_id'] ? '#'.$r['order_id'] : '–' ?></td>
                    <td><?= e($r['floor_name']) ?> - <?= e($r['table_name']) ?></td>
                    <td><?= e($r['customer_name'] ?? '-') ?></td>
                    <td><?= e($r['phone'] ?? '-') ?></td>
                    <td><?= date('H:i', $startTime) ?> - <?= date('H:i', $endTime) ?></td>
                    <td><?= $r['number_of_people'] ?> người</td>
                    <td style="font-weight:700; color:var(--primary);"><?= $r['total_amount'] ? format_currency($r['total_amount']) : '–' ?></td>
                    <td><?php 
                        $rs = $reservationStatusMap[$r['status']] ?? ['label' => $r['status'], 'class' => 'badge-role'];
                        echo '<span class="badge ' . $rs['class'] . '">' . $rs['label'] . '</span>';
                    ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9"><div class="empty-state">Chưa có đặt bàn nào trong ngày này</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal tạo đơn -->
<div class="modal-backdrop" id="createOrderModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h3>Tạo đơn hàng mới</h3>
                <p id="modalTableName" style="color:var(--muted); font-size:13px; margin-top:4px;"></p>
            </div>
            <button class="btn btn-secondary" onclick="document.getElementById('createOrderModal').classList.remove('show')">Đóng</button>
        </div>
        <form method="POST">
            <input type="hidden" name="table_id" id="modalTableId">
            <div class="modal-body">
                <div class="form-group" id="tableSelectGroup">
                    <label>Chọn bàn</label>
                    <select class="select" id="tableSelectDropdown">
                        <option value="">-- Chọn bàn --</option>
                        <?php
                        $availTables = $conn->query("
                            SELECT t.table_id, t.table_name, t.capacity, f.floor_name
                            FROM tables t
                            LEFT JOIN floors f ON f.floor_id = t.floor_id
                            LEFT JOIN orders o ON o.table_id = t.table_id 
                                AND o.status IN ('moi','dang_xu_ly','dang_phuc_vu')
                            WHERE o.order_id IS NULL
                            ORDER BY f.floor_name, t.table_name
                        ");
                        if ($availTables) while ($at = $availTables->fetch_assoc()):
                        ?>
                            <option value="<?= $at['table_id'] ?>" data-capacity="<?= (int)$at['capacity'] ?>"><?= e($at['floor_name'].' – '.$at['table_name']) ?> (<?= (int)$at['capacity'] ?> chỗ)</option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Khách hàng</label>
                    <select class="select" name="customer_id" id="orderCustomerSelect" onchange="toggleGuestFields()">
                        <option value="">Khách vãng lai</option>
                        <?php if ($customers) { $customers->data_seek(0); while ($c = $customers->fetch_assoc()):
                            $prefix = $c['src'] === 'user' ? 'user_' : '';
                            $val    = $prefix . $c['id'];
                        ?>
                            <option value="<?= $val ?>"
                                    data-name="<?= e($c['customer_name']) ?>"
                                    data-phone="<?= e($c['phone']) ?>">
                                <?= e($c['customer_name']) ?><?= $c['phone'] ? ' – ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                </div>

                <!-- Thông tin khách đã có tài khoản (chỉ hiển thị, không nhập) -->
                <div id="registeredInfo" style="display:none; padding:12px 16px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; margin-bottom:12px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:14px;">
                        <div><span style="color:#6b7280;">Tên:</span> <strong id="regName"></strong></div>
                        <div><span style="color:#6b7280;">SĐT:</span> <strong id="regPhone"></strong></div>
                    </div>
                </div>

                <!-- Trường cho khách vãng lai -->
                <div id="guestFields" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>Tên khách</label>
                        <input class="input" type="text" name="guest_name" id="guestName" placeholder="Nhập tên khách...">
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <input class="input" type="tel" name="guest_phone" id="guestPhone" maxlength="10" placeholder="10 chữ số">
                    </div>
                </div>

                <!-- Số lượng người (hiện với cả 2 loại khách) -->
                <div class="form-group">
                    <label>Số lượng người</label>
                    <input class="input" type="number" name="number_of_people" id="orderGuestCount" min="1" max="50" placeholder="Nhập số người..." required>
                    <small id="orderGuestHint" style="color:#6b7280; font-size:12px; margin-top:4px; display:block;"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" onclick="document.getElementById('createOrderModal').classList.remove('show')">Hủy</button>
                <button class="btn btn-primary" type="submit" name="create_order">Tạo đơn & Đặt món</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal đặt bàn trước -->
<div class="modal-backdrop" id="reservationModal">
    <div class="modal" style="max-width:700px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden;">
        <div class="modal-header">
            <h3>Đặt bàn trước</h3>
            <button class="btn btn-secondary" onclick="document.getElementById('reservationModal').classList.remove('show')">Đóng</button>
        </div>
        <form method="POST" id="reservationForm" style="display:flex; flex-direction:column; overflow:hidden; max-height:calc(90vh - 60px);">
            <input type="hidden" name="create_reservation" value="1">
            <div class="modal-body" style="overflow-y:auto; padding:20px;">
                <!-- Lịch bàn -->
                <div id="tableScheduleSection" style="display:none; margin-bottom:24px; padding:16px; background:#f9fafb; border-radius:8px;">
                    <h4 style="margin:0 0 12px; font-size:15px; font-weight:600;">Lịch bàn hôm nay</h4>
                    <div id="scheduleTimeline"></div>
                    <div id="scheduleList" style="margin-top:12px;"></div>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>Chọn bàn *</label>
                        <select class="select" name="table_id" id="reservationTableSelect" onchange="showTableSchedule()">
                            <option value="">-- Chọn bàn --</option>
                            <?php if ($allTablesForDropdown): while ($t = $allTablesForDropdown->fetch_assoc()): ?>
                                <option value="<?= $t['table_id'] ?>" 
                                        data-schedule='<?= isset($tableSchedules[$t['table_id']]) ? json_encode($tableSchedules[$t['table_id']], JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>'>
                                    <?= e($t['floor_name']) ?> - <?= e($t['table_name']) ?> (<?= $t['capacity'] ?> người)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Thời gian đặt *</label>
                        <input class="input" type="datetime-local" name="reservation_time" id="reservationTime" onchange="checkTimeConflict()">
                    </div>
                    
                    <div class="form-group">
                        <label>Số người *</label>
                        <input class="input" type="number" name="number_of_people" id="reservationPeople" min="1" onchange="checkTimeConflict()">
                    </div>
                    
                    <div class="form-group">
                        <label>Tên khách hàng *</label>
                        <input class="input" type="text" name="customer_name" id="reservationCustomerName">
                    </div>
                    
                    <div class="form-group">
                        <label>Số điện thoại * (10 số)</label>
                        <input class="input" type="tel" name="phone" id="reservationPhone" maxlength="10" pattern="[0-9]{10}">
                    </div>
                    
                    <div class="form-group">
                        <label>Ghi chú</label>
                        <input class="input" type="text" name="note" placeholder="Ví dụ: Sinh nhật, gần cửa sổ...">
                    </div>
                </div>
                
                <div id="conflictWarning" style="display:none; margin-top:16px; padding:12px; background:#fee; border-left:4px solid #dc2626; color:#dc2626; font-size:14px; font-weight:600;">
                    Khung giờ này đã có người đặt!
                </div>

                <!-- Chọn món đặt trước -->
                <div style="margin-top:20px; border-top:1px solid #e5e7eb; padding-top:16px;">
                    <h4 style="font-size:14px; font-weight:700; margin-bottom:12px; color:#374151;">Chọn món đặt trước (tuỳ chọn)</h4>
                    <div id="menuItemsContainer" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:8px; max-height:200px; overflow-y:auto; padding:4px;">
                        <?php
                        $menuItems = $conn->query("SELECT item_id, item_name, price FROM menu_items WHERE status='con_hang' ORDER BY item_name");
                        if ($menuItems) while ($mi = $menuItems->fetch_assoc()):
                        ?>
                        <div style="display:flex; align-items:center; gap:8px; padding:8px; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb;">
                            <div style="flex:1; font-size:13px;">
                                <div style="font-weight:600; color:#1f2937;"><?= e($mi['item_name']) ?></div>
                                <div style="color:#6b7280; font-size:12px;"><?= format_currency($mi['price']) ?></div>
                            </div>
                            <input type="number" name="menu_items[<?= $mi['item_id'] ?>]"
                                   min="0" value="0" style="width:50px; padding:4px; border:1px solid #d1d5db; border-radius:6px; text-align:center; font-size:13px;"
                                   onchange="updateOrderTotal()">
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div style="margin-top:12px; padding:10px 14px; background:#f0fdf4; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:14px; font-weight:600; color:#374151;">Tổng tiền món:</span>
                        <strong id="orderTotalDisplay" style="font-size:16px; color:#059669;">0 đ</strong>
                    </div>
                </div>

                <!-- Thanh toán trước -->
                <div style="margin-top:16px; border-top:1px solid #e5e7eb; padding-top:16px;">
                    <h4 style="font-size:14px; font-weight:700; margin-bottom:12px; color:#374151;">Thanh toán trước (cọc)</h4>
                    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px;">
                            <input type="radio" name="deposit_percent" value="50" checked onchange="updateDepositAmount()"> Cọc 50%
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px;">
                            <input type="radio" name="deposit_percent" value="100" onchange="updateDepositAmount()"> Cọc 100%
                        </label>
                    </div>
                    <div id="depositInfo" style="margin-top:10px; padding:10px 14px; background:#eff6ff; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:14px; color:#374151;">Số tiền cọc:</span>
                        <strong id="depositAmountDisplay" style="font-size:16px; color:#2563eb;">0 đ</strong>
                    </div>
                    <div style="margin-top:10px;">
                        <label style="font-size:13px; font-weight:600; color:#374151; display:block; margin-bottom:6px;">Phương thức thanh toán cọc</label>
                        <select class="select" name="deposit_method" style="max-width:200px;">
                            <option value="cash">Tiền mặt</option>
                            <option value="bank_transfer">Chuyển khoản</option>
                            <option value="card">Thẻ</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" onclick="document.getElementById('reservationModal').classList.remove('show')">Hủy</button>
                <button class="btn btn-primary" type="button" id="submitReservation" onclick="submitReservationForm()">Tạo đặt bàn</button>
            </div>
        </form>
    </div>
</div>

<style>
.timeline-container {
    position: relative;
    height: 60px;
    background: linear-gradient(to right, #f0fdf4 0%, #dcfce7 100%);
    border-radius: 8px;
    overflow: hidden;
}
.timeline-hour {
    position: absolute;
    top: 0;
    bottom: 0;
    border-left: 1px solid #d1d5db;
    font-size: 10px;
    color: #6b7280;
    padding-left: 4px;
    padding-top: 2px;
}
.timeline-block {
    position: absolute;
    top: 20px;
    height: 35px;
    background: #dc2626;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 11px;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.schedule-item {
    padding: 8px 12px;
    background: white;
    border-left: 4px solid #dc2626;
    border-radius: 4px;
    margin-bottom: 8px;
    font-size: 13px;
}
.schedule-item-time {
    font-weight: 700;
    color: #dc2626;
}
.schedule-item-info {
    color: #6b7280;
    margin-top: 2px;
}
</style>

<script>
let currentSchedule = [];
let menuPrices = {};

// Lưu giá món ăn
<?php
$menuPricesData = $conn->query("SELECT item_id, price FROM menu_items WHERE status='con_hang'");
$pricesArr = [];
if ($menuPricesData) while ($mp = $menuPricesData->fetch_assoc()) {
    $pricesArr[$mp['item_id']] = (float)$mp['price'];
}
echo "menuPrices = " . json_encode($pricesArr) . ";\n";
?>

function updateOrderTotal() {
    let total = 0;
    document.querySelectorAll('#menuItemsContainer input[type=number]').forEach(input => {
        const itemId = input.name.match(/\[(\d+)\]/)?.[1];
        const qty = parseInt(input.value) || 0;
        if (itemId && menuPrices[itemId]) {
            total += qty * menuPrices[itemId];
        }
    });
    document.getElementById('orderTotalDisplay').textContent = new Intl.NumberFormat('vi-VN').format(total) + ' đ';
    updateDepositAmount();
}

function updateDepositAmount() {
    let total = 0;
    document.querySelectorAll('#menuItemsContainer input[type=number]').forEach(input => {
        const itemId = input.name.match(/\[(\d+)\]/)?.[1];
        const qty = parseInt(input.value) || 0;
        if (itemId && menuPrices[itemId]) total += qty * menuPrices[itemId];
    });
    const pct = parseInt(document.querySelector('input[name=deposit_percent]:checked')?.value || 50);
    const depositInfo = document.getElementById('depositInfo');
    const amount = total > 0 ? Math.round(total * pct / 100) : 0;
    document.getElementById('depositAmountDisplay').textContent = new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
    depositInfo.style.display = 'flex';
}

function openReservationModal() {
    document.getElementById('reservationModal').classList.add('show');
    document.getElementById('tableScheduleSection').style.display = 'none';
    // Scroll modal body về đầu
    const body = document.querySelector('#reservationModal .modal-body');
    if (body) body.scrollTop = 0;
    document.getElementById('reservationTableSelect').value = '';
    // Reset submit button
    const submitBtn = document.getElementById('submitReservation');
    if (submitBtn) submitBtn.disabled = false;
    const warningDiv = document.getElementById('conflictWarning');
    if (warningDiv) warningDiv.style.display = 'none';
    // Hiển thị số tiền cọc mặc định
    updateDepositAmount();
}

function updateOrderGuestMax(capacity) {
    const input = document.getElementById('orderGuestCount');
    const hint  = document.getElementById('orderGuestHint');
    if (capacity > 0) {
        input.max = capacity;
        hint.textContent = 'Tối đa ' + capacity + ' người cho bàn này';
        hint.style.color = '#6b7280';
        // Nếu đang nhập quá max thì reset
        if (input.value > capacity) {
            input.value = capacity;
        }
    } else {
        input.max = 50;
        hint.textContent = '';
    }
}

function openCreateOrder(tableId, tableName) {
    const tableSelectGroup    = document.getElementById('tableSelectGroup');
    const modalTableId        = document.getElementById('modalTableId');
    const modalTableName      = document.getElementById('modalTableName');
    const tableSelectDropdown = document.getElementById('tableSelectDropdown');

    if (tableId > 0) {
        modalTableId.value = tableId;
        modalTableName.textContent = 'Bàn: ' + tableName;
        tableSelectGroup.style.display = 'none';
        updateOrderGuestMax(0); // reset hint khi không biết capacity
    } else {
        modalTableId.value = '';
        modalTableName.textContent = 'Chọn bàn trống bên dưới';
        tableSelectGroup.style.display = 'block';
        tableSelectDropdown.onchange = function () {
            modalTableId.value = this.value;
            const cap = parseInt(this.options[this.selectedIndex].getAttribute('data-capacity') || '0');
            updateOrderGuestMax(cap);
        };
        updateOrderGuestMax(0);
    }
    // Reset form
    document.getElementById('orderCustomerSelect').value = '';
    document.getElementById('orderGuestCount').value = '';
    toggleGuestFields();
    document.getElementById('createOrderModal').classList.add('show');
}

function toggleGuestFields() {
    const select       = document.getElementById('orderCustomerSelect');
    const guestFields  = document.getElementById('guestFields');
    const regInfo      = document.getElementById('registeredInfo');
    const isGuest      = select.value === '';

    if (isGuest) {
        guestFields.style.display = 'grid';
        regInfo.style.display     = 'none';
    } else {
        guestFields.style.display = 'none';
        regInfo.style.display     = 'block';
        const opt = select.options[select.selectedIndex];
        document.getElementById('regName').textContent  = opt.getAttribute('data-name');
        document.getElementById('regPhone').textContent = opt.getAttribute('data-phone');
    }

    document.getElementById('guestName').required  = isGuest;
    document.getElementById('guestPhone').required = isGuest;
}

function showTableSchedule() {
    const select = document.getElementById('reservationTableSelect');
    const selectedOption = select.options[select.selectedIndex];
    const scheduleSection = document.getElementById('tableScheduleSection');
    
    if (!select.value) {
        scheduleSection.style.display = 'none';
        return;
    }
    
    // Lấy dữ liệu từ data-schedule attribute
    currentSchedule = JSON.parse(selectedOption.getAttribute('data-schedule') || '[]');
    
    displaySchedule(currentSchedule);
    scheduleSection.style.display = 'block';
    checkTimeConflict();
}

function displaySchedule(schedule) {
    const timelineDiv = document.getElementById('scheduleTimeline');
    const listDiv = document.getElementById('scheduleList');
    
    if (schedule.length === 0) {
        timelineDiv.innerHTML = '<p style="text-align:center; padding:20px; color:#16a34a; font-weight:600;">Bàn trống cả ngày</p>';
        listDiv.innerHTML = '';
        return;
    }
    
    // Vẽ timeline từ 6h sáng đến 23h tối (17 giờ)
    const startHour = 6;
    const endHour = 23;
    const totalHours = endHour - startHour;
    
    let timelineHTML = '<div class="timeline-container">';
    
    // Vẽ các vạch giờ
    for (let h = startHour; h <= endHour; h++) {
        const left = ((h - startHour) / totalHours) * 100;
        timelineHTML += `<div class="timeline-hour" style="left:${left}%">${h}h</div>`;
    }
    
    // Vẽ các khối đặt bàn
    schedule.forEach(slot => {
        const startTime = new Date('2000-01-01 ' + slot.start);
        const endTime = new Date('2000-01-01 ' + slot.end);
        const startHourFloat = startTime.getHours() + startTime.getMinutes() / 60;
        const endHourFloat = endTime.getHours() + endTime.getMinutes() / 60;
        
        if (startHourFloat >= startHour && endHourFloat <= endHour) {
            const left = ((startHourFloat - startHour) / totalHours) * 100;
            const width = ((endHourFloat - startHourFloat) / totalHours) * 100;
            
            timelineHTML += `<div class="timeline-block" style="left:${left}%; width:${width}%">${slot.start}-${slot.end}</div>`;
        }
    });
    
    timelineHTML += '</div>';
    timelineDiv.innerHTML = timelineHTML;
    
    // Hiển thị danh sách
    let listHTML = '';
    schedule.forEach(slot => {
        listHTML += `
            <div class="schedule-item">
                <div class="schedule-item-time">${slot.start} - ${slot.end} (${slot.duration} phút)</div>
                <div class="schedule-item-info">${slot.people} người - ${slot.customer}</div>
            </div>
        `;
    });
    listDiv.innerHTML = listHTML;
}

function submitReservationForm() {
    const tableId = document.getElementById('reservationTableSelect').value;
    const resTime = document.getElementById('reservationTime').value;
    const people  = document.getElementById('reservationPeople').value;
    const custName = document.getElementById('reservationCustomerName').value.trim();
    const phone   = document.getElementById('reservationPhone').value.trim();
    const modalBody = document.querySelector('#reservationModal .modal-body');

    const errors = [];
    if (!tableId)   errors.push({ el: document.getElementById('reservationTableSelect'),   msg: 'Vui lòng chọn bàn' });
    if (!resTime)   errors.push({ el: document.getElementById('reservationTime'),           msg: 'Vui lòng chọn thời gian đặt' });
    if (!people || parseInt(people) < 1) errors.push({ el: document.getElementById('reservationPeople'), msg: 'Vui lòng nhập số người' });
    if (!custName)  errors.push({ el: document.getElementById('reservationCustomerName'),   msg: 'Vui lòng nhập tên khách hàng' });
    if (!phone)     errors.push({ el: document.getElementById('reservationPhone'),          msg: 'Vui lòng nhập số điện thoại' });
    else if (!/^[0-9]{10}$/.test(phone)) errors.push({ el: document.getElementById('reservationPhone'), msg: 'Số điện thoại phải có đúng 10 chữ số' });

    // Xóa lỗi cũ
    document.querySelectorAll('#reservationModal .field-error').forEach(e => e.remove());
    document.querySelectorAll('#reservationModal .input-error').forEach(e => e.classList.remove('input-error'));

    if (errors.length > 0) {
        errors.forEach(err => {
            err.el.classList.add('input-error');
            const errDiv = document.createElement('div');
            errDiv.className = 'field-error';
            errDiv.style.cssText = 'color:#dc2626; font-size:12px; margin-top:4px;';
            errDiv.textContent = err.msg;
            err.el.parentNode.appendChild(errDiv);
        });
        // Scroll lên field lỗi đầu tiên
        if (modalBody) {
            const firstErr = errors[0].el;
            modalBody.scrollTop = firstErr.offsetTop - 20;
        }
        return;
    }

    // Kiểm tra conflict
    const warningDiv = document.getElementById('conflictWarning');
    if (warningDiv && warningDiv.style.display !== 'none') {
        if (modalBody) modalBody.scrollTop = warningDiv.offsetTop - 20;
        return;
    }

    document.getElementById('reservationForm').submit();
}

function checkTimeConflict() {
    const timeInput = document.getElementById('reservationTime').value;
    const peopleInput = document.getElementById('reservationPeople').value;
    const warningDiv = document.getElementById('conflictWarning');
    const submitBtn = document.getElementById('submitReservation');
    
    // Nếu chưa nhập đủ thông tin, cho phép submit (server sẽ validate)
    if (!timeInput || !peopleInput) {
        warningDiv.style.display = 'none';
        submitBtn.disabled = false;
        return;
    }
    
    // Nếu không có lịch đặt bàn nào, cho phép đặt
    if (currentSchedule.length === 0) {
        warningDiv.style.display = 'none';
        submitBtn.disabled = false;
        return;
    }
    
    // Tính thời lượng dựa trên số người
    const people = parseInt(peopleInput);
    let duration = 90;
    if (people <= 2) duration = 90;
    else if (people <= 4) duration = 120;
    else duration = 180;
    
    // Chuyển đổi thời gian
    const newTime = new Date(timeInput);
    const newEndTime = new Date(newTime.getTime() + duration * 60000);
    
    // Kiểm tra xung đột
    let hasConflict = false;
    currentSchedule.forEach(slot => {
        const slotStart = slot.start_timestamp * 1000;
        const slotEnd = slot.end_timestamp * 1000;
        const newStart = newTime.getTime();
        const newEnd = newEndTime.getTime();
        
        if ((newStart >= slotStart && newStart < slotEnd) ||
            (slotStart >= newStart && slotStart < newEnd)) {
            hasConflict = true;
        }
    });
    
    if (hasConflict) {
        warningDiv.style.display = 'block';
        submitBtn.disabled = true;
    } else {
        warningDiv.style.display = 'none';
        submitBtn.disabled = false;
    }
}
</script>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>
