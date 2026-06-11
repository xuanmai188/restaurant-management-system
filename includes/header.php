<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
$roleName = strtolower($user['role_name'] ?? '');
$fullName = $user['full_name'] ?? '';
$firstLetter = $fullName !== '' ? mb_strtoupper(mb_substr($fullName, 0, 1)) : 'U';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhà hàng Hương vị</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/quanlynhahang/assets/css/style.css">
    <link rel="stylesheet" href="/quanlynhahang/assets/css/fa-all.min.css">
</head>
<body>

<header class="site-header">
    <div class="container nav-wrap">
        <a href="/quanlynhahang/index.php" class="logo">
            <img src="/quanlynhahang/assets/images/logo.png" alt="Nhà Hàng Miền Tây" style="height:70px; width:auto; object-fit:contain; vertical-align:middle;">
        </a>

        <?php $currentPath = $_SERVER['REQUEST_URI']; ?>
        <nav class="main-nav">
            <a href="/quanlynhahang/index.php" <?= strpos($currentPath, '/index.php') !== false || $currentPath === '/quanlynhahang/' ? 'class="active"' : '' ?>>Trang chủ</a>
            <a href="/quanlynhahang/customer/menu.php" <?= strpos($currentPath, '/customer/menu.php') !== false ? 'class="active"' : '' ?>>Thực đơn</a>
            <a href="/quanlynhahang/customer/reservation.php" <?= strpos($currentPath, '/customer/reservation.php') !== false ? 'class="active"' : '' ?>>Đặt bàn</a>
            <a href="/quanlynhahang/customer/reservation-history.php" <?= strpos($currentPath, '/customer/reservation-history.php') !== false ? 'class="active"' : '' ?>>Lịch sử đặt bàn</a>
        </nav>

        <div class="header-user-area">
            <?php if ($user): ?>
                <?php if (in_array($roleName, ['admin', 'quanly'])): ?>
                    <?php $backUrl = $roleName === 'admin' ? '/quanlynhahang/admin.php' : '/quanlynhahang/manager/index.php'; ?>
                    <a href="<?= $backUrl ?>" class="nav-btn">Trang quản lý</a>
                <?php endif; ?>
                <div class="user-dropdown">
                    <button class="user-dropdown-toggle" type="button" onclick="toggleUserMenu()">
                        <span class="user-avatar"><?= htmlspecialchars($firstLetter) ?></span>
                        <span class="user-meta">
                            <strong><?= htmlspecialchars($fullName) ?></strong>
                            <?php if (strtolower($user['role_name'] ?? '') !== 'khachhang'): ?>
                            <small><?= htmlspecialchars($user['role_name']) ?></small>
                            <?php endif; ?>
                        </span>
                        <span class="user-caret">▾</span>
                    </button>

                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <a href="/quanlynhahang/profile.php">Hồ sơ cá nhân</a>
                        <a href="/quanlynhahang/change-password.php">Đổi mật khẩu</a>
                        <a href="/quanlynhahang/auth/logout.php" class="logout-link">Đăng xuất</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/quanlynhahang/auth/login.php" class="nav-btn">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </div>
</header>