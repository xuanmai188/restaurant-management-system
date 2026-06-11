<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['phucvu', 'admin', 'quanly']);

$msg      = '';
$msgType  = 'alert-success';
$waiter_id = $_SESSION['user']['user_id'];
$date      = $_GET['date'] ?? date('Y-m-d');

// Tự động hủy đặt bàn quá 1 tiếng chưa nhận
auto_cancel_expired_reservations();

// Đồng bộ trạng thái bàn với thực tế từ orders và reservations
if ($conn) {
    sync_table_status();
}

// ── Force đồng bộ: order da_dat_coc của reservation đã hoan_thanh/da_huy/khong_den → da_huy ──
$conn->query("
    UPDATE orders o
    JOIN reservations r ON r.reservation_id = o.reservation_id
    SET o.status = 'da_huy'
    WHERE o.status IN ('da_dat_coc','da_coc')
      AND r.status IN ('hoan_thanh','da_huy','khong_den')
");

// ── Hoàn thành bàn (phục vụ xác nhận) ──────────────────────────────────────
if (isset($_GET['complete'])) {
    require_csrf($_GET['csrf_token'] ?? '');

    $oid = (int)$_GET['complete'];
    $pendingItems = $conn->query("
        SELECT COUNT(*) AS cnt FROM order_details 
        WHERE order_id=$oid AND item_status IN ('moi','dang_che_bien')
    ");
    $pendingCount = $pendingItems ? (int)$pendingItems->fetch_assoc()['cnt'] : 0;

    if ($pendingCount > 0) {
        $msg = "Còn $pendingCount món bếp chưa nấu xong. Vui lòng chờ bếp hoàn thành trước.";
        $msgType = 'alert-error';
    } else {
        $conn->query("UPDATE orders SET status='hoan_thanh' WHERE order_id=$oid");
        header('Location: index.php'); exit;
    }
}

// ── AJAX: Chi tiết đặt bàn (tất cả đơn của bàn trong ngày) ──────────────────
if (isset($_GET['ajax_reservation'])) {
    $res_id = (int)$_GET['ajax_reservation'];
    
    // Lấy thông tin reservation được chọn
    $mainRes = $conn->query("
        SELECT r.reservation_id, r.table_id, r.number_of_people, r.reservation_time, r.note, r.status,
               COALESCE(u.full_name, u.username) AS customer_name,
               u.phone
        FROM reservations r
        LEFT JOIN users u ON u.user_id = r.user_id
        WHERE r.reservation_id = $res_id
        LIMIT 1
    ")->fetch_assoc();

    if (!$mainRes) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
        exit;
    }

    $table_id = (int)$mainRes['table_id'];
    $today = date('Y-m-d');
    $todayStart = $today . ' 00:00:00';
    $todayEnd   = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';

    // Lấy TẤT CẢ reservations của bàn này trong ngày
    $allReservations = [];
    $allResQuery = $conn->query("
        SELECT r.reservation_id, r.number_of_people, r.reservation_time, r.end_time, r.note, r.status,
               COALESCE(u.full_name, u.username) AS customer_name,
               u.phone,
               o.order_id
        FROM reservations r
        LEFT JOIN users u ON u.user_id = r.user_id
        LEFT JOIN orders o ON o.reservation_id = r.reservation_id
        WHERE r.table_id = $table_id
          AND r.reservation_time >= '$todayStart' AND r.reservation_time < '$todayEnd'
          AND r.status IN ('da_xac_nhan', 'cho_xac_nhan', 'da_checkin', 'hoan_thanh')
        ORDER BY r.reservation_time ASC
    ");

    while ($res = $allResQuery->fetch_assoc()) {
        $duration = reservation_duration_minutes((int)$res['number_of_people']);
        if (!empty($res['end_time'])) {
            $endTs = strtotime($res['end_time']);
        } else {
            $endTs = strtotime($res['reservation_time']) + ($duration * 60);
            $res['end_time'] = date('Y-m-d H:i:s', $endTs);
        }
        $res['duration'] = $duration;

        // Lấy món đặt trước (nếu có order)
        $res['items'] = [];
        if ($res['order_id']) {
            $itemRes = $conn->query("
                SELECT od.quantity, od.unit_price, mi.item_name
                FROM order_details od
                JOIN menu_items mi ON mi.item_id = od.item_id
                WHERE od.order_id = {$res['order_id']}
            ");
            while ($i = $itemRes->fetch_assoc()) $res['items'][] = $i;
        }

        $allReservations[] = $res;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'main_reservation' => $mainRes,
        'all_reservations' => $allReservations,
        'table_id' => $table_id
    ]);
    exit;
}

// ── Nhận bàn từ đặt bàn trước ───────────────────────────────────────────────
if (isset($_GET['checkin'])) {
    require_csrf($_GET['csrf_token'] ?? '');

    $res_id = (int)$_GET['checkin'];

    // Lấy thông tin reservation
    $resRow = $conn->query("
        SELECT r.*, c.customer_id
        FROM reservations r
        LEFT JOIN customers c ON c.phone = (SELECT phone FROM users WHERE user_id = r.user_id LIMIT 1)
        WHERE r.reservation_id = $res_id AND r.status = 'da_xac_nhan'
        LIMIT 1
    ")->fetch_assoc();

    if ($resRow) {
        $table_id    = (int)$resRow['table_id'];
        $customer_id = $resRow['customer_id'] ? (int)$resRow['customer_id'] : null;

        // Kiểm tra bàn chưa có đơn active (ngoại trừ da_dat_coc của chính reservation này)
        $chk = $conn->query("SELECT order_id FROM orders WHERE table_id=$table_id AND status IN ('moi','dang_xu_ly','dang_phuc_vu') LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            $msg = 'Bàn này đang có đơn hàng chưa hoàn thành.';
            $msgType = 'alert-error';
        } else {
            $conn->begin_transaction();
            try {
                // Kiểm tra đã có order da_dat_coc cho reservation này chưa
                $existingOrder = $conn->query("SELECT order_id FROM orders WHERE reservation_id=$res_id AND status='da_dat_coc' LIMIT 1")->fetch_assoc();

                if ($existingOrder) {
                    // Chuyển order da_dat_coc → dang_phuc_vu (khách đã đến, bắt đầu phục vụ)
                    $new_order_id = (int)$existingOrder['order_id'];
                    $conn->query("UPDATE orders SET status='dang_phuc_vu', waiter_id=$waiter_id WHERE order_id=$new_order_id AND status='da_dat_coc'");
                } else {
                    // Fallback: tạo order mới nếu chưa có (trường hợp dữ liệu cũ)
                    $riTotal = 0;
                    $riItems = $conn->query("SELECT item_id, quantity, unit_price, note FROM reservation_items WHERE reservation_id = $res_id");
                    $riRows = [];
                    if ($riItems) {
                        while ($ri = $riItems->fetch_assoc()) {
                            $riRows[] = $ri;
                            $riTotal += $ri['quantity'] * $ri['unit_price'];
                        }
                    }

                    $prepaidForOrder = (float)$conn->query("
                        SELECT IFNULL(SUM(amount),0) AS total FROM reservation_payments 
                        WHERE reservation_id=$res_id AND payment_status IN ('thanh_cong','cho_xu_ly')
                    ")->fetch_assoc()['total'];

                    $stmt = $conn->prepare("INSERT INTO orders (table_id, customer_id, reservation_id, waiter_id, status, total_amount, paid_amount, guest_count) VALUES (?, ?, ?, ?, 'dang_phuc_vu', ?, ?, ?)");
                    $stmt->bind_param('iiiiddi', $table_id, $customer_id, $res_id, $waiter_id, $riTotal, $prepaidForOrder, $resRow['number_of_people']);
                    $stmt->execute();
                    $new_order_id = $conn->insert_id;

                    foreach ($riRows as $ri) {
                        $s = $conn->prepare("INSERT INTO order_details (order_id, item_id, quantity, unit_price, note, item_status) VALUES (?, ?, ?, ?, ?, 'moi')");
                        $s->bind_param('iiids', $new_order_id, $ri['item_id'], $ri['quantity'], $ri['unit_price'], $ri['note']);
                        $s->execute();
                    }
                }

                // Cập nhật trạng thái — da_checkin thay vì hoan_thanh
                $conn->query("UPDATE tables SET status='dang_su_dung' WHERE table_id=$table_id");
                $conn->query("UPDATE reservations SET status='da_checkin' WHERE reservation_id=$res_id");

                $conn->commit();
                header("Location: order.php?id=$new_order_id&msg=" . urlencode("Đã nhận bàn từ đặt bàn #$res_id!"));
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $msg = 'Lỗi nhận bàn: ' . $e->getMessage();
                $msgType = 'alert-error';
            }
        }
    } else {
        $msg = 'Không tìm thấy đặt bàn hoặc đã xử lý rồi.';
        $msgType = 'alert-error';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    require_post_csrf();

    $table_id    = (int)$_POST['table_id'];
    $customer_id = $_POST['customer_id'] ? $_POST['customer_id'] : null;

    // Xử lý nếu chọn user (có prefix "user_") hoặc customer (có prefix "cust_")
    if ($customer_id && strpos($customer_id, 'user_') === 0) {
        $user_id = (int)str_replace('user_', '', $customer_id);
        
        // Kiểm tra xem đã có customer cho user này chưa (dựa vào email hoặc phone)
        $userInfo = $conn->query("SELECT username, full_name, phone, email FROM users WHERE user_id=$user_id")->fetch_assoc();
        if ($userInfo) {
            // Tìm customer có email hoặc phone trùng
            $existingCustomer = $conn->query("SELECT customer_id FROM customers WHERE email='{$userInfo['email']}' OR phone='{$userInfo['phone']}' LIMIT 1");
            if ($existingCustomer && $existingCustomer->num_rows > 0) {
                $customer_id = $existingCustomer->fetch_assoc()['customer_id'];
            } else {
                // Tạo customer mới
                $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, email, created_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('sssi', $userInfo['full_name'], $userInfo['phone'], $userInfo['email'], $waiter_id);
                $stmt->execute();
                $customer_id = $conn->insert_id;
            }
        } else {
            $customer_id = null;
        }
    } elseif ($customer_id && strpos($customer_id, 'cust_') === 0) {
        $customer_id = (int)str_replace('cust_', '', $customer_id);
    } else {
        $customer_id = $customer_id ? (int)$customer_id : null;
    }

    $conn->begin_transaction();
    $lock = $conn->prepare("SELECT table_id FROM tables WHERE table_id = ? LIMIT 1 FOR UPDATE");
    $lock->bind_param('i', $table_id);
    $lock->execute();
    $tableExists = $lock->get_result()->num_rows > 0;
    $lock->close();

    $chk = $tableExists ? $conn->query("SELECT order_id FROM orders WHERE table_id=$table_id AND status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu') LIMIT 1") : false;
    if (!$tableExists) {
        $conn->rollback();
        $msg = 'Ban khong ton tai.';
        $msgType = 'alert-error';
    } elseif ($chk && $chk->num_rows > 0) {
        $conn->rollback();
        $msg = 'Bàn này đang có đơn hàng chưa hoàn thành.';
        $msgType = 'alert-error';
    } else {
        $guest_count = max(1, (int)($_POST['guest_count'] ?? 1));
        $stmt = $conn->prepare("INSERT INTO orders (table_id, customer_id, waiter_id, status, total_amount, guest_count) VALUES (?, ?, ?, 'moi', 0, ?)");
        $stmt->bind_param('iiii', $table_id, $customer_id, $waiter_id, $guest_count);
        if ($stmt->execute()) {
            $new_order_id = $conn->insert_id;
            $conn->query("UPDATE tables SET status='dang_su_dung' WHERE table_id=$table_id");
            $conn->commit();
            header("Location: order.php?id=$new_order_id&msg=" . urlencode("Tạo đơn #$new_order_id thành công!"));
            exit;
        } else {
            $conn->rollback();
            $msg = 'Lỗi tạo đơn hàng.';
            $msgType = 'alert-error';
        }
    }
}

// ── Tạo khách vãng lai ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_walkin'])) {
    require_post_csrf();

    $name  = trim($_POST['walkin_name']);
    $phone = trim($_POST['walkin_phone']);
    
    if (!$name || !$phone) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin']);
        exit;
    }
    
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại phải có đúng 10 chữ số']);
        exit;
    }
    
    // Kiểm tra SĐT đã tồn tại chưa
    $stmt_chk = $conn->prepare("SELECT customer_id, customer_name FROM customers WHERE phone=? LIMIT 1");
    $stmt_chk->bind_param('s', $phone);
    $stmt_chk->execute();
    $checkPhone = $stmt_chk->get_result();
    $stmt_chk->close();
    if ($checkPhone && $checkPhone->num_rows > 0) {
        $existing = $checkPhone->fetch_assoc();
        echo json_encode(['success' => true, 'customer_id' => $existing['customer_id'], 'name' => $existing['customer_name'], 'phone' => $phone, 'existing' => true]);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $name, $phone, $waiter_id);
    if ($stmt->execute()) {
        $new_customer_id = $conn->insert_id;
        echo json_encode(['success' => true, 'customer_id' => $new_customer_id, 'name' => $name, 'phone' => $phone]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi tạo khách hàng']);
    }
    exit;
}

// ── Dữ liệu ─────────────────────────────────────────────────────────────────
// Dùng t.status làm nguồn sự thật duy nhất (đã được sync_table_status() cập nhật ở trên)
// Chỉ lấy đơn active khi bàn đang dang_su_dung — tránh hiện đơn cũ khi admin/quản lý đã đổi trạng thái
$tables = $conn->query("
    SELECT t.table_id, t.table_name, t.status, t.capacity, f.floor_name,
           o.order_id, o.total_amount, o.status AS order_status, o.order_time, o.reservation_id,
           r.reservation_time
    FROM   tables t
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    LEFT JOIN orders o ON o.order_id = (
        SELECT o2.order_id FROM orders o2
        WHERE o2.table_id = t.table_id
          AND (
              -- Bàn đang dùng: lấy đơn active
              (
                  t.status = 'dang_su_dung'
                  AND o2.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
              )
              -- Bàn đã đặt: lấy đơn cọc còn hiệu lực
              OR (
                  t.status = 'da_dat'
                  AND o2.status IN ('da_dat_coc','da_coc')
                  AND EXISTS (
                      SELECT 1 FROM reservations r3
                      WHERE r3.reservation_id = o2.reservation_id
                        AND r3.status NOT IN ('hoan_thanh','da_huy','khong_den')
                  )
              )
          )
        ORDER BY
            CASE
                WHEN o2.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu') THEN 1
                WHEN o2.status = 'hoan_thanh' THEN 2
                WHEN o2.status IN ('da_dat_coc','da_coc') THEN 3
                ELSE 4
            END,
            o2.order_time ASC
        LIMIT 1
    )
    LEFT JOIN reservations r ON r.reservation_id = o.reservation_id
    ORDER  BY f.floor_name, t.table_name
");

// Lấy TẤT CẢ đặt bàn hôm nay chưa check-in (cả walk-in và online)
// Chỉ lấy reservation còn hiệu lực: chưa có order bị hủy/hoàn thành liên kết
$reservations = $conn->query("
    SELECT r.table_id, r.reservation_id, r.reservation_time, r.number_of_people, 
           r.status AS res_status,
           COALESCE(u.full_name, u.username) AS customer_name,
           u.phone AS customer_phone,
           rp.amount AS deposit_amount
    FROM   reservations r
    LEFT JOIN users u ON u.user_id = r.user_id
    LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
                                      AND rp.payment_status = 'thanh_cong'
    WHERE  r.status IN ('da_xac_nhan','cho_xac_nhan')
        AND r.reservation_time >= '{$date} 00:00:00'
        AND r.reservation_time < '" . date('Y-m-d', strtotime($date . ' +1 day')) . " 00:00:00'
        AND NOT EXISTS (
            SELECT 1 FROM orders o
            WHERE o.reservation_id = r.reservation_id
              AND o.status IN ('da_huy','hoan_thanh','da_thanh_toan')
        )
    ORDER  BY r.table_id, r.reservation_time
");

// Lấy danh sách khách hàng: gộp users (role_id=6) + customers, loại trùng theo phone
$customerUsers = $conn->query("
    SELECT 'user' AS src, user_id AS id, COALESCE(full_name, username) AS display_name, phone
    FROM users WHERE role_id = 6 AND status = 'hoat_dong'
    UNION
    SELECT 'customer' AS src, customer_id AS id, customer_name AS display_name, phone
    FROM customers
    WHERE phone NOT IN (SELECT phone FROM users WHERE role_id = 6 AND phone IS NOT NULL AND phone != '')
       OR phone IS NULL OR phone = ''
    ORDER BY display_name
");

$myOrders   = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE waiter_id=$waiter_id AND order_time >= '{$date} 00:00:00' AND order_time < '" . date('Y-m-d', strtotime($date . ' +1 day')) . " 00:00:00'");
$myCount    = $myOrders ? $myOrders->fetch_assoc()['cnt'] : 0;

// Đếm bàn trống
$availRes   = $conn->query("
    SELECT COUNT(DISTINCT t.table_id) AS cnt 
    FROM tables t
    LEFT JOIN orders o ON o.table_id = t.table_id 
        AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
    LEFT JOIN reservations r ON r.table_id = t.table_id 
        AND r.status IN ('da_xac_nhan','cho_xac_nhan')
        AND r.reservation_time >= '{$date} 00:00:00' AND r.reservation_time < '" . date('Y-m-d', strtotime($date . ' +1 day')) . " 00:00:00'
    WHERE o.order_id IS NULL AND r.reservation_id IS NULL
");
$availCount = $availRes ? $availRes->fetch_assoc()['cnt'] : 0;

// Đếm bàn có khách
$busyRes    = $conn->query("
    SELECT COUNT(DISTINCT t.table_id) AS cnt 
    FROM tables t
    INNER JOIN orders o ON o.table_id = t.table_id 
        AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
");
$busyCount  = $busyRes ? $busyRes->fetch_assoc()['cnt'] : 0;

// ── Counters mới: thông tin hữu ích hơn cho waiter ──
$now = time();
$dateNextStart = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';

// Reservation sắp tới trong 30 phút
$soon30 = date('Y-m-d H:i:s', $now + 1800);
$soonRes = $conn->query("
    SELECT COUNT(*) AS cnt FROM reservations
    WHERE status IN ('da_xac_nhan','cho_xac_nhan')
      AND reservation_time >= NOW() AND reservation_time <= '$soon30'
");
$soonCount = $soonRes ? (int)$soonRes->fetch_assoc()['cnt'] : 0;

// Bàn chờ thanh toán
$waitPayRes = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE status='hoan_thanh'");
$waitPayCount = $waitPayRes ? (int)$waitPayRes->fetch_assoc()['cnt'] : 0;

// Món đang chờ phục vụ (bếp đã nấu xong, chưa mang ra)
$pendingServeRes = $conn->query("
    SELECT COUNT(*) AS cnt FROM order_details od
    JOIN orders o ON o.order_id = od.order_id
    WHERE od.item_status = 'hoan_thanh'
      AND o.status IN ('dang_phuc_vu','dang_che_bien')
");
$pendingServeCount = $pendingServeRes ? (int)$pendingServeRes->fetch_assoc()['cnt'] : 0;

$pageTitle    = 'Quản lý bàn & Đặt món';
$activeMenu   = 'waiter';
$sidebarRole  = 'phucvu';
include __DIR__ . '/../includes/layout.php';
?>

<?php if ($msg): ?>
    <div class="alert <?= $msgType ?>"><?= e($msg) ?></div>
<?php endif; ?>

<!-- Stats: thông tin hữu ích cho waiter -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px;">
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #16a34a; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Bàn trống</div>
        <div style="font-size:28px; font-weight:800; color:#16a34a; line-height:1.2;"><?= $availCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Sẵn sàng</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid #dc2626; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Đang phục vụ</div>
        <div style="font-size:28px; font-weight:800; color:#dc2626; line-height:1.2;"><?= $busyCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Có khách</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid <?= $soonCount > 0 ? '#f59e0b' : '#9ca3af' ?>; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Sắp đến (30p)</div>
        <div style="font-size:28px; font-weight:800; color:<?= $soonCount > 0 ? '#f59e0b' : '#9ca3af' ?>; line-height:1.2;"><?= $soonCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Reservation</div>
    </div>
    <div style="background:white; border-radius:12px; padding:14px 16px; border-left:4px solid <?= $waitPayCount > 0 ? '#d97706' : '#9ca3af' ?>; box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Chờ thanh toán</div>
        <div style="font-size:28px; font-weight:800; color:<?= $waitPayCount > 0 ? '#d97706' : '#9ca3af' ?>; line-height:1.2;"><?= $waitPayCount ?></div>
        <div style="font-size:11px; color:#9ca3af;">Bàn</div>
    </div>
</div>

<!-- Sơ đồ bàn -->
<?php
// Group tables by floor
$floorData = [];
while ($row = $tables->fetch_assoc()) {
    $floorData[$row['floor_name']][$row['table_id']] = $row;
}

// Group reservations by table
$tableReservations = [];
if ($reservations) {
    while ($res = $reservations->fetch_assoc()) {
        $tableReservations[$res['table_id']][] = [
            'reservation_id'   => $res['reservation_id'],
            'reservation_time' => $res['reservation_time'],
            'number_of_people' => $res['number_of_people'],
            'customer_name'    => $res['customer_name'],
            'customer_phone'   => $res['customer_phone'],
            'deposit_amount'   => $res['deposit_amount'],
        ];
    }
}

foreach ($floorData as $floorName => $tableList):
?>
<div style="margin-bottom:30px;">
    <h3 style="font-size:16px; font-weight:700; margin-bottom:12px;"><?= e($floorName) ?></h3>
    <div class="table-grid">
        <?php foreach ($tableList as $tableId => $t):
            // Tính thời gian ăn
            $startTime = ''; $elapsed = ''; $elapsedSecs = 0;
            if ($t['order_time']) {
                $startTime   = date('H:i', strtotime($t['order_time']));
                $elapsedSecs = time() - strtotime($t['order_time']);
                $h = floor(abs($elapsedSecs) / 3600);
                $m = floor((abs($elapsedSecs) % 3600) / 60);
                $elapsed = $h > 0 ? "{$h}h{$m}p" : "{$m}p";
            }

            $hasOrder       = !empty($t['order_id']);
            $hasReservation = !empty($tableReservations[$tableId]);
            $orderStatus    = $t['order_status'] ?? '';

            $isEating          = in_array($orderStatus, ['dang_xu_ly','dang_che_bien','dang_phuc_vu']);
            $isReservedDeposit = in_array($orderStatus, ['da_dat_coc', 'da_coc']);
            $isNew             = $orderStatus === 'moi';
            $isDone            = $orderStatus === 'hoan_thanh';

            // Kiểm tra thời gian check-in cho đơn đã cọc
            $isTimeToCheckin = false;
            $isExpired = false;
            $minsUntil = null;
            if ($isReservedDeposit) {
                $checkTime = $t['reservation_time'] ?? $t['order_time'] ?? null;
                if ($checkTime) {
                    $diffSecs = time() - strtotime($checkTime);
                    $isTimeToCheckin = $diffSecs >= -1800;
                    $isExpired       = $diffSecs > 3600;
                    $minsUntil       = $diffSecs < 0 ? ceil(abs($diffSecs) / 60) : null;
                } else {
                    $isTimeToCheckin = true;
                }
            }

            // Kiểm tra reservation sắp tới (chưa có order)
            $isSoonReservation = false;
            $soonMins = null;
            if (!$hasOrder && $hasReservation) {
                $nextRes = $tableReservations[$tableId][0] ?? null;
                if ($nextRes) {
                    $diffToRes = strtotime($nextRes['reservation_time']) - time();
                    $isSoonReservation = $diffToRes >= 0 && $diffToRes <= 1800; // trong 30 phút
                    $soonMins = ceil($diffToRes / 60);
                }
            }

            // ── Màu semantic mạnh hơn ──
            $borderColor = match(true) {
                $isEating && $elapsedSecs > 7200    => '#7c3aed', // Tím - ăn lâu >2h
                $isEating                           => '#dc2626', // Đỏ - đang ăn
                $isDone                             => '#d97706', // Cam - chờ thanh toán
                ($isReservedDeposit && $isExpired)  => '#6b7280', // Xám - quá giờ
                ($isReservedDeposit && $isTimeToCheckin) => '#10b981', // Xanh - đã tới giờ
                $isReservedDeposit                  => '#8b5cf6', // Tím nhạt - chờ đến giờ
                $isNew                              => '#3b82f6', // Xanh dương - đơn mới
                $isSoonReservation                  => '#f59e0b', // Vàng - sắp có khách
                $hasReservation                     => '#a78bfa', // Tím nhạt - đã đặt
                default                             => '#e5e7eb', // Xám - trống
            };

            $isClickable = $isEating || $isNew || $isDone;
            $onclick = $isClickable ? "location='order.php?id={$t['order_id']}'" : '';

            // Đơn cọc quá giờ → coi như trống
            if ($isReservedDeposit && $isExpired) {
                $isReservedDeposit = false;
                $hasOrder = false;
                $borderColor = '#e5e7eb';
                $isClickable = false;
                $onclick = '';
            }

            // Label trạng thái
            $statusText = match(true) {
                $isEating && $elapsedSecs > 7200 => 'Ăn lâu',
                $isEating                        => 'Đang ăn',
                $isDone                          => 'Chờ TT',
                ($isReservedDeposit && $isTimeToCheckin) => 'Tới giờ',
                $isReservedDeposit               => 'Chờ giờ',
                $isNew                           => 'Đơn mới',
                $isSoonReservation               => 'Sắp đến',
                $hasReservation                  => 'Đã đặt',
                default                          => 'Trống',
            };
        ?>
            <div class="table-card" style="border-top:4px solid <?= $borderColor ?>; <?= $isClickable ? 'cursor:pointer;' : '' ?> position:relative;"
                 <?= $onclick ? "onclick=\"$onclick\"" : '' ?>>

                <!-- Header -->
                <div class="table-header">
                    <span class="table-name"><?= e($t['table_name']) ?></span>
                    <span style="font-size:11px; font-weight:700; color:<?= $borderColor ?>;"><?= $statusText ?></span>
                </div>

                <div class="table-body">
                    <?php if ($isNew): ?>
                        <p style="font-size:13px; color:#3b82f6; font-weight:700;">Đơn mới - Chưa xử lý</p>
                        <p class="price"><?= format_currency($t['total_amount']) ?></p>
                        <div style="display:flex; gap:6px; margin-top:8px;">
                            <a href="order.php?id=<?= $t['order_id'] ?>" class="btn btn-primary" style="flex:1;padding:8px;font-size:14px;text-align:center;" onclick="event.stopPropagation();">Đặt món</a>
                        </div>

                    <?php elseif ($isEating): ?>
                        <!-- Elapsed time nổi bật -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-size:12px; color:#6b7280;">Từ <?= $startTime ?></span>
                            <span style="font-size:14px; font-weight:800; color:<?= $elapsedSecs > 7200 ? '#7c3aed' : ($elapsedSecs > 5400 ? '#d97706' : '#dc2626') ?>;">
                                <?= $elapsed ?>
                            </span>
                        </div>
                        <p class="price"><?= format_currency($t['total_amount']) ?></p>
                        <div style="display:flex; gap:6px; margin-top:8px;">
                            <a href="order.php?id=<?= $t['order_id'] ?>" class="btn btn-secondary" style="flex:1;padding:8px;font-size:14px;text-align:center;" onclick="event.stopPropagation();">Đặt món</a>
                            <a href="?complete=<?= $t['order_id'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>" class="btn btn-primary" style="flex:1;padding:8px;font-size:14px;text-align:center;" onclick="event.stopPropagation(); return confirm('Xác nhận hoàn thành bàn?')">Xong</a>
                        </div>

                    <?php elseif ($isDone): ?>
                        <p style="font-size:13px; color:#d97706; font-weight:700;">Chờ thu ngân thanh toán</p>
                        <p class="price"><?= format_currency($t['total_amount']) ?></p>

                    <?php elseif ($isReservedDeposit): ?>
                        <?php if ($isTimeToCheckin): ?>
                            <p style="font-size:12px; color:#10b981; font-weight:700;">Khách đã đến giờ</p>
                            <p class="price"><?= format_currency($t['total_amount']) ?></p>
                            <div style="display:flex; gap:6px; margin-top:8px;">
                                <a href="?checkin=<?= $t['reservation_id'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>" class="btn btn-primary" style="flex:1;padding:8px;font-size:14px;text-align:center;" onclick="event.stopPropagation(); return confirm('Xác nhận khách đã đến?')">Xác nhận</a>
                                <button onclick="event.stopPropagation(); viewReservationDetail(<?= $t['reservation_id'] ?>)" class="btn btn-secondary" style="flex:1;padding:8px;font-size:14px;text-align:center;border:none;cursor:pointer;">Chi tiết</button>
                            </div>
                        <?php else: ?>
                            <p style="font-size:12px; color:#8b5cf6; font-weight:700;">
                                Chờ đến giờ<?= $minsUntil ? " · còn {$minsUntil}p" : '' ?>
                            </p>
                            <p class="time"><?= date('H:i', strtotime($t['reservation_time'])) ?></p>
                            <p class="price"><?= format_currency($t['total_amount']) ?></p>
                            <div style="display:flex; gap:6px; margin-top:8px;">
                                <button onclick="event.stopPropagation(); viewReservationDetail(<?= $t['reservation_id'] ?>)" class="btn btn-primary" style="flex:1;padding:8px;font-size:14px;text-align:center;border:none;cursor:pointer;">Chi tiết</button>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($hasReservation): ?>
                        <?php $reservationList = $tableReservations[$tableId]; ?>
                        <div style="margin-top:4px;">
                            <?php foreach ($reservationList as $res):
                                $isOnline = !empty($res['deposit_amount']);
                                $resTime  = strtotime($res['reservation_time']);
                                $diffToRes = $resTime - time();
                                $isSoon   = $diffToRes >= 0 && $diffToRes <= 1800;
                                $minsLeft = $isSoon ? ceil($diffToRes / 60) : null;
                            ?>
                                <div style="padding:8px; margin-bottom:6px; background:<?= $isSoon ? '#fffbeb' : '#f8f9fa' ?>; border-radius:8px; border:1px solid <?= $isSoon ? '#fde68a' : '#e5e7eb' ?>;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                                        <span style="font-size:12px; font-weight:700; color:<?= $isSoon ? '#d97706' : '#1f2937' ?>;">
                                            <?= date('H:i', $resTime) ?>
                                            <?= $minsLeft !== null ? "<span style='font-size:10px;'>(còn {$minsLeft}p)</span>" : '' ?>
                                        </span>
                                        <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:8px;
                                            <?= $isOnline ? 'background:#d1fae5; color:#065f46;' : 'background:#f3f4f6; color:#6b7280;' ?>">
                                            <?= $isOnline ? 'Online' : 'Walk-in' ?>
                                        </span>
                                    </div>
                                    <div style="font-size:12px; color:#374151; margin-bottom:6px;">
                                        <?= e($res['customer_name'] ?? 'Khách') ?> · <?= $res['number_of_people'] ?> người
                                    </div>
                                    <div style="display:flex; gap:6px;">
                                        <button onclick="viewReservationDetail(<?= $res['reservation_id'] ?>)"
                                            style="flex:1; padding:5px 0; background:#3b82f6; color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">
                                            Chi tiết
                                        </button>
                                        <a href="?checkin=<?= $res['reservation_id'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>"
                                            onclick="return confirm('Xác nhận khách #<?= $res['reservation_id'] ?> đã đến?')"
                                            style="flex:1; padding:5px 0; background:#0f766e; color:#fff; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; text-align:center; display:inline-block;">
                                            Xác nhận
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <p style="font-size:14px; color:#16a34a; font-weight:600; margin-top:8px;">Bàn trống</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

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
            <?= csrf_field() ?>
            <input type="hidden" name="table_id" id="modalTableId">
            <div class="modal-body">
                <div class="form-group" id="tableSelectGroup" style="display:none;">
                    <label>Chọn bàn</label>
                    <select class="select" id="tableSelectDropdown">
                        <option value="">-- Chọn bàn --</option>
                        <?php
                        $availTables = $conn->query("
                            SELECT t.table_id, t.table_name, t.capacity, f.floor_name
                            FROM tables t
                            LEFT JOIN floors f ON f.floor_id = t.floor_id
                            LEFT JOIN orders o ON o.table_id = t.table_id 
                                AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu')
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
                    <select class="select" name="customer_id" id="customerSelect">
                        <option value="">Khách hàng</option>
                        <?php if ($customerUsers) while ($cu = $customerUsers->fetch_assoc()): ?>
                            <?php
                            $prefix = $cu['src'] === 'user' ? 'user_' : 'cust_';
                            $label  = e($cu['display_name']) . ($cu['phone'] ? ' – ' . e($cu['phone']) : '');
                            ?>
                            <option value="<?= $prefix . $cu['id'] ?>"><?= $label ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="button" class="btn btn-secondary" style="margin-top:8px; width:100%;" onclick="openWalkInModal()">+ Tạo khách vãng lai</button>
                </div>
                <div class="form-group">
                    <label>Số lượng người</label>
                    <input class="input" type="number" name="guest_count" id="indexGuestCount" min="1" max="50" placeholder="Nhập số người..." required>
                    <small id="indexGuestHint" style="color:#6b7280; font-size:12px; margin-top:4px; display:block;"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" onclick="document.getElementById('createOrderModal').classList.remove('show')">Hủy</button>
                <button class="btn btn-primary" type="submit" name="create_order">Tạo đơn & Đặt món</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal tạo khách vãng lai -->
<div class="modal-backdrop" id="walkInModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Tạo khách vãng lai</h3>
            <button class="btn btn-secondary" onclick="document.getElementById('walkInModal').classList.remove('show')">Đóng</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Tên khách hàng *</label>
                <input class="input" type="text" id="walkinName" placeholder="Nhập tên khách hàng" required>
            </div>
            <div class="form-group">
                <label>Số điện thoại *</label>
                <input class="input" type="tel" id="walkinPhone" placeholder="Nhập 10 chữ số" maxlength="10" pattern="[0-9]{10}" required>
            </div>
            <div id="walkinError" class="alert alert-error" style="display:none; margin-top:12px;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('walkInModal').classList.remove('show')">Hủy</button>
            <button class="btn btn-primary" type="button" onclick="createWalkInCustomer()">Tạo</button>
        </div>
    </div>
</div>

<style>
.table-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 14px;
}
.table-card {
    background: #fff;
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: 0.2s;
}
.table-card:hover { transform: translateY(-4px); }
.table-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}
.table-name  { font-weight: 700; }
.table-status { font-size: 12px; font-weight: 600; }
.table-status.available  { color: #16a34a; }
.table-status.occupied   { color: #dc2626; }
.table-status.reserved   { color: #d97706; }
.table-status.maintenance{ color: #6b7280; }
.table-body p { font-size: 13px; margin: 4px 0; }
.time  { color: #dc2626; font-weight: 700; }
.price { color: #e11d48; font-weight: 600; }
.empty { color: #16a34a; }
</style>

<script>
function updateIndexGuestMax(capacity) {
    const input = document.getElementById('indexGuestCount');
    const hint  = document.getElementById('indexGuestHint');
    if (capacity > 0) {
        input.max = capacity;
        hint.textContent = 'Tối đa ' + capacity + ' người cho bàn này';
        hint.style.color = '#6b7280';
        if (parseInt(input.value) > capacity) input.value = capacity;
    } else {
        input.max = 50;
        hint.textContent = '';
    }
}

function openCreateOrder(tableId, tableName) {
    const tableSelectGroup   = document.getElementById('tableSelectGroup');
    const modalTableId       = document.getElementById('modalTableId');
    const modalTableName     = document.getElementById('modalTableName');
    const tableSelectDropdown = document.getElementById('tableSelectDropdown');

    if (tableId > 0) {
        modalTableId.value = tableId;
        modalTableName.textContent = 'Bàn: ' + tableName;
        tableSelectGroup.style.display = 'none';
        // Lấy capacity từ data attribute của option tương ứng
        const opt = tableSelectDropdown.querySelector('option[value="' + tableId + '"]');
        updateIndexGuestMax(opt ? parseInt(opt.getAttribute('data-capacity') || '0') : 0);
    } else {
        modalTableId.value = '';
        modalTableName.textContent = 'Chọn bàn trống bên dưới';
        tableSelectGroup.style.display = 'block';
        tableSelectDropdown.onchange = function () {
            modalTableId.value = this.value;
            const cap = parseInt(this.options[this.selectedIndex].getAttribute('data-capacity') || '0');
            updateIndexGuestMax(cap);
        };
        updateIndexGuestMax(0);
    }
    document.getElementById('indexGuestCount').value = '';
    document.getElementById('createOrderModal').classList.add('show');
}

function openWalkInModal() {
    document.getElementById('walkInModal').classList.add('show');
    document.getElementById('walkinName').value = '';
    document.getElementById('walkinPhone').value = '';
    document.getElementById('walkinError').style.display = 'none';
}

function createWalkInCustomer() {
    const name = document.getElementById('walkinName').value.trim();
    const phone = document.getElementById('walkinPhone').value.trim();
    const errorDiv = document.getElementById('walkinError');
    
    if (!name || !phone) {
        errorDiv.textContent = 'Vui lòng nhập đầy đủ thông tin';
        errorDiv.style.display = 'block';
        return;
    }
    
    if (!/^[0-9]{10}$/.test(phone)) {
        errorDiv.textContent = 'Số điện thoại phải có đúng 10 chữ số';
        errorDiv.style.display = 'block';
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'create_walkin=1&csrf_token=<?= urlencode(csrf_token()) ?>&walkin_name=' + encodeURIComponent(name) + '&walkin_phone=' + encodeURIComponent(phone)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Thêm option mới vào dropdown
            const select = document.getElementById('customerSelect');
            const option = document.createElement('option');
            option.value = data.customer_id;
            option.textContent = data.name + ' – ' + data.phone;
            option.selected = true;
            select.appendChild(option);
            
            // Đóng modal
            document.getElementById('walkInModal').classList.remove('show');
        } else {
            errorDiv.textContent = data.message;
            errorDiv.style.display = 'block';
        }
    })
    .catch(err => {
        errorDiv.textContent = 'Lỗi kết nối';
        errorDiv.style.display = 'block';
    });
}
</script>

<!-- Modal chi tiết đặt bàn -->
<div id="reservationDetailModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; width:90%; max-width:480px; max-height:85vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid #e5e7eb;">
            <h3 style="margin:0; font-size:18px; font-weight:700;">Chi tiết đặt bàn</h3>
            <button onclick="closeReservationDetail()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#9ca3af; line-height:1;">&times;</button>
        </div>
        <div id="reservationDetailContent" style="padding:20px 24px;">
            <div style="text-align:center; color:#9ca3af;">Đang tải...</div>
        </div>
    </div>
</div>

<script>
function viewReservationDetail(resId) {
    document.getElementById('reservationDetailModal').style.display = 'flex';
    document.getElementById('reservationDetailContent').innerHTML = '<div style="text-align:center;padding:20px;color:#9ca3af;">Đang tải...</div>';

    fetch('?ajax_reservation=' + resId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('reservationDetailContent').innerHTML = '<p style="color:#dc2626;">Không tìm thấy thông tin.</p>';
                return;
            }
            
            const allRes = data.all_reservations;
            const tableId = data.table_id;

            if (!allRes || allRes.length === 0) {
                document.getElementById('reservationDetailContent').innerHTML = '<p style="color:#9ca3af;">Không có đơn đặt nào.</p>';
                return;
            }

            let html = `<div style="margin-bottom:16px;padding:12px;background:#eff6ff;border-radius:8px;border-left:4px solid #3b82f6;">
                <strong style="color:#1e40af;">Tất cả đơn đặt bàn hôm nay (${allRes.length} đơn)</strong>
            </div>`;

            allRes.forEach((r, index) => {
                const isSelected = r.reservation_id == resId;
                const statusColors = {
                    'da_xac_nhan': '#10b981',
                    'cho_xac_nhan': '#f59e0b',
                    'da_checkin': '#3b82f6',
                    'hoan_thanh': '#6b7280'
                };
                const statusLabels = {
                    'da_xac_nhan': 'Đã xác nhận',
                    'cho_xac_nhan': 'Chờ xác nhận',
                    'da_checkin': 'Đã check-in',
                    'hoan_thanh': 'Hoàn thành'
                };
                const statusColor = statusColors[r.status] || '#6b7280';
                const statusLabel = statusLabels[r.status] || r.status;

                let itemsHtml = '';
                if (r.items && r.items.length > 0) {
                    itemsHtml = '<div style="margin-top:8px;"><strong style="font-size:12px;color:#6b7280;">Món đặt trước:</strong>';
                    r.items.forEach(i => {
                        itemsHtml += `<div style="font-size:12px;color:#374151;padding:4px 0;">• ${i.item_name} × ${i.quantity} = ${Number(i.unit_price * i.quantity).toLocaleString('vi-VN')}đ</div>`;
                    });
                    itemsHtml += '</div>';
                }

                html += `
                    <div style="margin-bottom:12px;padding:14px;background:${isSelected ? '#f0fdf4' : '#f9fafb'};border-radius:8px;border:2px solid ${isSelected ? '#10b981' : '#e5e7eb'};">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <strong style="color:#1f2937;font-size:15px;">#${r.reservation_id} ${isSelected ? '(Đơn này)' : ''}</strong>
                            <span style="padding:4px 8px;background:${statusColor};color:white;border-radius:6px;font-size:11px;font-weight:600;">${statusLabel}</span>
                        </div>
                        <div style="font-size:13px;color:#374151;line-height:1.6;">
                            <div><strong>Khách:</strong> ${r.customer_name || '-'} ${r.phone ? '(' + r.phone + ')' : ''}</div>
                            <div><strong>Số người:</strong> ${r.number_of_people} người</div>
                            <div><strong>Thời gian:</strong> ${r.reservation_time.substring(11, 16)} - ${r.end_time.substring(11, 16)} (${r.duration} phút)</div>
                            ${r.note ? '<div><strong>Ghi chú:</strong> ' + r.note + '</div>' : ''}
                            ${itemsHtml}
                        </div>
                    </div>
                `;
            });

            document.getElementById('reservationDetailContent').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('reservationDetailContent').innerHTML = '<p style="color:#dc2626;">Lỗi tải dữ liệu.</p>';
        });
}

function closeReservationDetail() {
    document.getElementById('reservationDetailModal').style.display = 'none';
}

// Tự làm mới sơ đồ bàn (đồng bộ với bếp/thu ngân)
setInterval(function() {
    if (!document.hidden) {
        location.reload();
    }
}, 30000);
</script>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>
