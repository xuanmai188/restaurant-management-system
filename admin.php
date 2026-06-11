<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['admin']);

$page = $_GET['page'] ?? 'reports';
$allowed = ['reports','employees','menu','tables','orders','reservations','customers','maintenance'];
if (!in_array($page, $allowed)) $page = 'reports';

$pageTitles = [
    'reports'     => 'Tổng quan & Báo cáo',
    'employees'   => 'Quản lý nhân viên',
    'menu'        => 'Quản lý thực đơn',
    'tables'      => 'Bàn & Tầng',
    'orders'      => 'Quản lý đơn hàng',
    'reservations'=> 'Quản lý đặt bàn',
    'customers'   => 'Khách hàng',
    'maintenance' => 'Bảo trì hệ thống',
];

$pageTitle   = $pageTitles[$page] ?? 'Admin';
$activeMenu  = $page;
$sidebarRole = 'admin';

// Dùng layout.php làm sidebar — không tự tạo sidebar riêng
define('ADMIN_EMBEDDED', true);
include __DIR__ . '/includes/layout.php';

// Include module content
$moduleFile = __DIR__ . '/admin/' . $page . '.php';
if (file_exists($moduleFile)) {
    include $moduleFile;
} else {
    echo '<div style="text-align:center;padding:60px;color:#9ca3af;">Module không tồn tại.</div>';
}

include __DIR__ . '/includes/layout_end.php';
?>
