<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host     = '127.0.0.1';
$dbname   = 'qlnhahang';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname, 3306);

if ($conn->connect_error) {
    die('Lỗi kết nối CSDL: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");
