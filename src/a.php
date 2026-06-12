<?php
// Quick admin access - CHỈ DÙNG CHO MÔI TRƯỜNG DEV
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Tìm tài khoản admin đầu tiên trong DB
$result = $conn->query("
    SELECT u.user_id, u.full_name, u.email, u.username, u.phone, r.role_name
    FROM users u
    LEFT JOIN roles r ON r.role_id = u.role_id
    WHERE r.role_name = 'admin' AND u.status = 'hoat_dong'
    LIMIT 1
");

$user = $result ? $result->fetch_assoc() : null;

if (!$user) {
    die('Không tìm thấy tài khoản admin nào trong hệ thống.');
}

// vào link này để đến trang đăng nhập của admin: http://localhost:8080/quanlynhahang/auth/admin-access.php
$_SESSION['user'] = $user;
generate_admin_key();

redirect('/quanlynhahang/admin.php');
