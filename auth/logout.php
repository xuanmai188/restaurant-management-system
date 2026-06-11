<?php
require_once __DIR__ . '/../includes/functions.php';
session_destroy();
redirect('/quanlynhahang/auth/login.php');
