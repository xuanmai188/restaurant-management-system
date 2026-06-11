<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user'])) {
    redirect_by_role();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $full_name = preg_replace('/\s+/', ' ', $full_name);

    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $ngay_sinh = trim($_POST['ngay_sinh'] ?? '');
    $gioi_tinh = trim($_POST['gioi_tinh'] ?? '');

    if ($full_name === '' || $username === '' || $password === '' || $confirm_password === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    }

    // ❌ Tên không số, không ký tự đặc biệt
    elseif (!preg_match('/^[a-zA-ZÀ-ỹ\s]+$/u', $full_name)) {
        $error = 'Họ tên không được chứa số hoặc ký tự đặc biệt.';
    }

    // ❌ Không cho nhiều khoảng trắng
    elseif (preg_match('/\s{2,}/', $full_name)) {
        $error = 'Họ tên không được chứa nhiều khoảng trắng.';
    }

    // ❌ SĐT phải 10 số
    elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Số điện thoại phải đúng 10 chữ số.';
    }

    // ❌ Username không dấu, không khoảng trắng
    elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $error = 'Tên đăng nhập chỉ được chứa chữ và số, không dấu, không khoảng trắng.';
    }

    // ❌ Email (chỉ validate nếu có nhập)
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không đúng định dạng.';
    }

    // ❌ Password
    elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error = 'Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt.';
    }
    elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu nhập lại không khớp.';
    }
    elseif (!in_array($gioi_tinh, ['Nam', 'Nữ'])) {
        $error = 'Vui lòng chọn giới tính.';
    }
    elseif ($ngay_sinh === '') {
        $error = 'Vui lòng nhập ngày sinh.';
    }

    else {
        // Kiểm tra trùng username hoặc email (chỉ check email nếu có nhập)
        if ($email !== '') {
            $checkStmt = $conn->prepare("
                SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1
            ");
            $checkStmt->bind_param('ss', $username, $email);
        } else {
            $checkStmt = $conn->prepare("
                SELECT user_id FROM users WHERE username = ? LIMIT 1
            ");
            $checkStmt->bind_param('s', $username);
        }
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($exists) {
            $error = 'Tên đăng nhập hoặc email đã tồn tại.';
        } else {
            // Kiểm tra SĐT đã tồn tại chưa
            $phoneCheck = $conn->prepare("SELECT user_id FROM users WHERE phone = ? LIMIT 1");
            $phoneCheck->bind_param('s', $phone);
            $phoneCheck->execute();
            $phoneExists = $phoneCheck->get_result()->fetch_assoc();
            $phoneCheck->close();

            if ($phoneExists) {
                $error = 'Số điện thoại này đã được đăng ký.';
            } else {
                $defaultRoleId = 6;
                $status = 'hoat_dong';
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $emailToSave = $email !== '' ? $email : null;

                $conn->begin_transaction();

                try {
                    $userStmt = $conn->prepare("
                        INSERT INTO users (full_name, phone, email, username, password, role_id, status, ngay_sinh, gioi_tinh)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $userStmt->bind_param(
                        'sssssisss',
                        $full_name,
                        $phone,
                        $emailToSave,
                        $username,
                        $hashedPassword,
                        $defaultRoleId,
                        $status,
                        $ngay_sinh,
                        $gioi_tinh
                    );

                    if (!$userStmt->execute()) {
                        throw new Exception('Không thể thêm tài khoản vào users.');
                    }

                    $new_user_id = $conn->insert_id;
                    $userStmt->close();

                    $customerEmail = $email !== '' ? $email : null;
                    $customerStmt = $conn->prepare("
                        INSERT INTO customers (customer_name, phone, email, user_id)
                        VALUES (?, ?, ?, ?)
                    ");
                    $customerStmt->bind_param(
                        'sssi',
                        $full_name,
                        $phone,
                        $customerEmail,
                        $new_user_id
                    );

                    if (!$customerStmt->execute()) {
                        throw new Exception('Không thể thêm hồ sơ khách hàng.');
                    }

                    $customerStmt->close();

                    $conn->commit();
                    $success = 'Đăng ký thành công. Bạn có thể đăng nhập ngay.';
                    header("refresh:2;url=/quanlynhahang/auth/login.php");
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="register-box-page">
    <div class="register-box-card">
        <div class="register-box-head">            
            <h1>Đăng ký</h1>
            <p>Điền thông tin để tạo tài khoản.</p>
        </div>

        <?php if ($error): ?>
            <div class="register-box-alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="register-box-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="register-box-form">
            <?= csrf_field() ?>

            <div class="register-box-group">
                <label>Họ và tên</label>
                <input type="text" name="full_name" required
                    pattern="[A-Za-zÀ-ỹ\s]+"
                    placeholder="Nguyễn Văn A"
                    value="<?= e($_POST['full_name'] ?? '') ?>">
            </div>

            <div class="register-box-group">
                <label>Số điện thoại</label>
                <input type="text" name="phone"
                    pattern="[0-9]{10}" maxlength="10"
                    placeholder="0123456789"
                    value="<?= e($_POST['phone'] ?? '') ?>">
            </div>

            <div class="register-box-group">
                <label>Ngày sinh</label>
                <input type="date" name="ngay_sinh" required
                    value="<?= e($_POST['ngay_sinh'] ?? '') ?>">
            </div>

            <div class="register-box-group">
                <label>Giới tính</label>
                <div style="display:flex; gap:24px; margin-top:8px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:500;">
                        <input type="radio" name="gioi_tinh" value="Nam" required
                            <?= ($_POST['gioi_tinh'] ?? '') === 'Nam' ? 'checked' : '' ?>
                            style="width:18px; height:18px; accent-color:#0f766e; cursor:pointer;">
                        Nam
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:500;">
                        <input type="radio" name="gioi_tinh" value="Nữ"
                            <?= ($_POST['gioi_tinh'] ?? '') === 'Nữ' ? 'checked' : '' ?>
                            style="width:18px; height:18px; accent-color:#0f766e; cursor:pointer;">
                        Nữ
                    </label>
                </div>
            </div>

            <div class="register-box-group">
                <label>Email <span style="color:#9ca3af; font-size:12px;">(không bắt buộc)</span></label>
                <input type="email" name="email"
                    placeholder="example@gmail.com"
                    value="<?= e($_POST['email'] ?? '') ?>">
            </div>

            <div class="register-box-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" required
                    pattern="[A-Za-z0-9]+"
                    placeholder="không dấu, không khoảng trắng"
                    value="<?= e($_POST['username'] ?? '') ?>">
            </div>

          <div class="register-box-group">
            <label>Mật khẩu</label>
            <div class="input-icon">
                <input type="password" name="password" id="password" required>
                <i class="fa fa-eye toggle-password" data-target="password"></i>
            </div>
        </div>

        <div class="register-box-group">
            <label>Nhập lại mật khẩu</label>
            <div class="input-icon">
                <input type="password" name="confirm_password" id="confirm_password" required>
                <i class="fa fa-eye toggle-password" data-target="confirm_password"></i>
            </div>
        </div>

            <button type="submit" class="register-box-btn">Đăng ký</button>

        </form>

        <div>
            Đã có tài khoản?
            <a href="/quanlynhahang/auth/login.php">Đăng nhập</a>
        </div>
    </div>
</section>
<script>
document.querySelectorAll(".toggle-password").forEach(icon => {
    icon.addEventListener("click", function () {
        const input = document.getElementById(this.dataset.target);

        if (input.type === "password") {
            input.type = "text";
            this.classList.remove("fa-eye");
            this.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            this.classList.remove("fa-eye-slash");
            this.classList.add("fa-eye");
        }
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
