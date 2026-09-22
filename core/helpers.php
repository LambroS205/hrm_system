<?php
// core/helpers.php - Các hàm tiện ích hỗ trợ toàn hệ thống

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Lấy đường dẫn gốc của ứng dụng (Base URL động)
 */
function base_url($path = '') {
    // Tự động nhận diện thư mục gốc hrm_system trên XAMPP
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    
    // Tìm vị trí của thư mục dự án
    $base = rtrim(str_replace('\\', '/', $script), '/');
    
    // Đảm bảo đưa về gốc dự án nếu đang ở trong thư mục con như modules/matrix
    if (strpos($base, '/modules') !== false) {
        $base = substr($base, 0, strpos($base, '/modules'));
    }

    $url = $protocol . $host . $base;
    return rtrim($url, '/') . '/' . ltrim($path, '/');
}

/**
 * Chống tấn công XSS khi hiển thị dữ liệu ra màn hình
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Chuyển hướng an toàn
 */
function redirect($path) {
    header("Location: " . base_url($path));
    exit;
}

/**
 * Thiết lập thông báo Flash (hiển thị 1 lần)
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Hiển thị thông báo Flash nếu có
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Định dạng tiền tệ Việt Nam (VND)
 */
function format_money($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

/**
 * Định dạng ngày tháng kiểu Việt Nam (dd/mm/YYYY)
 */
function format_date($date_string) {
    if (empty($date_string)) return '---';
    $time = strtotime($date_string);
    return date('d/m/Y', $time);
}