<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Nếu đã đăng nhập thì redirect luôn
if (!empty($_SESSION['user'])) {
    redirect_by_role();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.email, u.username, u.password, u.phone, r.role_name
        FROM users u
        LEFT JOIN roles r ON r.role_id = u.role_id
        WHERE u.username = ? AND u.status = 'hoat_dong'
        LIMIT 1
    ");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $rehashStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $rehashStmt->bind_param('si', $newHash, $user['user_id']);
            $rehashStmt->execute();
            $rehashStmt->close();
        }

        // Chặn admin đăng nhập ở trang này
        if (strtolower($user['role_name']) === 'admin') {
            $error = 'Tài khoản Admin không được phép đăng nhập tại đây. Vui lòng sử dụng trang đăng nhập dành riêng cho Admin.';
        } else {
            unset($user['password']);
            $_SESSION['user'] = $user;
            redirect_by_role();
        }
    } else {
        $error = 'Sai tài khoản hoặc mật khẩu.';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="login-clean-page">
    <div class="login-clean-shell">
        <div class="login-clean-visual">
            <div class="login-clean-overlay"></div>
            <div class="login-clean-brand">
                <span class="login-brand-dot"></span>
                <span>Nhà Hàng Hương vị</span>
            </div>
        </div>

        <div class="login-clean-panel">
            <div class="login-clean-card">
                <div class="login-clean-head">                    
                    <h1>Đăng nhập</h1>                   
                </div>

                <?php if ($error): ?>
                    <div class="login-clean-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="login-clean-form">
                    <?= csrf_field() ?>
                    <div class="login-clean-group">
                    <label for="username">Tên đăng nhập</label>
                    <div class="input-icon">                       
                        <input
                            id="username"
                            type="text"
                            name="username"
                            placeholder="Nhập tên đăng nhập"
                            required
                        >
                    </div>
                </div>

                <div class="login-clean-group">
                    <label for="password">Mật khẩu</label>
                    <div class="input-icon">                      
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Nhập mật khẩu"
                            required
                        >
                        <i class="fa fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                    <div style="text-align: right; margin-top: 8px;">
                        <a href="/quanlynhahang/forgot-password.php" style="color: #0f766e; font-size: 13px; text-decoration: none; font-weight: 600;">Quên mật khẩu?</a>
                    </div>
                </div>

                    <button type="submit" class="login-clean-btn">Đăng nhập</button>
                </form>

                <div class="login-clean-footer">
                    Chưa có tài khoản?
                    <a href="/quanlynhahang/auth/register.php">Đăng ký ngay</a>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
const toggle = document.getElementById("togglePassword");
const password = document.getElementById("password");

toggle.addEventListener("click", function () {
    const type = password.type === "password" ? "text" : "password";
    password.type = type;

    this.classList.toggle("fa-eye");
    this.classList.toggle("fa-eye-slash");
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
