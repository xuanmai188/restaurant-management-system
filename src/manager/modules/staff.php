<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);

// Get all staff members
$staff_query = "
    SELECT 
        u.user_id,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        r.role_name,
        u.created_at
    FROM users u
    LEFT JOIN roles r ON r.role_id = u.role_id
    WHERE r.role_name IN ('PhucVu', 'Bep', 'ThuNgan')
    AND (u.status IS NULL OR u.status != 'khoa')
    ORDER BY r.role_name, u.full_name
";
$staff_result = $conn->query($staff_query);
$staff_members = $staff_result->fetch_all(MYSQLI_ASSOC);

$role_labels = [
    'PhucVu' => 'Phục vụ',
    'Bep' => 'Nhà bếp',
    'ThuNgan' => 'Thu ngân'
];
?>

<div class="module-container">
    <div class="module-header">
        <div>
            <h2>Quản lý nhân viên</h2>
            <p>Phân ca và quản lý danh sách nhân viên</p>
        </div>
        <button class="btn btn-primary" onclick="openAddStaffModal()">
            <i class="fas fa-plus"></i> Thêm nhân viên
        </button>
    </div>

    <div class="staff-list" id="staff-list">
        <?php if (count($staff_members) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Họ tên</th>
                        <th>Tên đăng nhập</th>
                        <th>Vai trò</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_members as $staff): ?>
                        <tr>
                            <td><?= $staff['user_id'] ?></td>
                            <td><?= e($staff['full_name']) ?></td>
                            <td><?= e($staff['username']) ?></td>
                            <td>
                                <span class="badge badge-<?= $staff['role_name'] ?>">
                                    <?= $role_labels[$staff['role_name']] ?? $staff['role_name'] ?>
                                </span>
                            </td>
                            <td><?= e($staff['email'] ?? 'N/A') ?></td>
                            <td><?= e($staff['phone'] ?? 'N/A') ?></td>
                            <td><?= date('d/m/Y', strtotime($staff['created_at'])) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/quanlynhahang/manager/staff-history.php?id=<?= $staff['user_id'] ?>" class="btn-action btn-history">
                                        <i class="fas fa-history"></i> Lịch sử
                                    </a>
                                    <button class="btn-action btn-schedule" 
                                            onclick="openScheduleModal(<?= $staff['user_id'] ?>, '<?= htmlspecialchars($staff['full_name'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-calendar-alt"></i> Phân ca
                                    </button>
                                    <button class="btn-action btn-edit" 
                                            onclick="openEditStaffModal(<?= $staff['user_id'] ?>)">
                                        <i class="fas fa-edit"></i> Sửa
                                    </button>
                                    <button class="btn-action btn-delete" 
                                            onclick="confirmDeleteStaff(<?= $staff['user_id'] ?>, '<?= htmlspecialchars($staff['full_name'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-trash"></i> Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>Chưa có nhân viên nào trong hệ thống</p>
                <button class="btn btn-primary" onclick="openAddStaffModal()">
                    <i class="fas fa-plus"></i> Thêm nhân viên đầu tiên
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Thêm/Sửa nhân viên -->
<div id="staffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Thêm nhân viên mới</h3>
            <button class="modal-close" onclick="closeStaffModal()">&times;</button>
        </div>
        <form id="staffForm" method="post" onsubmit="handleStaffSubmit(event)">
            <input type="hidden" id="staffId" name="staff_id">
            
            <div class="form-group">
                <label for="fullName">Họ và tên <span class="required">*</span></label>
                <input type="text" id="fullName" name="full_name" required>
            </div>

            <div class="form-group">
                <label for="username">Tên đăng nhập <span class="required">*</span></label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu <span class="required" id="passwordRequired">*</span></label>
                <input type="password" id="password" name="password">
                <small id="passwordHint" style="display:none; color: #6b7280;">Để trống nếu không muốn đổi mật khẩu</small>
            </div>

            <div class="form-group">
                <label for="roleId">Vai trò <span class="required">*</span></label>
                <select id="roleId" name="role_id" required>
                    <option value="">-- Chọn vai trò --</option>
                    <option value="5">Phục vụ</option>
                    <option value="4">Nhà bếp</option>
                    <option value="3">Thu ngân</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email">
            </div>

            <div class="form-group">
                <label for="phone">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" pattern="[0-9]{10}" maxlength="10" placeholder="Nhập 10 số">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label for="ngaySinh">Ngày sinh</label>
                    <input type="date" id="ngaySinh" name="ngay_sinh">
                </div>
                <div class="form-group">
                    <label for="gioiTinh">Giới tính</label>
                    <select id="gioiTinh" name="gioi_tinh">
                        <option value="">-- Chọn --</option>
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Hủy</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Thêm nhân viên</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Phân ca làm việc -->
<div id="scheduleModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <div>
                <h3>Phân ca làm việc</h3>
                <p id="scheduleStaffName" style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;"></p>
            </div>
            <button class="modal-close" onclick="closeScheduleModal()">&times;</button>
        </div>
        
        <div class="schedule-container">
            <div class="schedule-tabs">
                <button class="schedule-tab active" onclick="switchScheduleTab('shifts')">
                    <i class="fas fa-clock"></i> Ca làm việc
                </button>
                <button class="schedule-tab" onclick="switchScheduleTab('days-off')">
                    <i class="fas fa-calendar-times"></i> Ngày nghỉ
                </button>
            </div>

            <!-- Tab Ca làm việc -->
            <div id="shiftsTab" class="schedule-tab-content active">
                <div class="schedule-header">
                    <h4>Lịch làm việc trong tuần</h4>
                    <button class="btn btn-sm btn-primary" onclick="saveShifts()">
                        <i class="fas fa-save"></i> Lưu lịch
                    </button>
                </div>
                
                <div class="week-selector">
                    <label>
                        <input type="radio" name="weekType" value="all" checked onchange="toggleWeekSelector()">
                        Áp dụng cho tất cả các tuần
                    </label>
                    <label>
                        <input type="radio" name="weekType" value="specific" onchange="toggleWeekSelector()">
                        Áp dụng cho tuần cụ thể
                    </label>
                    <div id="weekDatePicker" style="display: none; margin-top: 12px;">
                        <label style="font-size: 13px; color: #6b7280; margin-bottom: 4px; display: block;">Chọn tuần (ngày thứ 2):</label>
                        <input type="date" id="weekStartDate" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
                
                <div class="shifts-grid">
                    <div class="shift-day">
                        <div class="shift-day-header">
                            <input type="checkbox" id="mon" onchange="toggleDay('mon')">
                            <label for="mon">Thứ 2</label>
                        </div>
                        <div class="shift-time">
                            <input type="time" id="mon_start" value="08:00">
                            <span>-</span>
                            <input type="time" id="mon_end" value="17:00">
                        </div>
                    </div>

                    <div class="shift-day">
                        <div class="shift-day-header">
                            <input type="checkbox" id="tue" onchange="toggleDay('tue')">
                            <label for="tue">Thứ 3</label>
                        </div>
                        <div class="shift-time">
                            <input type="time" id="tue_start" value="08:00">
                            <span>-</span>
                            <input type="time" id="tue_end" value="17:00">
                        </div>
                    </div>

                    <div class="shift-day">
                        <div class="shift-day-header">
                            <input type="checkbox" id="wed" onchange="toggleDay('wed')">
                            <label for="wed">Thứ 4</label>
                        </div>
                        <div class="shift-time">
                            <input type="time" id="wed_start" value="08:00">
                            <span>-</span>
                            <input type="time" id="wed_end" value="17:00">
                        </div>
                    </div>

                    <div class="shift-day">
                        <div class="shift-day-header">
                            <input type="checkbox" id="thu" onchange="toggleDay('thu')">
                            <label for="thu">Thứ 5</label>
                        </div>
                        <div class="shift-time">
                            <input type="time" id="thu_start" value="08:00">
                            <span>-</span>
                            <input type="time" id="thu_end" value="17:00">
                        </div>
                    </div>

                    <div class="shift-day">
                        <div class="shift-day-header">
                            <input type="checkbox" id="fri" onchange="toggleDay('fri')">
                            <label for="fri">Thứ 6</label>
                        </div>
                        <div class="shift-time">
                            <input type="time" id="fri_start" value="08:00">
                            <span>-</span>
                            <input type="time" id="fri_end" value="17:00">
                        </div>
                    </div>

                    <div class="shift-day">
                        <div class="shift-day-header">
                            <input type="checkbox" id="sat" onchange="toggleDay('sat')">
                            <label for="sat">Thứ 7</label>
                        </div>
                        <div class="shift-time">
                            <input type="time" id="sat_start" value="08:00">
                            <span>-</span>
                            <input type="time" id="sat_end" value="17:00">
                        </div>
                    </div>

                    <div class="shift-day">
                        <div class="shift-day-header">
                            <input type="checkbox" id="sun" onchange="toggleDay('sun')">
                            <label for="sun">Chủ nhật</label>
                        </div>
                        <div class="shift-time">
                            <input type="time" id="sun_start" value="08:00">
                            <span>-</span>
                            <input type="time" id="sun_end" value="17:00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Ngày nghỉ -->
            <div id="daysOffTab" class="schedule-tab-content">
                <div class="schedule-header">
                    <h4>Đăng ký ngày nghỉ</h4>
                    <button class="btn btn-sm btn-primary" onclick="addDayOff()">
                        <i class="fas fa-plus"></i> Thêm ngày nghỉ
                    </button>
                </div>

                <div class="days-off-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Từ ngày</label>
                            <input type="date" id="dayOffStart">
                        </div>
                        <div class="form-group">
                            <label>Đến ngày</label>
                            <input type="date" id="dayOffEnd">
                        </div>
                        <div class="form-group">
                            <label>Lý do</label>
                            <input type="text" id="dayOffReason" placeholder="Nghỉ phép, ốm...">
                        </div>
                    </div>
                </div>

                <div class="days-off-list" id="daysOffList">
                    <p style="text-align: center; color: #9ca3af; padding: 20px;">Chưa có ngày nghỉ nào được đăng ký</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Lịch sử làm việc -->
<div id="historyModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <div>
                <h3>Lịch sử làm việc</h3>
                <p id="historyStaffName" style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;"></p>
            </div>
            <button class="modal-close" onclick="closeHistoryModal()">&times;</button>
        </div>
        
        <div class="history-container">
            <div class="history-filters">
                <div class="form-row">
                    <div class="form-group">
                        <label>Từ ngày</label>
                        <input type="date" id="historyStartDate">
                    </div>
                    <div class="form-group">
                        <label>Đến ngày</label>
                        <input type="date" id="historyEndDate">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button class="btn btn-primary" onclick="loadWorkHistory()">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                </div>
            </div>

            <div class="history-stats">
                <div class="stat-card">
                    <div class="stat-label">Tổng số ca</div>
                    <div class="stat-value" id="totalShifts">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Tổng giờ làm</div>
                    <div class="stat-value" id="totalHours">0h</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Ngày nghỉ</div>
                    <div class="stat-value" id="totalDaysOff">0</div>
                </div>
            </div>

            <div class="history-list" id="historyList">
                <div class="loading">Đang tải lịch sử...</div>
            </div>
        </div>
    </div>
</div>

<style>
.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.module-header h2 {
    margin: 0 0 4px 0;
    font-size: 24px;
    color: #111827;
}

.module-header p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-primary {
    background: #0f766e;
    color: white;
}

.btn-primary:hover {
    background: #0d6560;
}

.btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.btn-secondary:hover {
    background: #d1d5db;
}

.staff-list {
    margin-top: 20px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e0e0e0;
    font-size: 14px;
}

.table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}

.table tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-PhucVu {
    background: #dbeafe;
    color: #1e40af;
}

.badge-Bep {
    background: #fef3c7;
    color: #92400e;
}

.badge-ThuNgan {
    background: #d1fae5;
    color: #065f46;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-history {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #6ee7b7;
    text-decoration: none;
}

.btn-history:hover {
    background: linear-gradient(135deg, #a7f3d0 0%, #86efac 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    color: #065f46;
}

.btn-schedule {
    background: #fef3c7;
    color: #92400e;
}

.btn-schedule:hover {
    background: #fde68a;
}

.btn-edit {
    background: #dbeafe;
    color: #1e40af;
}

.btn-edit:hover {
    background: #bfdbfe;
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-delete:hover {
    background: #fecaca;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.empty-state p {
    margin-bottom: 20px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.2s;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s;
}

.modal-large {
    max-width: 800px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    color: #111827;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #9ca3af;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 32px;
    height: 32px;
}

.modal-close:hover {
    color: #374151;
}

.modal-content form {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.required {
    color: #dc2626;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #0f766e;
}

.form-group small {
    display: block;
    margin-top: 4px;
    font-size: 12px;
}

.modal-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

/* Schedule Modal Styles */
.schedule-container {
    padding: 24px;
}

.schedule-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid #e5e7eb;
}

.schedule-tab {
    padding: 12px 20px;
    border: none;
    background: none;
    cursor: pointer;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.schedule-tab:hover {
    color: #0f766e;
}

.schedule-tab.active {
    color: #0f766e;
    border-bottom-color: #0f766e;
}

.schedule-tab-content {
    display: none;
}

.schedule-tab-content.active {
    display: block;
}

.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.schedule-header h4 {
    margin: 0;
    font-size: 16px;
    color: #111827;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.week-selector {
    background: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
}

.week-selector label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #374151;
}

.week-selector input[type="radio"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.shifts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.shift-day {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    background: #f9fafb;
}

.shift-day-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.shift-day-header input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.shift-day-header label {
    font-weight: 600;
    color: #374151;
    cursor: pointer;
    margin: 0;
}

.shift-time {
    display: flex;
    align-items: center;
    gap: 8px;
}

.shift-time input[type="time"] {
    flex: 1;
    padding: 8px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
}

.shift-time input[type="time"]:disabled {
    background: #f3f4f6;
    color: #9ca3af;
}

.shift-time span {
    color: #6b7280;
}

.days-off-form {
    background: #f9fafb;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 2fr;
    gap: 16px;
}

.form-row .form-group {
    margin-bottom: 0;
}

.days-off-list {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    min-height: 200px;
}

.day-off-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.day-off-item:last-child {
    border-bottom: none;
}

.day-off-info {
    flex: 1;
}

.day-off-date {
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
}

.day-off-reason {
    color: #6b7280;
    font-size: 13px;
}

.day-off-actions {
    display: flex;
    gap: 8px;
}

.btn-icon-sm {
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.btn-icon-sm.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-icon-sm.btn-delete:hover {
    background: #fecaca;
}

/* History Modal Styles */
.history-container {
    padding: 24px;
}

.history-filters {
    background: #f9fafb;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.history-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 32px;
    font-weight: 800;
    color: #0f766e;
}

.history-list {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    background: white;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 20px;
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.2s;
}

.history-item:hover {
    background: #f9fafb;
}

.history-item:last-child {
    border-bottom: none;
}

.history-date {
    display: flex;
    align-items: center;
    gap: 12px;
}

.history-date-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #1e40af;
}

.history-date-info {
    display: flex;
    flex-direction: column;
}

.history-date-text {
    font-weight: 700;
    color: #111827;
    font-size: 15px;
}

.history-day-name {
    font-size: 13px;
    color: #6b7280;
    margin-top: 2px;
}

.history-time {
    display: flex;
    align-items: center;
    gap: 20px;
}

.history-time-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.history-time-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
}

.history-time-value {
    font-weight: 700;
    color: #111827;
    font-size: 15px;
}

.history-duration {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 14px;
    border: 1px solid #6ee7b7;
}

.history-empty {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.loading {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
</style>

<script>
(function() {
    console.log('=== STAFF MODULE SCRIPT LOADED ===');

    let currentStaffId = null;
    let currentStaffName = '';
    let daysOffData = [];

    // Mở modal thêm nhân viên
    window.openAddStaffModal = function() {
        document.getElementById('modalTitle').textContent = 'Thêm nhân viên mới';
        document.getElementById('submitBtn').textContent = 'Thêm nhân viên';
        document.getElementById('staffForm').reset();
        document.getElementById('staffId').value = '';
        document.getElementById('password').required = true;
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHint').style.display = 'none';
        document.getElementById('staffModal').classList.add('show');
    };

    // Mở modal sửa nhân viên
    window.openEditStaffModal = async function(userId) {
        try {
            const response = await fetch(`/quanlynhahang/manager/api/staff.php?action=get&id=${userId}`);
            
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                alert('Lỗi: ' + (text || 'HTTP ' + response.status));
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('modalTitle').textContent = 'Sửa thông tin nhân viên';
                document.getElementById('submitBtn').textContent = 'Cập nhật';
                document.getElementById('staffId').value = data.staff.user_id;
                document.getElementById('fullName').value = data.staff.full_name;
                document.getElementById('username').value = data.staff.username;
                document.getElementById('email').value = data.staff.email || '';
                document.getElementById('phone').value = data.staff.phone || '';
                document.getElementById('ngaySinh').value = data.staff.ngay_sinh || '';
                document.getElementById('gioiTinh').value = data.staff.gioi_tinh || '';
                document.getElementById('roleId').value = data.staff.role_id;
                document.getElementById('password').value = '';
                document.getElementById('password').required = false;
                document.getElementById('passwordRequired').style.display = 'none';
                document.getElementById('passwordHint').style.display = 'block';
                document.getElementById('staffModal').classList.add('show');
            } else {
                alert('Không thể tải thông tin nhân viên: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi tải thông tin nhân viên: ' + error.message);
        }
    };

    // Đóng modal nhân viên
    window.closeStaffModal = function() {
        const modal = document.getElementById('staffModal');
        if (modal) modal.classList.remove('show');
    };

    function reloadStaffList() {
        console.log('reloadStaffList called'); // DEBUG
        // Reload toàn bộ trang, xóa cache bằng timestamp
        const baseUrl = window.location.origin + window.location.pathname;
        window.location.replace(baseUrl + '?module=staff&t=' + Date.now());
    }

    // Xử lý submit form nhân viên
    window.handleStaffSubmit = async function(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const staffId = formData.get('staff_id');
        const action = staffId ? 'update' : 'create';
        
        formData.append('action', action);
        
        try {
            const response = await fetch('/quanlynhahang/manager/api/staff.php', {
                method: 'POST',
                body: formData
            });
            
            // Kiểm tra nếu response không thành công (status không phải 2xx)
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                alert('Lỗi: ' + (text || 'HTTP ' + response.status));
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                closeStaffModal();
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Lưu thông tin nhân viên thành công');
                }
                reloadStaffList();
            } else {
                alert('Lỗi: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi lưu thông tin nhân viên: ' + error.message);
        }
    };

    // Xác nhận xóa nhân viên
    window.confirmDeleteStaff = function(userId, fullName) {
        if (_deletingStaff) return;
        if (confirm(`Bạn có chắc chắn muốn xóa nhân viên "${fullName}"?\n\nLưu ý: Thao tác này không thể hoàn tác!`)) {
            deleteStaff(userId);
        }
    };

    let _deletingStaff = false;

    // Xóa nhân viên
    async function deleteStaff(userId) {
        if (_deletingStaff) return; // chặn double-call
        _deletingStaff = true;

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('staff_id', userId);
            
            const response = await fetch('/quanlynhahang/manager/api/staff.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            if (!response.ok) {
                const text = await response.text();
                alert('Lỗi: ' + (text || 'HTTP ' + response.status));
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                reloadStaffList();
            } else {
                alert('Lỗi: ' + data.message);
            }
        } catch (error) {
            alert('Có lỗi xảy ra khi xóa nhân viên: ' + error.message);
        } finally {
            _deletingStaff = false;
        }
    }

    // Mở modal phân ca
    window.openScheduleModal = async function(userId, fullName) {
        currentStaffId = userId;
        currentStaffName = fullName;
        document.getElementById('scheduleStaffName').textContent = fullName;
        document.getElementById('scheduleModal').classList.add('show');
        
        // Load dữ liệu ca làm việc và ngày nghỉ
        await loadScheduleData(userId);
    };

    // Đóng modal phân ca
    window.closeScheduleModal = function() {
        document.getElementById('scheduleModal').classList.remove('show');
        currentStaffId = null;
        currentStaffName = '';
    };

    // Chuyển tab
    window.switchScheduleTab = function(tabName) {
        // Remove active class from all tabs
        document.querySelectorAll('.schedule-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.schedule-tab-content').forEach(content => content.classList.remove('active'));
        
        // Add active class to selected tab
        if (tabName === 'shifts') {
            document.querySelector('.schedule-tab:first-child').classList.add('active');
            document.getElementById('shiftsTab').classList.add('active');
        } else {
            document.querySelector('.schedule-tab:last-child').classList.add('active');
            document.getElementById('daysOffTab').classList.add('active');
        }
    };

    // Toggle ngày làm việc
    window.toggleDay = function(day) {
        const checkbox = document.getElementById(day);
        const startInput = document.getElementById(day + '_start');
        const endInput = document.getElementById(day + '_end');
        
        startInput.disabled = !checkbox.checked;
        endInput.disabled = !checkbox.checked;
    };

    // Toggle week selector
    window.toggleWeekSelector = function() {
        const weekType = document.querySelector('input[name="weekType"]:checked').value;
        const weekDatePicker = document.getElementById('weekDatePicker');
        
        if (weekType === 'specific') {
            weekDatePicker.style.display = 'block';
            // Set default to next Monday
            const today = new Date();
            const dayOfWeek = today.getDay();
            const daysUntilMonday = dayOfWeek === 0 ? 1 : (8 - dayOfWeek);
            const nextMonday = new Date(today);
            nextMonday.setDate(today.getDate() + daysUntilMonday);
            document.getElementById('weekStartDate').valueAsDate = nextMonday;
        } else {
            weekDatePicker.style.display = 'none';
        }
    };

    // Load dữ liệu lịch làm việc
    async function loadScheduleData(userId) {
        try {
            const response = await fetch(`/quanlynhahang/manager/api/staff.php?action=get_schedule&id=${userId}`);
            
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Load shifts
                if (data.shifts) {
                    const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    days.forEach(day => {
                        const shift = data.shifts[day];
                        if (shift) {
                            document.getElementById(day).checked = true;
                            document.getElementById(day + '_start').value = shift.start;
                            document.getElementById(day + '_end').value = shift.end;
                            document.getElementById(day + '_start').disabled = false;
                            document.getElementById(day + '_end').disabled = false;
                        } else {
                            document.getElementById(day).checked = false;
                            document.getElementById(day + '_start').disabled = true;
                            document.getElementById(day + '_end').disabled = true;
                        }
                    });
                }
                
                // Load days off
                if (data.daysOff) {
                    daysOffData = data.daysOff;
                    renderDaysOff();
                }
            }
        } catch (error) {
            console.error('Error loading schedule:', error);
        }
    }

    // Lưu lịch làm việc
    window.saveShifts = async function() {
        const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        const shifts = {};
        
        days.forEach(day => {
            const checkbox = document.getElementById(day);
            if (checkbox.checked) {
                shifts[day] = {
                    start: document.getElementById(day + '_start').value,
                    end: document.getElementById(day + '_end').value
                };
            }
        });
        
        // Get week type and start date
        const weekType = document.querySelector('input[name="weekType"]:checked').value;
        let weekStartDate = null;
        
        if (weekType === 'specific') {
            weekStartDate = document.getElementById('weekStartDate').value;
            if (!weekStartDate) {
                alert('Vui lòng chọn ngày bắt đầu tuần');
                return;
            }
            
            // Validate that selected date is a Monday
            const selectedDate = new Date(weekStartDate);
            if (selectedDate.getDay() !== 1) {
                alert('Vui lòng chọn ngày thứ 2 (Monday)');
                return;
            }
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'save_shifts');
            formData.append('staff_id', currentStaffId);
            formData.append('shifts', JSON.stringify(shifts));
            if (weekStartDate) {
                formData.append('week_start_date', weekStartDate);
            }
            
            const response = await fetch('/quanlynhahang/manager/api/staff.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                alert('Lỗi: ' + (text || 'HTTP ' + response.status));
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                alert('Lưu lịch làm việc thành công');
            } else {
                alert('Lỗi: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi lưu lịch làm việc: ' + error.message);
        }
    };

    // Thêm ngày nghỉ
    window.addDayOff = async function() {
        const startDate = document.getElementById('dayOffStart').value;
        const endDate = document.getElementById('dayOffEnd').value;
        const reason = document.getElementById('dayOffReason').value;
        
        if (!startDate || !endDate) {
            alert('Vui lòng chọn ngày bắt đầu và kết thúc');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'add_day_off');
            formData.append('staff_id', currentStaffId);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('reason', reason);
            
            const response = await fetch('/quanlynhahang/manager/api/staff.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                alert('Lỗi: ' + (text || 'HTTP ' + response.status));
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('dayOffStart').value = '';
                document.getElementById('dayOffEnd').value = '';
                document.getElementById('dayOffReason').value = '';
                await loadScheduleData(currentStaffId);
            } else {
                alert('Lỗi: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi thêm ngày nghỉ: ' + error.message);
        }
    };

    // Xóa ngày nghỉ
    window.deleteDayOff = async function(dayOffId) {
        if (!confirm('Bạn có chắc chắn muốn xóa ngày nghỉ này?')) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_day_off');
            formData.append('day_off_id', dayOffId);
            
            const response = await fetch('/quanlynhahang/manager/api/staff.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                alert('Lỗi: ' + (text || 'HTTP ' + response.status));
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                await loadScheduleData(currentStaffId);
            } else {
                alert('Lỗi: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi xóa ngày nghỉ');
        }
    };

    // Render danh sách ngày nghỉ
    function renderDaysOff() {
        const container = document.getElementById('daysOffList');
        
        if (daysOffData.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #9ca3af; padding: 20px;">Chưa có ngày nghỉ nào được đăng ký</p>';
            return;
        }
        
        container.innerHTML = daysOffData.map(item => `
            <div class="day-off-item">
                <div class="day-off-info">
                    <div class="day-off-date">${formatDate(item.start_date)} - ${formatDate(item.end_date)}</div>
                    <div class="day-off-reason">${item.reason || 'Không có lý do'}</div>
                </div>
                <div class="day-off-actions">
                    <button class="btn-action btn-delete" onclick="deleteDayOff(${item.id})">
                        <i class="fas fa-trash"></i> Xóa
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Format date
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('vi-VN');
    }

    // Mở modal lịch sử làm việc
    window.openHistoryModal = async function(userId, fullName) {
        currentStaffId = userId;
        currentStaffName = fullName;
        document.getElementById('historyStaffName').textContent = fullName;
        document.getElementById('historyModal').classList.add('show');
        
        // Set default date range (last 30 days)
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - 30);
        
        document.getElementById('historyEndDate').valueAsDate = endDate;
        document.getElementById('historyStartDate').valueAsDate = startDate;
        
        // Load work history
        await loadWorkHistory();
    };

    // Đóng modal lịch sử
    window.closeHistoryModal = function() {
        document.getElementById('historyModal').classList.remove('show');
        currentStaffId = null;
        currentStaffName = '';
    };

    // Load lịch sử làm việc
    window.loadWorkHistory = async function() {
        const startDate = document.getElementById('historyStartDate').value;
        const endDate = document.getElementById('historyEndDate').value;
        
        if (!startDate || !endDate) {
            alert('Vui lòng chọn khoảng thời gian');
            return;
        }
        
        const historyList = document.getElementById('historyList');
        historyList.innerHTML = '<div class="loading">Đang tải lịch sử...</div>';
        
        try {
            const response = await fetch(`/quanlynhahang/manager/api/staff.php?action=get_work_history&id=${currentStaffId}&start_date=${startDate}&end_date=${endDate}`);
            const data = await response.json();
            
            if (data.success) {
                // Update stats
                document.getElementById('totalShifts').textContent = data.stats.total_shifts || 0;
                document.getElementById('totalHours').textContent = (data.stats.total_hours || 0) + 'h';
                document.getElementById('totalDaysOff').textContent = data.stats.total_days_off || 0;
                
                // Render history
                renderWorkHistory(data.history);
            } else {
                historyList.innerHTML = '<div class="history-empty">Không thể tải lịch sử làm việc</div>';
            }
        } catch (error) {
            console.error('Error loading work history:', error);
            historyList.innerHTML = '<div class="history-empty">Có lỗi xảy ra khi tải lịch sử</div>';
        }
    };

    // Render lịch sử làm việc
    function renderWorkHistory(history) {
        const container = document.getElementById('historyList');
        
        if (!history || history.length === 0) {
            container.innerHTML = '<div class="history-empty">Không có lịch sử làm việc trong khoảng thời gian này</div>';
            return;
        }
        
        const dayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        
        container.innerHTML = history.map(item => {
            const date = new Date(item.work_date);
            const dayName = dayNames[date.getDay()];
            const dateText = date.toLocaleDateString('vi-VN');
            
            // Calculate duration
            const start = item.start_time.split(':');
            const end = item.end_time.split(':');
            const startMinutes = parseInt(start[0]) * 60 + parseInt(start[1]);
            const endMinutes = parseInt(end[0]) * 60 + parseInt(end[1]);
            const durationMinutes = endMinutes - startMinutes;
            const hours = Math.floor(durationMinutes / 60);
            const minutes = durationMinutes % 60;
            const durationText = `${hours}h${minutes > 0 ? ' ' + minutes + 'p' : ''}`;
            
            return `
                <div class="history-item">
                    <div class="history-date">
                        <div class="history-date-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="history-date-info">
                            <div class="history-date-text">${dateText}</div>
                            <div class="history-day-name">${dayName}</div>
                        </div>
                    </div>
                    <div class="history-time">
                        <div class="history-time-item">
                            <div class="history-time-label">Giờ vào</div>
                            <div class="history-time-value">${item.start_time}</div>
                        </div>
                        <div class="history-time-item">
                            <div class="history-time-label">Giờ ra</div>
                            <div class="history-time-value">${item.end_time}</div>
                        </div>
                        <div class="history-duration">${durationText}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Đóng modal khi click bên ngoài
    window.onclick = function(event) {
        const staffModal = document.getElementById('staffModal');
        const scheduleModal = document.getElementById('scheduleModal');
        const historyModal = document.getElementById('historyModal');
        
        if (event.target === staffModal) {
            closeStaffModal();
        }
        if (event.target === scheduleModal) {
            closeScheduleModal();
        }
        if (event.target === historyModal) {
            closeHistoryModal();
        }
    };

    // Initialize: disable all time inputs
    const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    days.forEach(day => {
        document.getElementById(day + '_start').disabled = true;
        document.getElementById(day + '_end').disabled = true;
    });

    // Auto-open schedule modal if URL parameters are present
    const urlParams = new URLSearchParams(window.location.search);
    const editScheduleId = urlParams.get('edit_schedule');
    const weekStartDate = urlParams.get('week');
    
    if (editScheduleId && weekStartDate) {
        // Find staff name from the table
        const staffRow = document.querySelector(`button[onclick*="openScheduleModal(${editScheduleId}"]`);
        if (staffRow) {
            const staffNameMatch = staffRow.getAttribute('onclick').match(/'([^']+)'/);
            const staffName = staffNameMatch ? staffNameMatch[1] : 'Nhân viên';
            
            // Open modal with specific week
            currentStaffId = parseInt(editScheduleId);
            currentStaffName = staffName;
            document.getElementById('scheduleStaffName').textContent = staffName;
            
            // Set week selector to specific week
            document.querySelector('input[name="weekType"][value="specific"]').checked = true;
            toggleWeekSelector();
            document.getElementById('weekStartDate').value = weekStartDate;
            
            // Load schedule data for this week
            loadScheduleDataForWeek(currentStaffId, weekStartDate);
            
            document.getElementById('scheduleModal').classList.add('show');
        }
    }

    // Load schedule data for specific week
    async function loadScheduleDataForWeek(userId, weekStartDate) {
        try {
            const response = await fetch(`/quanlynhahang/manager/api/staff.php?action=get_schedule&id=${userId}&week_start_date=${weekStartDate}`);
            
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Load shifts
                if (data.shifts && Object.keys(data.shifts).length > 0) {
                    const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    days.forEach(day => {
                        const shift = data.shifts[day];
                        if (shift) {
                            document.getElementById(day).checked = true;
                            document.getElementById(day + '_start').value = shift.start;
                            document.getElementById(day + '_end').value = shift.end;
                            document.getElementById(day + '_start').disabled = false;
                            document.getElementById(day + '_end').disabled = false;
                        } else {
                            document.getElementById(day).checked = false;
                            document.getElementById(day + '_start').disabled = true;
                            document.getElementById(day + '_end').disabled = true;
                        }
                    });
                } else {
                    // No specific schedule for this week, load general schedule
                    const generalResponse = await fetch(`/quanlynhahang/manager/api/staff.php?action=get_schedule&id=${userId}`);
                    
                    if (!generalResponse.ok) {
                        const text = await generalResponse.text();
                        console.error('HTTP Error:', generalResponse.status, text);
                        return;
                    }
                    
                    const generalData = await generalResponse.json();
                    
                    if (generalData.success && generalData.shifts) {
                        const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                        days.forEach(day => {
                            const shift = generalData.shifts[day];
                            if (shift) {
                                document.getElementById(day).checked = true;
                                document.getElementById(day + '_start').value = shift.start;
                                document.getElementById(day + '_end').value = shift.end;
                                document.getElementById(day + '_start').disabled = false;
                                document.getElementById(day + '_end').disabled = false;
                            }
                        });
                    }
                }
                
                // Load days off
                if (data.daysOff) {
                    daysOffData = data.daysOff;
                    renderDaysOff();
                }
            }
        } catch (error) {
            console.error('Error loading schedule:', error);
        }
    }
})();
</script>
