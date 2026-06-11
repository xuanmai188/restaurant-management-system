<?php

if (session_status() === PHP_SESSION_NONE) session_start();

$_layoutUser     = $_SESSION['user'] ?? [];
$_layoutFullName = $_layoutUser['full_name'] ?? '';
$_layoutRole     = strtolower($_layoutUser['role_name'] ?? '');
$_layoutAvatar   = mb_strtoupper(mb_substr($_layoutFullName, 0, 1)) ?: 'U';

// Lấy admin key nếu là admin
$_adminKey = '';
if ($_layoutRole === 'admin' && isset($_SESSION['admin_key'])) {
    $_adminKey = '?key=' . $_SESSION['admin_key'];
}

$_adminMenu = [
    'reports'     => ['label' => 'Tổng quan',       'url' => '/quanlynhahang/admin.php?page=reports'],
    'employees'   => ['label' => 'Nhân viên',        'url' => '/quanlynhahang/admin.php?page=employees'],
    'menu'        => ['label' => 'Thực đơn',         'url' => '/quanlynhahang/admin.php?page=menu'],
    'tables'      => ['label' => 'Bàn & Tầng',       'url' => '/quanlynhahang/admin.php?page=tables'],
    'orders'      => ['label' => 'Đơn hàng',         'url' => '/quanlynhahang/admin.php?page=orders'],
    'reservations'=> ['label' => 'Đặt bàn',          'url' => '/quanlynhahang/admin.php?page=reservations'],
    'customers'   => ['label' => 'Khách hàng',       'url' => '/quanlynhahang/admin.php?page=customers'],
    'maintenance' => ['label' => 'Bảo trì hệ thống', 'url' => '/quanlynhahang/admin.php?page=maintenance'],
];

$_cashierMenu = [
    'cashier'         => ['label' => 'Thanh toán',   'url' => '/quanlynhahang/cashier/index.php'],
    'cashier_history' => ['label' => 'Lịch sử',      'url' => '/quanlynhahang/cashier/history.php'],
];

$_waiterMenu = [
    'waiter'  => ['label' => 'Sơ đồ bàn',   'url' => '/quanlynhahang/waiter/index.php'],
    'w_orders'=> ['label' => 'Đơn hôm nay',  'url' => '/quanlynhahang/waiter/my_orders.php'],
];

$_kitchenMenu = [
    'kitchen' => ['label' => 'Bill món ăn',  'url' => '/quanlynhahang/kitchen/index.php'],
    'kitchen_history' => ['label' => 'Lịch sử',  'url' => '/quanlynhahang/kitchen/history.php'],
];

$_menuItems = match($sidebarRole ?? 'admin') {
    'thungan' => $_cashierMenu,
    'phucvu'  => $_waiterMenu,
    'bep'     => $_kitchenMenu,
    default   => $_adminMenu,
};
$_sidebarLabel = match($sidebarRole ?? 'admin') {
    'thungan' => 'Thu ngân',
    'phucvu'  => 'Phục vụ',
    'bep'     => 'Bếp',
    default   => 'Quản trị',
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Quản lý nhà hàng') ?></title>
    <link rel="stylesheet" href="/quanlynhahang/assets/css/app.css">
    <?php if (false): // admin-key-helper không còn cần thiết ?>
    <script src="/quanlynhahang/admin/includes/admin-key-helper.js"></script>
    <?php endif; ?>
</head>
<body class="theme-warm">
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo">QL</div>
            <div>
                <h2>Quản Trị</h2>
                <p><?= $_sidebarLabel ?></p>
            </div>
        </div>
        <nav class="menu">
            <?php foreach ($_menuItems as $key => $item): ?>
                <a href="<?= $item['url'] ?>" class="<?= ($activeMenu ?? '') === $key ? 'active' : '' ?>">
                    <?= $item['label'] ?>
                </a>
            <?php endforeach; ?>
            <?php if (in_array($_layoutRole, ['admin', 'quanly'])): ?>
                <a href="/quanlynhahang/index.php" target="_blank">
                    Trang chủ
                </a>
            <?php endif; ?>
            <a href="/quanlynhahang/auth/logout.php" style="margin-top:auto; color:#dc2626;">Đăng xuất</a>
        </nav>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
                <p><?= htmlspecialchars($pageSubtitle ?? '') ?></p>
            </div>
            <div class="userbox">
                <div class="avatar"><?= $_layoutAvatar ?></div>
                <div>
                    <strong><?= htmlspecialchars($_layoutFullName) ?></strong>
                    <p><?= htmlspecialchars($_layoutUser['role_name'] ?? '') ?></p>
                </div>
            </div>
        </div>
