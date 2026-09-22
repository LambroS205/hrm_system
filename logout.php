<?php
// logout.php - Xử lý đăng xuất an toàn, xóa sạch session và cookie
require_once __DIR__ . '/core/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}
session_destroy();

// Bắt đầu session mới chỉ để truyền thông báo flash ra trang đăng nhập
session_start();
set_flash('info', 'Bạn đã đăng xuất khỏi hệ thống thành công.');
redirect('login.php');