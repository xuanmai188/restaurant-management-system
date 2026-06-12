<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Kiểm tra đăng nhập
if (empty($_SESSION['user'])) {
    header('Location: /quanlynhahang/auth/login.php');
    exit;
}

$userId = (int)$_SESSION['user']['user_id'];
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Validate
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu mới và xác nhận mật khẩu không khớp';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } else {
        // Kiểm tra mật khẩu hiện tại
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            $error = 'Không tìm thấy người dùng';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Mật khẩu hiện tại không đúng';
        } else {
            // Cập nhật mật khẩu mới
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param('si', $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                $success = 'Đổi mật khẩu thành công';
            } else {
                $error = 'Có lỗi xảy ra khi đổi mật khẩu';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="change-password-page">
    <div class="change-password-shell">
        <div class="change-password-card">
            <div class="change-password-header">
                <div class="change-password-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Đổi mật khẩu</h1>
                <p>Cho phép người dùng thay đổi mật khẩu tài khoản.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="change-password-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="current_password">Mật khẩu hiện tại</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            placeholder="Nhập mật khẩu hiện tại"
                            required
                        >
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('current_password')"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">Mật khẩu mới</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            placeholder="Nhập mật khẩu mới (tối thiểu 6 ký tự)"
                            required
                            minlength="6"
                        >
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password')"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu mới</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Nhập lại mật khẩu mới"
                            required
                            minlength="6"
                        >
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Đổi mật khẩu
                    </button>
                    <a href="javascript:history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                </div>
            </form>

            <div class="password-tips">
                <h3><i class="fas fa-info-circle"></i> Lưu ý khi đổi mật khẩu:</h3>
                <ul>
                    <li>Mật khẩu mới phải có ít nhất 6 ký tự</li>
                    <li>Nên sử dụng kết hợp chữ hoa, chữ thường, số và ký tự đặc biệt</li>
                    <li>Không sử dụng mật khẩu quá đơn giản hoặc dễ đoán</li>
                    <li>Sau khi đổi mật khẩu, bạn sẽ cần đăng nhập lại với mật khẩu mới</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<style>
.change-password-page {
    padding: 40px 16px 60px;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    min-height: calc(100vh - 90px);
}

.change-password-shell {
    max-width: 600px;
    margin: 0 auto;
}

.change-password-card {
    background: white;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 12px 34px rgba(15, 23, 42, 0.08);
    border: 1px solid #e5e7eb;
}

.change-password-header {
    text-align: center;
    margin-bottom: 32px;
}

.change-password-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 36px;
    color: white;
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
}

.change-password-header h1 {
    margin: 0 0 8px;
    font-size: 32px;
    color: #111827;
    font-weight: 800;
}

.change-password-header p {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
}

.alert {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
}

.alert i {
    font-size: 18px;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.alert-success {
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.change-password-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #374151;
}

.input-wrapper {
    position: relative;
}

.form-group input {
    width: 100%;
    height: 50px;
    border: 1px solid #d1d5db;
    border-radius: 12px;
    padding: 0 44px 0 14px;
    font-size: 15px;
    color: #111827;
    background: #fff;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-group input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.toggle-password {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    cursor: pointer;
    font-size: 18px;
    transition: color 0.2s ease;
}

.toggle-password:hover {
    color: #3b82f6;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.btn {
    height: 50px;
    padding: 0 24px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    flex: 1;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(59, 130, 246, 0.4);
}

.btn-secondary {
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background: #f9fafb;
}

.password-tips {
    margin-top: 32px;
    padding: 20px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
    border-radius: 16px;
}

.password-tips h3 {
    margin: 0 0 12px;
    font-size: 16px;
    color: #0c4a6e;
    display: flex;
    align-items: center;
    gap: 8px;
}

.password-tips ul {
    margin: 0;
    padding-left: 24px;
    color: #075985;
}

.password-tips li {
    margin-bottom: 6px;
    font-size: 14px;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .change-password-page {
        padding: 24px 12px 40px;
    }

    .change-password-card {
        padding: 28px 20px;
        border-radius: 18px;
    }

    .change-password-header h1 {
        font-size: 26px;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}
</style>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
