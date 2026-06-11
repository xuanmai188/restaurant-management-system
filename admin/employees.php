<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

$msg = ''; $msgType = 'alert-success';

// Sửa nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $user_id   = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $email     = trim($_POST['email']     ?? '');
    $role_id   = (int)($_POST['role_id']  ?? 0);
    $ngay_sinh = trim($_POST['ngay_sinh'] ?? '') ?: null;
    $gioi_tinh = trim($_POST['gioi_tinh'] ?? '') ?: null;

    if (!$user_id || !$full_name || !$phone || !$email || !$role_id) {
        $msg = 'Vui lòng nhập đầy đủ thông tin.'; $msgType = 'alert-error';
    } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
        $msg = 'Số điện thoại phải đúng 10 số và bắt đầu bằng số 0 (VD: 0901234567).'; $msgType = 'alert-error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Email không hợp lệ. Vui lòng nhập đúng định dạng (VD: example@gmail.com).'; $msgType = 'alert-error';
    } elseif (!in_array($role_id, [2,3,4,5])) {
        $msg = 'Vai trò không hợp lệ.'; $msgType = 'alert-error';
    } else {
        // Kiểm tra trùng phone/email với user khác
        $chk = $conn->prepare("SELECT user_id FROM users WHERE (email=? OR phone=?) AND user_id != ? LIMIT 1");
        $chk->bind_param('ssi', $email, $phone, $user_id);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) {
            $msg = 'Email hoặc SĐT đã tồn tại ở nhân viên khác.'; $msgType = 'alert-error';
        } else {
            $upd = $conn->prepare("UPDATE users SET full_name=?, phone=?, email=?, role_id=?, ngay_sinh=?, gioi_tinh=? WHERE user_id=?");
            $upd->bind_param('sssissi', $full_name, $phone, $email, $role_id, $ngay_sinh, $gioi_tinh, $user_id);
            $msg = $upd->execute() ? 'Cập nhật nhân viên thành công.' : 'Lỗi cập nhật nhân viên.';
            if ($msg !== 'Cập nhật nhân viên thành công.') $msgType = 'alert-error';
        }
        $chk->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_employee'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $email     = trim($_POST['email']     ?? '');
    $username  = trim($_POST['username']  ?? '');
    $password  = trim($_POST['password']  ?? '');
    $role_id   = (int)($_POST['role_id']  ?? 0);
    $status    = trim($_POST['status']    ?? 'hoat_dong');
    $ngay_sinh = trim($_POST['ngay_sinh'] ?? '') ?: null;
    $gioi_tinh = trim($_POST['gioi_tinh'] ?? '') ?: null;

    if (!$full_name || !$phone || !$email || !$username || !$password || !$role_id) {
        $msg = 'Vui lòng nhập đầy đủ thông tin.'; $msgType = 'alert-error';
    } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
        $msg = 'Số điện thoại phải đúng 10 số và bắt đầu bằng số 0 (VD: 0901234567).'; $msgType = 'alert-error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Email không hợp lệ. Vui lòng nhập đúng định dạng (VD: example@gmail.com).'; $msgType = 'alert-error';
    } elseif (!in_array($role_id, [2,3,4,5])) {
        $msg = 'Vai trò không hợp lệ.'; $msgType = 'alert-error';
    } else {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE username=? OR email=? OR phone=? LIMIT 1");
        $chk->bind_param('sss', $username, $email, $phone);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) {
            $msg = 'Username, email hoặc SĐT đã tồn tại.'; $msgType = 'alert-error';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (full_name,phone,email,username,password,role_id,status,ngay_sinh,gioi_tinh) VALUES (?,?,?,?,?,?,?,?,?)");
            $ins->bind_param('sssssisss', $full_name, $phone, $email, $username, $hashed, $role_id, $status, $ngay_sinh, $gioi_tinh);
            $msg = $ins->execute() ? 'Tạo tài khoản thành công.' : 'Lỗi tạo tài khoản.';
            if ($msg !== 'Tạo tài khoản thành công.') $msgType = 'alert-error';
        }
        $chk->close();
    }
}

// Xóa nhân viên
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Kiểm tra không phải admin
    $check = $conn->query("SELECT role_id FROM users WHERE user_id=$id LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $role = $check->fetch_assoc()['role_id'];
        if ($role != 1) { // Không cho xóa admin
            $conn->query("DELETE FROM users WHERE user_id=$id");
            $msg = 'Đã xóa nhân viên.'; $msgType = 'alert-success';
        } else {
            $msg = 'Không thể xóa tài khoản Admin.'; $msgType = 'alert-error';
        }
    }
    $key = $_GET['key'] ?? '';
    header('Location: admin.php?page=employees' . ($key ? '&key='.$key : '')); exit;
}

// Thay đổi vai trò
if (isset($_GET['change_role'])) {
    $id = (int)$_GET['change_role'];
    $new_role = (int)($_GET['new_role'] ?? 0);
    if (in_array($new_role, [2,3,4,5])) {
        $conn->query("UPDATE users SET role_id=$new_role WHERE user_id=$id");
        $msg = 'Đã thay đổi vai trò.'; $msgType = 'alert-success';
    }
    $key = $_GET['key'] ?? '';
    header('Location: admin.php?page=employees' . ($key ? '&key='.$key : '')); exit;
}

$keyword       = trim($_GET['keyword'] ?? '');
$filter_role   = trim($_GET['role']    ?? '');
$filter_status = trim($_GET['status']  ?? '');

$sql = "SELECT user_id,full_name,username,phone,email,role_id,status,gioi_tinh FROM users WHERE role_id IN (2,3,4,5)";
$params = []; $types = '';
if ($keyword) { $kw="%$keyword%"; $sql.=" AND (full_name LIKE ? OR username LIKE ? OR phone LIKE ? OR email LIKE ?)"; array_push($params,$kw,$kw,$kw,$kw); $types.='ssss'; }
if ($filter_role)   { $sql.=" AND role_id=?";  $params[]=$filter_role;   $types.='i'; }
if ($filter_status) { $sql.=" AND status=?";   $params[]=$filter_status; $types.='s'; }
$sql .= " ORDER BY user_id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$employees = $stmt->get_result();

function roleName(int $id): string {
    return match($id){2=>'Quản lý',3=>'Thu ngân',4=>'Bếp',5=>'Phục vụ',default=>'Không rõ'};
}

$pageTitle = 'Quản lý nhân viên'; 
$activeMenu = 'employees'; $sidebarRole = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<?php if ($msg): ?><div class="alert <?= $msgType ?>"><?= e($msg) ?></div><?php endif; ?>

<div class="page-actions">
    <h3>Danh sách nhân viên</h3>
    <button class="btn btn-primary" onclick="document.getElementById('empModal').classList.add('show')">+ Thêm nhân viên</button>
</div>

<form method="GET" action="admin.php" class="filters">
    <input type="hidden" name="page" value="employees">
    <input class="input" type="text" name="keyword" placeholder="Tìm tên, username, phone, email..." value="<?= e($keyword) ?>">
    <select class="select" name="role">
        <option value="">Tất cả vai trò</option>
        <?php foreach ([2=>'Quản lý',3=>'Thu ngân',4=>'Bếp',5=>'Phục vụ'] as $id=>$lbl): ?>
            <option value="<?= $id ?>" <?= $filter_role==$id?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary" type="submit">Lọc</button>
</form>

<div class="card panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Họ tên</th><th>Username</th><th>SĐT</th><th>Email</th><th>Giới tính</th><th>Vai trò</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php if ($employees && $employees->num_rows > 0): ?>
                <?php while ($row = $employees->fetch_assoc()): ?>
                <tr>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['username']) ?></td>
                    <td><?= e($row['phone']) ?></td>
                    <td><?= e($row['email']) ?></td>
                    <td><?= e($row['gioi_tinh'] ?: '-') ?></td>
                    <td>
                        <select class="select" style="padding:6px 10px;font-size:13px;width:auto;" onchange="if(confirm('Thay đổi vai trò thành ' + this.options[this.selectedIndex].text + '?')) location.href='admin.php?page=employees&change_role=<?= $row['user_id'] ?>&new_role=' + this.value; else this.value='<?= $row['role_id'] ?>';">
                            <option value="2" <?= $row['role_id']==2?'selected':'' ?>>Quản lý</option>
                            <option value="3" <?= $row['role_id']==3?'selected':'' ?>>Thu ngân</option>
                            <option value="4" <?= $row['role_id']==4?'selected':'' ?>>Bếp</option>
                            <option value="5" <?= $row['role_id']==5?'selected':'' ?>>Phục vụ</option>
                        </select>
                    </td>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button class="btn btn-primary" style="padding:8px 16px;font-size:13px;border:none;cursor:pointer;border-radius:6px;font-weight:600;" onclick="editEmployee(<?= $row['user_id'] ?>)">Sửa</button>
                            <a href="admin.php?page=employees&delete=<?= $row['user_id'] ?>" class="btn btn-danger" style="padding:8px 16px;font-size:13px;text-decoration:none;display:inline-block;border-radius:6px;font-weight:600;" onclick="return confirm('Xóa nhân viên này vĩnh viễn?')">Xóa</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7"><div class="empty-state">Không có nhân viên nào.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="empModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Thêm nhân viên</h3>
            <button class="btn btn-secondary" onclick="document.getElementById('empModal').classList.remove('show')">Đóng</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="form-group"><label>Họ và tên</label><input class="input" type="text" name="full_name" required></div>
                    <div class="form-group">
                        <label>SĐT (bắt buộc 10 số)</label>
                        <input class="input" type="text" name="phone" id="phone-input" pattern="^0[0-9]{9}$" minlength="10" maxlength="10" title="Nhập đúng 10 số, bắt đầu bằng số 0 (VD: 0901234567)" required>
                        <small style="color:#666;font-size:12px;">VD: 0901234567 (đúng 10 số, bắt đầu bằng 0)</small>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input class="input" type="email" name="email" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Nhập email hợp lệ (VD: example@gmail.com)" required>
                        <small style="color:#666;font-size:12px;">VD: example@gmail.com</small>
                    </div>
                    <div class="form-group"><label>Username</label><input class="input" type="text" name="username" required></div>
                    <div class="form-group">
                        <label>Mật khẩu</label>
                        <div style="position:relative;">
                            <input class="input" type="password" name="password" id="empPassword" required>
                            <button type="button" onclick="togglePassword('empPassword', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;padding:0;color:#666;">
                                👁️
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Vai trò</label>
                        <select class="select" name="role_id" required>
                            <option value="">-- Chọn --</option>
                            <option value="2">Quản lý</option><option value="3">Thu ngân</option>
                            <option value="4">Bếp</option><option value="5">Phục vụ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ngày sinh</label>
                        <input class="input" type="date" name="ngay_sinh">
                    </div>
                    <div class="form-group">
                        <label>Giới tính</label>
                        <select class="select" name="gioi_tinh">
                            <option value="">-- Chọn --</option>
                            <option value="Nam">Nam</option>
                            <option value="Nữ">Nữ</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" onclick="document.getElementById('empModal').classList.remove('show')">Hủy</button>
                <button class="btn btn-primary" type="submit" name="create_employee">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa nhân viên -->
<div class="modal-backdrop" id="editEmpModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Sửa thông tin nhân viên</h3>
            <button class="btn btn-secondary" onclick="document.getElementById('editEmpModal').classList.remove('show')">Đóng</button>
        </div>
        <form method="POST">
            <input type="hidden" name="user_id" id="edit-user-id">
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="form-group"><label>Họ và tên</label><input class="input" type="text" name="full_name" id="edit-full-name" required></div>
                    <div class="form-group">
                        <label>SĐT (bắt buộc 10 số)</label>
                        <input class="input" type="text" name="phone" id="edit-phone" pattern="^0[0-9]{9}$" minlength="10" maxlength="10" title="Nhập đúng 10 số, bắt đầu bằng số 0 (VD: 0901234567)" required>
                        <small style="color:#666;font-size:12px;">VD: 0901234567 (đúng 10 số, bắt đầu bằng 0)</small>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input class="input" type="email" name="email" id="edit-email" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Nhập email hợp lệ (VD: example@gmail.com)" required>
                        <small style="color:#666;font-size:12px;">VD: example@gmail.com</small>
                    </div>
                    <div class="form-group">
                        <label>Vai trò</label>
                        <select class="select" name="role_id" id="edit-role-id" required>
                            <option value="">-- Chọn --</option>
                            <option value="2">Quản lý</option><option value="3">Thu ngân</option>
                            <option value="4">Bếp</option><option value="5">Phục vụ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ngày sinh</label>
                        <input class="input" type="date" name="ngay_sinh" id="edit-ngay-sinh">
                    </div>
                    <div class="form-group">
                        <label>Giới tính</label>
                        <select class="select" name="gioi_tinh" id="edit-gioi-tinh">
                            <option value="">-- Chọn --</option>
                            <option value="Nam">Nam</option>
                            <option value="Nữ">Nữ</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" onclick="document.getElementById('editEmpModal').classList.remove('show')">Hủy</button>
                <button class="btn btn-primary" type="submit" name="edit_employee">Cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
function editEmployee(userId) {
    fetch(`/quanlynhahang/manager/api/employees.php?action=get&user_id=${userId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const emp = data.data;
                document.getElementById('edit-user-id').value = emp.user_id;
                document.getElementById('edit-full-name').value = emp.full_name;
                document.getElementById('edit-phone').value = emp.phone;
                document.getElementById('edit-email').value = emp.email;
                document.getElementById('edit-role-id').value = emp.role_id;
                document.getElementById('edit-ngay-sinh').value = emp.ngay_sinh || '';
                document.getElementById('edit-gioi-tinh').value = emp.gioi_tinh || '';
                document.getElementById('editEmpModal').classList.add('show');
            } else {
                alert('Lỗi: ' + data.message);
            }
        })
        .catch(err => alert('Lỗi kết nối: ' + err.message));
}

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '🙈';
    } else {
        input.type = 'password';
        button.textContent = '👁️';
    }
}

const phoneInput = document.getElementById('phone-input');
phoneInput.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    this.setCustomValidity('');
});
phoneInput.addEventListener('blur', function() {
    if (this.value && (this.value.length !== 10 || !this.value.startsWith('0'))) {
        this.setCustomValidity('Số điện thoại phải đúng 10 số và bắt đầu bằng số 0 (VD: 0901234567)');
        this.reportValidity();
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>


