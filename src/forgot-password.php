<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$error   = null;
$success = null;
$newPass = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if ($username === '') {
        $error = 'Vui lòng nhập tên đăng nhập.';
    } else {
        // Tìm user theo username trước
        $stmt = $conn->prepare("
            SELECT user_id, email FROM users
            WHERE username = ? AND status = 'hoat_dong'
            LIMIT 1
        ");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Nếu tài khoản có email thì bắt buộc nhập đúng email mới cho reset
        if ($user && !empty($user['email']) && $email === '') {
            $error = 'Tài khoản này có email đăng ký, vui lòng nhập email để xác thực.';
            $user = null;
        } elseif ($user && !empty($user['email']) && $email !== '' && strtolower($email) !== strtolower($user['email'])) {
            $error = 'Email không khớp với tài khoản.';
            $user = null;
        }

        if (!$user && !$error) {
            $error = 'Không tìm thấy tài khoản với tên đăng nhập này.';
        } else {
            $newPass = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 8);
            $hash    = password_hash($newPass, PASSWORD_DEFAULT);

            $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $upd->bind_param('si', $hash, $user['user_id']);
            $upd->execute();
            $upd->close();

            $success = true;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="forgot-password-page">
    <div class="forgot-password-shell">
        <div class="forgot-password-card">
            <div class="forgot-password-header">
                <div class="forgot-password-icon"><i class="fas fa-lock"></i></div>
                <h1>Quên mật khẩu</h1>
                <p>Nhập tên đăng nhập và email để đặt lại mật khẩu.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Xác thực thành công!
                </div>

                <div class="new-pass-box">
                    <p class="sim-note">
                        <i class="fas fa-info-circle"></i>
                        <em>Đây là hệ thống demo — thay vì gửi email, mật khẩu mới được hiển thị trực tiếp tại đây.</em>
                    </p>
                    <p>Mật khẩu mới của tài khoản <strong><?= e($_POST['username'] ?? '') ?></strong>:</p>
                    <div class="new-pass-value"><?= e($newPass) ?></div>
                    <p class="new-pass-note">Hãy đăng nhập và đổi lại mật khẩu trong phần <strong>Đổi mật khẩu</strong>.</p>
                </div>

                <div style="text-align:center; margin-top:20px;">
                    <a href="/quanlynhahang/auth/login.php" class="btn btn-primary">Đăng nhập ngay</a>
                </div>
            <?php else: ?>
                <form method="POST" class="forgot-password-form">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="username">Tên đăng nhập</label>
                        <input type="text" id="username" name="username"
                               placeholder="Nhập tên đăng nhập"
                               value="<?= e($_POST['username'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email <span class="optional">(nếu có)</span></label>
                        <input type="email" id="email" name="email"
                               placeholder="Nhập email đã đăng ký (nếu có)"
                               value="<?= e($_POST['email'] ?? '') ?>">
                        <span class="field-hint">Tài khoản không có email thì bỏ trống.</span>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Đặt lại mật khẩu
                        </button>
                        <a href="/quanlynhahang/auth/login.php" class="btn btn-secondary">Quay lại</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.forgot-password-page{padding:40px 16px 60px;background:#f9fafb;min-height:calc(100vh - 90px)}
.forgot-password-shell{max-width:480px;margin:0 auto}
.forgot-password-card{background:#fff;border-radius:18px;padding:34px;box-shadow:0 12px 34px rgba(15,23,42,.08);border:1px solid #e5e7eb}
.forgot-password-header{text-align:center;margin-bottom:28px}
.forgot-password-icon{width:72px;height:72px;background:#dc2626;border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:30px;color:#fff}
.forgot-password-header h1{margin:0 0 8px;font-size:28px;color:#111827;font-weight:800}
.forgot-password-header p{margin:0;color:#6b7280;font-size:15px}
.alert{padding:14px 16px;border-radius:12px;margin-bottom:18px;display:flex;gap:10px;font-size:14px;font-weight:600;align-items:center}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.alert-success{background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}
.new-pass-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:22px;text-align:center}
.sim-note{background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;color:#92400e;font-size:13px;display:flex;gap:8px;align-items:flex-start;text-align:left;margin-bottom:16px}
.sim-note i{margin-top:2px;flex-shrink:0}
.new-pass-box>p{margin:0 0 8px;color:#374151;font-size:14px}
.new-pass-value{font-size:32px;font-weight:800;color:#166534;letter-spacing:4px;padding:12px 0;font-family:monospace}
.new-pass-note{margin:10px 0 0;color:#6b7280;font-size:13px}
.forgot-password-form{display:flex;flex-direction:column;gap:18px}
.form-group{display:flex;flex-direction:column}
.form-group label{margin-bottom:8px;font-size:14px;font-weight:700;color:#374151}
.form-group input{height:48px;border:1px solid #d1d5db;border-radius:10px;padding:0 14px;font-size:15px;outline:none;transition:border-color .2s}
.form-group input:focus{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.1)}
.optional{font-weight:400;color:#9ca3af;font-size:13px}
.field-hint{font-size:12px;color:#9ca3af;margin-top:5px}
.form-actions{display:flex;gap:12px;margin-top:6px}
.btn{height:48px;padding:0 20px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;font-weight:700;font-size:15px;border:none;cursor:pointer;flex:1;transition:all .2s}
.btn-primary{background:#dc2626;color:white}
.btn-primary:hover{background:#b91c1c}
.btn-secondary{background:white;color:#374151;border:1px solid #d1d5db}
.btn-secondary:hover{background:#f9fafb}
@media(max-width:640px){.forgot-password-card{padding:26px 18px}.form-actions{flex-direction:column}}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
