<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin']);

// Đồng bộ trạng thái bàn trước khi hiển thị
sync_table_status();

$tables_result = $conn->query("
    SELECT t.table_id, t.table_name, t.capacity, t.status, f.floor_name,
           o.order_id, o.order_time, o.guest_count, o.total_amount, o.status AS order_status,
           CASE 
               WHEN c.customer_id IS NOT NULL THEN c.customer_name
               WHEN u.user_id IS NOT NULL THEN u.full_name
               ELSE 'Khách vãng lai'
           END as customer_name,
           (SELECT COUNT(*) FROM reservations r
            LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
            WHERE r.table_id = t.table_id
              AND r.status IN ('da_xac_nhan','cho_xac_nhan')
              AND DATE(r.reservation_time) = CURDATE()
              AND rp.reservation_payment_id IS NULL
           ) as walkin_count,
           (SELECT COUNT(*) FROM reservations r
            LEFT JOIN reservation_payments rp ON rp.reservation_id = r.reservation_id
            WHERE r.table_id = t.table_id
              AND r.status IN ('da_xac_nhan','cho_xac_nhan')
              AND DATE(r.reservation_time) = CURDATE()
              AND rp.reservation_payment_id IS NOT NULL
           ) as online_count
    FROM tables t
    LEFT JOIN floors f ON f.floor_id = t.floor_id
    LEFT JOIN orders o ON o.order_id = (
        SELECT order_id FROM orders
        WHERE table_id = t.table_id
          AND status IN ('da_dat_coc','moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
          AND (DATE(order_time) = CURDATE() OR status IN ('dang_phuc_vu','dang_che_bien','dang_xu_ly','moi'))
        ORDER BY
          CASE WHEN status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu') THEN 0
               WHEN status = 'hoan_thanh' THEN 1
               ELSE 2 END,
          order_id DESC
        LIMIT 1
    )
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    LEFT JOIN users u ON u.user_id = o.customer_id AND c.customer_id IS NULL
    ORDER BY f.floor_name, t.table_name
");
$tables = $tables_result->fetch_all(MYSQLI_ASSOC);

// Lấy TẤT CẢ bàn và tầng để tính thống kê tổng quan
$allTables = $conn->query("SELECT table_id, floor_id, status FROM tables")->fetch_all(MYSQLI_ASSOC);
$floors = $conn->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_name")->fetch_all(MYSQLI_ASSOC);

// Thống kê tổng quan - Dựa trên TẤT CẢ bàn trong database
$totalTables = count($allTables);
$totalFloors = count($floors);

// Bàn trống: không có đơn active và không có đặt bàn hôm nay
$availRes = $conn->query("
    SELECT COUNT(DISTINCT t.table_id) AS cnt 
    FROM tables t
    LEFT JOIN orders o ON o.table_id = t.table_id 
        AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
    LEFT JOIN reservations r ON r.table_id = t.table_id 
        AND r.status IN ('da_xac_nhan','cho_xac_nhan')
        AND DATE(r.reservation_time) = CURDATE()
    WHERE o.order_id IS NULL AND r.reservation_id IS NULL AND t.status != 'bao_tri'
");
$tablesAvailable = $availRes ? $availRes->fetch_assoc()['cnt'] : 0;

// Bàn đang ăn: có đơn hàng active
$busyRes = $conn->query("
    SELECT COUNT(DISTINCT t.table_id) AS cnt 
    FROM tables t
    INNER JOIN orders o ON o.table_id = t.table_id 
        AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
");
$tablesOccupied = $busyRes ? $busyRes->fetch_assoc()['cnt'] : 0;

// Bàn đã đặt: có reservation hôm nay nhưng chưa có đơn
$reservedRes = $conn->query("
    SELECT COUNT(DISTINCT t.table_id) AS cnt 
    FROM tables t
    INNER JOIN reservations r ON r.table_id = t.table_id 
        AND r.status IN ('da_xac_nhan','cho_xac_nhan')
        AND DATE(r.reservation_time) = CURDATE()
    LEFT JOIN orders o ON o.table_id = t.table_id 
        AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
    WHERE o.order_id IS NULL
");
$tablesReserved = $reservedRes ? $reservedRes->fetch_assoc()['cnt'] : 0;

// Bàn bảo trì
$maintenanceRes = $conn->query("SELECT COUNT(*) AS cnt FROM tables WHERE status = 'bao_tri'");
$tablesMaintenance = $maintenanceRes ? $maintenanceRes->fetch_assoc()['cnt'] : 0;

// Thống kê theo tầng - Dựa trên TẤT CẢ bàn
$floorStats = [];
foreach ($floors as $floor) {
    $floorId = $floor['floor_id'];
    $floorName = $floor['floor_name'];
    
    // Đếm tổng bàn của tầng
    $totalInFloor = $conn->query("SELECT COUNT(*) AS cnt FROM tables WHERE floor_id = $floorId")->fetch_assoc()['cnt'];
    
    // Đếm bàn trống
    $availInFloor = $conn->query("
        SELECT COUNT(DISTINCT t.table_id) AS cnt 
        FROM tables t
        LEFT JOIN orders o ON o.table_id = t.table_id AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
        LEFT JOIN reservations r ON r.table_id = t.table_id AND r.status IN ('da_xac_nhan','cho_xac_nhan') AND DATE(r.reservation_time) = CURDATE()
        WHERE t.floor_id = $floorId AND o.order_id IS NULL AND r.reservation_id IS NULL AND t.status != 'bao_tri'
    ")->fetch_assoc()['cnt'];
    
    // Đếm bàn đang ăn
    $occupiedInFloor = $conn->query("
        SELECT COUNT(DISTINCT t.table_id) AS cnt 
        FROM tables t
        INNER JOIN orders o ON o.table_id = t.table_id AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
        WHERE t.floor_id = $floorId
    ")->fetch_assoc()['cnt'];
    
    // Đếm bàn đã đặt
    $reservedInFloor = $conn->query("
        SELECT COUNT(DISTINCT t.table_id) AS cnt 
        FROM tables t
        INNER JOIN reservations r ON r.table_id = t.table_id AND r.status IN ('da_xac_nhan','cho_xac_nhan') AND DATE(r.reservation_time) = CURDATE()
        LEFT JOIN orders o ON o.table_id = t.table_id AND o.status IN ('moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh')
        WHERE t.floor_id = $floorId AND o.order_id IS NULL
    ")->fetch_assoc()['cnt'];
    
    $floorStats[$floorName] = [
        'total' => $totalInFloor,
        'available' => $availInFloor,
        'occupied' => $occupiedInFloor,
        'reserved' => $reservedInFloor,
        'maintenance' => 0
    ];
}

$pageTitle = 'Quản lý bàn';
$activeMenu = 'tables'; $sidebarRole = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
    <h2 style="margin:0; font-size:28px; font-weight:700; color:#111827;">Quản lý bàn</h2>
    <div style="display:flex; gap:12px;">
        <button class="btn btn-secondary" onclick="openAddFloorModal()">Tạo tầng mới</button>
        <button class="btn btn-primary" onclick="openAddTableModal()">Thêm bàn mới</button>
    </div>
</div>

<!-- Thống kê tổng quan -->
<div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius:16px; padding:24px; margin-bottom:30px; box-shadow:0 10px 30px rgba(102,126,234,0.3);">
    <h3 style="color:white; font-size:20px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
        📊 Tổng quan hiện trạng
    </h3>
    
    <!-- Thống kê tổng -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
        <div style="background:rgba(255,255,255,0.95); border-radius:12px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
            <div style="font-size:14px; color:#666; margin-bottom:8px; font-weight:600;">Tổng số bàn</div>
            <div style="font-size:32px; font-weight:700; color:#667eea;"><?= $totalTables ?></div>
            <div style="font-size:12px; color:#999; margin-top:4px;">Trên <?= $totalFloors ?> tầng</div>
        </div>
        <div style="background:rgba(255,255,255,0.95); border-radius:12px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
            <div style="font-size:14px; color:#666; margin-bottom:8px; font-weight:600;">Bàn trống</div>
            <div style="font-size:32px; font-weight:700; color:#10b981;"><?= $tablesAvailable ?></div>
            <div style="font-size:12px; color:#999; margin-top:4px;"><?= $totalTables > 0 ? round($tablesAvailable/$totalTables*100) : 0 ?>% tổng số</div>
        </div>
        <div style="background:rgba(255,255,255,0.95); border-radius:12px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
            <div style="font-size:14px; color:#666; margin-bottom:8px; font-weight:600;">Đang ăn</div>
            <div style="font-size:32px; font-weight:700; color:#ef4444;"><?= $tablesOccupied ?></div>
            <div style="font-size:12px; color:#999; margin-top:4px;"><?= $totalTables > 0 ? round($tablesOccupied/$totalTables*100) : 0 ?>% tổng số</div>
        </div>
        <div style="background:rgba(255,255,255,0.95); border-radius:12px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
            <div style="font-size:14px; color:#666; margin-bottom:8px; font-weight:600;">Đã đặt</div>
            <div style="font-size:32px; font-weight:700; color:#f59e0b;"><?= $tablesReserved ?></div>
            <div style="font-size:12px; color:#999; margin-top:4px;"><?= $totalTables > 0 ? round($tablesReserved/$totalTables*100) : 0 ?>% tổng số</div>
        </div>
    </div>
    
    <!-- Thống kê theo tầng -->
    <div style="background:rgba(255,255,255,0.95); border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
        <h4 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px; border-bottom:2px solid #e5e7eb; padding-bottom:10px;">
            📍 Chi tiết theo tầng
        </h4>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
            <?php foreach ($floorStats as $floorName => $stats): ?>
            <div style="background:#f9fafb; border:2px solid #e5e7eb; border-radius:10px; padding:16px;">
                <div style="font-size:16px; font-weight:700; color:#111827; margin-bottom:12px;"><?= e($floorName) ?></div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#666;">Tổng:</span>
                        <strong style="color:#667eea;"><?= $stats['total'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#666;">Trống:</span>
                        <strong style="color:#10b981;"><?= $stats['available'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#666;">Đang ăn:</span>
                        <strong style="color:#ef4444;"><?= $stats['occupied'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#666;">Đã đặt:</span>
                        <strong style="color:#f59e0b;"><?= $stats['reserved'] ?></strong>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
// Tạo danh sách bàn theo tầng
$tablesByFloor = [];
foreach ($tables as $table) {
    $fn = $table['floor_name'] ?? 'Không rõ';
    $tablesByFloor[$fn][] = $table;
}

// Tạo map floor_name => floor_id
$floorIdMap = [];
foreach ($floors as $floor) {
    $floorIdMap[$floor['floor_name']] = $floor['floor_id'];
}

// Thêm các tầng chưa có bàn vào danh sách
foreach ($floors as $floor) {
    $floorName = $floor['floor_name'];
    if (!isset($tablesByFloor[$floorName])) {
        $tablesByFloor[$floorName] = []; // Tầng trống
    }
}

$statusLabels = ['trong'=>'Trống','dang_su_dung'=>'Đang ăn','da_dat'=>'Đã đặt','bao_tri'=>'Bảo trì'];
foreach ($tablesByFloor as $floorName => $floorTables):
    $floorId = $floorIdMap[$floorName] ?? 0;
?>
<div style="margin-bottom:40px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid #e0e0e0;">
        <h3 style="font-size:24px; font-weight:700; margin:0; color:#333;"><?= e($floorName) ?></h3>
        <button onclick="deleteFloor('<?= e($floorName) ?>', <?= $floorId ?>)" style="padding:8px 16px; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s ease;">
            Xóa tầng
        </button>
    </div>
    
    <?php if (empty($floorTables)): ?>
        <!-- Tầng chưa có bàn -->
        <div style="background:white; border-radius:16px; padding:40px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.04); border:2px dashed #e5e7eb;">
            <div style="font-size:48px; margin-bottom:16px;">🪑</div>
            <h4 style="font-size:18px; font-weight:600; color:#6b7280; margin-bottom:12px;">Tầng này chưa có bàn</h4>
            <p style="font-size:14px; color:#9ca3af; margin-bottom:0;">Hãy thêm bàn mới cho tầng này</p>
        </div>
    <?php else: ?>
        <!-- Có bàn -->
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px;">
        <?php foreach ($floorTables as $table):
            $walkinCount = (int)($table['walkin_count'] ?? 0);
            $onlineCount = (int)($table['online_count'] ?? 0);
            $reservationTotal = $walkinCount + $onlineCount;

            // Xác định trạng thái thực tế dựa trên đơn hàng
            $actualStatus = $table['status'];
            $statusDisplay = $statusLabels[$table['status']] ?? $table['status'];
            
            if ($table['status'] === 'bao_tri') {
                // Bảo trì - giữ nguyên
                $actualStatus = 'bao_tri';
                $statusDisplay = 'Bảo trì';
            } elseif ($table['order_id'] && !in_array($table['order_status'], ['da_dat_coc', 'da_coc'])) {
                // Có đơn hàng active (không phải cọc) = Đang ăn / Chờ thanh toán
                $actualStatus = 'dang_su_dung';
                $statusDisplay = $table['order_status'] === 'hoan_thanh' ? 'Chờ thanh toán' : 'Đang ăn';
            } elseif ($table['order_id'] && in_array($table['order_status'], ['da_dat_coc', 'da_coc'])) {
                // Có đơn cọc = Đã đặt (khách chưa đến)
                $actualStatus = 'da_dat';
                $statusDisplay = 'Đã đặt';
            } elseif ($reservationTotal > 0) {
                // Có đặt bàn = Đã đặt
                $actualStatus = 'da_dat';
                $statusDisplay = 'Đã đặt';
            } else {
                // Không có gì = Trống
                $actualStatus = 'trong';
                $statusDisplay = 'Trống';
            }
            
            $statusStyle = match($actualStatus) {
                'trong'        => 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
                'dang_su_dung' => 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;',
                'da_dat'       => 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d;',
                'bao_tri'      => 'background:#e5e7eb; color:#1f2937; border:1px solid #9ca3af;',
                default       => 'background:#e5e7eb; color:#374151;'
            };
        ?>
        <div style="background:white; border:2px solid #e5e7eb; border-radius:16px; overflow:hidden; transition:all 0.3s ease; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="display:flex; justify-content:space-between; align-items:start; padding:18px; background:linear-gradient(135deg,#f9fafb,#f3f4f6); border-bottom:1px solid #e5e7eb;">
                <div>
                    <div style="font-size:22px; font-weight:700; margin-bottom:4px; color:#111827;"><?= e($table['table_name']) ?></div>
                    <div style="font-size:13px; color:#6b7280; font-weight:500;"><?= e($table['floor_name']) ?></div>
                </div>
                <div style="display:flex; gap:6px;">
                    <button onclick="editTable(<?= $table['table_id'] ?>)" style="background:linear-gradient(135deg,#3b82f6,#2563eb); color:white; border:none; font-size:12px; font-weight:600; cursor:pointer; padding:6px 12px; border-radius:8px;">Sửa</button>
                    <button onclick="deleteTable(<?= $table['table_id'] ?>)" style="background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; font-size:12px; font-weight:600; cursor:pointer; padding:6px 12px; border-radius:8px;">Xóa</button>
                </div>
            </div>
            <div style="padding:18px;">
                <div style="margin-bottom:14px; font-size:14px; color:#6b7280; font-weight:500;">Sức chứa: <?= $table['capacity'] ?> người</div>
                <span style="font-size:13px; padding:8px 14px; border-radius:20px; display:inline-block; margin-bottom:14px; font-weight:600; <?= $statusStyle ?>">
                    <?= $statusDisplay ?>
                </span>
                <?php if ($actualStatus === 'dang_su_dung' && $table['order_id']): ?>
                <div style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe); padding:14px; border-radius:12px; margin-top:14px; border:1px solid #bae6fd;">
                    <div style="font-size:13px; margin-bottom:8px; color:#0c4a6e; display:flex; justify-content:space-between;"><strong>Mã đơn:</strong> #<?= $table['order_id'] ?></div>
                    <div style="font-size:13px; margin-bottom:8px; color:#0c4a6e; display:flex; justify-content:space-between;"><strong>Khách:</strong> <?= e($table['customer_name'] ?? 'Khách vãng lai') ?></div>
                    <div style="font-size:13px; margin-bottom:8px; color:#0c4a6e; display:flex; justify-content:space-between;"><strong>Số người:</strong> <?= $table['guest_count'] ?? $table['capacity'] ?></div>
                    <div style="font-size:13px; margin-bottom:8px; color:#0c4a6e; display:flex; justify-content:space-between;"><strong>Giờ vào:</strong> <?= date('H:i', strtotime($table['order_time'])) ?></div>
                    <div style="font-size:13px; color:#0c4a6e; display:flex; justify-content:space-between;"><strong>Tổng tiền:</strong> <span style="color:#ef4444; font-weight:700;"><?= format_currency($table['total_amount'] ?? 0) ?></span></div>
                </div>
                <?php endif; ?>
                <?php if ($actualStatus === 'da_dat' && $reservationTotal > 0):
                    if ($walkinCount > 0 && $onlineCount > 0) {
                        $badgeText = $walkinCount . ' walk-in, ' . $onlineCount . ' online';
                    } elseif ($onlineCount > 0) {
                        $badgeText = $onlineCount . ' online';
                    } else {
                        $badgeText = $walkinCount . ' walk-in';
                    }
                ?>
                <div style="margin-top:14px;">
                    <span style="background:#fef3c7; color:#92400e; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; border:1px solid #fcd34d;"><?= e($badgeText) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div style="padding:14px 18px; border-top:1px solid #f3f4f6; background:#fafafa;">
                <button onclick="viewTableDetail(<?= $table['table_id'] ?>)" style="width:100%; padding:10px; background:linear-gradient(135deg,#3b82f6,#2563eb); color:white; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer;">Xem chi tiết</button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Table Modal -->
<div id="table-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:white; border-radius:16px; width:90%; max-width:500px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px; border-bottom:1px solid #e0e0e0;">
            <h3 id="modal-title" style="margin:0;">Thêm bàn mới</h3>
            <button onclick="closeTableModal()" style="background:none; border:none; font-size:28px; cursor:pointer; color:#999;">&times;</button>
        </div>
        <form id="table-form" style="padding:20px;">
            <input type="hidden" id="table-id">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; font-size:14px;">Tên bàn</label>
                <input type="text" id="table-name" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:14px; box-sizing:border-box;" placeholder="VD: Bàn 1, Bàn VIP 1" required>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; font-size:14px;">Tầng</label>
                <select id="table-floor" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:14px; box-sizing:border-box;" required>
                    <option value="">Chọn tầng</option>
                    <?php foreach ($floors as $floor): ?>
                        <option value="<?= $floor['floor_id'] ?>"><?= e($floor['floor_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; font-size:14px;">Sức chứa</label>
                <input type="number" id="table-capacity" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:14px; box-sizing:border-box;" min="1" max="20" required>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; font-size:14px;">Trạng thái</label>
                <select id="table-status" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:14px; box-sizing:border-box;">
                    <option value="auto">Tự động (dựa trên đơn hàng/đặt bàn)</option>
                    <option value="bao_tri">Bảo trì</option>
                </select>
                <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                    Chọn "Tự động" để hệ thống tự động xác định trạng thái dựa trên đơn hàng và đặt bàn
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; padding-top:16px; border-top:1px solid #e0e0e0;">
                <button type="button" onclick="closeTableModal()" style="padding:10px 20px; border:none; border-radius:8px; font-size:14px; cursor:pointer; background:#95a5a6; color:white;">Hủy</button>
                <button type="submit" style="padding:10px 20px; border:none; border-radius:8px; font-size:14px; cursor:pointer; background:#3498db; color:white;">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- Floor Modal -->
<div id="floor-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:white; border-radius:16px; width:90%; max-width:400px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px; border-bottom:1px solid #e0e0e0;">
            <h3 style="margin:0;">Tạo tầng mới</h3>
            <button onclick="closeFloorModal()" style="background:none; border:none; font-size:28px; cursor:pointer; color:#999;">&times;</button>
        </div>
        <form id="floor-form" style="padding:20px;">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; font-size:14px;">Tên tầng</label>
                <input type="text" id="floor-name" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:14px; box-sizing:border-box;" placeholder="VD: Tầng 1, Tầng 2" required>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; padding-top:16px; border-top:1px solid #e0e0e0;">
                <button type="button" onclick="closeFloorModal()" style="padding:10px 20px; border:none; border-radius:8px; font-size:14px; cursor:pointer; background:#95a5a6; color:white;">Hủy</button>
                <button type="submit" style="padding:10px 20px; border:none; border-radius:8px; font-size:14px; cursor:pointer; background:#3498db; color:white;">Tạo tầng</button>
            </div>
        </form>
    </div>
</div>

<!-- Detail Modal -->
<div id="detail-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:white; border-radius:16px; width:90%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px; border-bottom:1px solid #e0e0e0;">
            <h3 style="margin:0;">Chi tiết bàn</h3>
            <button onclick="closeDetailModal()" style="background:none; border:none; font-size:28px; cursor:pointer; color:#999;">&times;</button>
        </div>
        <div id="detail-content" style="padding:20px;">Đang tải...</div>
        <div style="display:flex; justify-content:flex-end; padding:16px 20px; border-top:1px solid #e0e0e0;">
            <button onclick="closeDetailModal()" style="padding:10px 20px; border:none; border-radius:8px; font-size:14px; cursor:pointer; background:#3498db; color:white;">Đóng</button>
        </div>
    </div>
</div>

<style>
.detail-section { margin-bottom: 24px; }
.detail-section h4 { font-size:16px; font-weight:700; margin-bottom:12px; color:#333; border-bottom:2px solid #e0e0e0; padding-bottom:8px; }
.detail-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0; }
.detail-row:last-child { border-bottom:none; }
.detail-label { font-weight:600; color:#666; }
.detail-value { color:#333; }
.reservation-item { background:#f8f9fa; padding:12px; border-radius:8px; margin-bottom:8px; }
.reservation-item:last-child { margin-bottom:0; }
</style>

<script>
const API_URL = 'manager/api/tables.php';

function openAddTableModal() {
    document.getElementById('modal-title').textContent = 'Thêm bàn mới';
    document.getElementById('table-form').reset();
    document.getElementById('table-id').value = '';
    document.getElementById('table-modal').style.display = 'flex';
}

function openAddFloorModal() {
    document.getElementById('floor-form').reset();
    document.getElementById('floor-modal').style.display = 'flex';
}

function deleteFloor(floorName, floorId) {
    if (!floorId) {
        alert('Không tìm thấy ID tầng');
        return;
    }
    
    // Thông báo cảnh báo chi tiết
    const confirmMessage = `⚠️ CẢNH BÁO: BẠN SẮP XÓA TẦNG!\n\n` +
                          `Tầng: ${floorName}\n\n` +
                          `LƯU Ý QUAN TRỌNG:\n` +
                          `• Chỉ có thể xóa tầng KHÔNG CÓ BÀN nào\n` +
                          `• Nếu tầng có bàn, vui lòng xóa hết bàn trước\n` +
                          `• Hành động này KHÔNG THỂ HOÀN TÁC\n\n` +
                          `Bạn có chắc chắn muốn xóa tầng "${floorName}" không?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch(`${API_URL}?action=deleteFloor`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({floor_id: floorId})
    })
    .then(r => r.text().then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response was:', text);
            throw new Error('API trả về không phải JSON');
        }
    }))
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + (data.message || 'Lỗi xóa tầng'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Lỗi kết nối: ' + err.message);
    });
}

function closeTableModal() { document.getElementById('table-modal').style.display = 'none'; }
function closeFloorModal() { document.getElementById('floor-modal').style.display = 'none'; }
function closeDetailModal() { document.getElementById('detail-modal').style.display = 'none'; }

function editTable(tableId) {
    document.getElementById('modal-title').textContent = 'Sửa thông tin bàn';
    
    // Lấy thông tin bàn từ API detail để có đầy đủ order/reservation
    fetch(`${API_URL}?action=detail&table_id=${tableId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const {table, order, reservations} = data.data;
                
                document.getElementById('table-id').value = table.table_id;
                document.getElementById('table-name').value = table.table_name;
                document.getElementById('table-floor').value = table.floor_id;
                document.getElementById('table-capacity').value = table.capacity;
                
                // Chỉ set "bao_tri" nếu đang bảo trì, còn lại để "auto"
                if (table.status === 'bao_tri') {
                    document.getElementById('table-status').value = 'bao_tri';
                } else {
                    document.getElementById('table-status').value = 'auto';
                }
                
                document.getElementById('table-modal').style.display = 'flex';
            }
        });
}

function deleteTable(tableId) {
    if (!confirm('Bạn có chắc muốn xóa bàn này?')) return;
    fetch(`${API_URL}?action=delete`, {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({table_id: tableId})
    }).then(r => r.json()).then(data => {
        if (data.success) { alert(data.message); location.reload(); }
        else alert(data.message || 'Lỗi xóa bàn');
    });
}

function viewTableDetail(tableId) {
    document.getElementById('detail-content').innerHTML = 'Đang tải...';
    document.getElementById('detail-modal').style.display = 'flex';
    
    console.log('Fetching table detail for ID:', tableId);
    console.log('API URL:', `${API_URL}?action=detail&table_id=${tableId}`);
    
    fetch(`${API_URL}?action=detail&table_id=${tableId}`)
        .then(r => {
            console.log('Response status:', r.status);
            console.log('Response headers:', r.headers.get('content-type'));
            
            // Đọc response dưới dạng text trước
            return r.text().then(text => {
                console.log('Response text:', text);
                
                // Thử parse JSON
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    throw new Error('API trả về không phải JSON. Response: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success) {
                const {table, order, order_items, reservations} = data.data;
                
                // Tính toán trạng thái thực tế giống như ở card
                let actualStatus = 'trong';
                let statusDisplay = 'Trống';
                
                if (table.status === 'bao_tri') {
                    actualStatus = 'bao_tri';
                    statusDisplay = 'Bảo trì';
                } else if (order) {
                    actualStatus = 'dang_su_dung';
                    statusDisplay = 'Đang ăn';
                } else if (reservations && reservations.length > 0) {
                    actualStatus = 'da_dat';
                    statusDisplay = 'Đã đặt';
                }

                let html = `<div class="detail-section">
                    <h4>Thông tin bàn</h4>
                    <div class="detail-row"><span class="detail-label">Tên bàn:</span><span class="detail-value">${table.table_name}</span></div>
                    <div class="detail-row"><span class="detail-label">Tầng:</span><span class="detail-value">${table.floor_name || 'Không rõ'}</span></div>
                    <div class="detail-row"><span class="detail-label">Sức chứa:</span><span class="detail-value">${table.capacity} người</span></div>
                    <div class="detail-row"><span class="detail-label">Trạng thái:</span><span class="detail-value">${statusDisplay}</span></div>
                </div>`;

                if (order) {
                    html += `<div class="detail-section">
                        <h4>Đơn hàng hiện tại</h4>
                        <div class="detail-row"><span class="detail-label">Mã đơn:</span><span class="detail-value">#${order.order_id}</span></div>
                        <div class="detail-row"><span class="detail-label">Khách hàng:</span><span class="detail-value">${order.customer_name || 'Khách vãng lai'}</span></div>
                        <div class="detail-row"><span class="detail-label">Số người:</span><span class="detail-value">${order.guest_count}</span></div>
                        <div class="detail-row"><span class="detail-label">Giờ vào:</span><span class="detail-value">${new Date(order.order_time).toLocaleTimeString('vi-VN')}</span></div>
                        <div class="detail-row"><span class="detail-label">Tổng tiền:</span><span class="detail-value" style="color:#ef4444; font-weight:700;">${new Intl.NumberFormat('vi-VN').format(order.total_amount)}đ</span></div>
                    </div>`;
                    
                    // Display order items
                    if (order_items && order_items.length > 0) {
                        html += `<div class="detail-section">
                            <h4>Món ăn đã gọi (${order_items.length})</h4>`;
                        order_items.forEach(item => {
                            const itemTotal = item.quantity * item.unit_price;
                            html += `<div class="reservation-item" style="display:flex; gap:12px; align-items:center;">
                                ${item.image_url ? `<img src="../${item.image_url}" style="width:60px; height:60px; object-fit:cover; border-radius:8px;" alt="${item.item_name}">` : ''}
                                <div style="flex:1;">
                                    <div style="font-weight:600; color:#333; margin-bottom:4px;">${item.item_name}</div>
                                    <div style="font-size:13px; color:#666;">
                                        ${new Intl.NumberFormat('vi-VN').format(item.unit_price)}đ × ${item.quantity} = 
                                        <strong style="color:#ef4444;">${new Intl.NumberFormat('vi-VN').format(itemTotal)}đ</strong>
                                    </div>
                                    ${item.note ? `<div style="font-size:12px; color:#999; margin-top:4px;">Ghi chú: ${item.note}</div>` : ''}
                                </div>
                            </div>`;
                        });
                        html += `</div>`;
                    }
                }

                if (reservations && reservations.length > 0) {
                    html += `<div class="detail-section"><h4>Đặt bàn (${reservations.length})</h4>`;
                    reservations.forEach(res => {
                        html += `<div class="reservation-item">
                            <div class="detail-row"><span class="detail-label">Khách hàng:</span><span class="detail-value">${res.customer_name || 'Khách vãng lai'}</span></div>
                            <div class="detail-row"><span class="detail-label">Thời gian:</span><span class="detail-value">${new Date(res.reservation_time).toLocaleString('vi-VN')}</span></div>
                            <div class="detail-row"><span class="detail-label">Số người:</span><span class="detail-value">${res.number_of_people}</span></div>
                        </div>`;
                    });
                    html += `</div>`;
                }

                document.getElementById('detail-content').innerHTML = html;
            } else {
                document.getElementById('detail-content').innerHTML = `<div style="color:red;">Lỗi: ${data.message}</div>`;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('detail-content').innerHTML = '<div style="color:red;">Lỗi kết nối: ' + err.message + '</div>';
        });
}

document.getElementById('table-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const tableId = document.getElementById('table-id').value;
    const action = tableId ? 'update' : 'create';
    fetch(`${API_URL}?action=${action}`, {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            table_id: tableId || undefined,
            table_name: document.getElementById('table-name').value,
            floor_id: document.getElementById('table-floor').value,
            capacity: document.getElementById('table-capacity').value,
            status: document.getElementById('table-status').value
        })
    }).then(r => r.json()).then(data => {
        if (data.success) { alert(data.message); closeTableModal(); location.reload(); }
        else alert(data.message || 'Lỗi lưu bàn');
    });
});

document.getElementById('floor-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    console.log('Creating floor...');
    console.log('API URL:', `${API_URL}?action=createFloor`);
    
    fetch(`${API_URL}?action=createFloor`, {
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({floor_name: document.getElementById('floor-name').value})
    })
    .then(r => {
        console.log('Response status:', r.status);
        console.log('Response headers:', r.headers.get('content-type'));
        
        // Đọc response dưới dạng text trước
        return r.text().then(text => {
            console.log('Response text:', text);
            
            // Thử parse JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response was:', text);
                throw new Error('API trả về không phải JSON. Response: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) { 
            alert(data.message); 
            closeFloorModal(); 
            location.reload(); 
        } else {
            alert(data.message || 'Lỗi tạo tầng');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Lỗi kết nối: ' + err.message);
    });
});

// Tự làm mới trạng thái bàn mỗi 30 giây
setInterval(function() {
    if (!document.hidden) {
        location.reload();
    }
}, 30000);
</script>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>


