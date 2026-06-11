<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

/*
|--------------------------------------------------------------------------
| CHUẨN HÓA KẾT NỐI DB
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
    die('Không tìm thấy biến kết nối CSDL. Hãy kiểm tra file config/database.php');
}

/*
|--------------------------------------------------------------------------
| HÀM TIỆN ÍCH
|--------------------------------------------------------------------------
*/
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

/*
|--------------------------------------------------------------------------
| DB HELPER
|--------------------------------------------------------------------------
*/
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
        $bindValues = [];

        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_null($value)) {
                $types .= 's';
                $value = null;
            } else {
                $types .= 's';
            }
            $bindValues[] = $value;
        }

        $stmt->bind_param($types, ...$bindValues);
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
    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function db_fetch_one($db, string $dbType, string $sql, array $params = []): ?array
{
    $rows = db_fetch_all($db, $dbType, $sql, $params);
    return $rows[0] ?? null;
}

/*
|--------------------------------------------------------------------------
| XÁC ĐỊNH KHÁCH HÀNG ĐANG ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/
$sessionCustomerId = (int)($_SESSION['customer']['customer_id'] ?? $_SESSION['user']['customer_id'] ?? 0);
$sessionPhone      = trim($_SESSION['customer']['phone'] ?? $_SESSION['user']['phone'] ?? '');
$sessionEmail      = trim($_SESSION['customer']['email'] ?? $_SESSION['user']['email'] ?? '');

$customer = null;

if ($sessionCustomerId > 0) {
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

/*
|--------------------------------------------------------------------------
| CẬP NHẬT HỒ SƠ
|--------------------------------------------------------------------------
*/
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $customerId   = (int)($_POST['customer_id'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');

    if ($customerId <= 0) {
        $errors[] = 'Không xác định được hồ sơ khách hàng.';
    }
    if ($customerName === '') {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    if ($phone === '') {
        $errors[] = 'Vui lòng nhập số điện thoại.';
    }

    if (!$errors) {
        $dupPhone = db_fetch_one(
            $db,
            $dbType,
            "SELECT customer_id
             FROM customers
             WHERE phone = ? AND customer_id <> ?
             LIMIT 1",
            [$phone, $customerId]
        );

        if ($dupPhone) {
            $errors[] = 'Số điện thoại đã tồn tại ở hồ sơ khác.';
        }
    }

    if (!$errors && $email !== '') {
        $dupEmail = db_fetch_one(
            $db,
            $dbType,
            "SELECT customer_id
             FROM customers
             WHERE email = ? AND customer_id <> ?
             LIMIT 1",
            [$email, $customerId]
        );

        if ($dupEmail) {
            $errors[] = 'Email đã tồn tại ở hồ sơ khác.';
        }
    }

    if (!$errors) {
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

        $customer = db_fetch_one(
            $db,
            $dbType,
            "SELECT customer_id, customer_name, phone, email, created_at
             FROM customers
             WHERE customer_id = ?
             LIMIT 1",
            [$customerId]
        );

        $_SESSION['customer']['customer_id'] = (int)$customer['customer_id'];
        $_SESSION['customer']['customer_name'] = $customer['customer_name'];
        $_SESSION['customer']['phone'] = $customer['phone'];
        $_SESSION['customer']['email'] = $customer['email'];

        $success = 'Cập nhật thông tin cá nhân thành công.';
    }
}

/*
|--------------------------------------------------------------------------
| CHƯA CÓ HỒ SƠ
|--------------------------------------------------------------------------
*/
if (!$customer) {
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="profile-page-modern">
        <div class="profile-shell">
            <div class="profile-card-single">
                <div class="profile-empty-box">
                    <h1>Trang cá nhân</h1>
                    <p>Chưa tìm thấy hồ sơ khách hàng trong hệ thống.</p>
                    <div class="profile-warning-box">
                        Bạn có thể đặt bàn một lần trước để hệ thống tự tạo hồ sơ khách hàng.
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$customerId = (int)$customer['customer_id'];

include __DIR__ . '/includes/header.php';
?>

<section class="profile-page-modern">
    <div class="profile-shell">
        <div class="profile-hero-modern">
            <div class="profile-hero-left">
                <div class="profile-avatar-modern">
                    <?= e(mb_strtoupper(mb_substr($customer['customer_name'] ?: 'K', 0, 1))) ?>
                </div>
                <div class="profile-hero-text">
                    <span class="profile-chip">Trang cá nhân</span>
                    <h1><?= e($customer['customer_name']) ?></h1>
                    <p>Quản lý thông tin cơ bản và cập nhật hồ sơ của bạn.</p>
                    <div class="profile-meta">
                        Thành viên từ <?= format_datetime_vn($customer['created_at'] ?? null) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="profile-alert-success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="profile-alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="profile-card-single">
            <div class="profile-card-head">
                <div>
                    <h2>Thông tin cá nhân</h2>
                    <p>Xem và chỉnh sửa hồ sơ cơ bản của bản thân.</p>
                </div>
            </div>

            <form method="POST" class="profile-form-modern">
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="customer_id" value="<?= (int)$customer['customer_id'] ?>">

                <div class="profile-form-grid">
                    <div class="profile-field">
                        <label>Họ tên</label>
                        <input
                            type="text"
                            name="customer_name"
                            value="<?= e($customer['customer_name']) ?>"
                            required
                        >
                    </div>

                    <div class="profile-field">
                        <label>Số điện thoại</label>
                        <input
                            type="text"
                            name="phone"
                            value="<?= e($customer['phone']) ?>"
                            required
                        >
                    </div>

                    <div class="profile-field profile-field-full">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            value="<?= e($customer['email']) ?>"
                        >
                    </div>
                </div>

                <div class="profile-form-actions">
                    <button type="submit" class="profile-btn-primary">
                        Lưu thay đổi
                    </button>                    
                </div>
            </form>
        </div>
    </div>
</section>

<style>
.profile-page-modern{
    padding:40px 16px 60px;
    background:
        radial-gradient(circle at top left, rgba(15,118,110,.08), transparent 26%),
        radial-gradient(circle at bottom right, rgba(180,83,9,.08), transparent 28%),
        #f8f5f0;
    min-height:calc(100vh - 90px);
}

.profile-shell{
    max-width:980px;
    margin:0 auto;
}

.profile-hero-modern{
    background: linear-gradient(135deg, #0f766e, #b45309);
    border-radius:28px;
    padding:34px 36px;
    color:#fff;
    margin-bottom:24px;
    box-shadow:0 18px 40px rgba(15, 23, 42, 0.12);
}

.profile-hero-left{
    display:flex;
    align-items:center;
    gap:20px;
    flex-wrap:wrap;
}

.profile-avatar-modern{
    width:84px;
    height:84px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:32px;
    font-weight:800;
    border:1px solid rgba(255,255,255,.18);
    backdrop-filter:blur(8px);
}

.profile-chip{
    display:inline-block;
    padding:8px 14px;
    border-radius:999px;
    background:rgba(255,255,255,.16);
    font-size:13px;
    font-weight:700;
    margin-bottom:12px;
}

.profile-hero-text h1{
    margin:0 0 8px;
    font-size:42px;
    line-height:1.1;
    font-weight:800;
}

.profile-hero-text p{
    margin:0 0 10px;
    color:#ecfdf5;
    font-size:16px;
    line-height:1.6;
}

.profile-meta{
    font-size:14px;
    color:#d1fae5;
}

.profile-alert-success,
.profile-alert-error{
    border-radius:16px;
    padding:14px 16px;
    margin-bottom:18px;
    font-size:14px;
}

.profile-alert-success{
    background:#ecfdf5;
    border:1px solid #bbf7d0;
    color:#166534;
}

.profile-alert-error{
    background:#fef2f2;
    border:1px solid #fecaca;
    color:#991b1b;
}

.profile-alert-error ul{
    margin:0;
    padding-left:18px;
}

.profile-card-single{
    background:#fff;
    border-radius:24px;
    padding:28px;
    box-shadow:0 12px 34px rgba(15, 23, 42, 0.08);
    border:1px solid #f0e7df;
}

.profile-card-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:20px;
}

.profile-card-head h2{
    margin:0 0 6px;
    font-size:30px;
    color:#1f2937;
}

.profile-card-head p{
    margin:0;
    color:#6b7280;
    font-size:15px;
}

.profile-form-modern{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.profile-form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

.profile-field{
    display:flex;
    flex-direction:column;
}

.profile-field-full{
    grid-column:1 / -1;
}

.profile-field label{
    margin-bottom:8px;
    font-size:14px;
    font-weight:700;
    color:#374151;
}

.profile-field input{
    width:100%;
    height:50px;
    border:1px solid #d1d5db;
    border-radius:14px;
    padding:0 14px;
    font-size:15px;
    color:#111827;
    background:#fff;
    outline:none;
    transition:all .2s ease;
    box-sizing:border-box;
}

.profile-field input:focus{
    border-color:#0f766e;
    box-shadow:0 0 0 4px rgba(15,118,110,.10);
}

.profile-form-actions{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:4px;
}

.profile-btn-primary,
.profile-btn-secondary{
    height:48px;
    padding:0 18px;
    border-radius:14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    font-weight:700;
    font-size:15px;
    transition:all .2s ease;
}

.profile-btn-primary{
    border:none;
    background:linear-gradient(135deg, #0f766e, #0b5f59);
    color:#fff;
    cursor:pointer;
    box-shadow:0 12px 24px rgba(15,118,110,.18);
}

.profile-btn-primary:hover{
    transform:translateY(-1px);
}

.profile-btn-secondary{
    border:1px solid #d1d5db;
    background:#fff;
    color:#111827;
}

.profile-btn-secondary:hover{
    background:#f9fafb;
}

.profile-empty-box h1{
    margin:0 0 10px;
    font-size:34px;
    color:#1f2937;
}

.profile-empty-box p{
    margin:0 0 14px;
    color:#6b7280;
}

.profile-warning-box{
    padding:14px 16px;
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
    border-radius:12px;
}

@media (max-width: 768px){
    .profile-page-modern{
        padding:24px 12px 40px;
    }

    .profile-hero-modern{
        padding:24px 20px;
        border-radius:22px;
    }

    .profile-avatar-modern{
        width:68px;
        height:68px;
        font-size:26px;
    }

    .profile-hero-text h1{
        font-size:32px;
    }

    .profile-card-single{
        padding:20px 16px;
        border-radius:18px;
    }

    .profile-card-head h2{
        font-size:26px;
    }

    .profile-form-grid{
        grid-template-columns:1fr;
    }

    .profile-field-full{
        grid-column:auto;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>