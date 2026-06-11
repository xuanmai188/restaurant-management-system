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
| Hàm tiện ích
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

if (!function_exists('format_datetime_vn')) {
    function format_datetime_vn($datetime)
    {
        if (!$datetime) return '--';
        $ts = strtotime($datetime);
        if (!$ts) return e($datetime);
        return date('d/m/Y H:i', $ts);
    }
}

function db_prepare_and_execute($db, string $dbType, string $sql, array $params = [])
{
    if ($dbType === 'pdo') {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        die('Lỗi prepare SQL: ' . $db->error);
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

    $stmt->execute();
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

function reservation_status_label($status)
{
    $status = strtolower((string)$status);

    return match ($status) {
        'cho_xac_nhan' => 'Chờ xác nhận',
        'da_xac_nhan'  => 'Đã xác nhận',
        'da_checkin'   => 'Đã check-in',
        'khong_den'    => 'Không đến',
        'hoan_thanh'   => 'Hoàn thành',
        'da_huy'       => 'Đã hủy',
        default        => ucfirst($status ?: 'Không rõ'),
    };
}

function reservation_status_class($status)
{
    $status = strtolower((string)$status);

    return match ($status) {
        'cho_xac_nhan' => 'status-pending',
        'da_xac_nhan'  => 'status-confirmed',
        'hoan_thanh'   => 'status-completed',
        'da_huy'       => 'status-cancelled',
        default        => 'status-default',
    };
}

function payment_status_label($status)
{
    $status = strtolower((string)$status);

    return match ($status) {
        'pending', 'cho_xu_ly'          => 'Chờ thanh toán',
        'paid', 'completed', 'thanh_cong' => 'Đã thanh toán',
        'success'                        => 'Đã thanh toán',
        'failed', 'that_bai'            => 'Thất bại',
        'hoan_tien'                      => 'Hoàn tiền',
        default                          => $status ? ucfirst($status) : 'Chưa có',
    };
}

function payment_status_class($status)
{
    $status = strtolower((string)$status);

    return match ($status) {
        'pending', 'cho_xu_ly'          => 'pay-pending',
        'paid', 'completed', 'thanh_cong', 'success' => 'pay-paid',
        'failed', 'that_bai'            => 'pay-failed',
        'hoan_tien'                      => 'pay-failed',
        default                          => 'pay-default',
    };
}

/*
|--------------------------------------------------------------------------
| Tự động chuyển reservation quá giờ sang 'khong_den'
|--------------------------------------------------------------------------
*/
if ($db && $dbType === 'mysqli') {
    // Gọi hàm auto-cancel từ functions.php
    auto_cancel_expired_reservations();
}

/*
|--------------------------------------------------------------------------
| Xử lý hủy đặt bàn
|--------------------------------------------------------------------------
*/
$cancelMessage = '';
$cancelMessageType = '';

/*
|--------------------------------------------------------------------------
| Xác định khách hàng hiện tại
|--------------------------------------------------------------------------
*/
$sessionUserId     = (int)($_SESSION['user']['user_id'] ?? 0);
$sessionCustomerId = (int)($_SESSION['customer']['customer_id'] ?? 0);
$sessionPhone      = trim($_SESSION['customer']['phone'] ?? $_SESSION['user']['phone'] ?? '');
$sessionEmail      = trim($_SESSION['customer']['email'] ?? $_SESSION['user']['email'] ?? '');

$customer = null;

// Ưu tiên tìm theo user_id (khi đăng nhập)
if ($sessionUserId > 0) {
    $customer = db_fetch_one(
        $db,
        $dbType,
        "SELECT customer_id, customer_name, phone, email, created_at
         FROM customers
         WHERE user_id = ?
         LIMIT 1",
        [$sessionUserId]
    );
}

// Nếu không tìm thấy, thử tìm theo customer_id
if (!$customer && $sessionCustomerId > 0) {
    $customer = db_fetch_one(
        $db,
        $dbType,
        "SELECT customer_id, customer_name, phone, email, created_at
         FROM customers
         WHERE customer_id = ?
         LIMIT 1",
        [$sessionCustomerId]
    );
}

// Nếu vẫn không có, thử tìm theo phone
if (!$customer && $sessionPhone !== '') {
    $customer = db_fetch_one(
        $db,
        $dbType,
        "SELECT customer_id, customer_name, phone, email, created_at
         FROM customers
         WHERE phone = ?
         LIMIT 1",
        [$sessionPhone]
    );
}

// Cuối cùng thử tìm theo email
if (!$customer && $sessionEmail !== '') {
    $customer = db_fetch_one(
        $db,
        $dbType,
        "SELECT customer_id, customer_name, phone, email, created_at
         FROM customers
         WHERE email = ?
         LIMIT 1",
        [$sessionEmail]
    );
}

// Xử lý hủy đặt bàn (sau khi đã có $customer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation'])) {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    
    if ($reservationId > 0 && $customer) {
        // Kiểm tra reservation có thuộc về khách hàng này không
        $reservation = db_fetch_one(
            $db,
            $dbType,
            "SELECT r.*, rp.amount AS prepaid_amount
             FROM reservations r
             LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
             WHERE r.reservation_id = ? AND r.user_id = ?
             LIMIT 1",
            [$reservationId, $sessionUserId]
        );
        
        if (!$reservation) {
            $cancelMessage = 'Không tìm thấy đơn đặt bàn hoặc bạn không có quyền hủy đơn này.';
            $cancelMessageType = 'error';
        } elseif ($reservation['status'] === 'da_huy') {
            $cancelMessage = 'Đơn đặt bàn này đã được hủy trước đó.';
            $cancelMessageType = 'error';
        } elseif ($reservation['status'] === 'hoan_thanh') {
            $cancelMessage = 'Không thể hủy đơn đặt bàn đã hoàn thành.';
            $cancelMessageType = 'error';
        } else {
            // Cập nhật trạng thái thành cancelled
            db_prepare_and_execute(
                $db,
                $dbType,
                "UPDATE reservations SET status = 'da_huy' WHERE reservation_id = ?",
                [$reservationId]
            );
            
            $prepaidAmount = (float)($reservation['prepaid_amount'] ?? 0);
            
            if ($prepaidAmount > 0) {
                $cancelMessage = "Đã hủy đặt bàn #$reservationId thành công. Số tiền đã đặt cọc " . format_currency($prepaidAmount) . " sẽ không được hoàn trả theo chính sách của nhà hàng.";
            } else {
                $cancelMessage = "Đã hủy đặt bàn #$reservationId thành công.";
            }
            $cancelMessageType = 'success';
            
            // Reload để cập nhật danh sách
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Lấy danh sách reservations
|--------------------------------------------------------------------------
*/
$reservations = [];
$stats = [
    'total'        => 0,
    'cho_xac_nhan' => 0,
    'da_xac_nhan'  => 0,
    'hoan_thanh'   => 0,
];

if ($customer) {
    $customerId = (int)$customer['customer_id'];

    // Tìm reservations theo user_id (vì bảng reservations không có customer_id)
    // Cần tìm user_id từ session hoặc từ customer record
    $reservations = [];
    
    if ($sessionUserId > 0) {
        // Tìm theo user_id từ session
        $reservations = db_fetch_all(
            $db,
            $dbType,
            "SELECT
                r.reservation_id,
                r.table_id,
                r.reservation_time,
                r.number_of_people,
                r.note,
                r.status,
                r.created_at,
                t.table_name,
                rp.payment_percent,
                rp.amount AS payment_amount,
                rp.payment_method,
                rp.payment_status,
                rp.payment_time,
                (SELECT COUNT(*) FROM orders o WHERE o.reservation_id = r.reservation_id) AS has_order
             FROM reservations r
             LEFT JOIN tables t ON t.table_id = r.table_id
             LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC, r.reservation_id DESC",
            [$sessionUserId]
        );
    } else {
        // Nếu không có user_id trong session, tìm qua orders
        $reservations = db_fetch_all(
            $db,
            $dbType,
            "SELECT DISTINCT
                r.reservation_id,
                r.table_id,
                r.reservation_time,
                r.number_of_people,
                r.note,
                r.status,
                r.created_at,
                t.table_name,
                rp.payment_percent,
                rp.amount AS payment_amount,
                rp.payment_method,
                rp.payment_status,
                rp.payment_time,
                (SELECT COUNT(*) FROM orders o WHERE o.reservation_id = r.reservation_id) AS has_order
             FROM reservations r
             LEFT JOIN tables t ON t.table_id = r.table_id
             LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
             INNER JOIN orders o ON o.reservation_id = r.reservation_id
             WHERE o.customer_id = ?
             ORDER BY r.created_at DESC, r.reservation_id DESC",
            [$customerId]
        );
    }

    $stats['total'] = count($reservations);

    foreach ($reservations as $item) {
        $st = strtolower((string)$item['status']);
        if ($st === 'cho_xac_nhan') $stats['cho_xac_nhan']++;
        if ($st === 'da_xac_nhan') $stats['da_xac_nhan']++;
        if ($st === 'hoan_thanh') $stats['hoan_thanh']++;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="reservation-history-page">
    <div class="history-shell">
        <div class="history-hero">
            <div class="history-hero-left">
                <span class="history-chip">Lịch sử đặt bàn</span>
                <h1>Theo dõi các đơn đặt bàn của bạn</h1>
            </div>           
        </div>

        <?php if (!$customer): ?>
            <div class="history-empty-card">
                <h2>Chưa tìm thấy hồ sơ khách hàng</h2>
                <p>Bạn hãy đặt bàn một lần hoặc đăng nhập đúng tài khoản để xem lịch sử.</p>
                <a href="/quanlynhahang/customer/reservation.php" class="history-btn-primary">Đặt bàn ngay</a>
            </div>
        <?php else: ?>

            <?php if ($cancelMessage): ?>
                <div class="cancel-alert cancel-alert-<?= $cancelMessageType ?>">
                    <?= e($cancelMessage) ?>
                </div>
            <?php endif; ?>

            <div class="history-stats">
                <div class="stat-card">
                    <span>Tổng lượt đặt bàn</span>
                    <strong><?= (int)$stats['total'] ?></strong>
                </div>
                <div class="stat-card">
                    <span>Chờ xác nhận</span>
                    <strong><?= (int)$stats['cho_xac_nhan'] ?></strong>
                </div>
                <div class="stat-card">
                    <span>Đã xác nhận</span>
                    <strong><?= (int)$stats['da_xac_nhan'] ?></strong>
                </div>
                <div class="stat-card">
                    <span>Hoàn thành</span>
                    <strong><?= (int)$stats['hoan_thanh'] ?></strong>
                </div>
            </div>

            <?php if (empty($reservations)): ?>
                <div class="history-empty-card">
                    <h2>Chưa có lịch sử đặt bàn</h2>
                    <p>Bạn chưa có đơn đặt bàn nào trong hệ thống.</p>
                    <a href="/quanlynhahang/customer/reservation.php" class="history-btn-primary">Đặt bàn mới</a>
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($reservations as $item): ?>
                        <div class="history-card">
                            <div class="history-card-head">
                                <div>
                                    <div class="history-id">Mã đặt bàn #<?= (int)$item['reservation_id'] ?></div>
                                    <h3><?= e($item['table_name'] ?: 'Chưa gán bàn') ?></h3>
                                </div>
                                <div class="history-badges">
                                    <?php
                                        $st = strtolower($item['status']);
                                        $ps = strtolower($item['payment_status'] ?? '');
                                        $isPaid = in_array($ps, ['thanh_cong','success','paid','completed']);
                                        $paymentPercent = (int)($item['payment_percent'] ?? 0);
                                    ?>
                                    <?php if ($st === 'hoan_thanh'): ?>
                                        <span class="status-badge status-completed">Hoàn thành</span>
                                    <?php elseif ($st === 'da_huy'): ?>
                                        <span class="status-badge status-cancelled">Đã hủy</span>
                                    <?php elseif ($st === 'da_xac_nhan'): ?>
                                        <span class="status-badge status-confirmed">Đã xác nhận</span>
                                    <?php elseif ($st === 'cho_xac_nhan'): ?>
                                        <span class="status-badge status-pending">Chờ xác nhận</span>
                                    <?php elseif ($st === 'da_checkin'): ?>
                                        <span class="status-badge status-completed">Đã check-in</span>
                                    <?php elseif ($st === 'khong_den'): ?>
                                        <span class="status-badge status-cancelled">Không đến</span>
                                    <?php else: ?>
                                        <span class="status-badge status-default">
                                            <?= e(reservation_status_label($item['status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="history-grid">
                                <div class="history-item">
                                    <label>Thời gian đặt bàn</label>
                                    <div><?= e(format_datetime_vn($item['reservation_time'])) ?></div>
                                </div>

                                <div class="history-item">
                                    <label>Số người</label>
                                    <div><?= (int)$item['number_of_people'] ?> người</div>
                                </div>

                                <div class="history-item">
                                    <label>Ngày tạo đơn</label>
                                    <div><?= e(format_datetime_vn($item['created_at'])) ?></div>
                                </div>

                                <div class="history-item">
                                    <label>Thanh toán trước</label>
                                    <div>
                                        <?php if (!empty($item['payment_percent'])): ?>
                                            <?= (int)$item['payment_percent'] ?>%<?php if (!empty($item['payment_amount'])): ?> - <?= e(format_currency($item['payment_amount'])) ?><?php endif; ?>
                                        <?php else: ?>
                                            Chưa có
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="history-item history-item-full">
                                    <label>Ghi chú</label>
                                    <div><?= e($item['note'] ?: 'Không có ghi chú.') ?></div>
                                </div>
                            </div>
                            
                            <?php 
                                $st = strtolower($item['status']);
                                $hasOrder = (int)($item['has_order'] ?? 0) > 0;
                                $canCancel = !in_array($st, ['da_huy', 'hoan_thanh']) && !$hasOrder;
                            ?>
                            <?php if ($canCancel): ?>
                                <div class="history-actions">
                                    <form method="POST" onsubmit="return confirmCancel(<?= (int)$item['reservation_id'] ?>, '<?= e($item['payment_amount'] ?? '0') ?>')">
                                        <input type="hidden" name="reservation_id" value="<?= (int)$item['reservation_id'] ?>">
                                        <button type="submit" name="cancel_reservation" class="btn-cancel">
                                            <i class="fas fa-times-circle"></i> Hủy đặt bàn
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

<style>
.reservation-history-page{
    padding:40px 16px 60px;
    min-height:calc(100vh - 90px);
    background:
        radial-gradient(circle at top left, rgba(15,118,110,.08), transparent 26%),
        radial-gradient(circle at bottom right, rgba(180,83,9,.08), transparent 28%),
        #f8f5f0;
}

.history-shell{
    max-width:1100px;
    margin:0 auto;
}

.history-hero{
    background:linear-gradient(135deg, #0f766e, #b45309);
    color:#fff;
    border-radius:28px;
    padding:30px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    margin-bottom:24px;
    box-shadow:0 18px 40px rgba(15,23,42,.12);
}

.history-chip{
    display:inline-block;
    padding:8px 14px;
    border-radius:999px;
    background:rgba(255,255,255,.16);
    font-size:13px;
    font-weight:700;
    margin-bottom:12px;
}

.history-hero h1{
    margin:0 0 8px;
    font-size:40px;
    line-height:1.1;
    font-weight:800;
}

.history-hero p{
    margin:0;
    max-width:700px;
    color:#fef3c7;
    font-size:16px;
    line-height:1.7;
}

.history-hero-user{
    min-width:220px;
    display:flex;
    align-items:center;
    gap:14px;
    padding:14px 16px;
    border-radius:18px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.14);
}

.history-avatar{
    width:54px;
    height:54px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    font-weight:800;
}

.history-hero-user strong{
    display:block;
    font-size:18px;
}

.history-hero-user span{
    display:block;
    color:#ecfdf5;
    font-size:14px;
    margin-top:4px;
}

.history-stats{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:16px;
    margin-bottom:24px;
}

.stat-card{
    background:#fff;
    border-radius:20px;
    padding:20px;
    box-shadow:0 10px 30px rgba(15,23,42,.08);
    border:1px solid #f0e7df;
}

.stat-card span{
    display:block;
    color:#6b7280;
    font-size:14px;
    margin-bottom:10px;
}

.stat-card strong{
    font-size:34px;
    color:#111827;
    line-height:1;
}

.history-list{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.history-card{
    background:#fff;
    border-radius:22px;
    padding:22px;
    box-shadow:0 12px 30px rgba(15,23,42,.08);
    border:1px solid #f0e7df;
}

.history-card-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:18px;
}

.history-id{
    font-size:13px;
    color:#6b7280;
    margin-bottom:8px;
}

.history-card h3{
    margin:0;
    font-size:26px;
    color:#111827;
}

.history-badges{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    justify-content:flex-end;
}

.status-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:8px 12px;
    border-radius:999px;
    font-size:13px;
    font-weight:700;
    white-space:nowrap;
}

.status-pending{
    background:#fff7ed;
    color:#c2410c;
    border:1px solid #fdba74;
}

.status-confirmed{
    background:#ecfdf5;
    color:#166534;
    border:1px solid #86efac;
}

.status-completed{
    background:#eff6ff;
    color:#1d4ed8;
    border:1px solid #93c5fd;
}

.status-cancelled{
    background:#fef2f2;
    color:#b91c1c;
    border:1px solid #fca5a5;
}

.status-default{
    background:#f3f4f6;
    color:#374151;
    border:1px solid #d1d5db;
}

.pay-pending{
    background:#fefce8;
    color:#a16207;
    border:1px solid #fde68a;
}

.pay-paid{
    background:#ecfdf5;
    color:#166534;
    border:1px solid #86efac;
}

.pay-deposit{
    background:#fef3c7;
    color:#a16207;
    border:1px solid #fde68a;
}

.pay-failed{
    background:#fef2f2;
    color:#b91c1c;
    border:1px solid #fca5a5;
}

.pay-default{
    background:#f3f4f6;
    color:#374151;
    border:1px solid #d1d5db;
}

.history-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

.history-item{
    padding:14px;
    border-radius:16px;
    background:#fafaf9;
    border:1px solid #ece7e1;
}

.history-item-full{
    grid-column:1 / -1;
}

.history-item label{
    display:block;
    font-size:13px;
    font-weight:700;
    color:#6b7280;
    margin-bottom:8px;
}

.history-item div{
    font-size:15px;
    color:#111827;
    line-height:1.6;
}

.history-empty-card{
    background:#fff;
    border-radius:24px;
    padding:32px 26px;
    text-align:center;
    box-shadow:0 12px 30px rgba(15,23,42,.08);
    border:1px solid #f0e7df;
}

.history-empty-card h2{
    margin:0 0 10px;
    font-size:30px;
    color:#111827;
}

.history-empty-card p{
    margin:0 0 18px;
    color:#6b7280;
    line-height:1.7;
}

.history-btn-primary{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    height:48px;
    padding:0 18px;
    border-radius:14px;
    background:linear-gradient(135deg, #0f766e, #0b5f59);
    color:#fff;
    text-decoration:none;
    font-weight:700;
    box-shadow:0 12px 24px rgba(15,118,110,.18);
}

.cancel-alert{
    padding:16px 20px;
    border-radius:16px;
    margin-bottom:20px;
    font-size:15px;
    line-height:1.6;
}

.cancel-alert-success{
    background:#ecfdf5;
    color:#166534;
    border:1px solid #86efac;
}

.cancel-alert-error{
    background:#fef2f2;
    color:#b91c1c;
    border:1px solid #fca5a5;
}

.history-actions{
    margin-top:18px;
    padding-top:18px;
    border-top:1px solid #f0e7df;
    display:flex;
    justify-content:flex-end;
}

.btn-cancel{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 18px;
    border-radius:12px;
    background:#fef2f2;
    color:#b91c1c;
    border:1px solid #fca5a5;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
    transition:all 0.2s;
}

.btn-cancel:hover{
    background:#fee2e2;
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(185,28,28,0.2);
}

@media (max-width: 900px){
    .history-hero{
        flex-direction:column;
        align-items:flex-start;
    }

    .history-stats{
        grid-template-columns:1fr 1fr;
    }

    .history-grid{
        grid-template-columns:1fr;
    }

    .history-item-full{
        grid-column:auto;
    }
}

@media (max-width: 640px){
    .reservation-history-page{
        padding:24px 12px 40px;
    }

    .history-hero{
        padding:22px 18px;
        border-radius:22px;
    }

    .history-hero h1{
        font-size:30px;
    }

    .history-stats{
        grid-template-columns:1fr;
    }

    .history-card{
        padding:18px 16px;
        border-radius:18px;
    }

    .history-card-head{
        flex-direction:column;
        align-items:flex-start;
    }

    .history-card h3{
        font-size:22px;
    }

    .history-badges{
        justify-content:flex-start;
    }
}
</style>

<script>
function confirmCancel(reservationId, prepaidAmount) {
    const amount = parseFloat(prepaidAmount) || 0;
    
    let message = 'Bạn có chắc chắn muốn hủy đặt bàn #' + reservationId + '?\n\n';
    
    if (amount > 0) {
        const formatted = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
        message += '⚠️ LƯU Ý: Số tiền đã đặt cọc ' + formatted + ' sẽ KHÔNG được hoàn trả theo chính sách của nhà hàng.\n\n';
        message += 'Bạn vẫn muốn tiếp tục hủy?';
    } else {
        message += 'Hành động này không thể hoàn tác.';
    }
    
    return confirm(message);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
    