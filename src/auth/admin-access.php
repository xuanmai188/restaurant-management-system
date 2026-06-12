<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Nếu đã đăng nhập và là admin thì redirect với key
if (!empty($_SESSION['user']) && strtolower($_SESSION['user']['role_name'] ?? '') === 'admin') {
    if (!isset($_SESSION['admin_key'])) {
        generate_admin_key();
    }
    redirect('/quanlynhahang/admin.php?key=' . $_SESSION['admin_key']);
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

        // Chỉ cho phép admin đăng nhập
        if (strtolower($user['role_name']) === 'admin') {
            unset($user['password']);
            $_SESSION['user'] = $user;
            generate_admin_key();
            redirect('/quanlynhahang/admin.php?key=' . $_SESSION['admin_key']);
        } else {
            $error = 'Trang này chỉ dành cho Admin. Vui lòng đăng nhập tại trang chính.';
        }
    } else {
        $error = 'Sai tài khoản hoặc mật khẩu Admin.';
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
                    <div style="background:linear-gradient(135deg,#ef4444,#dc2626); color:white; padding:12px 20px; border-radius:12px; margin-bottom:20px; text-align:center; font-weight:700; font-size:14px; box-shadow:0 4px 12px rgba(239,68,68,0.3);">
                        🔐 ADMIN ACCESS ONLY
                    </div>
                    <h1>Đăng nhập Admin</h1>
                    <p style="color:#6b7280; font-size:14px; margin-top:8px;">Trang đăng nhập dành riêng cho quản trị viên</p>
                </div>

                <?php if ($error): ?>
                    <div class="login-clean-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="login-clean-form">
                    <?= csrf_field() ?>
                    <div class="login-clean-group">
                        <label for="username">Tên đăng nhập Admin</label>
                        <div class="input-icon">
                            <input
                                id="username"
                                type="text"
                                name="username"
                                placeholder="Nhập tên đăng nhập Admin"
                                required
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="login-clean-group">
                        <label for="password">Mật khẩu Admin</label>
                        <div class="input-icon">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Nhập mật khẩu Admin"
                                required
                            >
                            <i class="fa fa-eye toggle-password" id="togglePassword"></i>
                        </div>
                    </div>

                    <button type="submit" class="login-clean-btn" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                        🔐 Đăng nhập Admin
                    </button>
                </form>

                <div class="login-clean-footer" style="text-align:center; color:#9ca3af; font-size:13px;">
                    <i class="fa fa-shield-alt"></i> Trang bảo mật - Chỉ dành cho Admin
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
