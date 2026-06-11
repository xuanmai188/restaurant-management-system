<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['quanly']);

// Get staff ID from URL
$staffId = (int)($_GET['id'] ?? 0);

if ($staffId <= 0) {
    header('Location: /quanlynhahang/manager/index.php?module=staff');
    exit;
}

// Get staff info
$stmt = $conn->prepare("
    SELECT u.user_id, u.full_name, u.username, r.role_name, u.email, u.phone
    FROM users u
    LEFT JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = ?
");
$stmt->bind_param('i', $staffId);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    header('Location: /quanlynhahang/manager/index.php?module=staff');
    exit;
}

$role_labels = [
    'PhucVu' => 'Phục vụ',
    'Bep' => 'Nhà bếp',
    'ThuNgan' => 'Thu ngân'
];

// Set default date range (current month)
$startDate = date('Y-m-01'); // First day of current month
$endDate = date('Y-m-t'); // Last day of current month

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử làm việc - <?= e($staff['full_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9fafb;
            color: #111827;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e5e7eb;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(107, 114, 128, 0.2);
        }

        .back-button:hover {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }

        .staff-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            padding: 20px;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 15px;
            color: #111827;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .badge-PhucVu {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .badge-Bep {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .badge-ThuNgan {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .filters-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e5e7eb;
        }

        .filters-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #111827;
        }

        .filter-form {
            display: flex;
            gap: 16px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-search {
            padding: 12px 24px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-search:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
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
            font-size: 36px;
            font-weight: 800;
            color: #111827;
        }

        .history-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #111827;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .history-item:hover {
            background: linear-gradient(135deg, #f3f4f6 0%, #f9fafb 100%);
            border-color: #d1d5db;
            transform: translateX(4px);
        }

        .history-date {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .history-date-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #1e40af;
        }

        .history-date-info {
            display: flex;
            flex-direction: column;
        }

        .history-date-text {
            font-weight: 700;
            color: #111827;
            font-size: 16px;
        }

        .history-day-name {
            font-size: 13px;
            color: #6b7280;
            margin-top: 2px;
        }

        .history-time {
            display: flex;
            align-items: center;
            gap: 24px;
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
            font-weight: 600;
        }

        .history-time-value {
            font-weight: 700;
            color: #111827;
            font-size: 16px;
        }

        .history-duration {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 15px;
            border: 1px solid #6ee7b7;
        }

        .btn-edit-shift {
            padding: 8px 16px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-edit-shift:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
        }

        .loading {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .filter-form {
                flex-direction: column;
            }

            .history-item {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .history-time {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="header-top">
                <h1 class="page-title">Lịch sử làm việc</h1>
                <a href="/quanlynhahang/manager/index.php?module=staff" class="back-button">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>
            </div>

            <div class="staff-info">
                <div class="info-item">
                    <div class="info-label">Nhân viên</div>
                    <div class="info-value"><?= e($staff['full_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tên đăng nhập</div>
                    <div class="info-value"><?= e($staff['username']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Vai trò</div>
                    <div class="info-value">
                        <span class="badge badge-<?= $staff['role_name'] ?>">
                            <?= $role_labels[$staff['role_name']] ?? $staff['role_name'] ?>
                        </span>
                    </div>
                </div>
                <?php if ($staff['email']): ?>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= e($staff['email']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($staff['phone']): ?>
                <div class="info-item">
                    <div class="info-label">Số điện thoại</div>
                    <div class="info-value"><?= e($staff['phone']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="filters-section">
            <h3 class="filters-title">Bộ lọc thời gian</h3>
            <form method="GET" class="filter-form">
                <input type="hidden" name="id" value="<?= $staffId ?>">
                <div class="form-group">
                    <label>Từ ngày</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" required>
                </div>
                <div class="form-group">
                    <label>Đến ngày</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" required>
                </div>
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Tìm kiếm
                </button>
            </form>
        </div>

        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-label">Tổng số ca</div>
                <div class="stat-value" id="totalShifts">-</div>
                <div class="stat-detail" id="shiftsDetail" style="font-size: 13px; color: #6b7280; margin-top: 8px;"></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Tổng giờ làm</div>
                <div class="stat-value" id="totalHours">-</div>
                <div class="stat-detail" id="hoursDetail" style="font-size: 13px; color: #6b7280; margin-top: 8px;"></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-label">Ngày nghỉ</div>
                <div class="stat-value" id="totalDaysOff">-</div>
            </div>
        </div>

        <div class="history-section">
            <h3 class="section-title">Chi tiết lịch sử</h3>
            <div class="history-list" id="historyList">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Đang tải lịch sử...
                </div>
            </div>
        </div>
    </div>

    <script>
        const staffId = <?= $staffId ?>;
        const startDate = '<?= $startDate ?>';
        const endDate = '<?= $endDate ?>';

        async function loadWorkHistory() {
            try {
                const response = await fetch(`/quanlynhahang/manager/api/staff.php?action=get_work_history&id=${staffId}&start_date=${startDate}&end_date=${endDate}`);
                const data = await response.json();
                
                if (data.success) {
                    // Calculate days and weeks
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    const weeks = Math.floor(diffDays / 7);
                    const avgShiftsPerWeek = weeks > 0 ? (data.stats.total_shifts / weeks).toFixed(1) : 0;
                    const avgHoursPerWeek = weeks > 0 ? (data.stats.total_hours / weeks).toFixed(1) : 0;
                    
                    // Update stats
                    document.getElementById('totalShifts').textContent = data.stats.total_shifts || 0;
                    document.getElementById('shiftsDetail').textContent = `${diffDays} ngày (${weeks} tuần) • TB: ${avgShiftsPerWeek} ca/tuần`;
                    
                    document.getElementById('totalHours').textContent = (data.stats.total_hours || 0) + 'h';
                    document.getElementById('hoursDetail').textContent = `TB: ${avgHoursPerWeek}h/tuần`;
                    
                    document.getElementById('totalDaysOff').textContent = data.stats.total_days_off || 0;
                    
                    // Render history
                    renderWorkHistory(data.history);
                } else {
                    document.getElementById('historyList').innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Không thể tải lịch sử làm việc</p></div>';
                }
            } catch (error) {
                console.error('Error loading work history:', error);
                document.getElementById('historyList').innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Có lỗi xảy ra khi tải lịch sử</p></div>';
            }
        }

        function renderWorkHistory(history) {
            const container = document.getElementById('historyList');
            
            if (!history || history.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-times"></i><p>Không có lịch sử làm việc trong khoảng thời gian này</p></div>';
                return;
            }
            
            const dayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
            
            // Group history by week
            const weekGroups = {};
            history.forEach(item => {
                const date = new Date(item.work_date);
                const dayNum = date.getDay() === 0 ? 7 : date.getDay(); // Convert Sunday from 0 to 7
                const daysFromMonday = dayNum - 1;
                const weekStart = new Date(date);
                weekStart.setDate(date.getDate() - daysFromMonday);
                const weekKey = weekStart.toISOString().split('T')[0];
                
                if (!weekGroups[weekKey]) {
                    weekGroups[weekKey] = [];
                }
                weekGroups[weekKey].push(item);
            });
            
            let html = '';
            Object.keys(weekGroups).sort().reverse().forEach(weekKey => {
                const weekItems = weekGroups[weekKey];
                const weekStart = new Date(weekKey);
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);
                
                html += `
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 12px 16px; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 10px;">
                            <div style="font-weight: 700; color: #374151; font-size: 15px;">
                                <i class="fas fa-calendar-week" style="margin-right: 8px; color: #6b7280;"></i>
                                Tuần ${weekStart.toLocaleDateString('vi-VN')} - ${weekEnd.toLocaleDateString('vi-VN')}
                            </div>
                            <a href="/quanlynhahang/manager/index.php?module=staff" class="btn-edit-shift" onclick="event.preventDefault(); editWeekSchedule(${staffId}, '${weekKey}')">
                                <i class="fas fa-edit"></i> Sửa lịch tuần này
                            </a>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                `;
                
                weekItems.forEach(item => {
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
                    
                    html += `
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
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function editWeekSchedule(userId, weekStartDate) {
            // Redirect to staff management page with parameters to open schedule modal
            window.location.href = `/quanlynhahang/manager/index.php?module=staff&edit_schedule=${userId}&week=${weekStartDate}`;
        }

        // Load history on page load
        loadWorkHistory();
    </script>
</body>
</html>
