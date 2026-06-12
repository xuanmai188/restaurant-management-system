<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| Chuẩn hóa kết nối DB
|--------------------------------------------------------------------------
*/
$db = null;
$dbType = '';

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
    $dbType = 'pdo';
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
    $dbType = 'mysqli';
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
    $dbType = 'mysqli';
} else {
    die('Không tìm thấy biến kết nối CSDL. Kiểm tra lại config/database.php');
}

/*
|--------------------------------------------------------------------------
| Tự động hủy reservation quá 1 tiếng
|--------------------------------------------------------------------------
*/
if ($db && $dbType === 'mysqli') {
    // Gọi hàm auto-cancel từ functions.php
    auto_cancel_expired_reservations();
}

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount)
    {
        return number_format((float)$amount, 0, ',', '.') . ' đ';
    }
}

function db_begin($db, $dbType)
{
    if ($dbType === 'pdo') {
        return $db->beginTransaction();
    }
    return $db->begin_transaction();
}

function db_commit($db, $dbType)
{
    if ($dbType === 'pdo') {
        return $db->commit();
    }
    return $db->commit();
}

function db_rollback($db, $dbType)
{
    if ($dbType === 'pdo') {
        if ($db->inTransaction()) {
            return $db->rollBack();
        }
        return true;
    }
    return $db->rollback();
}

function db_last_insert_id($db, $dbType)
{
    if ($dbType === 'pdo') {
        return (int)$db->lastInsertId();
    }
    return (int)$db->insert_id;
}

function db_prepare_and_execute($db, string $dbType, string $sql, array $params = [])
{
    if ($dbType === 'pdo') {
        $stmt = $db->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception('Lỗi execute SQL.');
        }
        return $stmt;
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Lỗi prepare SQL: ' . $db->error);
    }

    if (!empty($params)) {
        $types = '';
        $values = [];

        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }

        $stmt->bind_param($types, ...$values);
    }

    if (!$stmt->execute()) {
        throw new Exception('Lỗi execute SQL: ' . $stmt->error);
    }

    return $stmt;
}

function db_fetch_all($db, string $dbType, string $sql, array $params = []): array
{
    $stmt = db_prepare_and_execute($db, $dbType, $sql, $params);

    if ($dbType === 'pdo') {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function db_fetch_one($db, string $dbType, string $sql, array $params = []): ?array
{
    $rows = db_fetch_all($db, $dbType, $sql, $params);
    return $rows[0] ?? null;
}

function first_non_empty(...$values)
{
    foreach ($values as $value) {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return null;
}

function current_customer_from_session($db, string $dbType): ?array
{
    $sessionCustomerId = (int) first_non_empty(
        $_SESSION['customer']['customer_id'] ?? null,
        $_SESSION['user']['customer_id'] ?? null,
        $_SESSION['auth']['customer_id'] ?? null
    );

    if ($sessionCustomerId > 0) {
        $customer = db_fetch_one(
            $db,
            $dbType,
            "SELECT customer_id, customer_name, phone, email
             FROM customers
             WHERE customer_id = ?
             LIMIT 1",
            [$sessionCustomerId]
        );

        if ($customer) {
            return $customer;
        }
    }

    $sessionPhone = first_non_empty(
        $_SESSION['customer']['phone'] ?? null,
        $_SESSION['user']['phone'] ?? null,
        $_SESSION['auth']['phone'] ?? null
    );

    if ($sessionPhone) {
        $customer = db_fetch_one(
            $db,
            $dbType,
            "SELECT customer_id, customer_name, phone, email
             FROM customers
             WHERE phone = ?
             LIMIT 1",
            [$sessionPhone]
        );

        if ($customer) {
            return $customer;
        }
    }

    $sessionEmail = first_non_empty(
        $_SESSION['customer']['email'] ?? null,
        $_SESSION['user']['email'] ?? null,
        $_SESSION['auth']['email'] ?? null
    );

    if ($sessionEmail) {
        $customer = db_fetch_one(
            $db,
            $dbType,
            "SELECT customer_id, customer_name, phone, email
             FROM customers
             WHERE email = ?
             LIMIT 1",
            [$sessionEmail]
        );

        if ($customer) {
            return $customer;
        }
    }

    return null;
}

function sync_customer_session(array $customer): void
{
    $_SESSION['customer']['customer_id'] = (int)($customer['customer_id'] ?? 0);
    $_SESSION['customer']['customer_name'] = $customer['customer_name'] ?? '';
    $_SESSION['customer']['phone'] = $customer['phone'] ?? '';
    $_SESSION['customer']['email'] = $customer['email'] ?? '';

    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $_SESSION['user']['customer_id'] = (int)($customer['customer_id'] ?? 0);
    }
}

/*
|--------------------------------------------------------------------------
| Cấu hình chuyển khoản
|--------------------------------------------------------------------------
*/
$bankConfig = [
    'bank_bin'      => '970422',
    'bank_name'     => 'MB BANK',
    'account_no'    => '0123456789',
    'account_name'  => 'NHA HANG ABC',
];

/*
|--------------------------------------------------------------------------
| Bắt buộc đăng nhập mới được đặt bàn
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['user'])) {
    header('Location: /quanlynhahang/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Chỉ cho role KhachHang (role_id = 6)
$sessionRole = strtolower($_SESSION['user']['role_name'] ?? '');
if (!in_array($sessionRole, ['khachhang', 'khach_hang'])) {
    header('Location: /quanlynhahang/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Lấy thông tin user đang đăng nhập từ bảng users
|--------------------------------------------------------------------------
*/
$loggedUserId = (int)($_SESSION['user']['user_id'] ?? 0);
$loggedUser = db_fetch_one($db, $dbType,
    "SELECT user_id, full_name, phone, email FROM users WHERE user_id = ? LIMIT 1",
    [$loggedUserId]
);

// Fallback: nếu db_fetch_one trả null, lấy từ session trực tiếp
if (!$loggedUser) {
    $loggedUser = [
        'full_name' => $_SESSION['user']['full_name'] ?? '',
        'phone'     => $_SESSION['user']['phone'] ?? '',
        'email'     => $_SESSION['user']['email'] ?? '',
    ];
}
/*
|--------------------------------------------------------------------------
| Load dữ liệu ban đầu
|--------------------------------------------------------------------------
*/
$errors = [];
$success = null;
$successReservationId = null;

$currentCustomer = current_customer_from_session($db, $dbType);

$tables = db_fetch_all(
    $db,
    $dbType,
    "SELECT t.table_id, t.table_name, t.capacity, t.status, f.floor_name
     FROM tables t
     LEFT JOIN floors f ON f.floor_id = t.floor_id
     LEFT JOIN orders o ON o.table_id = t.table_id 
         AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
     WHERE t.status IN ('trong', 'da_dat')
         AND o.order_id IS NULL
     ORDER BY f.floor_name ASC, t.table_name ASC"
);

$menuItems = db_fetch_all(
    $db,
    $dbType,
    "SELECT
        mi.item_id,
        mi.item_name,
        mi.price,
        mi.description,
        mi.status,
        mi.category_id,
        c.category_name
     FROM menu_items mi
     LEFT JOIN categories c ON c.category_id = mi.category_id
     WHERE mi.status = 'con_hang'
     ORDER BY c.category_name ASC, mi.item_name ASC"
);

/*
|--------------------------------------------------------------------------
| Món được chọn từ menu.php
|--------------------------------------------------------------------------
*/
$preselectedItemId = (int)($_GET['item_id'] ?? 0);
$preselectedQty = max(1, (int)($_GET['qty'] ?? 1));

$preselectedItem = null;
if ($preselectedItemId > 0) {
    foreach ($menuItems as $mi) {
        if ((int)$mi['item_id'] === $preselectedItemId) {
            $preselectedItem = $mi;
            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Form data
|--------------------------------------------------------------------------
*/
$formData = [
    'customer_name'    => $_POST['customer_name'] ?? ($loggedUser['full_name'] ?? ''),
    'phone'            => $_POST['phone']         ?? ($loggedUser['phone'] ?? ''),
    'email'            => $_POST['email']         ?? ($loggedUser['email'] ?? ''),
    'table_id'         => $_POST['table_id'] ?? '',
    'reservation_time' => $_POST['reservation_time'] ?? '',
    'number_of_people' => $_POST['number_of_people'] ?? '',
    'note'             => $_POST['note'] ?? '',
    'payment_percent'  => $_POST['payment_percent'] ?? '50',
];

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $customerName     = trim($_POST['customer_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $tableId          = (int)($_POST['table_id'] ?? 0);
    $reservationTime  = trim($_POST['reservation_time'] ?? '');
    $numberOfPeople   = (int)($_POST['number_of_people'] ?? 0);
    $paymentPercent   = (int)($_POST['payment_percent'] ?? 50);
    $paymentMethod    = 'bank_transfer';
    $note             = trim($_POST['note'] ?? '');

    $itemIds    = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $itemNotes  = $_POST['item_note'] ?? [];

    if ($customerName === '') $errors[] = 'Vui lòng nhập họ tên khách hàng.';
    if ($phone === '') $errors[] = 'Vui lòng nhập số điện thoại.';
    if ($tableId <= 0) $errors[] = 'Vui lòng chọn bàn.';
    if ($reservationTime === '') $errors[] = 'Vui lòng chọn thời gian đặt bàn.';
    elseif (strtotime(str_replace('T', ' ', $reservationTime)) <= time()) $errors[] = 'Thời gian đặt bàn phải là thời điểm trong tương lai.';
    if ($numberOfPeople <= 0) $errors[] = 'Số người phải lớn hơn 0.';
    if (!in_array($paymentPercent, [50, 100], true)) $errors[] = 'Mức thanh toán không hợp lệ.';
    
    $durationMinutes = reservation_duration_minutes($numberOfPeople);
    $endTime = reservation_compute_end_time(str_replace('T', ' ', $reservationTime), $numberOfPeople);

    $selectedItems = [];
    $estimatedTotal = 0;

    if (is_array($itemIds)) {
        foreach ($itemIds as $index => $itemIdRaw) {
            $itemId = (int)$itemIdRaw;
            $qty = isset($quantities[$index]) ? (int)$quantities[$index] : 0;
            $lineNote = trim($itemNotes[$index] ?? '');

            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $menuItem = db_fetch_one(
                $db,
                $dbType,
                "SELECT item_id, item_name, price, status
                 FROM menu_items
                 WHERE item_id = ?
                 LIMIT 1
                 FOR UPDATE",
                [$itemId]
            );

            if (!$menuItem) {
                continue;
            }

            if (($menuItem['status'] ?? '') !== 'con_hang') {
                continue;
            }

            $unitPrice = (float)$menuItem['price'];
            $lineTotal = $unitPrice * $qty;

            $selectedItems[] = [
                'item_id'    => (int)$menuItem['item_id'],
                'item_name'  => $menuItem['item_name'],
                'quantity'   => $qty,
                'unit_price' => $unitPrice,
                'note'       => $lineNote,
                'line_total' => $lineTotal,
            ];

            $estimatedTotal += $lineTotal;
        }
    }

    if ($estimatedTotal <= 0) {
        $errors[] = 'Vui lòng chọn ít nhất 1 món để đặt bàn.';
    }

    try {
        if (!$errors) {
            db_begin($db, $dbType);

            $tableRow = db_fetch_one(
                $db,
                $dbType,
                "SELECT table_id, status, capacity
                 FROM tables
                 WHERE table_id = ?
                 LIMIT 1
                 FOR UPDATE",
                [$tableId]
            );

            if (!$tableRow) {
                throw new Exception('Bàn không tồn tại.');
            }

            if (!in_array($tableRow['status'], ['trong', 'da_dat'], true)) {
                throw new Exception('Bàn hiện không thể đặt.');
            }

            if ((int)$tableRow['capacity'] < $numberOfPeople) {
                throw new Exception('Số người vượt quá sức chứa của bàn.');
            }

            // Kiểm tra conflict time slot
            $reservationTimeFormatted = str_replace('T', ' ', $reservationTime);
            if (strlen($reservationTimeFormatted) === 16) {
                $reservationTimeFormatted .= ':00';
            }
            
            $endTimeFormatted = $endTime;

            $conflictCheck = null;
            if ($dbType === 'mysqli' && $db instanceof mysqli) {
                $conflictCheck = reservation_find_time_conflict(
                    $db,
                    $tableId,
                    $reservationTimeFormatted,
                    $endTimeFormatted
                );
            } else {
                $conflictCheck = db_fetch_one(
                    $db,
                    $dbType,
                    "SELECT reservation_id, reservation_time, end_time
                     FROM reservations
                     WHERE table_id = ?
                       AND status IN ('cho_xac_nhan', 'da_xac_nhan', 'da_checkin')
                       AND (
                           (start_time IS NOT NULL AND end_time IS NOT NULL AND start_time < ? AND end_time > ?)
                           OR (start_time IS NULL AND end_time IS NULL AND reservation_time < ? AND DATE_ADD(reservation_time, INTERVAL 2 HOUR) > ?)
                       )
                     LIMIT 1
                     FOR UPDATE",
                    [$tableId, $endTimeFormatted, $reservationTimeFormatted, $endTimeFormatted, $reservationTimeFormatted]
                );
            }

            if ($conflictCheck) {
                $conflictStart = $conflictCheck['start_time'] ?? $conflictCheck['reservation_time'];
                $conflictEnd = $conflictCheck['end_time'] ?? date('Y-m-d H:i:s', strtotime($conflictCheck['reservation_time'] . ' +2 hours'));
                throw new Exception('Bàn này đã được đặt trong khung giờ từ ' . date('H:i d/m/Y', strtotime($conflictStart)) . ' đến ' . date('H:i d/m/Y', strtotime($conflictEnd)) . '. Vui lòng chọn bàn khác hoặc thời gian khác.');
            }

            $customerId = 0;
            $customerRow = current_customer_from_session($db, $dbType);

            if ($customerRow) {
                $customerId = (int)$customerRow['customer_id'];

                db_prepare_and_execute(
                    $db,
                    $dbType,
                    "UPDATE customers
                     SET customer_name = ?, phone = ?, email = ?
                     WHERE customer_id = ?",
                    [
                        $customerName,
                        $phone,
                        $email !== '' ? $email : null,
                        $customerId
                    ]
                );
            } else {
                $existingCustomer = db_fetch_one(
                    $db,
                    $dbType,
                    "SELECT customer_id, customer_name, phone, email
                     FROM customers
                     WHERE phone = ?
                     LIMIT 1",
                    [$phone]
                );

                if (!$existingCustomer && $email !== '') {
                    $existingCustomer = db_fetch_one(
                        $db,
                        $dbType,
                        "SELECT customer_id, customer_name, phone, email
                         FROM customers
                         WHERE email = ?
                         LIMIT 1",
                        [$email]
                    );
                }

                if ($existingCustomer) {
                    $customerId = (int)$existingCustomer['customer_id'];

                    db_prepare_and_execute(
                        $db,
                        $dbType,
                        "UPDATE customers
                         SET customer_name = ?, phone = ?, email = ?
                         WHERE customer_id = ?",
                        [
                            $customerName,
                            $phone,
                            $email !== '' ? $email : null,
                            $customerId
                        ]
                    );
                } else {
                    try {
                        db_prepare_and_execute(
                            $db,
                            $dbType,
                            "INSERT INTO customers (customer_name, phone, email, created_by, created_at)
                             VALUES (?, ?, ?, ?, NOW())",
                            [
                                $customerName,
                                $phone,
                                $email !== '' ? $email : null,
                                $_SESSION['user']['user_id'] ?? 1
                            ]
                        );
                    } catch (Throwable $e) {
                        db_prepare_and_execute(
                            $db,
                            $dbType,
                            "INSERT INTO customers (customer_name, phone, email, created_at)
                             VALUES (?, ?, ?, NOW())",
                            [
                                $customerName,
                                $phone,
                                $email !== '' ? $email : null
                            ]
                        );
                    }

                    $customerId = db_last_insert_id($db, $dbType);
                }
            }

            if ($customerId <= 0) {
                throw new Exception('Không xác định được khách hàng để tạo đơn.');
            }

            $updatedCustomer = db_fetch_one(
                $db,
                $dbType,
                "SELECT customer_id, customer_name, phone, email
                 FROM customers
                 WHERE customer_id = ?
                 LIMIT 1",
                [$customerId]
            );

            if ($updatedCustomer) {
                sync_customer_session($updatedCustomer);
            }

            $reservationTimeFormatted = str_replace('T', ' ', $reservationTime);
            if (strlen($reservationTimeFormatted) === 16) {
                $reservationTimeFormatted .= ':00';
            }

            // Get user_id from session or customer record
            $userId = (int)($_SESSION['user']['user_id'] ?? 0);
            
            // If no user_id in session, try to get from customer record
            if ($userId === 0 && $customerId > 0) {
                $customerRecord = db_fetch_one(
                    $db,
                    $dbType,
                    "SELECT user_id FROM customers WHERE customer_id = ? LIMIT 1",
                    [$customerId]
                );
                $userId = (int)($customerRecord['user_id'] ?? 0);
            }
            
            // If still no user_id, use a default value or create one
            if ($userId === 0) {
                $userId = 1; // Default user_id
            }

            db_prepare_and_execute(
                $db,
                $dbType,
                "INSERT INTO reservations
                 (user_id, table_id, reservation_time, number_of_people, note, status, created_at, start_time, end_time)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)",
                [
                    $userId,
                    $tableId,
                    $reservationTimeFormatted,
                    $numberOfPeople,
                    $note !== '' ? $note : 'Đặt trước',
                    'da_xac_nhan',
                    $reservationTimeFormatted,
                    $endTimeFormatted
                ]
            );

            $reservationId = db_last_insert_id($db, $dbType);

            // Lưu món đặt trước vào reservation_items
            foreach ($selectedItems as $item) {
                db_prepare_and_execute(
                    $db,
                    $dbType,
                    "INSERT INTO reservation_items
                     (reservation_id, item_id, quantity, unit_price, note)
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $reservationId,
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['note'] !== '' ? $item['note'] : null
                    ]
                );
            }

            // Tạo order với trạng thái da_dat_coc — khách đã cọc nhưng chưa đến
            // Khi waiter nhận bàn (checkin), order sẽ chuyển sang 'moi'
            // waiter_id = NULL vì khách đặt online, chưa có phục vụ phụ trách
            db_prepare_and_execute(
                $db,
                $dbType,
                "INSERT INTO orders
                 (table_id, customer_id, reservation_id, waiter_id, status, total_amount, paid_amount, guest_count, order_time)
                 VALUES (?, ?, ?, NULL, 'da_dat_coc', ?, 0, ?, NOW())",
                [
                    $tableId,
                    $customerId,
                    $reservationId,
                    $estimatedTotal,
                    $numberOfPeople,
                ]
            );
            $newOrderId = db_last_insert_id($db, $dbType);

            // Copy reservation_items → order_details (để bếp thấy trước)
            foreach ($selectedItems as $item) {
                db_prepare_and_execute(
                    $db,
                    $dbType,
                    "INSERT INTO order_details (order_id, item_id, quantity, unit_price, note, item_status)
                     VALUES (?, ?, ?, ?, ?, 'moi')",
                    [
                        $newOrderId,
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['note'] !== '' ? $item['note'] : null,
                    ]
                );
            }

            $depositAmount = round($estimatedTotal * ($paymentPercent / 100), 2);
            $paymentType   = ($paymentPercent === 100) ? 'full_payment' : 'deposit';
            $transferContent = 'DATBAN ' . $reservationId . ' ' . preg_replace('/[^A-Za-z0-9]/', '', strtoupper($phone));

            $paymentNote = 'Đặt từ website';
            if ($note !== '') {
                $paymentNote .= ' | ' . $note;
            }
            $paymentNote .= ' | Mức thanh toán trước: ' . $paymentPercent . '%';
            $paymentNote .= ' | Noi dung CK: ' . $transferContent;
            $paymentNote .= ' | Tong mon dat truoc: ' . $estimatedTotal;

            // Hệ thống hiện chưa có cổng thanh toán/callback xác nhận tự động.
            // Để luồng 50% / 100% hoạt động ngay tại thu ngân, đánh dấu khoản trả trước là success.
            // Nếu sau này có trang đối soát chuyển khoản riêng, có thể đổi lại thành pending.
            db_prepare_and_execute(
                $db,
                $dbType,
                "INSERT INTO reservation_payments
                 (reservation_id, cashier_id, payment_type, payment_percent, amount, payment_method, payment_time, payment_status, note)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)",
                [
                    $reservationId,
                    $_SESSION['user']['user_id'] ?? 1,
                    $paymentType,
                    $paymentPercent,
                    $depositAmount,
                    $paymentMethod,
                    'thanh_cong',
                    $paymentNote
                ]
            );

            db_prepare_and_execute(
                $db,
                $dbType,
                "UPDATE tables SET status = 'da_dat' WHERE table_id = ?",
                [$tableId]
            );

            db_commit($db, $dbType);

            $successReservationId = $reservationId;
            $success = 'Bạn đã đặt bàn thành công. Khoản thanh toán trước đã được ghi nhận vào đơn.';

            $formData = [
                'customer_name'    => '',
                'phone'            => '',
                'email'            => '',
                'table_id'         => '',
                'reservation_time' => '',
                'number_of_people' => '',
                'note'             => '',
                'payment_percent'  => '50',
            ];

            $preselectedItemId = 0;
            $preselectedQty = 1;
            $preselectedItem = null;
        }
    } catch (Throwable $e) {
        db_rollback($db, $dbType);
        $errors[] = 'Không thể lưu đặt bàn: ' . $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="reservation-page" style="padding:40px 0;background:#f8f5f0;">
    <div class="container" style="max-width:1100px;margin:0 auto;">
        <div class="reservation-hero" style="margin-bottom:24px;background:linear-gradient(135deg, rgba(180,83,9,.92), rgba(220,38,38,.78)), url('/quanlynhahang/assets/images/featured-2.jpg') center/cover no-repeat;border-radius:24px;padding:48px 40px;">
            <div class="reservation-hero-content" style="text-align:left;max-width:760px;">
                <span style="display:inline-block;padding:8px 14px;border-radius:999px;background:#fff7ed;color:#a16207;font-weight:700;font-size:13px;">Đặt bàn trực tuyến</span>
                <h1 style="margin:16px 0 10px;font-size:42px;color:#fff;">Đặt bàn của bạn</h1>
                
            </div>
        </div>

        <div style="display:flex;justify-content:center;">
            <div style="width:100%;max-width:980px;background:#fff;padding:28px;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,0.08);">
                <?php if ($success): ?>
                    <div style="padding:14px 16px;background:#eafaf1;color:#166534;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:16px;font-weight:600;">
                        <?= e($success) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div style="padding:14px 16px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;margin-bottom:16px;">
                        <ul style="margin:0;padding-left:18px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" id="reservationForm" novalidate>
                    <?= csrf_field() ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label>Họ và tên</label>
                            <input type="text" name="customer_name" required value="<?= e($formData['customer_name']) ?>" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
                        </div>

                        <div>
                            <label>Số điện thoại</label>
                            <input type="text" name="phone" id="phoneInput" required value="<?= e($formData['phone']) ?>" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
                        </div>

                        <div>
                            <label>Email</label>
                            <input type="email" name="email" value="<?= e($formData['email']) ?>" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
                        </div>

                        <div>
                            <label>Chọn bàn</label>
                            <select name="table_id" id="tableSelect" required style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;" onchange="updateMaxGuests()">
                                <option value="" data-capacity="0">-- Chọn bàn --</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?= (int)$table['table_id'] ?>"
                                        data-capacity="<?= (int)$table['capacity'] ?>"
                                        <?= ((string)$formData['table_id'] === (string)$table['table_id']) ? 'selected' : '' ?>>
                                        <?= e($table['table_name']) ?> - <?= (int)$table['capacity'] ?> chỗ - <?= e($table['floor_name'] ?? 'Không rõ tầng') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Thời gian đến</label>
                            <input type="datetime-local" name="reservation_time" id="reservationTime" required
                                value="<?= e($formData['reservation_time']) ?>"
                                min="<?= date('Y-m-d\TH:i') ?>"
                                style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
                            <small style="color:#6b7280; font-size:12px; margin-top:4px; display:block;">Thời gian bắt đầu sử dụng bàn</small>
                        </div>

                        <div>
                            <label>Số khách</label>
                            <input type="number" min="1" max="99" id="guestInput" name="number_of_people" required value="<?= e($formData['number_of_people']) ?>" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
                            <small id="guestHint" style="color:#6b7280; font-size:12px; margin-top:4px; display:block;"></small>
                        </div>
                    </div>

                    <div style="margin-top:18px;">
                        <label>Yêu cầu thêm</label>
                        <textarea name="note" rows="3" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;" placeholder="Ví dụ: sinh nhật, gần cửa sổ..."><?= e($formData['note']) ?></textarea>
                    </div>

                    <hr style="margin:28px 0;">

                    <div style="margin-bottom:16px;">
                        <h2 style="margin:0 0 8px;font-size:24px;color:#1f2937;">Chọn món trước</h2>
                    </div>



                    <div id="selected-items-wrapper">
                        <div class="item-row" style="display:grid;grid-template-columns:2fr 100px 2fr 80px;gap:12px;align-items:center;margin-bottom:12px;">
                            <select name="item_id[]" class="item-select" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
                                <option value="">-- Chọn món --</option>
                                <?php foreach ($menuItems as $item): ?>
                                    <option
                                        value="<?= (int)$item['item_id'] ?>"
                                        data-price="<?= (float)$item['price'] ?>"
                                        <?= $preselectedItemId === (int)$item['item_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($item['item_name']) ?> - <?= format_currency($item['price']) ?> - <?= e($item['category_name'] ?? 'Khác') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="number" name="quantity[]" class="item-qty" min="1" value="<?= (int)$preselectedQty ?>" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">

                            <input type="text" name="item_note[]" placeholder="Ghi chú món" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">

                            <button type="button" class="remove-row-btn" style="padding:12px;border:none;border-radius:10px;background:#ef4444;color:#fff;">Xóa</button>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                        <button type="button" id="addItemRowBtn" style="padding:12px 16px;border:none;border-radius:10px;background:#0f766e;color:#fff;font-weight:600;">
                            + Thêm món
                        </button>
                    </div>

                    <div id="orderPreviewBox" style="padding:14px 16px;border:1px solid #e5e7eb;background:#f9fafb;border-radius:10px;margin-bottom:16px;">
                       Bạn chưa chọn món nào
                    </div>

                    <input type="hidden" name="estimated_total" id="estimated_total_hidden" value="0">

                    <hr style="margin:28px 0;">

                    <div style="margin-bottom:16px;">
                        <h2 style="margin:0 0 8px;font-size:24px;color:#1f2937;">Đặt cọc giữ bàn</h2>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label>Hình thức thanh toán</label>
                            <input type="text" value="Chuyển khoản" readonly style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;background:#f3f4f6;">
                        </div>

                        <div>
                            <label>Tổng tiền món đã chọn</label>
                            <div id="estimatedTotalDisplay" style="padding:12px;border:1px solid #ddd;border-radius:10px;background:#fafafa;">0 đ</div>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label>Chọn mức đặt cọc</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px;">
                            <label><input type="radio" name="payment_percent" value="50" <?= ($formData['payment_percent'] == '50') ? 'checked' : '' ?>> Đặt cọc 50% giá trị đơn</label>
                            <label><input type="radio" name="payment_percent" value="100" <?= ($formData['payment_percent'] == '100') ? 'checked' : '' ?>> Thanh toán toàn bộ</label>
                        </div>
                    </div>

                    <div id="depositPreview" style="margin-top:14px;padding:14px 16px;border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:10px;display:none;">
                        
                    </div>

                    <div style="margin-top:16px;padding:18px;border:1px solid #dbeafe;background:#eff6ff;border-radius:14px;">
                        <h3 style="margin:0 0 14px;color:#1d4ed8;">Thông tin thanh toán</h3>

                        <div style="display:grid;grid-template-columns:220px 1fr;gap:18px;align-items:start;">
                            <div style="text-align:center;">
                                <img id="qrImage" src="" alt="QR chuyển khoản" style="width:200px;height:200px;object-fit:contain;border:1px solid #dbeafe;border-radius:12px;background:#fff;display:none;margin:0 auto;">
                                <div id="qrPlaceholder" style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;border:1px dashed #93c5fd;border-radius:12px;background:#fff;color:#6b7280;margin:0 auto;">
                                    QR sẽ hiển thị ở đây
                                </div>
                            </div>

                            <div>
                                <div style="margin-bottom:10px;"><strong>Ngân hàng:</strong> <?= e($bankConfig['bank_name']) ?></div>
                                <div style="margin-bottom:10px;"><strong>Số tài khoản:</strong> <?= e($bankConfig['account_no']) ?></div>
                                <div style="margin-bottom:10px;"><strong>Chủ tài khoản:</strong> <?= e($bankConfig['account_name']) ?></div>
                                <div style="margin-bottom:10px;"><strong>Số tiền cần chuyển:</strong> <span id="transferAmountText">0 đ</span></div>
                                <div style="margin-bottom:10px;"><strong>Nội dung thanh toán:</strong></div>
                                <input type="text" id="transferContentText" readonly value="" style="width:100%;padding:12px;border:1px solid #bfdbfe;border-radius:10px;background:#fff;">
                                <p style="margin:10px 0 0;color:#6b7280;font-size:14px;">
                                    Khách cần chuyển đúng số tiền và đúng nội dung để hệ thống dễ đối soát.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
                        <button type="submit" style="padding:13px 20px;border:none;border-radius:10px;background:#b45309;color:#fff;font-weight:700;">
                            Hoàn tất đặt bàn
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
function updateMaxGuests() {
    const select = document.getElementById('tableSelect');
    const guestInput = document.getElementById('guestInput');
    const hint = document.getElementById('guestHint');
    const selected = select.options[select.selectedIndex];
    const capacity = parseInt(selected.getAttribute('data-capacity') || '0');

    if (capacity > 0) {
        guestInput.max = capacity;
        hint.textContent = 'Tối đa ' + capacity + ' khách cho bàn này';
        hint.style.color = '#6b7280';
        if (parseInt(guestInput.value) > capacity) {
            guestInput.value = capacity;
        }
    } else {
        guestInput.max = 99;
        hint.textContent = '';
    }
}

// Chặn nhập vượt quá max khi gõ
document.addEventListener('DOMContentLoaded', function() {
    updateMaxGuests();

    const guestInput = document.getElementById('guestInput');
    guestInput.addEventListener('input', function() {
        const max = parseInt(this.max);
        const val = parseInt(this.value);
        const hint = document.getElementById('guestHint');
        if (max > 0 && val > max) {
            this.value = max;
            hint.textContent = 'Tối đa ' + max + ' khách cho bàn này';
            hint.style.color = '#dc2626';
        } else if (max > 0) {
            hint.textContent = 'Tối đa ' + max + ' khách cho bàn này';
            hint.style.color = '#6b7280';
        }
    });

    // Validate khi submit
    document.getElementById('reservationForm').addEventListener('submit', function(e) {
        const select = document.getElementById('tableSelect');
        const guestInput = document.getElementById('guestInput');
        const selected = select.options[select.selectedIndex];
        const capacity = parseInt(selected.getAttribute('data-capacity') || '0');
        const val = parseInt(guestInput.value);

        // Chỉ kiểm tra số khách, các validation khác để PHP xử lý
        if (capacity > 0 && val > capacity) {
            e.preventDefault();
            const hint = document.getElementById('guestHint');
            hint.textContent = 'Số khách không được vượt quá ' + capacity + ' người!';
            hint.style.color = '#dc2626';
            guestInput.focus();
            return;
        }
    });
});
</script>

<script>
(function () {
    const wrapper = document.getElementById('selected-items-wrapper');
    const addBtn = document.getElementById('addItemRowBtn');
    const depositPreview = document.getElementById('depositPreview');
    const estimatedTotalDisplay = document.getElementById('estimatedTotalDisplay');
    const estimatedTotalHidden = document.getElementById('estimated_total_hidden');
    const orderPreviewBox = document.getElementById('orderPreviewBox');
    const transferAmountText = document.getElementById('transferAmountText');
    const transferContentText = document.getElementById('transferContentText');
    const qrImage = document.getElementById('qrImage');
    const qrPlaceholder = document.getElementById('qrPlaceholder');
    const phoneInput = document.getElementById('phoneInput');

    const BANK_BIN = '<?= e($bankConfig['bank_bin']) ?>';
    const ACCOUNT_NO = '<?= e($bankConfig['account_no']) ?>';
    const ACCOUNT_NAME = '<?= e($bankConfig['account_name']) ?>';

    function formatMoney(value) {
        return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ';
    }

    function cleanPhone(value) {
        return String(value || '').replace(/[^0-9A-Za-z]/g, '').toUpperCase();
    }

    function createTransferContent() {
        const phone = cleanPhone(phoneInput ? phoneInput.value : '');
        return phone ? ('DATBAN ' + phone) : 'DATBAN KHACH';
    }

    function buildQrUrl(amount, content) {
        return 'https://img.vietqr.io/image/' + BANK_BIN + '-' + ACCOUNT_NO + '-compact2.png?amount='
            + amount + '&addInfo=' + encodeURIComponent(content)
            + '&accountName=' + encodeURIComponent(ACCOUNT_NAME);
    }

    function bindRowEvents(row) {
        const select = row.querySelector('.item-select');
        const qty = row.querySelector('.item-qty');
        const removeBtn = row.querySelector('.remove-row-btn');

        if (select) select.addEventListener('change', updateSummary);
        if (qty) qty.addEventListener('input', updateSummary);

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const rows = wrapper.querySelectorAll('.item-row');
                if (rows.length === 1) {
                    row.querySelector('.item-select').value = '';
                    row.querySelector('.item-qty').value = 1;
                    const noteInput = row.querySelector('input[name="item_note[]"]');
                    if (noteInput) noteInput.value = '';
                } else {
                    row.remove();
                }
                updateSummary();
            });
        }
    }

    function createRow() {
        const div = document.createElement('div');
        div.className = 'item-row';
        div.style.display = 'grid';
        div.style.gridTemplateColumns = '2fr 100px 2fr 80px';
        div.style.gap = '12px';
        div.style.alignItems = 'center';
        div.style.marginBottom = '12px';

        div.innerHTML = `
            <select name="item_id[]" class="item-select" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
                <option value="">-- Chọn món --</option>
                <?php foreach ($menuItems as $item): ?>
                    <option value="<?= (int)$item['item_id'] ?>" data-price="<?= (float)$item['price'] ?>">
                        <?= e($item['item_name']) ?> - <?= format_currency($item['price']) ?> - <?= e($item['category_name'] ?? 'Khác') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="quantity[]" class="item-qty" min="1" value="1" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
            <input type="text" name="item_note[]" placeholder="Ghi chú cho món" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;">
            <button type="button" class="remove-row-btn" style="padding:12px;border:none;border-radius:10px;background:#ef4444;color:#fff;">Xóa</button>
        `;

        wrapper.appendChild(div);
        bindRowEvents(div);
        updateSummary();
    }

    function updateDepositPreview(total) {
        const checked = document.querySelector('input[name="payment_percent"]:checked');
        const percent = checked ? Number(checked.value) : 50;
        const content = createTransferContent();

        transferContentText.value = content;

        if (total <= 0) {
            depositPreview.style.display = 'none';
            depositPreview.textContent = '';
            transferAmountText.textContent = '0 đ';
            qrImage.style.display = 'none';
            qrImage.src = '';
            qrPlaceholder.style.display = 'flex';
            return;
        }

        const deposit = Math.round(total * percent / 100);

        depositPreview.style.display = 'none';
        depositPreview.textContent = '';

        transferAmountText.textContent = formatMoney(deposit);
        qrImage.src = buildQrUrl(deposit, content);
        qrImage.style.display = 'block';
        qrPlaceholder.style.display = 'none';
    }

    function updateSummary() {
        const rows = wrapper.querySelectorAll('.item-row');
        let total = 0;
        let lines = [];

        rows.forEach(function (row) {
            const select = row.querySelector('.item-select');
            const qtyInput = row.querySelector('.item-qty');
            if (!select || !qtyInput) return;

            const option = select.options[select.selectedIndex];
            const itemId = select.value;
            const qty = Number(qtyInput.value || 0);
            const price = Number(option ? option.getAttribute('data-price') : 0);
            const itemName = option ? option.textContent : '';

            if (itemId && qty > 0 && price > 0) {
                const lineTotal = price * qty;
                total += lineTotal;
                lines.push(itemName + ' | SL: ' + qty + ' | Thành tiền: ' + formatMoney(lineTotal));
            }
        });

        estimatedTotalDisplay.textContent = formatMoney(total);
        estimatedTotalHidden.value = total;

        if (lines.length === 0) {
            orderPreviewBox.textContent = 'Chưa chọn món nào.';
        } else {
            orderPreviewBox.innerHTML = lines.map(line => '<div style="margin-bottom:6px;">• ' + line + '</div>').join('');
        }

        updateDepositPreview(total);
    }

    addBtn.addEventListener('click', createRow);
    document.querySelectorAll('.item-row').forEach(bindRowEvents);
    document.querySelectorAll('input[name="payment_percent"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateDepositPreview(Number(estimatedTotalHidden.value || 0));
        });
    });

    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            updateDepositPreview(Number(estimatedTotalHidden.value || 0));
        });
    }

    updateSummary();
})();
</script>

<?php if ($success): ?>
<script>
    alert("Bạn đã đặt bàn thành công.");
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
